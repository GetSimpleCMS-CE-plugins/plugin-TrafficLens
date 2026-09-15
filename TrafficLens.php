<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
Plugin Name: TrafficLens
Description: Privacy-first visitor analytics with country, browser, and referrer breakdowns. (SQLite3 required)
Version: 1.2
Author: risingisland
Author URI: https://ko-fi.com/ericmontgomery
*/

i18n_merge('TrafficLens') || i18n_merge('TrafficLens', 'en_US');

// ─── Constants
define('TrafficLens_SLUG',       basename(__FILE__, '.php'));
define('TrafficLens_PLUGIN_DIR', GSPLUGINPATH . TrafficLens_SLUG . '/');
define('TrafficLens_PLUGIN_URL', $SITEURL . 'plugins/' . TrafficLens_SLUG . '/');

require_once TrafficLens_PLUGIN_DIR . 'inc/db.php';
require_once TrafficLens_PLUGIN_DIR . 'inc/ua-parser.php';
require_once TrafficLens_PLUGIN_DIR . 'inc/queries.php';

// ─── Bootstrap

register_plugin(
	TrafficLens_SLUG,
	'TrafficLens',
	'1.3',
	'risingisland',
	'https://ko-fi.com/ericmontgomery',
	'Privacy-first visitor analytics with country, browser, and referrer breakdowns. (SQLite3 required)',
	'settings',
	'TrafficLens_admin_page'
);

add_action('settings-sidebar', 'createSideMenu', array(TrafficLens_SLUG, i18n_r('TrafficLens/lang_Menu_Title') . ' <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="18px" height="18px" viewBox="0 0 14 14"><path d="M0 0h14v14H0z" fill="none"/><g fill="none"><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M.75.75v10.5a2 2 0 0 0 2 2h10.5"/><path fill="#d7e0ff" d="M4.5 7.25c1.12 0 1.75-.63 1.75-1.75S5.62 3.75 4.5 3.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.25c1.12 0 1.75-.63 1.75-1.75S5.62 3.75 4.5 3.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path fill="#d7e0ff" d="M11.5 4.25c1.12 0 1.75-.63 1.75-1.75S12.62.75 11.5.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M11.5 4.25c1.12 0 1.75-.63 1.75-1.75S12.62.75 11.5.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path fill="#d7e0ff" d="M9 10.75c1.12 0 1.75-.63 1.75-1.75S10.12 7.25 9 7.25S7.25 7.88 7.25 9s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M9 10.75c1.12 0 1.75-.63 1.75-1.75S10.12 7.25 9 7.25S7.25 7.88 7.25 9s.63 1.75 1.75 1.75M3.313 6.906L.75 9.766m10.437-5.538l-1 3.366M6.113 6.292L7.56 7.883"/></g></svg>'));

TrafficLens_init_db();

// ── Early AJAX intercept
if (
	!empty($_REQUEST['tl_action']) &&
	isset($_GET['id']) && $_GET['id'] === TrafficLens_SLUG
) {
	require_once TrafficLens_PLUGIN_DIR . 'inc/ajax.php';
	TrafficLens_handle_ajax($_REQUEST['tl_action']);
	// TrafficLens_handle_ajax() always exit()s - this is a safety net:
	exit;
}

// Record the hit after the page has rendered - same hook point as the plugin this replaces.
add_action('theme-footer', 'TrafficLens_track_visit');

// CORE TRACKING HOOK  (runs on every front-end page view)

function TrafficLens_track_visit() {
	// Skip 404s - not a real page view
	if (http_response_code() === 404) return;

	$settings = TrafficLens_settings();

	// Skip the site owner's own browsing, if enabled (default: on)
	if (!empty($settings['exclude_admin']) && function_exists('is_logged_in') && is_logged_in()) {
		return;
	}

	$current_url = $_SERVER['REQUEST_URI'] ?? '';

	// Skip GetSimple's internal search/pagination query params, same as the plugin this replaces
	if (strpos($current_url, '?search=') !== false || strpos($current_url, '?is=') !== false) {
		return;
	}
	$current_url = filter_var($current_url, FILTER_SANITIZE_URL);

	$raw_ip = $_SERVER['REMOTE_ADDR'] ?? '';

	// Geo lookup MUST happen before hashing - the raw IP is never stored.
	$country_code = TrafficLens_lookup_country($raw_ip);
	$ip_hash      = hash('sha256', $raw_ip);

	$referrer_domain = null;
	if (!empty($_SERVER['HTTP_REFERER'])) {
		$host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		// Only record referrers from other sites - skip self-referrals from internal navigation
		if ($host && $host !== ($_SERVER['HTTP_HOST'] ?? '')) {
			$referrer_domain = $host;
		}
	}

	$ua = TrafficLens_parse_ua($_SERVER['HTTP_USER_AGENT'] ?? '');
	$now = time();

	$db = TrafficLens_db();
	$stmt = $db->prepare('
		INSERT INTO visits (ip_hash, country_code, referrer_domain, browser, os, device_type, url, timestamp)
		VALUES (:ip_hash, :country_code, :referrer_domain, :browser, :os, :device_type, :url, :ts)
	');
	$stmt->bindValue(':ip_hash',         $ip_hash,               SQLITE3_TEXT);
	$stmt->bindValue(':country_code',    $country_code,          SQLITE3_TEXT);
	$stmt->bindValue(':referrer_domain', $referrer_domain,       SQLITE3_TEXT);
	$stmt->bindValue(':browser',         $ua['browser'],         SQLITE3_TEXT);
	$stmt->bindValue(':os',              $ua['os'],              SQLITE3_TEXT);
	$stmt->bindValue(':device_type',     $ua['device_type'],     SQLITE3_TEXT);
	$stmt->bindValue(':url',             $current_url,           SQLITE3_TEXT);
	$stmt->bindValue(':ts',              $now,                   SQLITE3_INTEGER);
	$stmt->execute();

	// Prune old rows on write, same pattern as the plugin this replaces
	$retention_days = (int)($settings['retention_days'] ?? 400);
	if ($retention_days > 0) {
		$cutoff = $now - ($retention_days * 86400);
		$db->exec("DELETE FROM visits WHERE timestamp < {$cutoff}");
	}
}

// ADMIN PAGE

function TrafficLens_admin_page() {
	require TrafficLens_PLUGIN_DIR . 'dashboard.php';
}
