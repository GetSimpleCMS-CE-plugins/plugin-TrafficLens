<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$i18n = [

	// ── Sidebar ──────────────────────────────────────────────────────────────
	'lang_Menu_Title'    	=> 'TrafficLens',

	// ── Top bar ──────────────────────────────────────────────────────────────
	'lang_Range_Today'		=> 'Today',
	'lang_Range_7d'			=> 'Last 7 days',
	'lang_Range_30d'		=> 'Last 30 days',
	'lang_Range_90d'		=> 'Last 90 days',
	'lang_Range_365d'		=> 'Past year',
	'lang_Range_All'		=> 'All time',
	'lang_Refresh'			=> 'Refresh',
	'lang_Export_CSV'		=> 'Export CSV',
	'lang_Export_PDF'		=> 'Export PDF',
	'lang_Settings'			=> 'Settings',

	// ── Settings panel ───────────────────────────────────────────────────────
	'lang_Exclude_Admin'	=> 'Exclude my own visits while logged in',
	'lang_Keep_Data_For'	=> 'Keep data for',
	'lang_Days'				=> 'days',
	'lang_Auto_Refresh_Every' => 'Auto-refresh live feed every',
	'lang_Seconds'			=> 'seconds',
	'lang_Save_Settings'	=> 'Save settings',
	'lang_Saving'			=> 'Saving...',
	'lang_Saved'			=> 'Saved.',
	'lang_Save_Failed'		=> 'Save failed.',

	// ── Stat cards ───────────────────────────────────────────────────────────
	'lang_Total_Pageviews'	=> 'Total page views',
	'lang_Unique_Visitors'	=> 'Unique visitors',
	'lang_Today'			=> 'Today',
	'lang_Live_Now'			=> 'Live now',

	// ── Chart / section titles ──────────────────────────────────────────────
	'lang_Daily_Visitors'	=> 'Daily visitors',
	'lang_Visitors_By_Hour'	=> 'Visitors by hour (today)',
	'lang_Devices'			=> 'Devices',
	'lang_Browsers'			=> 'Browsers',
	'lang_Operating_Systems' => 'Operating systems',
	'lang_Peak_Hours'		=> 'Peak hours',
	'lang_Day_Of_Week'		=> 'Day of week',
	'lang_New_Vs_Returning'	=> 'New vs. returning',
	'lang_Top_Countries'	=> 'Top countries',
	'lang_Top_Referrers'	=> 'Top referrers',
	'lang_Entry_Pages'		=> 'Entry pages',
	'lang_Exit_Pages'		=> 'Exit pages',
	'lang_Top_Pages'		=> 'Top pages',
	'lang_Live_Activity'	=> 'Live activity',

	// ── Chart series labels ──────────────────────────────────────────────────
	'lang_Series_Page_Views' => 'Page views',

	// ── Table / list UI ──────────────────────────────────────────────────────
	'lang_Search_URL'		=> 'Search URL...',
	'lang_Col_URL'			=> 'URL',
	'lang_Col_Views'		=> 'Views',
	'lang_Col_Uniques'		=> 'Uniques',
	'lang_Loading'			=> 'Loading...',
	'lang_Generating'		=> 'Generating...',
	'lang_No_Data_Range'	=> 'No data for this range.',
	'lang_No_Pages_Found'	=> 'No pages found.',
	'lang_No_Recent_Activity' => 'No recent activity.',
	'lang_Prev'				=> '« Prev',
	'lang_Next'				=> 'Next »',

	// Templates use {placeholder} tokens, substituted in JS - keep the
	// {tokens} intact when translating, but feel free to reorder them.
	'lang_Pagination_Template'	=> 'Page {page} of {totalPages} ({total} pages tracked)',
	'lang_Online_Now_Template'	=> '({count} online now)',
	'lang_Bounce_Rate_Template'	=> '({rate}% bounce rate)',
	'lang_Newret_New_Template'	=> 'New: {count} ({pct}%)',
	'lang_Newret_Returning_Template' => 'Returning: {count} ({pct}%)',
	'lang_Newret_Unavailable'	=> 'Select a shorter time range (not "All time") to see new vs. returning visitors.',

	// ── Page footer ──────────────────────────────────────────────────────────
	'lang_Footer_Country_Data'	=> 'Country data',
	'lang_Footer_Licensed_Under' => 'licensed under',

	// ── PDF export ───────────────────────────────────────────────────────────
	'lang_Pdf_Title'		=> 'TrafficLens Report',
	'lang_Pdf_Generated'	=> 'Generated',
	'lang_Pdf_Stat_Line_Template'   => 'Total page views: {total}     Unique visitors: {unique}     Today: {today}     Live now: {live}',
	'lang_Pdf_Newret_Title'	=> 'New vs. Returning Visitors',
	'lang_Pdf_Newret_Unavailable'   => 'Not available for the "All time" range.',
	'lang_Pdf_Col_Country'	=> 'Country',
	'lang_Pdf_Col_Referrer'	=> 'Referrer',
	'lang_Pdf_Col_Visits'	=> 'Visits',
	'lang_Pdf_Col_Entry_Page'	=> 'Entry Page',
	'lang_Pdf_Col_Exit_Page'	=> 'Exit Page',
	'lang_Pdf_Col_Sessions'	=> 'Sessions',
	'lang_Pdf_Bounce_Note_Template' => ' ({rate}% bounce rate, {sessions} sessions)',
	'lang_Pdf_Footer'		=> 'Country data (c) DB-IP, licensed under CC BY 4.0 - db-ip.com',
	'lang_Pdf_Export_Failed'	=> 'PDF export failed - see the browser console for details.',

];
