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
 * It also generates BOT traffic (is_bot = 1, with a bot_name) so the "Bot
 * traffic" setting has something to filter: search/AI crawlers that read many
 * pages in quick bursts, uptime monitors and scripts that hit one page and
 * leave (these inflate the bounce rate), plus a few hits in the last few
 * minutes so the Live now counter reacts too.
 *
 * All dummy rows use an ip_hash starting with "dummy_" (real hashed IPs are
 * 64 hex chars and will never collide with this), so they're easy to find
 * and wipe later.
 *
 * Usage:
 *   php TrafficLens-seed.php            -> inserts dummy data (human + bot + a few live hits)
 *   php TrafficLens-seed.php live       -> inserts ONLY a few hits from the last few minutes
 *                                          (refresh the Live now counter without adding history)
 *   php TrafficLens-seed.php clear      -> deletes all dummy rows, nothing else
 *
 * Or open in a browser: TrafficLens-seed.php / ?live=1 / ?clear=1
 *
 * Tip: run "clear" before re-seeding, otherwise the new data is added on top of the old.
 */

// ── Config - adjust the path if you're not running from the GS root ────────
$db_path = __DIR__ . '/data/other/TrafficLens/TrafficLens.db';

$days_back           = 30;   // how many days of history to generate
$min_sessions_per_day = 4;
$max_sessions_per_day = 25;
$persona_pool_size    = 60;  // distinct fake "visitors" - some will naturally repeat across days (returning visitors)

$min_bot_sessions_per_day = 3;   // bot visits per day (each one is a crawl burst or a single ping)
$max_bot_sessions_per_day = 14;

// ─────────────────────────────────────────────────────────────────────────

$is_cli = (php_sapi_name() === 'cli');
$arg    = $is_cli ? ($argv[1] ?? '') : (isset($_GET['clear']) ? 'clear' : (isset($_GET['live']) ? 'live' : ''));
$clear_only = ($arg === 'clear');
$live_only  = ($arg === 'live');

if (!$is_cli) header('Content-Type: text/plain; charset=UTF-8');

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

// The bot columns are added by the plugin's own migration, which runs when the plugin loads.
$has_bot_cols = false;
$cols = $db->query('PRAGMA table_info(visits)');
while ($c = $cols->fetchArray(SQLITE3_ASSOC)) {
	if ($c['name'] === 'is_bot') $has_bot_cols = true;
}
if (!$has_bot_cols) {
	die("The visits table has no is_bot column yet.\nUpload the Phase 1 plugin files and load any page of the site (or the TrafficLens dashboard) once so the plugin migrates the database, then run this again.\n");
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

// Bot profiles. 'kind' drives behaviour:
//   crawler = reads several pages in quick bursts, no referrer
//   monitor = one request to the home page, then gone (a "bounce")
//   script  = one or two requests, then gone
// 'weight' is how often this bot shows up relative to the others.
$bot_profiles = [
	['name' => 'Googlebot',       'kind' => 'crawler', 'country' => 'US', 'os' => 'Android', 'weight' => 6],
	['name' => 'Bingbot',         'kind' => 'crawler', 'country' => 'US', 'os' => 'Other',   'weight' => 3],
	['name' => 'GPTBot',          'kind' => 'crawler', 'country' => 'US', 'os' => 'Other',   'weight' => 3],
	['name' => 'ClaudeBot',       'kind' => 'crawler', 'country' => 'US', 'os' => 'Other',   'weight' => 2],
	['name' => 'AhrefsBot',       'kind' => 'crawler', 'country' => 'DE', 'os' => 'Other',   'weight' => 3],
	['name' => 'SemrushBot',      'kind' => 'crawler', 'country' => 'US', 'os' => 'Other',   'weight' => 2],
	['name' => 'Facebook',        'kind' => 'script',  'country' => 'IE', 'os' => 'Other',   'weight' => 2],
	['name' => 'UptimeRobot',     'kind' => 'monitor', 'country' => 'US', 'os' => 'Other',   'weight' => 5],
	['name' => 'curl',            'kind' => 'script',  'country' => 'SG', 'os' => 'Other',   'weight' => 2],
	['name' => 'Headless Chrome', 'kind' => 'script',  'country' => 'NL', 'os' => 'Linux',   'weight' => 2],
	['name' => 'Unknown client',  'kind' => 'script',  'country' => 'CN', 'os' => 'Other',   'weight' => 2],
];

function pick($arr) { return $arr[array_rand($arr)]; }

function pick_weighted($items) {
	$total = 0;
	foreach ($items as $it) $total += $it['weight'];
	$r = rand(1, $total);
	foreach ($items as $it) {
		$r -= $it['weight'];
		if ($r <= 0) return $it;
	}
	return $items[0];
}

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

// Bots: each bot gets a small pool of "IPs" of its own (dummy_bot_...), like real crawler farms.
$bot_personas = [];
foreach ($bot_profiles as $bi => $bp) {
	$pool = ($bp['kind'] === 'crawler') ? 3 : 2;
	for ($k = 0; $k < $pool; $k++) {
		$bot_personas[] = $bp + ['ip_hash' => 'dummy_bot_' . str_pad($bi, 2, '0', STR_PAD_LEFT) . '_' . $k];
	}
}

// ── One prepared statement for every row (human and bot) ────────────────────
$stmt = $db->prepare('
	INSERT INTO visits (ip_hash, country_code, referrer_domain, browser, os, device_type, is_bot, bot_name, url, timestamp)
	VALUES (:ip_hash, :country_code, :referrer_domain, :browser, :os, :device_type, :is_bot, :bot_name, :url, :ts)
');

function insert_human($stmt, $persona, $referrer, $url, $ts) {
	$stmt->bindValue(':ip_hash',         $persona['ip_hash'],     SQLITE3_TEXT);
	$stmt->bindValue(':country_code',    $persona['country'],     SQLITE3_TEXT);
	$stmt->bindValue(':referrer_domain', $referrer,               SQLITE3_TEXT);
	$stmt->bindValue(':browser',         $persona['browser'],     SQLITE3_TEXT);
	$stmt->bindValue(':os',              $persona['os'],          SQLITE3_TEXT);
	$stmt->bindValue(':device_type',     $persona['device_type'], SQLITE3_TEXT);
	$stmt->bindValue(':is_bot',          0,                       SQLITE3_INTEGER);
	$stmt->bindValue(':bot_name',        null,                    SQLITE3_TEXT);
	$stmt->bindValue(':url',             $url,                    SQLITE3_TEXT);
	$stmt->bindValue(':ts',              $ts,                     SQLITE3_INTEGER);
	$stmt->execute();
	$stmt->reset();
}

function insert_bot($stmt, $bot, $url, $ts) {
	$stmt->bindValue(':ip_hash',         $bot['ip_hash'],  SQLITE3_TEXT);
	$stmt->bindValue(':country_code',    $bot['country'],  SQLITE3_TEXT);
	$stmt->bindValue(':referrer_domain', null,             SQLITE3_TEXT);
	$stmt->bindValue(':browser',         'Bot/Crawler',    SQLITE3_TEXT);
	$stmt->bindValue(':os',              $bot['os'],       SQLITE3_TEXT);
	$stmt->bindValue(':device_type',     'bot',            SQLITE3_TEXT);
	$stmt->bindValue(':is_bot',          1,                SQLITE3_INTEGER);
	$stmt->bindValue(':bot_name',        $bot['name'],     SQLITE3_TEXT);
	$stmt->bindValue(':url',             $url,             SQLITE3_TEXT);
	$stmt->bindValue(':ts',              $ts,              SQLITE3_INTEGER);
	$stmt->execute();
	$stmt->reset();
}

$db->exec('BEGIN IMMEDIATE');
$inserted_human = 0;
$inserted_bot   = 0;

// ── Generate sessions across the date range ─────────────────────────────────
if (!$live_only) {
	for ($day = $days_back - 1; $day >= 0; $day--) {
		$day_start = strtotime("-$day days", strtotime('today'));
		$now       = time();

		// Human sessions
		$sessions_today = rand($min_sessions_per_day, $max_sessions_per_day);

		for ($s = 0; $s < $sessions_today; $s++) {
			$persona = pick($personas);

			// Bias session start toward business hours (9am-9pm) without excluding other hours entirely
			$hour = (rand(0, 100) < 70) ? rand(9, 21) : rand(0, 23);
			$session_start = $day_start + ($hour * 3600) + rand(0, 3599);

			// ~35% single-page sessions (bounce), rest 2-5 pages
			$page_count = (rand(0, 100) < 35) ? 1 : rand(2, 5);

			// Today: keep the whole session in the past and outside the 5-minute "Live now" window,
			// otherwise random future hours would inflate Live now. The "live" hits are added separately below.
			if ($day === 0) {
				$latest = $now - 400 - ($page_count * 240);
				if ($session_start > $latest) $session_start = $latest - rand(0, 7200);
			}

			$ts = $session_start;
			// Referrer only applies to the first page of a session (like a real entry)
			$session_referrer = pick($referrers);

			for ($p = 0; $p < $page_count; $p++) {
				insert_human($stmt, $persona, $p === 0 ? $session_referrer : null, pick($urls), $ts);
				$inserted_human++;

				$ts += rand(20, 240); // 20s-4min between pages in the same session
			}
		}

		// Bot sessions - spread evenly across the day, not just business hours
		$bot_sessions_today = rand($min_bot_sessions_per_day, $max_bot_sessions_per_day);

		for ($s = 0; $s < $bot_sessions_today; $s++) {
			$bp = pick_weighted($bot_profiles);
			// pick one of this bot's own "IPs"
			$candidates = array_values(array_filter($bot_personas, function ($b) use ($bp) { return $b['name'] === $bp['name']; }));
			$bot = pick($candidates);

			$ts = $day_start + rand(0, 86399);

			if ($bot['kind'] === 'crawler')      $page_count = rand(3, 12);
			elseif ($bot['kind'] === 'monitor')  $page_count = 1;
			else                                 $page_count = rand(1, 2);

			if ($day === 0) {
				$latest = $now - 400 - ($page_count * 6);
				if ($ts > $latest) $ts = $latest - rand(0, 7200);
			}

			for ($p = 0; $p < $page_count; $p++) {
				$url = ($bot['kind'] === 'monitor') ? '/' : pick($urls);
				insert_bot($stmt, $bot, $url, $ts);
				$inserted_bot++;

				$ts += rand(1, 6); // crawlers move fast: seconds, not minutes
			}
		}
	}
}

// ── A few hits inside the "Live now" window (last 5 minutes) ────────────────
$now = time();
for ($i = 0; $i < 2; $i++) {
	insert_human($stmt, pick($personas), null, pick($urls), $now - rand(10, 240));
	$inserted_human++;
}
for ($i = 0; $i < 3; $i++) {
	insert_bot($stmt, pick($bot_personas), pick($urls), $now - rand(10, 240));
	$inserted_bot++;
}

$db->exec('COMMIT');

if ($live_only) {
	echo "Inserted $inserted_human human + $inserted_bot bot hits from the last few minutes (for the Live now counter).\n";
} else {
	echo "Inserted $inserted_human human page views and $inserted_bot bot page views across $days_back days ";
	echo "from " . count($personas) . " fake visitors and " . count($bot_personas) . " fake bot IPs.\n";
}
echo "Run again with 'clear' as an argument (or ?clear=1 in a browser) to remove them.\n";
