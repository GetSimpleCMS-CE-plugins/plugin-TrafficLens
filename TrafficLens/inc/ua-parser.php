<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
 * Lightweight User-Agent parsing. Deliberately coarse - broad browser/OS
 * families and a device-type bucket, not version numbers. No dependency.
 */

function TrafficLens_parse_ua($ua) {
	$ua = (string)$ua;

	return [
		'browser'     => TrafficLens_ua_browser($ua),
		'os'          => TrafficLens_ua_os($ua),
		'device_type' => TrafficLens_ua_device($ua),
	];
}

function TrafficLens_ua_browser($ua) {
	if ($ua === '') return 'Unknown';

	// Order matters: many browsers include "Safari" or "Chrome" tokens
	// in their UA string for compatibility, so check the more specific ones first.
	if (preg_match('/Edg\//i', $ua))                          return 'Edge';
	if (preg_match('/OPR\/|Opera/i', $ua))                     return 'Opera';
	if (preg_match('/SamsungBrowser/i', $ua))                  return 'Samsung Internet';
	if (preg_match('/YaBrowser/i', $ua))                       return 'Yandex';
	if (preg_match('/Firefox\//i', $ua))                       return 'Firefox';
	if (preg_match('/CriOS/i', $ua))                           return 'Chrome'; // Chrome on iOS
	if (preg_match('/Chrome\//i', $ua) && !preg_match('/Chromium/i', $ua)) return 'Chrome';
	if (preg_match('/Chromium/i', $ua))                        return 'Chromium';
	if (preg_match('/Version\/.*Safari/i', $ua))               return 'Safari'; // must come after Chrome/CriOS check
	if (preg_match('/MSIE|Trident/i', $ua))                    return 'Internet Explorer';
	if (preg_match('/bot|spider|crawl|slurp/i', $ua))          return 'Bot/Crawler';

	return 'Other';
}

function TrafficLens_ua_os($ua) {
	if ($ua === '') return 'Unknown';

	if (preg_match('/Windows NT 10/i', $ua))       return 'Windows 10/11';
	if (preg_match('/Windows NT/i', $ua))          return 'Windows';
	if (preg_match('/Mac OS X|Macintosh/i', $ua))  return 'macOS';
	if (preg_match('/iPhone|iPad|iPod/i', $ua))    return 'iOS';
	if (preg_match('/Android/i', $ua))             return 'Android';
	if (preg_match('/CrOS/i', $ua))                return 'ChromeOS';
	if (preg_match('/Linux/i', $ua))               return 'Linux';

	return 'Other';
}

function TrafficLens_ua_device($ua) {
	if ($ua === '') return 'unknown';

	if (preg_match('/bot|spider|crawl|slurp/i', $ua)) return 'bot';
	if (preg_match('/iPad|Tablet(?!.*Mobile)/i', $ua)) return 'tablet';
	if (preg_match('/Mobi|Android.*Mobile|iPhone|iPod/i', $ua)) return 'mobile';

	return 'desktop';
}
