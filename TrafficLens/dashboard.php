<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$tl_settings = TrafficLens_settings();
$tl_ajax_base = '?id=' . TrafficLens_SLUG . '&tl_action=';
?>
<div class="tl-wrap">

	<div class="tl-topbar">
		<h2 class="tl-title">TrafficLens</h2>
		<div class="tl-controls">
			<select id="tl-range">
				<option value="today">Today</option>
				<option value="7d">Last 7 days</option>
				<option value="30d" selected>Last 30 days</option>
				<option value="90d">Last 90 days</option>
				<option value="all">All time</option>
			</select>
			<button type="button" id="tl-refresh" class="tl-btn">Refresh</button>
			<button type="button" id="tl-export" class="tl-btn">Export CSV</button>
			<button type="button" id="tl-settings-toggle" class="tl-btn tl-btn-ghost">Settings</button>
		</div>
	</div>

	<div id="tl-settings-panel" class="tl-card tl-settings-panel" hidden>
		<h3>Settings</h3>
		<label class="tl-field-row">
			<input type="checkbox" id="tl-set-exclude-admin">
			Exclude my own visits while logged in
		</label>
		<label class="tl-field-row">
			Keep data for
			<input type="number" id="tl-set-retention" min="1" style="width:5rem;"> days
		</label>
		<label class="tl-field-row">
			Auto-refresh live feed every
			<input type="number" id="tl-set-refresh" min="15" style="width:5rem;"> seconds
		</label>
		<div class="tl-field-row">
			<button type="button" id="tl-settings-save" class="tl-btn">Save settings</button>
			<span id="tl-settings-status" class="tl-muted"></span>
		</div>
	</div>

	<div class="tl-stats-grid">
		<div class="tl-stat-card">
			<div class="tl-stat-label">Total page views</div>
			<div class="tl-stat-value" id="tl-stat-total">-</div>
		</div>
		<div class="tl-stat-card">
			<div class="tl-stat-label">Unique visitors</div>
			<div class="tl-stat-value" id="tl-stat-unique">-</div>
		</div>
		<div class="tl-stat-card">
			<div class="tl-stat-label">Today</div>
			<div class="tl-stat-value" id="tl-stat-today">-</div>
		</div>
		<div class="tl-stat-card tl-stat-live">
			<div class="tl-stat-label">Live now</div>
			<div class="tl-stat-value" id="tl-stat-live">-</div>
		</div>
	</div>

	<div class="tl-card">
		<h3>Daily visitors</h3>
		<div class="tl-chart-box tl-chart-box-wide"><canvas id="tl-chart-daily"></canvas></div>
	</div>

	<div class="tl-grid tl-grid-charts">
		<div class="tl-card">
			<h3>Devices</h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-device"></canvas></div>
		</div>
		<div class="tl-card">
			<h3>Browsers</h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-browser"></canvas></div>
		</div>
		<div class="tl-card">
			<h3>Operating systems</h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-os"></canvas></div>
		</div>
	</div>

	<div class="tl-grid tl-grid-charts">
		<div class="tl-card">
			<h3>Peak hours</h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-hourly"></canvas></div>
		</div>
		<div class="tl-card">
			<h3>Day of week</h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-dow"></canvas></div>
		</div>
		<div class="tl-card">
			<h3>New vs. returning</h3>
			<div id="tl-newret-body" class="tl-newret-body">
				<div class="tl-muted">Loading...</div>
			</div>
		</div>
	</div>

	<div class="tl-grid tl-grid-lists">
		<div class="tl-card">
			<h3>Top countries</h3>
			<ul class="tl-list tl-list-accent-teal" id="tl-list-countries"></ul>
		</div>
		<div class="tl-card">
			<h3>Top referrers</h3>
			<ul class="tl-list tl-list-accent-purple" id="tl-list-referrers"></ul>
		</div>
		<div class="tl-card">
			<h3>Entry pages</h3>
			<ul class="tl-list tl-list-accent-amber" id="tl-list-entry"></ul>
		</div>
		<div class="tl-card">
			<h3>Exit pages <span class="tl-muted" id="tl-bounce-rate"></span></h3>
			<ul class="tl-list tl-list-accent-red" id="tl-list-exit"></ul>
		</div>
	</div>

	<div class="tl-card">
		<div class="tl-pages-header">
			<h3>Top pages</h3>
			<input type="search" id="tl-pages-search" placeholder="Search URL..." class="tl-search">
		</div>
		<table class="tl-table">
			<thead>
				<tr>
					<th data-sort="url">URL</th>
					<th data-sort="views">Views</th>
					<th data-sort="uniques">Uniques</th>
				</tr>
			</thead>
			<tbody id="tl-pages-body">
				<tr><td colspan="3" class="tl-muted">Loading...</td></tr>
			</tbody>
		</table>
		<div class="tl-pagination" id="tl-pages-pagination"></div>
	</div>

	<div class="tl-card">
		<h3>Live activity <span class="tl-muted" id="tl-live-now-label"></span></h3>
		<ul class="tl-list tl-live-feed" id="tl-live-feed">
			<li class="tl-muted">Loading...</li>
		</ul>
	</div>

	<div class="tl-footer">
		Country data &copy; <a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>, licensed under
		<a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.
	</div>
</div>

<link rel="stylesheet" href="<?= TrafficLens_PLUGIN_URL ?>inc/css/dashboard.css">
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/chart.umd.min.js"></script>
<script>
	var TL_AJAX_BASE        = <?= json_encode($tl_ajax_base) ?>;
	var TL_REFRESH_SECONDS  = <?= (int)$tl_settings['refresh_seconds'] ?>;
	var TL_INITIAL_SETTINGS = <?= json_encode($tl_settings) ?>;
</script>
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/dashboard.js"></script>
