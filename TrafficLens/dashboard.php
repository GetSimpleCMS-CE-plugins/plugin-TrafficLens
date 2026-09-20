<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$tl_settings = TrafficLens_settings();
$tl_ajax_base = '?id=' . TrafficLens_SLUG . '&tl_action=';

// One flat list of every i18n key dashboard.js (including the PDF export)
$tl_js_keys = [
	'lang_Daily_Visitors', 'lang_Visitors_By_Hour', 'lang_Unique_Visitors', 'lang_Series_Page_Views',
	'lang_Loading', 'lang_No_Data_Range', 'lang_No_Pages_Found', 'lang_No_Recent_Activity', 'lang_Generating',
	'lang_Prev', 'lang_Next', 'lang_Pagination_Template', 'lang_Online_Now_Template', 'lang_Bounce_Rate_Template',
	'lang_Newret_New_Template', 'lang_Newret_Returning_Template', 'lang_Newret_Unavailable',
	'lang_Saving', 'lang_Saved', 'lang_Save_Failed',
	'lang_Range_Today', 'lang_Range_7d', 'lang_Range_30d', 'lang_Range_90d', 'lang_Range_365d', 'lang_Range_All',
	'lang_Pdf_Title', 'lang_Pdf_Generated', 'lang_Pdf_Stat_Line_Template',
	'lang_Daily_Visitors', 'lang_Devices', 'lang_Browsers', 'lang_Operating_Systems',
	'lang_Peak_Hours', 'lang_Day_Of_Week', 'lang_Pdf_Newret_Title', 'lang_Pdf_Newret_Unavailable',
	'lang_Top_Countries', 'lang_Pdf_Col_Country', 'lang_Top_Referrers',
	'lang_Pdf_Col_Referrer', 'lang_Pdf_Col_Visits', 'lang_Entry_Pages', 'lang_Pdf_Col_Entry_Page',
	'lang_Exit_Pages', 'lang_Pdf_Col_Exit_Page', 'lang_Pdf_Col_Sessions', 'lang_Pdf_Bounce_Note_Template',
	'lang_Top_Pages', 'lang_Col_URL', 'lang_Col_Views', 'lang_Col_Uniques', 'lang_Pdf_Footer', 'lang_Pdf_Export_Failed',
	'lang_Bot_Summary_Template', 'lang_Bot_Last_Seen_Template', 'lang_No_Bots', 'lang_Bot_Skip_Note',
	'lang_Bot_Unidentified', 'lang_Bot_Unknown_Client', 'lang_Bot_Other',
	'lang_Bot_Activity', 'lang_Pdf_Col_Bot', 'lang_Pdf_Col_Hits', 'lang_Pdf_Col_Last_Seen',
];
$tl_i18n_js = [];
foreach (array_unique($tl_js_keys) as $k) {
	$tl_i18n_js[$k] = i18n_r('TrafficLens/' . $k);
}
?>
<div class="tl-wrap">

	<div class="tl-topbar">
		<h2 class="tl-title"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="22px" height="22px" viewBox="0 0 14 14"><path d="M0 0h14v14H0z" fill="none"/><g fill="none"><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M.75.75v10.5a2 2 0 0 0 2 2h10.5"/><path fill="#d7e0ff" d="M4.5 7.25c1.12 0 1.75-.63 1.75-1.75S5.62 3.75 4.5 3.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.25c1.12 0 1.75-.63 1.75-1.75S5.62 3.75 4.5 3.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path fill="#d7e0ff" d="M11.5 4.25c1.12 0 1.75-.63 1.75-1.75S12.62.75 11.5.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M11.5 4.25c1.12 0 1.75-.63 1.75-1.75S12.62.75 11.5.75s-1.75.63-1.75 1.75s.63 1.75 1.75 1.75"/><path fill="#d7e0ff" d="M9 10.75c1.12 0 1.75-.63 1.75-1.75S10.12 7.25 9 7.25S7.25 7.88 7.25 9s.63 1.75 1.75 1.75"/><path stroke="#4147d5" stroke-linecap="round" stroke-linejoin="round" d="M9 10.75c1.12 0 1.75-.63 1.75-1.75S10.12 7.25 9 7.25S7.25 7.88 7.25 9s.63 1.75 1.75 1.75M3.313 6.906L.75 9.766m10.437-5.538l-1 3.366M6.113 6.292L7.56 7.883"/></g></svg> <?= i18n_r('TrafficLens/lang_Menu_Title') ?> </h2>
		<div class="tl-controls">
			<select id="tl-range">
				<option value="today"><?= i18n_r('TrafficLens/lang_Range_Today') ?></option>
				<option value="7d"><?= i18n_r('TrafficLens/lang_Range_7d') ?></option>
				<option value="30d" selected><?= i18n_r('TrafficLens/lang_Range_30d') ?></option>
				<option value="90d"><?= i18n_r('TrafficLens/lang_Range_90d') ?></option>
				<option value="365d"><?= i18n_r('TrafficLens/lang_Range_365d') ?></option>
				<option value="all"><?= i18n_r('TrafficLens/lang_Range_All') ?></option>
			</select>
			<button type="button" id="tl-refresh" class="tl-btn tl-btn-orange"><?= i18n_r('TrafficLens/lang_Refresh') ?></button>
			<button type="button" id="tl-export" class="tl-btn"><?= i18n_r('TrafficLens/lang_Export_CSV') ?></button>
			<button type="button" id="tl-export-pdf" class="tl-btn  tl-btn-red"><?= i18n_r('TrafficLens/lang_Export_PDF') ?></button>
			<button type="button" id="tl-settings-toggle" class="tl-btn tl-btn-ghost"><?= i18n_r('TrafficLens/lang_Settings') ?></button>
		</div>
	</div>

	<div id="tl-settings-panel" class="tl-card tl-settings-panel" hidden>
		<h3><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1.2em" height="1.2em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" fill-rule="evenodd" d="M9.024 2.783A1 1 0 0 1 10 2h4a1 1 0 0 1 .976.783l.44 1.981q.6.285 1.14.66l1.938-.61a1 1 0 0 1 1.166.454l2 3.464a1 1 0 0 1-.19 1.237l-1.497 1.373a8 8 0 0 1 0 1.316l1.497 1.373a1 1 0 0 1 .19 1.237l-2 3.464a1 1 0 0 1-1.166.454l-1.937-.61q-.54.375-1.14.66l-.44 1.98A1 1 0 0 1 14 22h-4a1 1 0 0 1-.976-.783l-.44-1.981q-.6-.285-1.14-.66l-1.938.61a1 1 0 0 1-1.166-.454l-2-3.464a1 1 0 0 1 .19-1.237l1.497-1.373a8 8 0 0 1 0-1.316L2.53 9.97a1 1 0 0 1-.19-1.237l2-3.464a1 1 0 0 1 1.166-.454l1.937.61q.54-.375 1.14-.66l.44-1.98zM12 15a3 3 0 1 0 0-6a3 3 0 0 0 0 6" clip-rule="evenodd"/></svg> <?= i18n_r('TrafficLens/lang_Settings') ?></h3>
		<label class="tl-field-row">
			<input type="checkbox" id="tl-set-exclude-admin">
			<?= i18n_r('TrafficLens/lang_Exclude_Admin') ?>
		</label>
		<label class="tl-field-row">
			<?= i18n_r('TrafficLens/lang_Bots_Label') ?>
			<select id="tl-set-bot-mode">
				<option value="include"><?= i18n_r('TrafficLens/lang_Bots_Include') ?></option>
				<option value="separate"><?= i18n_r('TrafficLens/lang_Bots_Separate') ?></option>
				<option value="skip"><?= i18n_r('TrafficLens/lang_Bots_Skip') ?></option>
			</select>
		</label>
		<div class="tl-field-row tl-muted"><?= i18n_r('TrafficLens/lang_Bots_Help') ?></div>
		<label class="tl-field-row">
			<?= i18n_r('TrafficLens/lang_Keep_Data_For') ?>
			<input type="number" id="tl-set-retention" min="1" style="width:5rem;"> <?= i18n_r('TrafficLens/lang_Days') ?>
		</label>
		<label class="tl-field-row">
			<?= i18n_r('TrafficLens/lang_Auto_Refresh_Every') ?>
			<input type="number" id="tl-set-refresh" min="15" style="width:5rem;"> <?= i18n_r('TrafficLens/lang_Seconds') ?>
		</label>
		<div class="tl-field-row">
			<button type="button" id="tl-settings-save" class="tl-btn tl-btn-green"><?= i18n_r('TrafficLens/lang_Save_Settings') ?></button>
			<span id="tl-settings-status" class="tl-muted"></span>
		</div>
	</div>

	<div class="tl-stats-grid">
		<div class="tl-stat-card">
			<div class="tl-stat-label"><?= i18n_r('TrafficLens/lang_Total_Pageviews') ?></div>
			<div class="tl-stat-value" id="tl-stat-total">-</div>
		</div>
		<div class="tl-stat-card">
			<div class="tl-stat-label"><?= i18n_r('TrafficLens/lang_Unique_Visitors') ?></div>
			<div class="tl-stat-value" id="tl-stat-unique">-</div>
		</div>
		<div class="tl-stat-card">
			<div class="tl-stat-label"><?= i18n_r('TrafficLens/lang_Today') ?></div>
			<div class="tl-stat-value" id="tl-stat-today">-</div>
		</div>
		<div class="tl-stat-card tl-stat-live">
			<div class="tl-stat-label"><?= i18n_r('TrafficLens/lang_Live_Now') ?></div>
			<div class="tl-stat-value" id="tl-stat-live">-</div>
			
			<!-- Live breakdown tooltip -->
			<div class="tl-live-popup" id="tl-live-popup">
				<table class="tl-table tl-table-compact">
					<thead>
						<tr>
							<th><?= i18n_r('TrafficLens/lang_Pdf_Col_Country') ?></th>
							<th><?= i18n_r('TrafficLens/lang_Col_Views') ?></th>
						</tr>
					</thead>
					<tbody id="tl-live-countries-tbody">
						<tr><td colspan="2" style="text-align:center;">-</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="tl-card tl-card-full">
		<h3 id="tl-chart-daily-title"><?= i18n_r('TrafficLens/lang_Daily_Visitors') ?></h3>
		<div class="tl-chart-box tl-chart-box-wide"><canvas id="tl-chart-daily"></canvas></div>
	</div>

	<div class="tl-grid tl-grid-charts">
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Devices') ?></h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-device"></canvas></div>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Browsers') ?></h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-browser"></canvas></div>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Operating_Systems') ?></h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-os"></canvas></div>
		</div>
	</div>

	<div class="tl-grid tl-grid-charts">
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Peak_Hours') ?></h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-hourly"></canvas></div>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Day_Of_Week') ?></h3>
			<div class="tl-chart-box tl-chart-box-sm"><canvas id="tl-chart-dow"></canvas></div>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_New_Vs_Returning') ?></h3>
			<div id="tl-newret-body" class="tl-newret-body">
				<div class="tl-muted"><?= i18n_r('TrafficLens/lang_Loading') ?></div>
			</div>
		</div>
	</div>

	<div class="tl-grid tl-grid-lists">
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Top_Countries') ?></h3>
			<ul class="tl-list tl-list-accent-teal" id="tl-list-countries"></ul>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Top_Referrers') ?></h3>
			<ul class="tl-list tl-list-accent-purple" id="tl-list-referrers"></ul>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Entry_Pages') ?></h3>
			<ul class="tl-list tl-list-accent-amber" id="tl-list-entry"></ul>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Exit_Pages') ?> <span class="tl-muted" id="tl-bounce-rate"></span></h3>
			<ul class="tl-list tl-list-accent-red" id="tl-list-exit"></ul>
		</div>
	</div>

	<div class="tl-card tl-card-full">
		<div class="tl-pages-header">
			<h3><?= i18n_r('TrafficLens/lang_Top_Pages') ?></h3>
			<input type="search" id="tl-pages-search" placeholder="<?= htmlspecialchars(i18n_r('TrafficLens/lang_Search_URL')) ?>" class="tl-search">
		</div>
		<table class="tl-table">
			<thead>
				<tr>
					<th data-sort="url"><?= i18n_r('TrafficLens/lang_Col_URL') ?></th>
					<th data-sort="views"><?= i18n_r('TrafficLens/lang_Col_Views') ?></th>
					<th data-sort="uniques"><?= i18n_r('TrafficLens/lang_Col_Uniques') ?></th>
				</tr>
			</thead>
			<tbody id="tl-pages-body">
				<tr><td colspan="3" class="tl-muted"><?= i18n_r('TrafficLens/lang_Loading') ?></td></tr>
			</tbody>
		</table>
		<div class="tl-pagination" id="tl-pages-pagination"></div>
	</div>

	<div class="tl-grid tl-grid-pair">
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Live_Activity') ?> <span class="tl-muted" id="tl-live-now-label"></span></h3>
			<ul class="tl-list tl-live-feed" id="tl-live-feed">
				<li class="tl-muted"><?= i18n_r('TrafficLens/lang_Loading') ?></li>
			</ul>
		</div>
		<div class="tl-card">
			<h3><?= i18n_r('TrafficLens/lang_Bot_Activity') ?> <span class="tl-muted" id="tl-bots-summary"></span></h3>
			<div class="tl-muted tl-bots-note" id="tl-bots-note" hidden></div>
			<ul class="tl-list tl-list-accent-slate" id="tl-list-bots">
				<li class="tl-muted"><?= i18n_r('TrafficLens/lang_Loading') ?></li>
			</ul>
		</div>
	</div>

	<div class="tl-footer">
		<?= i18n_r('TrafficLens/lang_Footer_Country_Data') ?> &copy; <a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>,
		<?= i18n_r('TrafficLens/lang_Footer_Licensed_Under') ?>
		<a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.
	</div>
</div>

<link rel="stylesheet" href="<?= TrafficLens_PLUGIN_URL ?>inc/css/dashboard.css">
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/chart.umd.min.js"></script>
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/jspdf.umd.min.js"></script>
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/jspdf.plugin.autotable.min.js"></script>
<script>
	var TL_AJAX_BASE		= <?= json_encode($tl_ajax_base) ?>;
	var TL_REFRESH_SECONDS	= <?= (int)$tl_settings['refresh_seconds'] ?>;
	var TL_INITIAL_SETTINGS	= <?= json_encode($tl_settings) ?>;
	var TL_I18N				= <?= json_encode($tl_i18n_js, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= TrafficLens_PLUGIN_URL ?>inc/js/dashboard.js"></script>
