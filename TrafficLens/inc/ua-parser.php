<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
 * Lightweight User-Agent parsing. Deliberately coarse - broad browser/OS
 * families and a device-type bucket, not version numbers. No dependency.
 */

function TrafficLens_parse_ua($ua) {
	$ua = (string)$ua;

	$bot_name = TrafficLens_detect_bot($ua);
	$is_bot   = ($bot_name !== null);

	// Bots get one consistent label everywhere, so a bot that carries a "Chrome/" token
	// (e.g. Googlebot smartphone) no longer shows up as Chrome in the browser breakdown.
	return [
		'browser'     => $is_bot ? 'Bot/Crawler' : TrafficLens_ua_browser($ua),
		'os'          => TrafficLens_ua_os($ua),
		'device_type' => $is_bot ? 'bot' : TrafficLens_ua_device($ua),
		'is_bot'      => $is_bot ? 1 : 0,
		'bot_name'    => $bot_name,
	];
}

/*
 * Known bots: 'token to look for (case-insensitive)' => 'display name'.
 */
function TrafficLens_bot_signatures() {
	return [
		// Search engines (variants grouped)
		'Googlebot'              => 'Googlebot',
		'AdsBot-Google'          => 'Google AdsBot',
		'Mediapartners-Google'   => 'Google AdSense',
		'Google-InspectionTool'  => 'Google Inspection Tool',
		'GoogleOther'            => 'GoogleOther',
		'Storebot-Google'        => 'Google Storebot',
		'FeedFetcher-Google'     => 'Google Feedfetcher',
		'Google-Read-Aloud'      => 'Google Read Aloud',
		'APIs-Google'            => 'Google APIs',
		'Chrome-Lighthouse'      => 'Lighthouse',
		'bingbot'                => 'Bingbot',
		'BingPreview'            => 'Bing Preview',
		'AdIdxBot'               => 'Bing AdIdxBot',
		'msnbot'                 => 'MSNbot',
		'Slurp'                  => 'Yahoo Slurp',
		'YandexImages'           => 'YandexImages',
		'YandexMetrika'          => 'YandexMetrika',

		// AI assistants / crawlers that don't carry a plain "bot" token
		'ChatGPT-User'           => 'ChatGPT-User',
		'Claude-User'            => 'Claude-User',
		'Perplexity-User'        => 'Perplexity-User',
		'MistralAI-User'         => 'MistralAI-User',
		'anthropic-ai'           => 'anthropic-ai',
		'cohere-ai'              => 'cohere-ai',
		'Meta-ExternalAgent'     => 'Meta-ExternalAgent',
		'meta-externalfetcher'   => 'Meta-ExternalFetcher',

		// Link previews / social
		'facebookexternalhit'    => 'Facebook',
		'WhatsApp/'              => 'WhatsApp',
		'Pinterest/0.'           => 'Pinterest',   // crawler only; the Pinterest in-app browser is a real visitor
		'SkypeUriPreview'        => 'Skype Preview',
		'Embedly'                => 'Embedly',
		'Iframely'               => 'Iframely',
		'Mastodon/'              => 'Mastodon',

		// Uptime / performance monitors
		'Pingdom'                => 'Pingdom',
		'StatusCake'             => 'StatusCake',
		'Site24x7'               => 'Site24x7',
		'Uptime-Kuma'            => 'Uptime Kuma',
		'GTmetrix'               => 'GTmetrix',
		'NewRelicPinger'         => 'New Relic',
		'Datadog'                => 'Datadog',

		// Security scanners
		'Nmap'                   => 'Nmap',
		'Nikto'                  => 'Nikto',
		'sqlmap'                 => 'sqlmap',
		'masscan'                => 'masscan',
		'zgrab'                  => 'zgrab',
		'Nuclei'                 => 'Nuclei',
		'CensysInspect'          => 'Censys',

		// HTTP libraries, CLI tools and headless browsers
		'HeadlessChrome'         => 'Headless Chrome',
		'PhantomJS'              => 'PhantomJS',
		'Playwright'             => 'Playwright',
		'Puppeteer'              => 'Puppeteer',
		'curl/'                  => 'curl',
		'Wget'                   => 'Wget',
		'python-requests'        => 'python-requests',
		'python-urllib'          => 'python-urllib',
		'python-httpx'           => 'python-httpx',
		'aiohttp'                => 'aiohttp',
		'Go-http-client'         => 'Go-http-client',
		'Java/'                  => 'Java client',
		'Apache-HttpClient'      => 'Apache HttpClient',
		'libwww-perl'            => 'libwww-perl',
		'Scrapy'                 => 'Scrapy',
		'node-fetch'             => 'node-fetch',
		'axios/'                 => 'axios',
		'okhttp'                 => 'okhttp',
		'PostmanRuntime'         => 'Postman',
		'HTTPie'                 => 'HTTPie',
		'WordPress/'             => 'WordPress pingback',
	];
}

/*
 * Returns a display name if the User-Agent looks like a bot, otherwise null.
 */
function TrafficLens_detect_bot($ua) {
	$ua = trim((string)$ua);

	foreach (TrafficLens_bot_signatures() as $token => $name) {
		if (stripos($ua, $token) !== false) return $name;
	}

	// Real browsers always send a substantial UA. Empty or tiny ones are scripts.
	if (strlen($ua) < 12) return 'Unknown client';

	// CUBOT phones have "bot" in their model name but are real devices.
	$probe = str_ireplace('cubot', '', $ua);

	// Generic catch-all: name the bot after its own token (e.g. "SeznamBot", "MJ12bot").
	if (preg_match('/([A-Za-z0-9._-]*(?:bot|spider|crawl|slurp)[A-Za-z0-9._-]*)/i', $probe, $m)) {
		$name = substr(preg_replace('/[^A-Za-z0-9 ._-]/', '', $m[1]), 0, 40);
		// A bare "bot", "bots" or "bot.html" is just the tail of an info URL, not a name.
		if ($name === '' || preg_match('/^(?:bots?|crawlers?|spiders?)(?:\.\w+)?$/i', $name)) {
			return 'Other bot';
		}
		return $name;
	}

	if (preg_match('/scrap(?:er|y)|fetcher|headless|validator|archiver|monitor/i', $probe)) {
		return 'Other bot';
	}

	return null;
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
