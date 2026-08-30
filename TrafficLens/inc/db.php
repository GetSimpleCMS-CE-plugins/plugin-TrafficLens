<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

// PATHS

function TrafficLens_data_dir() {
	return GSDATAOTHERPATH . 'TrafficLens/';
}

function TrafficLens_db_path() {
	return TrafficLens_data_dir() . 'TrafficLens.db';
}

function TrafficLens_settings_path() {
	return TrafficLens_data_dir() . 'settings.json';
}

function TrafficLens_mmdb_path() {
	return TrafficLens_PLUGIN_DIR . 'vendor/dbip-country-lite.mmdb';
}

// DATABASE

function TrafficLens_db() {
	static $db = null;
	if ($db === null) {
		$dir = TrafficLens_data_dir();
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$htaccess = $dir . '.htaccess';
		if (!file_exists($htaccess)) {
			file_put_contents($htaccess, "Require all denied\n");
		}

		$db = new SQLite3(TrafficLens_db_path());
		$db->busyTimeout(5000);
		$db->exec('PRAGMA journal_mode=WAL;');
		$db->exec('PRAGMA synchronous=NORMAL;');
	}
	return $db;
}

function TrafficLens_init_db() {
	$db = TrafficLens_db();
	$db->exec("
		CREATE TABLE IF NOT EXISTS visits (
			id              INTEGER PRIMARY KEY AUTOINCREMENT,
			ip_hash         TEXT    NOT NULL,
			country_code    TEXT,
			referrer_domain TEXT,
			browser         TEXT,
			os              TEXT,
			device_type     TEXT,
			url             TEXT    NOT NULL,
			timestamp       INTEGER NOT NULL
		);
		CREATE INDEX IF NOT EXISTS idx_visits_ts      ON visits(timestamp);
		CREATE INDEX IF NOT EXISTS idx_visits_ip_hash ON visits(ip_hash);
		CREATE INDEX IF NOT EXISTS idx_visits_url     ON visits(url);
	");
}

// SETTINGS  (simple JSON store - swapped for a proper admin form in the dashboard phase)

function TrafficLens_default_settings() {
	return [
		'exclude_admin'    => true,  // don't log visits from logged-in GS users browsing the front-end
		'retention_days'   => 400,   // prune rows older than this on each write
		'refresh_seconds'  => 60,    // dashboard auto-refresh interval (used by the future dashboard JS)
	];
}

function TrafficLens_settings() {
	static $settings = null;
	if ($settings === null) {
		$path = TrafficLens_settings_path();
		$defaults = TrafficLens_default_settings();
		if (file_exists($path)) {
			$stored = json_decode(file_get_contents($path), true);
			$settings = is_array($stored) ? array_merge($defaults, $stored) : $defaults;
		} else {
			$settings = $defaults;
		}
	}
	return $settings;
}

function TrafficLens_save_settings($new_settings) {
	$dir = TrafficLens_data_dir();
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	$merged = array_merge(TrafficLens_default_settings(), $new_settings);
	file_put_contents(TrafficLens_settings_path(), json_encode($merged, JSON_PRETTY_PRINT));
	return $merged;
}

// GEO LOOKUP

/**
 * Returns a 2-letter ISO country code for a raw (unhashed) IP address,
 * or null if the .mmdb file isn't present yet or the IP isn't found.
 * Always call this BEFORE hashing the IP - the raw address never gets stored.
 */
function TrafficLens_lookup_country($raw_ip) {
	static $mmdb = null;
	static $checked = false;

	if (!$checked) {
		$checked = true;
		$path = TrafficLens_mmdb_path();
		if (is_readable($path)) {
			require_once TrafficLens_PLUGIN_DIR . 'inc/mmdb-reader.php';
			$reader = new TrafficLens_MMDB($path);
			if ($reader->is_ready()) $mmdb = $reader;
		}
	}

	if (!$mmdb || empty($raw_ip)) return null;

	$data = $mmdb->lookup($raw_ip);
	return $data['country']['iso_code'] ?? null;
}
