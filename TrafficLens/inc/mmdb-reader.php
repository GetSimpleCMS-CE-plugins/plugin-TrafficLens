<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
 * Minimal pure-PHP reader for the MaxMind DB (.mmdb) binary format.
 * https://maxmind.github.io/MaxMind-DB/  (open, publicly documented format)
 *
 * No PECL extension required - works on any PHP install. Built specifically
 * for country-level lookups (DB-IP / MaxMind Country/Country-Lite databases),
 * but the decoder supports the general data types so it will also read
 * City-tier databases if someone drops one in later.
 *
 * Usage:
 *   $mmdb = new TrafficLens_MMDB($path);
 *   $data = $mmdb->lookup('203.0.113.5');   // array|null
 *   $iso  = $data['country']['iso_code'] ?? null;
 */

class TrafficLens_MMDB {

	private $fh;
	private $file_size;
	private $node_count;
	private $record_size;
	private $ip_version;
	private $search_tree_bytes;
	private $ok = false;

	const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

	public function __construct($path) {
		if (!is_readable($path)) return;

		$this->fh = fopen($path, 'rb');
		if (!$this->fh) return;

		$this->file_size = filesize($path);
		$meta = $this->read_metadata();
		if (!$meta) return;

		$this->node_count  = $meta['node_count'];
		$this->record_size = $meta['record_size'];
		$this->ip_version  = $meta['ip_version'];
		// size in bytes of one node = (record_size * 2) bits
		$this->search_tree_bytes = (int)(($this->record_size * 2 / 8) * $this->node_count);
		$this->ok = true;
	}

	public function is_ready() {
		return $this->ok;
	}

	public function __destruct() {
		if ($this->fh) fclose($this->fh);
	}

	// Locate and decode the metadata section (near end of file).
	private function read_metadata() {
		$search_window = min($this->file_size, 128 * 1024); // metadata section is small, no need to scan whole file
		fseek($this->fh, $this->file_size - $search_window);
		$tail = fread($this->fh, $search_window);

		$marker_pos = strrpos($tail, self::METADATA_MARKER);
		if ($marker_pos === false) return null;

		$meta_offset = $marker_pos + strlen(self::METADATA_MARKER);

		// Metadata itself is a MaxMind DB "map" data structure - decode it directly.
		$abs_offset = ($this->file_size - $search_window) + $meta_offset;
		fseek($this->fh, $abs_offset);
		$raw = fread($this->fh, $this->file_size - $abs_offset);

		$pos = 0;
		$value = $this->decode_value($raw, $pos);
		if (!is_array($value)) return null;

		if (empty($value['node_count']) || empty($value['record_size']) || empty($value['ip_version'])) {
			return null;
		}
		return $value;
	}

	/**
	 * Look up a single IP address (v4 or v6 string). Returns decoded data
	 * array for the matching network, or null if not found / db unreadable.
	 */
	public function lookup($ip) {
		if (!$this->ok) return null;

		$packed = @inet_pton($ip);
		if ($packed === false) return null;

		// Convert to a flat bit array. If the tree is IPv6 (ip_version 6)
		// and we have a v4 address, map it into the last 32 bits per spec.
		$is_v4 = (strlen($packed) === 4);
		$bits  = [];
		if ($this->ip_version === 6 && $is_v4) {
			for ($i = 0; $i < 96; $i++) $bits[] = 0; // ::/96 prefix zero bits
		}
		foreach (str_split($packed) as $byte) {
			$b = ord($byte);
			for ($i = 7; $i >= 0; $i--) $bits[] = ($b >> $i) & 1;
		}

		$node = 0;
		$node_bytes = (int)($this->record_size * 2 / 8);

		foreach ($bits as $bit) {
			if ($node >= $this->node_count) break; // hit data section / leaf
			$record = $this->read_node_record($node, $bit, $node_bytes);
			if ($record === $this->node_count) {
				return null; // no data for this IP
			} elseif ($record > $this->node_count) {
				$file_offset = ($record - $this->node_count) + $this->search_tree_bytes;
				return $this->decode_at($file_offset);
			}
			$node = $record;
		}

		return null;
	}

	// Read one 0-indexed record (left=bit 0, right=bit 1) of a given node.
	private function read_node_record($node, $bit, $node_bytes) {
		fseek($this->fh, $node * $node_bytes);
		$raw = fread($this->fh, $node_bytes);

		if ($this->record_size === 24) {
			$left  = (ord($raw[0]) << 16) | (ord($raw[1]) << 8) | ord($raw[2]);
			$right = (ord($raw[3]) << 16) | (ord($raw[4]) << 8) | ord($raw[5]);
		} elseif ($this->record_size === 28) {
			$mid   = ord($raw[3]);
			$left  = (ord($raw[0]) << 16) | (ord($raw[1]) << 8) | ord($raw[2]);
			$left  = $left | (($mid >> 4) << 24);
			$right = ((($mid & 0x0F) << 24)) | (ord($raw[4]) << 16) | (ord($raw[5]) << 8) | ord($raw[6]);
		} else { // 32
			$left  = unpack('N', substr($raw, 0, 4))[1];
			$right = unpack('N', substr($raw, 4, 4))[1];
		}

		return $bit === 0 ? $left : $right;
	}

	// Decode a data-section value starting at an absolute file offset.
	private function decode_at($file_offset) {
		fseek($this->fh, $file_offset);
		// Data values are usually small (a handful of map entries); 8KB is generous headroom.
		$raw = fread($this->fh, 8192);
		$pos = 0;
		return $this->decode_value($raw, $pos);
	}

	/**
	 * Decode one value from $buf starting at $pos (advances $pos).
	 * Pointer values resolve to absolute file offsets independently of
	 * where $buf itself was read from, so no base-offset tracking is needed.
	 */
	private function decode_value(&$buf, &$pos) {
		$ctrl = ord($buf[$pos]);
		$pos++;
		$type = $ctrl >> 5;

		if ($type === 1) { // pointer
			return $this->decode_pointer($buf, $pos, $ctrl);
		}

		if ($type === 0) { // extended type
			$ext = ord($buf[$pos]);
			$pos++;
			$type = $ext + 7;
		}

		$size = $ctrl & 0x1F;
		if ($size === 29) {
			$size = 29 + ord($buf[$pos]);
			$pos += 1;
		} elseif ($size === 30) {
			$size = 285 + ((ord($buf[$pos]) << 8) | ord($buf[$pos+1]));
			$pos += 2;
		} elseif ($size === 31) {
			$size = 65821 + ((ord($buf[$pos]) << 16) | (ord($buf[$pos+1]) << 8) | ord($buf[$pos+2]));
			$pos += 3;
		}

		switch ($type) {
			case 2: // utf8_string
				$s = substr($buf, $pos, $size);
				$pos += $size;
				return $s;

			case 3: // double
				$v = unpack('E', substr($buf, $pos, 8))[1]; // big-endian double
				$pos += 8;
				return $v;

			case 4: // bytes - not needed for our use case, skip
				$pos += $size;
				return null;

			case 5: // uint16
			case 6: // uint32
			case 9: // uint64
			case 10: // uint128 (truncated to PHP int, fine for our purposes)
				$v = 0;
				for ($i = 0; $i < $size; $i++) { $v = ($v << 8) | ord($buf[$pos + $i]); }
				$pos += $size;
				return $v;

			case 8: // int32
				$v = 0;
				for ($i = 0; $i < $size; $i++) { $v = ($v << 8) | ord($buf[$pos + $i]); }
				$pos += $size;
				if ($size > 0 && ($v & (1 << (8 * $size - 1)))) { $v -= (1 << (8 * $size)); }
				return $v;

			case 7: // map
				$out = [];
				for ($i = 0; $i < $size; $i++) {
					$key = $this->decode_value($buf, $pos);
					$val = $this->decode_value($buf, $pos);
					$out[$key] = $val;
				}
				return $out;

			case 11: // array
				$out = [];
				for ($i = 0; $i < $size; $i++) {
					$out[] = $this->decode_value($buf, $pos);
				}
				return $out;

			case 14: // boolean - value IS the size field (0 or 1)
				return $size === 1;

			case 15: // float
				$v = unpack('G', substr($buf, $pos, 4))[1]; // big-endian float
				$pos += 4;
				return $v;

			default:
				return null;
		}
	}

	// Pointers are absolute offsets into the data section; re-fetch a fresh buffer at that offset.
	private function decode_pointer(&$buf, &$pos, $ctrl) {
		$size_flag = ($ctrl >> 3) & 0x03;
		$partial   = $ctrl & 0x07;

		if ($size_flag === 0) {
			$val = ($partial << 8) | ord($buf[$pos]);
			$pos += 1;
		} elseif ($size_flag === 1) {
			$val = ($partial << 16) | (ord($buf[$pos]) << 8) | ord($buf[$pos+1]);
			$val += 2048;
			$pos += 2;
		} elseif ($size_flag === 2) {
			$val = ($partial << 24) | (ord($buf[$pos]) << 16) | (ord($buf[$pos+1]) << 8) | ord($buf[$pos+2]);
			$val += 526336;
			$pos += 3;
		} else {
			$val = unpack('N', substr($buf, $pos, 4))[1];
			$pos += 4;
		}

		// Pointer target is an absolute offset within the data section, i.e. an
		// absolute file offset once we account for where the data section starts
		// (search_tree_bytes + 16-byte data section separator).
		$target_file_offset = $this->search_tree_bytes + 16 + $val;
		return $this->decode_at($target_file_offset);
	}
}
