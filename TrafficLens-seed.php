<?php
/*
 * TrafficLens dummy data generator - NOT part of the plugin, for personal
 * testing only. Delete when you're done.
 *
 * Inserts synthetic visits directly into the SQLite DB (bypassing the real
 * tracking hook entirely, so we can backdate timestamps across the last N
 * days). Generates realistic-ish "sessions" - a pool of fake visitors, each
 * with a consistent country/browser/os/device, taking 1-5 page views in a
 * cluster - so entry/exit pages, bounce rate, and new-vs-returning all have
 * something meaningful to show instead of being empty.
 *
 * All dummy rows use an ip_hash starting with "dummy_" (real hashed IPs are
 * 64 hex chars and will never collide with this), so they're easy to find
 * and wipe later.
 *
 * Usage:
 *   php TrafficLens-seed.php            -> inserts dummy data
 *   php TrafficLens-seed.php clear      -> deletes all dummy rows, nothing else
 *
 * Or open in a browser: TrafficLens-seed.php / TrafficLens-seed.php?clear=1
 */

// ── Config - adjust the path if you're not running from the GS root ────────
$db_path = __DIR__ . '/data/other/TrafficLens/TrafficLens.db';

$days_back           = 30;   // how many days of history to generate
$min_sessions_per_day = 4;
$max_sessions_per_day = 25;
$persona_pool_size    = 60;  // distinct fake "visitors" - some will naturally repeat across days (returning visitors)

// ─────────────────────────────────────────────────────────────────────────

$clear_only = (php_sapi_name() === 'cli')
	? (isset($argv[1]) && $argv[1] === 'clear')
	: isset($_GET['clear']);

if (!file_exists($db_path)) {
	die("Can't find the database at: $db_path\nAdjust \$db_path at the top of this script if TrafficLens.db lives somewhere else, or run the plugin at least once first so it gets created.\n");
}

$db = new SQLite3($db_path);
$db->busyTimeout(5000);

if ($clear_only) {
	$db->exec("DELETE FROM visits WHERE ip_hash LIKE 'dummy_%'");
	echo "Cleared all dummy rows. Real tracked visits were left untouched.\n";
	exit;
}

// ── Reference data pools ────────────────────────────────────────────────────

$countries = ['US','US','US','GB','DE','FR','CA','AU','BR','IN','JP','NL','ES','SE','MX']; // weighted toward US
$referrers = [null, null, null, null, 'google.com', 'google.com', 'bing.com', 'duckduckgo.com', 'facebook.com', 'x.com', 'reddit.com', 't.co']; // ~1/3 direct
$urls = ['/', '/', '/about', '/contact', '/products', '/blog/post-1', '/blog/post-2', '/blog/post-3', '/pricing', '/faq'];

$device_profiles = [
	// [device_type, os, browser weight-list]
	['desktop', 'Windows 10/11', ['Chrome','Chrome','Firefox','Edge']],
	['desktop', 'macOS',         ['Safari','Chrome','Firefox']],
	['desktop', 'Linux',         ['Firefox','Chrome']],
	['mobile',  'iOS',           ['Safari','Chrome']],
	['mobile',  'Android',       ['Chrome','Samsung Internet']],
	['tablet',  'iOS',           ['Safari']],
];

function pick($arr) { return $arr[array_rand($arr)]; }

// ── Build a pool of fake visitor "personas" with consistent characteristics ─
$personas = [];
for ($i = 0; $i < $persona_pool_size; $i++) {
	$profile = pick($device_profiles);
	$personas[] = [
		'ip_hash'     => 'dummy_' . str_pad($i, 4, '0', STR_PAD_LEFT),
		'country'     => pick($countries),
		'device_type' => $profile[0],
		'os'          => $profile[1],
		'browser'     => pick($profile[2]),
	];
}

// ── Generate sessions across the date range ─────────────────────────────────
$stmt = $db->prepare('
	INSERT INTO visits (ip_hash, country_code, referrer_domain, browser, os, device_type, url, timestamp)
	VALUES (:ip_hash, :country_code, :referrer_domain, :browser, :os, :device_type, :url, :ts)
');

$db->exec('BEGIN IMMEDIATE');
$inserted = 0;

for ($day = $days_back - 1; $day >= 0; $day--) {
	$day_start = strtotime("-$day days", strtotime('today'));
	$sessions_today = rand($min_sessions_per_day, $max_sessions_per_day);

	for ($s = 0; $s < $sessions_today; $s++) {
		$persona = pick($personas);

		// Bias session start toward business hours (9am-9pm) without excluding other hours entirely
		$hour = (rand(0, 100) < 70) ? rand(9, 21) : rand(0, 23);
		$session_start = $day_start + ($hour * 3600) + rand(0, 3599);

		// ~35% single-page sessions (bounce), rest 2-5 pages
		$page_count = (rand(0, 100) < 35) ? 1 : rand(2, 5);

		$ts = $session_start;
		// Referrer only applies to the first page of a session (like a real entry)
		$session_referrer = pick($referrers);

		for ($p = 0; $p < $page_count; $p++) {
			$stmt->bindValue(':ip_hash',         $persona['ip_hash'],                  SQLITE3_TEXT);
			$stmt->bindValue(':country_code',    $persona['country'],                  SQLITE3_TEXT);
			$stmt->bindValue(':referrer_domain', $p === 0 ? $session_referrer : null,  SQLITE3_TEXT);
			$stmt->bindValue(':browser',         $persona['browser'],                  SQLITE3_TEXT);
			$stmt->bindValue(':os',              $persona['os'],                       SQLITE3_TEXT);
			$stmt->bindValue(':device_type',     $persona['device_type'],              SQLITE3_TEXT);
			$stmt->bindValue(':url',             pick($urls),                          SQLITE3_TEXT);
			$stmt->bindValue(':ts',              $ts,                                  SQLITE3_INTEGER);
			$stmt->execute();
			$inserted++;

			$ts += rand(20, 240); // 20s-4min between pages in the same session
		}
	}
}

$db->exec('COMMIT');

echo "Inserted $inserted dummy page views across $days_back days from " . count($personas) . " fake visitors.\n";
echo "Run again with 'clear' as an argument (or ?clear=1 in a browser) to remove them.\n";
