# TrafficLens

Privacy-first visitor analytics for GetSimpleCMS-CE. Tracks page views, unique
visitors, referrers, browser/OS/device, and country — without cookies, without
third-party trackers, and without ever storing a visitor's raw IP address.

---

## Features

- **Stat overview** — total page views, unique visitors, today's traffic, and a live "visitors online now" count
- **Daily visitors chart** — 30/90/365-day trend of unique visitors and page views; automatically switches to an hour-by-hour breakdown when viewing "Today," since a single day has nothing to trend
- **Device, browser, and OS breakdowns** — lightweight regex-based detection, no external library
- **Country breakdown** — via a local GeoIP database file, no API key and no outbound network request per visit
- **Referrers** — which sites are sending you traffic
- **Entry / exit pages and bounce rate** — approximated from visit clustering (30-minute gap = new session), with no cookies or session tracking required
- **New vs. returning visitors**
- **Peak hours and day-of-week patterns**
- **Sortable, searchable, paginated Top Pages table**
- **Live activity feed**, auto-refreshing
- **CSV export** of raw visit data for the selected date range
- **One-click PDF export** — a full report with charts, tables, and stats, generated entirely in the browser
- **Optional Dashboard module** — a compact "Daily Visitors" widget for the Dashboard plugin (see below)
- Ships in English and Spanish; every string in the admin UI (including the PDF report) is translatable

<img width="950" alt="image" src="https://github.com/user-attachments/assets/a9a2d551-16fc-4204-8765-815454b4b540" />

## Privacy design

This plugin is built around one rule: **the visitor's IP address is never stored.**

- The IP is looked up against the local GeoIP database (to get a country) and then immediately discarded — only a SHA-256 hash of the IP is written to the database, and the country code that came from it.
- No cookies are set for tracking. Entry/exit pages and bounce rate are approximated by clustering hits from the same hashed IP within a 30-minute window — no session identifiers involved.
- No third-party APIs are called for geolocation. The country lookup is a local, offline binary file read.
- Site owners can exclude their own visits (while logged into the GS admin) via a setting.

## Requirements

- GetSimpleCMS-CE 3.3.22 or later
- PHP with the `SQLite3` and `mbstring` extensions (both standard on most hosts)
- No Composer, no build step — every JS dependency (Chart.js, jsPDF) is vendored and loaded locally

## Installation

1. Copy `TrafficLens.php` and the `TrafficLens/` folder into your `plugins/` directory, as siblings:
   ```
   plugins/
   ├── TrafficLens.php
   └── TrafficLens/
       ├── dashboard.php
       ├── inc/
       ├── lang/
       └── vendor/
   ```
2. Activate **TrafficLens** from the GetSimple admin plugin list.
3. **(Recommended) Set up country tracking** — see [GeoIP setup](#geoip-setup-optional-but-recommended) below. The plugin works fully without this step; country data will simply stay empty until the database file is in place.
4. Open the **TrafficLens** page from the Pages sidebar to view the dashboard.

## GeoIP setup (optional but recommended)

Country data comes from DB-IP's free **"IP to Country Lite"** database — no account or API key required.

1. Download it in `.mmdb` format from: https://db-ip.com/db/download/ip-to-country-lite
2. Place the file at:
   ```
   plugins/TrafficLens/vendor/dbip-country-lite.mmdb
   ```
3. That's it — no configuration needed. The plugin reads the file directly with its own pure-PHP reader (no PECL extension required).

This database is released under **CC BY 4.0**, which requires attribution — already included in the dashboard's footer and in every PDF export, so no action needed on your part.

The database is updated monthly by DB-IP. Re-downloading and replacing the file periodically will keep country data current, though this isn't required for the plugin to keep working.

- If you have limited space with your web host, this file may be safely removed (GeoIP will cease  to function).

## Settings

Available from the **Settings** panel on the dashboard:

| Setting | Description | Default |
|---|---|---|
| Exclude my own visits while logged in | Skips tracking when you're browsing the front-end while logged into GS admin | On |
| Keep data for _N_ days | Rows older than this are pruned automatically | 400 days |
| Auto-refresh live feed every _N_ seconds | How often the live activity feed and "online now" count refresh in the background | 60 seconds |

## Folder structure

```
plugins/
├── TrafficLens.php              ← plugin entry point (registration, tracking hook)
└── TrafficLens/
    ├── dashboard.php             ← admin dashboard page
    ├── inc/
    │   ├── ajax.php               ← AJAX action dispatcher
    │   ├── db.php                 ← DB init, settings storage, GeoIP lookup wrapper
    │   ├── mmdb-reader.php        ← pure-PHP .mmdb binary format reader
    │   ├── queries.php            ← all dashboard data/aggregation logic
    │   ├── ua-parser.php          ← browser/OS/device detection
    │   ├── css/dashboard.css
    │   └── js/
    │       ├── dashboard.js
    │       ├── chart.umd.min.js          (Chart.js, MIT)
    │       ├── jspdf.umd.min.js          (jsPDF, MIT)
    │       └── jspdf.plugin.autotable.min.js  (jsPDF-AutoTable, MIT)
    ├── lang/
    │   ├── en_US.php
    │   └── es_ES.php
    └── vendor/
        ├── dbip-country-lite.mmdb   ← you provide this, see GeoIP setup
        └── readme.txt
```

## Dashboard module

TrafficLens ships with an optional module for the separate **Dashboard** plugin, showing a compact "Daily Visitors" widget (this week's views, live-now count, and a 30-day chart) on the GS admin home screen. It's distributed separately — see `README.md` inside the module's own folder for installation instructions.

<img width="950"  alt="image" src="https://github.com/user-attachments/assets/d732f3f1-e0ca-4747-953b-1d4edc734e99" />


## Known limitations

- **Flag emoji don't render in the PDF export.** jsPDF's built-in fonts have no emoji glyphs; the PDF's Top Countries table shows plain country codes instead (the on-screen dashboard still shows flags fine, since that's rendered by the browser).
- **Entry/exit pages and bounce rate are approximations**, not exact session tracking — by design, since exact session tracking would require cookies.
- Retention pruning runs as a `DELETE` on every tracked visit. Fine at typical site traffic levels; if you're running TrafficLens on a very high-traffic site, this is the first thing worth revisiting.

## Credits

- Country data: [DB-IP](https://db-ip.com) IP to Country Lite, licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
- [Chart.js](https://www.chartjs.org/) — MIT License
- [jsPDF](https://github.com/parallax/jsPDF) / [jsPDF-AutoTable](https://github.com/simonbengtsson/jsPDF-AutoTable) — MIT License

## Author

**risingisland** — [ko-fi.com/ericmontgomery](https://ko-fi.com/ericmontgomery)
