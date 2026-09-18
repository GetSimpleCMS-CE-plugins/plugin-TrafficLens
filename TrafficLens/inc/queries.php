<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
 * Dashboard data layer.
 *
 * Design choice: for the main dashboard payload, we pull all rows in the
 * selected range ONCE (ordered by ip_hash, timestamp) and aggregate everything
 * - stats, daily trend, peak hours, device/browser breakdown, countries,
 *   referrers, and the session/bounce approximation - in a single PHP pass.
 *
 * This keeps day-bucketing consistent with PHP's own timezone handling
 * (avoids SQLite's 'localtime' modifier disagreeing with date_default_timezone_set),
 * and avoids juggling ~10 separate aggregate SQL queries with their own edge cases.
 * For typical GS-CE site traffic volumes this is fine; if a site's dataset ever
 * gets large enough for this to matter, that's a good problem to have and worth
 * revisiting with pre-aggregated tables at that point.
 *
 * Top Pages is the one exception - kept as a real paginated/sorted SQL query,
 * since it needs independent sorting, search and paging from the UI.
 */

// RANGE HELPERS

function TrafficLens_allowed_ranges() {
	return ['today', '7d', '30d', '90d', '365d', 'all'];
}

function TrafficLens_range_start($range) {
	$now = time();
	switch ($range) {
		case 'today': return strtotime('today');
		case '7d':    return $now - 7   * 86400;
		case '90d':   return $now - 90  * 86400;
		case '365d':  return $now - 365 * 86400;
		case 'all':   return 0;
		case '30d':
		default:      return $now - 30 * 86400;
	}
}

// FLAG EMOJI  (no image assets or country-name table needed)

function TrafficLens_flag_emoji($iso) {
	$iso = strtoupper((string)$iso);
	if (!preg_match('/^[A-Z]{2}$/', $iso)) return '';
	$flag = '';
	foreach (str_split($iso) as $c) {
		$flag .= mb_chr(0x1F1E6 + (ord($c) - 65), 'UTF-8');
	}
	return $flag;
}

// MAIN DASHBOARD PAYLOAD

function TrafficLens_get_range_rows($db, $start) {
	$stmt = $db->prepare('
		SELECT ip_hash, country_code, referrer_domain, browser, os, device_type, url, timestamp
		FROM visits WHERE timestamp >= :start
		ORDER BY ip_hash, timestamp
	');
	$stmt->bindValue(':start', $start, SQLITE3_INTEGER);
	$res = $stmt->execute();
	$rows = [];
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) { $rows[] = $r; }
	return $rows;
}

function TrafficLens_top_n_assoc($assoc, $n) {
	$out = [];
	$i = 0;
	foreach ($assoc as $k => $v) {
		if ($i++ >= $n) break;
		$out[] = ['key' => $k, 'count' => $v];
	}
	return $out;
}

// Groups consecutive same-ip_hash rows into sessions (30-min gap = new session).
function TrafficLens_session_stats($rows, $gap_seconds = 1800) {
	$entry = []; $exit = []; $sessions = 0; $bounces = 0;
	$prev_ip = null; $prev_ts = null;
	$session_first_url = null; $session_last_url = null; $session_pagecount = 0;

	$flush = function () use (&$entry, &$exit, &$sessions, &$bounces, &$session_first_url, &$session_last_url, &$session_pagecount) {
		if ($session_pagecount === 0) return;
		$entry[$session_first_url] = ($entry[$session_first_url] ?? 0) + 1;
		$exit[$session_last_url]   = ($exit[$session_last_url]   ?? 0) + 1;
		$sessions++;
		if ($session_pagecount === 1) $bounces++;
	};

	foreach ($rows as $r) {
		$ip = $r['ip_hash']; $ts = (int)$r['timestamp']; $url = $r['url'];
		if ($prev_ip !== $ip || ($prev_ts !== null && ($ts - $prev_ts) > $gap_seconds)) {
			$flush();
			$session_first_url = $url;
			$session_pagecount = 0;
		}
		$session_last_url = $url;
		$session_pagecount++;
		$prev_ip = $ip; $prev_ts = $ts;
	}
	$flush();

	arsort($entry);
	arsort($exit);

	return [
		'entry_pages'     => TrafficLens_top_n_assoc($entry, 10),
		'exit_pages'      => TrafficLens_top_n_assoc($exit, 10),
		'bounce_rate'     => $sessions > 0 ? round(($bounces / $sessions) * 100, 1) : 0.0,
		'total_sessions'  => $sessions,
	];
}

function TrafficLens_dashboard_payload($range) {
	$db    = TrafficLens_db();
	$start = TrafficLens_range_start($range);
	$rows  = TrafficLens_get_range_rows($db, $start);

	$now         = time();
	$today_start = strtotime('today');

	$unique_set          = [];
	$today_count         = 0;
	$live_set            = [];
	$live_country_counts = [];
	$daily               = []; // 'Y-m-d' => ['uniques' => [ip=>true,...], 'views' => int]
	$hourly              = array_fill(0, 24, 0);
	$hourly_uniques      = array_fill(0, 24, []); // hour => [ip=>true,...] - only needed for the "Today" chart
	$dow                 = array_fill(0, 7, 0); // 0 = Sunday
	$device              = [];
	$browser             = [];
	$os_bucket           = [];
	$country_sets        = []; // code => [ip=>true,...]
	$referrer            = [];

	foreach ($rows as $r) {
		$ip = $r['ip_hash'];
		$ts = (int)$r['timestamp'];

		$unique_set[$ip] = true;
		if ($ts >= $today_start) $today_count++;
		if ($ts >= $now - 300) {
			$live_set[$ip] = true;
			$code = $r['country_code'] ?: '??';
			$live_country_counts[$code] = ($live_country_counts[$code] ?? 0) + 1;
		}

		$day = date('Y-m-d', $ts);
		if (!isset($daily[$day])) $daily[$day] = ['uniques' => [], 'views' => 0];
		$daily[$day]['uniques'][$ip] = true;
		$daily[$day]['views']++;

		$hourly[(int)date('G', $ts)]++;
		$hourly_uniques[(int)date('G', $ts)][$ip] = true;
		$dow[(int)date('w', $ts)]++;

		$d = $r['device_type'] ?: 'unknown';
		$device[$d] = ($device[$d] ?? 0) + 1;

		$b = $r['browser'] ?: 'Unknown';
		$browser[$b] = ($browser[$b] ?? 0) + 1;

		$o = $r['os'] ?: 'Unknown';
		$os_bucket[$o] = ($os_bucket[$o] ?? 0) + 1;

		if (!empty($r['country_code'])) {
			$country_sets[$r['country_code']][$ip] = true;
		}
		if (!empty($r['referrer_domain'])) {
			$referrer[$r['referrer_domain']] = ($referrer[$r['referrer_domain']] ?? 0) + 1;
		}
	}

	// Trend chart 

	$labels = $uniques_series = $views_series = [];
	$trend_granularity = 'daily';

	if ($range === 'today') {
		$trend_granularity = 'hourly';
		for ($h = 0; $h < 24; $h++) {
			$labels[]         = date('g A', mktime($h, 0, 0));
			$uniques_series[] = count($hourly_uniques[$h]);
			$views_series[]   = $hourly[$h];
		}
	} elseif (!empty($daily)) {
		$first_day = $start > 0 ? date('Y-m-d', $start) : min(array_keys($daily));
		$cursor = strtotime($first_day);
		$end    = strtotime(date('Y-m-d', $now));
		$guard  = 0;
		while ($cursor <= $end && $guard < 3660) {
			$d = date('Y-m-d', $cursor);
			$labels[]         = $d;
			$uniques_series[] = isset($daily[$d]) ? count($daily[$d]['uniques']) : 0;
			$views_series[]   = isset($daily[$d]) ? $daily[$d]['views']          : 0;
			$cursor = strtotime('+1 day', $cursor);
			$guard++;
		}
	}

	// Top countries by unique visitor count
	$country_counts = [];
	foreach ($country_sets as $code => $set) { $country_counts[$code] = count($set); }
	arsort($country_counts);
	$top_countries = [];
	$i = 0;
	foreach ($country_counts as $code => $cnt) {
		if ($i++ >= 10) break;
		$top_countries[] = ['code' => $code, 'flag' => TrafficLens_flag_emoji($code), 'count' => $cnt];
	}

	// Live countries list
	arsort($live_country_counts);
	$live_countries = [];
	foreach ($live_country_counts as $code => $views) {
		$live_countries[] = [
			'code'  => $code,
			'flag'  => ($code !== '??') ? TrafficLens_flag_emoji($code) : '🌐',
			'views' => $views,
		];
	}

	// Top referrers
	arsort($referrer);
	$top_referrers = [];
	$i = 0;
	foreach ($referrer as $domain => $cnt) {
		if ($i++ >= 10) break;
		$top_referrers[] = ['domain' => $domain, 'count' => $cnt];
	}

	// New vs. returning
	// Only meaningful for a bounded range - for "all time" everyone is by
	// definition appearing for the first time within the window, so we
	// flag it as unavailable rather than show a misleading 100/0 split.
	$new_vs_returning = ['available' => false, 'new' => 0, 'returning' => 0];
	if ($start > 0 && !empty($unique_set)) {
		$earliest = $db->querySingle('SELECT MIN(timestamp) FROM visits');
		if ($earliest !== false && $earliest !== null && (int)$earliest < $start) {
			$ip_list = array_keys($unique_set);
			$first_seen_map = [];
			foreach (array_chunk($ip_list, 500) as $chunk) {
				$placeholders = implode(',', array_fill(0, count($chunk), '?'));
				$stmt = $db->prepare("SELECT ip_hash, MIN(timestamp) as fs FROM visits WHERE ip_hash IN ($placeholders) GROUP BY ip_hash");
				foreach ($chunk as $idx => $ip) { $stmt->bindValue($idx + 1, $ip, SQLITE3_TEXT); }
				$res = $stmt->execute();
				while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $first_seen_map[$row['ip_hash']] = (int)$row['fs']; }
			}
			$new_count = 0; $returning_count = 0;
			foreach ($ip_list as $ip) {
				$fs = $first_seen_map[$ip] ?? $now;
				if ($fs < $start) $returning_count++; else $new_count++;
			}
			$new_vs_returning = ['available' => true, 'new' => $new_count, 'returning' => $returning_count];
		}
	}

	// Session-based entry/exit/bounce
	$session_stats = TrafficLens_session_stats($rows);

	return [
		'range' => $range,
		'stats' => [
			'total'          => count($rows),
			'unique'         => count($unique_set),
			'today'          => $today_count,
			'live'           => count($live_set),
			'live_countries' => $live_countries,
		],
		'daily_trend' => [
			'labels'  => $labels,
			'uniques' => $uniques_series,
			'views'   => $views_series,
			'granularity' => $trend_granularity,
		],
		'hourly'            => $hourly,
		'day_of_week'       => $dow,
		'device_breakdown'  => $device,
		'browser_breakdown' => $browser,
		'os_breakdown'      => $os_bucket,
		'top_countries'     => $top_countries,
		'top_referrers'     => $top_referrers,
		'new_vs_returning'  => $new_vs_returning,
		'entry_pages'       => $session_stats['entry_pages'],
		'exit_pages'        => $session_stats['exit_pages'],
		'bounce_rate'       => $session_stats['bounce_rate'],
		'total_sessions'    => $session_stats['total_sessions'],
	];
}

// DASHBOARD MODULE SUMMARY  (lean - just what the "Daily Visitors" module needs)

function TrafficLens_daily_summary() {
	$db    = TrafficLens_db();
	$start = TrafficLens_range_start('30d');
	$now   = time();

	$stmt = $db->prepare('SELECT ip_hash, timestamp FROM visits WHERE timestamp >= :start ORDER BY timestamp');
	$stmt->bindValue(':start', $start, SQLITE3_INTEGER);
	$res = $stmt->execute();

	$daily = []; // 'Y-m-d' => ['uniques' => [ip=>true,...], 'views' => int]
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$ts  = (int)$r['timestamp'];
		$day = date('Y-m-d', $ts);
		if (!isset($daily[$day])) $daily[$day] = ['uniques' => [], 'views' => 0];
		$daily[$day]['uniques'][$r['ip_hash']] = true;
		$daily[$day]['views']++;
	}

	$labels = $uniques = $views = [];
	$cursor = strtotime(date('Y-m-d', $start));
	$end    = strtotime(date('Y-m-d', $now));
	$guard  = 0;
	while ($cursor <= $end && $guard < 40) {
		$d = date('Y-m-d', $cursor);
		$labels[]  = $d;
		$uniques[] = isset($daily[$d]) ? count($daily[$d]['uniques']) : 0;
		$views[]   = isset($daily[$d]) ? $daily[$d]['views']          : 0;
		$cursor = strtotime('+1 day', $cursor);
		$guard++;
	}

	$live_cutoff = $now - 300;
	$live_now    = (int)$db->querySingle('SELECT COUNT(DISTINCT ip_hash) FROM visits WHERE timestamp >= ' . $live_cutoff);
	$week_views  = array_sum(array_slice($views, -7));

	// Country breakdown for active visitors
	$c_stmt = $db->prepare('
		SELECT country_code, COUNT(*) as views 
		FROM visits 
		WHERE timestamp >= :cutoff 
		GROUP BY country_code 
		ORDER BY views DESC
	');
	$c_stmt->bindValue(':cutoff', $live_cutoff, SQLITE3_INTEGER);
	$c_res = $c_stmt->execute();

	$live_countries = [];
	while ($row = $c_res->fetchArray(SQLITE3_ASSOC)) {
		$code = $row['country_code'] ?: '??';
		$live_countries[] = [
			'code'  => $code,
			'flag'  => ($code !== '??') ? TrafficLens_flag_emoji($code) : '🌐',
			'views' => (int)$row['views'],
		];
	}

	return [
		'labels'         => $labels,
		'uniques'        => $uniques,
		'views'          => $views,
		'live_now'       => $live_now,
		'live_countries' => $live_countries,
		'week_views'     => $week_views,
	];
}

// TOP PAGES  (real SQL pagination/sort/search - independent of the range payload above)

function TrafficLens_top_pages($range, $page, $per_page, $sort, $dir, $search) {
	$db    = TrafficLens_db();
	$start = TrafficLens_range_start($range);

	$sort_map = ['url' => 'url', 'views' => 'views', 'uniques' => 'uniques'];
	$sort_col = $sort_map[$sort] ?? 'views';
	$dir      = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

	$where = 'WHERE timestamp >= :start';
	if ($search !== '') $where .= ' AND url LIKE :search';

	$count_stmt = $db->prepare("SELECT COUNT(*) as c FROM (SELECT url FROM visits $where GROUP BY url)");
	$count_stmt->bindValue(':start', $start, SQLITE3_INTEGER);
	if ($search !== '') $count_stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
	$total = (int)($count_stmt->execute()->fetchArray(SQLITE3_ASSOC)['c'] ?? 0);

	$per_page = max(1, min(100, (int)$per_page));
	$page     = max(1, (int)$page);
	$offset   = ($page - 1) * $per_page;

	$sql = "SELECT url, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques
	        FROM visits $where GROUP BY url ORDER BY $sort_col $dir LIMIT :limit OFFSET :offset";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':start', $start, SQLITE3_INTEGER);
	if ($search !== '') $stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
	$stmt->bindValue(':limit',  $per_page, SQLITE3_INTEGER);
	$stmt->bindValue(':offset', $offset,   SQLITE3_INTEGER);
	$res = $stmt->execute();

	$rows = [];
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) { $rows[] = $r; }

	return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page];
}

// LIVE ACTIVITY FEED

function TrafficLens_live_feed($limit = 20) {
	$db = TrafficLens_db();
	$stmt = $db->prepare('SELECT country_code, url, timestamp FROM visits ORDER BY id DESC LIMIT :limit');
	$stmt->bindValue(':limit', (int)$limit, SQLITE3_INTEGER);
	$res = $stmt->execute();

	$feed = [];
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$feed[] = [
			'time'         => date('H:i', (int)$r['timestamp']),
			'country_code' => $r['country_code'],
			'flag'         => $r['country_code'] ? TrafficLens_flag_emoji($r['country_code']) : '',
			'url'          => $r['url'],
		];
	}

	$live_cutoff = time() - 300;
	$live_now    = (int)$db->querySingle('SELECT COUNT(DISTINCT ip_hash) FROM visits WHERE timestamp >= ' . $live_cutoff);

	// Country breakdown for active visitors
	$c_stmt = $db->prepare('
		SELECT country_code, COUNT(*) as views 
		FROM visits 
		WHERE timestamp >= :cutoff 
		GROUP BY country_code 
		ORDER BY views DESC
	');
	$c_stmt->bindValue(':cutoff', $live_cutoff, SQLITE3_INTEGER);
	$c_res = $c_stmt->execute();

	$live_countries = [];
	while ($row = $c_res->fetchArray(SQLITE3_ASSOC)) {
		$code = $row['country_code'] ?: '??';
		$live_countries[] = [
			'code'  => $code,
			'flag'  => ($code !== '??') ? TrafficLens_flag_emoji($code) : '🌐',
			'views' => (int)$row['views'],
		];
	}

	return [
		'feed'           => $feed,
		'live_now'       => $live_now,
		'live_countries' => $live_countries,
	];
}

// CSV EXPORT  (streams directly, does not return - caller should treat as terminal)

function TrafficLens_export_csv($range) {
	$db    = TrafficLens_db();
	$start = TrafficLens_range_start($range);

	$stmt = $db->prepare('
		SELECT country_code, referrer_domain, browser, os, device_type, url, timestamp
		FROM visits WHERE timestamp >= :start ORDER BY timestamp DESC
	');
	$stmt->bindValue(':start', $start, SQLITE3_INTEGER);
	$res = $stmt->execute();

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="trafficlens-export-' . date('Y-m-d') . '.csv"');

	$out = fopen('php://output', 'w');
	fputcsv($out, ['date', 'time', 'country', 'referrer', 'browser', 'os', 'device', 'url']);
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		fputcsv($out, [
			date('Y-m-d', (int)$r['timestamp']),
			date('H:i:s', (int)$r['timestamp']),
			$r['country_code'],
			$r['referrer_domain'],
			$r['browser'],
			$r['os'],
			$r['device_type'],
			$r['url'],
		]);
	}
	fclose($out);
	exit;
}
