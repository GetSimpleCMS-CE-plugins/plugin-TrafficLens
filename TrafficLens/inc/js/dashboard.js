(function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────
	var state = {
		range: '30d',
		pages: { page: 1, perPage: 10, sort: 'views', dir: 'desc', search: '' },
		charts: {},
		liveTimer: null,
		lastData: null,
	};

	var RANGE_LABELS = {
		today: TL_I18N.lang_Range_Today, '7d': TL_I18N.lang_Range_7d, '30d': TL_I18N.lang_Range_30d,
		'90d': TL_I18N.lang_Range_90d, '365d': TL_I18N.lang_Range_365d, all: TL_I18N.lang_Range_All,
	};

	// ── Fetch helpers ──────────────────────────────────────────────────────
	function tlGet(action, params) {
		var qs = '';
		if (params) {
			var parts = [];
			for (var k in params) {
				if (params.hasOwnProperty(k)) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
			}
			qs = '&' + parts.join('&');
		}
		return fetch(TL_AJAX_BASE + action + qs, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function tlPost(action, formBody) {
		return fetch(TL_AJAX_BASE + action, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: formBody,
		}).then(function (r) { return r.json(); });
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = (s === null || s === undefined) ? '' : String(s);
		return d.innerHTML;
	}

	// Simple {token} substitution for translated template strings, e.g.
	// tlFormat(TL_I18N.lang_Pagination_Template, { page: 1, total: 40 })
	function tlFormat(template, vars) {
		return String(template || '').replace(/\{(\w+)\}/g, function (m, key) {
			return (vars && Object.prototype.hasOwnProperty.call(vars, key)) ? vars[key] : m;
		});
	}

	// ── Chart helpers ──────────────────────────────────────────────────────
	var CHART_COLORS = ['#2764e7', '#e67e22', '#16a085', '#9b59b6', '#e74c3c', '#0094f0', '#f5c518', '#34495e'];

	function upsertChart(id, config) {
		var canvas = document.getElementById(id);
		if (!canvas) return;
		if (state.charts[id]) {
			state.charts[id].data = config.data;
			state.charts[id].options = config.options || state.charts[id].options;
			state.charts[id].update();
		} else {
			state.charts[id] = new Chart(canvas.getContext('2d'), config);
		}
	}

	function renderDailyChart(daily) {
		var titleEl = document.getElementById('tl-chart-daily-title');
		if (titleEl) titleEl.textContent = (daily.granularity === 'hourly') ? TL_I18N.lang_Visitors_By_Hour : TL_I18N.lang_Daily_Visitors;

		var isHourly = daily.granularity === 'hourly';
		upsertChart('tl-chart-daily', {
			type: 'line',
			data: {
				labels: daily.labels,
				datasets: [
					{
						label: TL_I18N.lang_Unique_Visitors,
						data: daily.uniques,
						borderColor: CHART_COLORS[0],
						backgroundColor: 'rgba(39,100,231,.08)',
						fill: true,
						tension: .25,
						pointRadius: 0,
					},
					{
						label: TL_I18N.lang_Series_Page_Views,
						data: daily.views,
						borderColor: CHART_COLORS[1],
						backgroundColor: 'transparent',
						tension: .25,
						pointRadius: 0,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				scales: {
					y: { beginAtZero: true, ticks: { precision: 0 } },
					x: { ticks: { autoSkip: !isHourly, maxRotation: 0 } },
				},
				plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
			},
		});
	}

	function renderDonut(id, breakdown, colorOffset) {
		var labels = Object.keys(breakdown);
		var data = labels.map(function (k) { return breakdown[k]; });
		var offset = colorOffset || 0;
		var colors = labels.map(function (_, i) { return CHART_COLORS[(i + offset) % CHART_COLORS.length]; });
		upsertChart(id, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{ data: data, backgroundColor: colors, borderWidth: 1 }],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
			},
		});
	}

	function renderHourlyChart(hourly) {
		var labels = [];
		for (var h = 0; h < 24; h++) labels.push(h + ':00');
		upsertChart('tl-chart-hourly', {
			type: 'bar',
			data: { labels: labels, datasets: [{ data: hourly, backgroundColor: CHART_COLORS[1] }] },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});
	}

	function renderDowChart(dow) {
		var labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		upsertChart('tl-chart-dow', {
			type: 'bar',
			data: { labels: labels, datasets: [{ data: dow, backgroundColor: CHART_COLORS[3] }] },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});
	}

	// ── List renderers ─────────────────────────────────────────────────────
	function renderKeyCountList(el, items, keyField, formatKey) {
		if (!items || !items.length) {
			el.innerHTML = '<li class="tl-muted">' + esc(TL_I18N.lang_No_Data_Range) + '</li>';
			return;
		}
		var html = items.map(function (it) {
			var label = formatKey ? formatKey(it) : esc(it[keyField]);
			return '<li><span class="tl-list-key" title="' + esc(it[keyField]) + '">' + label + '</span><span class="tl-list-count">' + it.count + '</span></li>';
		}).join('');
		el.innerHTML = html;
	}

	// Bot names are stored in English; the three generic ones are translated here.
	function botLabel(name) {
		if (!name) return TL_I18N.lang_Bot_Unidentified;
		if (name === 'Unknown client') return TL_I18N.lang_Bot_Unknown_Client;
		if (name === 'Other bot') return TL_I18N.lang_Bot_Other;
		return name;
	}

	function renderBots(b) {
		if (!b) return;
		var list = document.getElementById('tl-list-bots');
		var summary = document.getElementById('tl-bots-summary');
		var note = document.getElementById('tl-bots-note');
		if (!list || !summary || !note) return;

		summary.textContent = b.total > 0 ? tlFormat(TL_I18N.lang_Bot_Summary_Template, { hits: b.total, pct: b.share }) : '';

		// In "don't record" mode the card only shows what was recorded earlier - say so.
		note.textContent = (b.mode === 'skip') ? TL_I18N.lang_Bot_Skip_Note : '';
		note.hidden = (b.mode !== 'skip');

		if (!b.list || !b.list.length) {
			list.innerHTML = '<li class="tl-muted">' + esc(TL_I18N.lang_No_Bots) + '</li>';
			return;
		}
		list.innerHTML = b.list.map(function (r) {
			var label = botLabel(r.name);
			var tip = label + ' - ' + tlFormat(TL_I18N.lang_Bot_Last_Seen_Template, { time: r.last_seen });
			return '<li><span class="tl-list-key" title="' + esc(tip) + '">' + esc(label) + '</span>' +
				'<span class="tl-list-count">' + r.hits + '</span></li>';
		}).join('');
	}

	function renderNewVsReturning(nr) {
		var el = document.getElementById('tl-newret-body');
		if (!nr.available) {
			el.innerHTML = '<div class="tl-muted">' + esc(TL_I18N.lang_Newret_Unavailable) + '</div>';
			return;
		}
		var total = nr.new + nr.returning;
		var newPct = total > 0 ? Math.round((nr.new / total) * 100) : 0;
		var retPct = 100 - newPct;
		var newText = tlFormat(TL_I18N.lang_Newret_New_Template, { count: nr.new, pct: newPct });
		var retText = tlFormat(TL_I18N.lang_Newret_Returning_Template, { count: nr.returning, pct: retPct });
		el.innerHTML =
			'<div class="tl-newret-bar"><span class="new" style="width:' + newPct + '%"></span><span class="returning" style="width:' + retPct + '%"></span></div>' +
			'<div class="tl-newret-legend">' +
			'<span><span class="dot new"></span>' + esc(newText) + '</span>' +
			'<span><span class="dot returning"></span>' + esc(retText) + '</span>' +
			'</div>';
	}

	// ── Main dashboard load ────────────────────────────────────────────────
	function loadDashboard() {
		tlGet('get_dashboard_data', { range: state.range }).then(function (res) {
			if (!res.ok) return;
			state.lastData = res;

			document.getElementById('tl-stat-total').textContent  = res.stats.total;
			document.getElementById('tl-stat-unique').textContent = res.stats.unique;
			document.getElementById('tl-stat-today').textContent  = res.stats.today;
			document.getElementById('tl-stat-live').textContent   = res.stats.live;
			
			if (res.stats.live_countries) {
				renderLiveCountries(res.stats.live_countries);
			}

			renderDailyChart(res.daily_trend);
			renderDonut('tl-chart-device', res.device_breakdown, 0);
			renderDonut('tl-chart-browser', res.browser_breakdown, 2);
			renderDonut('tl-chart-os', res.os_breakdown, 4);
			renderHourlyChart(res.hourly);
			renderDowChart(res.day_of_week);
			renderNewVsReturning(res.new_vs_returning);
			renderBots(res.bots);

			renderKeyCountList(document.getElementById('tl-list-countries'), res.top_countries, 'code', function (it) {
				return (it.flag ? it.flag + ' ' : '') + esc(it.code);
			});
			renderKeyCountList(document.getElementById('tl-list-referrers'), res.top_referrers.map(function (r) { return { key: r.domain, count: r.count }; }), 'key');
			renderKeyCountList(document.getElementById('tl-list-entry'), res.entry_pages.map(function (r) { return { key: r.key, count: r.count }; }), 'key');
			renderKeyCountList(document.getElementById('tl-list-exit'), res.exit_pages.map(function (r) { return { key: r.key, count: r.count }; }), 'key');

			document.getElementById('tl-bounce-rate').textContent =
				res.total_sessions > 0 ? tlFormat(TL_I18N.lang_Bounce_Rate_Template, { rate: res.bounce_rate }) : '';
		});
	}

	// ── Top pages table ────────────────────────────────────────────────────
	function loadTopPages() {
		var tbody = document.getElementById('tl-pages-body');
		tbody.innerHTML = '<tr><td colspan="3" class="tl-muted">' + esc(TL_I18N.lang_Loading) + '</td></tr>';

		tlGet('get_top_pages', {
			range: state.range,
			page: state.pages.page,
			per_page: state.pages.perPage,
			sort: state.pages.sort,
			dir: state.pages.dir,
			search: state.pages.search,
		}).then(function (res) {
			if (!res.ok) return;

			if (!res.rows.length) {
				tbody.innerHTML = '<tr><td colspan="3" class="tl-muted">' + esc(TL_I18N.lang_No_Pages_Found) + '</td></tr>';
			} else {
				tbody.innerHTML = res.rows.map(function (r) {
					return '<tr><td title="' + esc(r.url) + '">' + esc(r.url) + '</td><td>' + r.views + '</td><td>' + r.uniques + '</td></tr>';
				}).join('');
			}

			renderPagination(res.total, res.page, res.per_page);
			updateSortHeaders();
		});
	}

	function renderPagination(total, page, perPage) {
		var totalPages = Math.max(1, Math.ceil(total / perPage));
		var el = document.getElementById('tl-pages-pagination');
		el.innerHTML =
			'<button type="button" id="tl-pg-prev" ' + (page <= 1 ? 'disabled' : '') + '>' + esc(TL_I18N.lang_Prev) + '</button>' +
			'<span>' + esc(tlFormat(TL_I18N.lang_Pagination_Template, { page: page, totalPages: totalPages, total: total })) + '</span>' +
			'<button type="button" id="tl-pg-next" ' + (page >= totalPages ? 'disabled' : '') + '>' + esc(TL_I18N.lang_Next) + '</button>';

		var prev = document.getElementById('tl-pg-prev');
		var next = document.getElementById('tl-pg-next');
		if (prev) prev.addEventListener('click', function () { state.pages.page--; loadTopPages(); });
		if (next) next.addEventListener('click', function () { state.pages.page++; loadTopPages(); });
	}

	function updateSortHeaders() {
		var ths = document.querySelectorAll('.tl-table th[data-sort]');
		ths.forEach(function (th) {
			th.classList.toggle('tl-sort-active', th.getAttribute('data-sort') === state.pages.sort);
		});
	}

	// ── Live feed ───────────────────────────────────────────────────────────
	function renderLiveCountries(countries) {
		var tbody = document.getElementById('tl-live-countries-tbody');
		if (!tbody) return;

		if (!countries || !countries.length) {
			tbody.innerHTML = '<tr><td colspan="2" class="tl-muted" style="text-align:center;">-</td></tr>';
			return;
		}

		tbody.innerHTML = countries.map(function (c) {
			var flag = c.flag ? c.flag + ' ' : '';
			return '<tr><td>' + flag + esc(c.code) + '</td><td>' + c.views + '</td></tr>';
		}).join('');
	}

	function loadLiveFeed() {
		tlGet('get_live_feed', {}).then(function (res) {
			if (!res.ok) return;

			// Update the counter on the stat card if it changed
			var statLive = document.getElementById('tl-stat-live');
			if (statLive && res.live_now !== undefined) {
				statLive.textContent = res.live_now;
			}

			// Render the hover table list
			if (res.live_countries) {
				renderLiveCountries(res.live_countries);
			}

			var label = document.getElementById('tl-live-now-label');
			if (label) {
				label.textContent = res.live_now > 0 ? tlFormat(TL_I18N.lang_Online_Now_Template, { count: res.live_now }) : '';
			}

			var el = document.getElementById('tl-live-feed');
			if (!el) return;
			if (!res.feed.length) {
				el.innerHTML = '<li class="tl-muted">' + esc(TL_I18N.lang_No_Recent_Activity) + '</li>';
				return;
			}
			el.innerHTML = res.feed.map(function (f) {
				return '<li><span class="tl-live-time">' + esc(f.time) + '</span>' +
					'<span class="tl-live-url" title="' + esc(f.url) + '">' + (f.flag ? f.flag + ' ' : '') + esc(f.url) + '</span></li>';
			}).join('');
		});
	}

	function startLiveTimer() {
		if (state.liveTimer) clearInterval(state.liveTimer);
		var seconds = Math.max(15, TL_REFRESH_SECONDS || 60);
		state.liveTimer = setInterval(loadLiveFeed, seconds * 1000);
	}

	// ── Settings panel ─────────────────────────────────────────────────────
	function initSettingsPanel() {
		var toggle = document.getElementById('tl-settings-toggle');
		var panel  = document.getElementById('tl-settings-panel');

		document.getElementById('tl-set-exclude-admin').checked = !!TL_INITIAL_SETTINGS.exclude_admin;
		document.getElementById('tl-set-bot-mode').value        = TL_INITIAL_SETTINGS.bot_mode || 'separate';
		document.getElementById('tl-set-retention').value       = TL_INITIAL_SETTINGS.retention_days;
		document.getElementById('tl-set-refresh').value         = TL_INITIAL_SETTINGS.refresh_seconds;

		toggle.addEventListener('click', function () {
			panel.hidden = !panel.hidden;
			toggle.classList.toggle('tl-btn-active', !panel.hidden);
		});

		document.getElementById('tl-settings-save').addEventListener('click', function () {
			var status = document.getElementById('tl-settings-status');
			var body = 'exclude_admin=' + (document.getElementById('tl-set-exclude-admin').checked ? '1' : '0') +
				'&bot_mode=' + encodeURIComponent(document.getElementById('tl-set-bot-mode').value) +
				'&retention_days=' + encodeURIComponent(document.getElementById('tl-set-retention').value) +
				'&refresh_seconds=' + encodeURIComponent(document.getElementById('tl-set-refresh').value);

			status.textContent = TL_I18N.lang_Saving;
			tlPost('save_settings', body).then(function (res) {
				if (res.ok) {
					status.textContent = TL_I18N.lang_Saved;
					TL_REFRESH_SECONDS = res.settings.refresh_seconds;
					startLiveTimer();
					// The bot setting changes what every query returns, so redraw straight away.
					loadDashboard();
					loadTopPages();
					loadLiveFeed();
					setTimeout(function () { status.textContent = ''; }, 2000);
				} else {
					status.textContent = TL_I18N.lang_Save_Failed;
				}
			});
		});
	}

	// ── CSV export ─────────────────────────────────────────────────────────
	function initExport() {
		document.getElementById('tl-export').addEventListener('click', function () {
			window.location.href = TL_AJAX_BASE + 'export_csv&range=' + encodeURIComponent(state.range);
		});
	}

	// ── PDF export ─────────────────────────────────────────────────────────
	var PDF_MARGIN = 40;

	function pdfEnsureSpace(doc, y, needed) {
		var pageH = doc.internal.pageSize.getHeight();
		if (y + needed > pageH - PDF_MARGIN) {
			doc.addPage();
			pdfDrawFooter(doc);
			return PDF_MARGIN;
		}
		return y;
	}

	function pdfDrawFooter(doc) {
		var pageW = doc.internal.pageSize.getWidth();
		var pageH = doc.internal.pageSize.getHeight();
		doc.setFontSize(8);
		doc.setTextColor(150);
		doc.text(TL_I18N.lang_Pdf_Footer, PDF_MARGIN, pageH - 18);
		doc.text(String(doc.internal.getNumberOfPages()), pageW - PDF_MARGIN, pageH - 18, { align: 'right' });
		doc.setTextColor(20);
	}

	// Places a chart's current on-screen image at a target width, preserving its actual aspect ratio.
	function pdfAddChartImage(doc, chartId, y, targetWidth) {
		var chart = state.charts[chartId];
		if (!chart) return y;
		var dataUrl = chart.toBase64Image('image/png', 1);
		var props = doc.getImageProperties(dataUrl);
		var h = (props.height * targetWidth) / props.width;
		y = pdfEnsureSpace(doc, y, h + 10);
		doc.addImage(dataUrl, 'PNG', PDF_MARGIN, y, targetWidth, h);
		return y + h + 14;
	}

	function pdfSectionTitle(doc, y, text) {
		y = pdfEnsureSpace(doc, y, 24);
		doc.setFontSize(12);
		doc.setFont(undefined, 'bold');
		doc.setTextColor(20);
		doc.text(text, PDF_MARGIN, y);
		doc.setFont(undefined, 'normal');
		return y + 16;
	}

	// Draws a title with no space-checking - for use inside a row where the
	// caller has already reserved space for the whole row (title + image).
	function pdfTitleOnly(doc, x, y, text) {
		doc.setFontSize(11);
		doc.setFont(undefined, 'bold');
		doc.setTextColor(20);
		doc.text(text, x, y);
		doc.setFont(undefined, 'normal');
	}

	function pdfTable(doc, y, head, body, title) {
		y = pdfSectionTitle(doc, y, title);
		if (!body.length) {
			doc.setFontSize(9);
			doc.setTextColor(140);
			doc.text(TL_I18N.lang_No_Data_Range, PDF_MARGIN, y);
			doc.setTextColor(20);
			return y + 18;
		}
		doc.autoTable({
			startY: y,
			head: [head],
			body: body,
			margin: { left: PDF_MARGIN, right: PDF_MARGIN },
			styles: { fontSize: 8, cellPadding: 4 },
			headStyles: { fillColor: [39, 100, 231] },
			theme: 'striped',
		});
		return doc.lastAutoTable.finalY + 18;
	}

	function pdfNewVsReturning(doc, y, nr) {
		y = pdfSectionTitle(doc, y, TL_I18N.lang_Pdf_Newret_Title);
		if (!nr.available) {
			doc.setFontSize(9);
			doc.setTextColor(140);
			doc.text(TL_I18N.lang_Pdf_Newret_Unavailable, PDF_MARGIN, y);
			doc.setTextColor(20);
			return y + 18;
		}
		var total = nr.new + nr.returning;
		var newPct = total > 0 ? Math.round((nr.new / total) * 100) : 0;
		var barW = 515;
		var newW = barW * (newPct / 100);
		y = pdfEnsureSpace(doc, y, 30);
		doc.setFillColor(39, 100, 231);
		doc.rect(PDF_MARGIN, y, newW, 10, 'F');
		doc.setFillColor(230, 126, 34);
		doc.rect(PDF_MARGIN + newW, y, barW - newW, 10, 'F');
		y += 20;
		doc.setFontSize(9);
		var newText = tlFormat(TL_I18N.lang_Newret_New_Template, { count: nr.new, pct: newPct });
		var retText = tlFormat(TL_I18N.lang_Newret_Returning_Template, { count: nr.returning, pct: 100 - newPct });
		doc.text(newText + '   |   ' + retText, PDF_MARGIN, y);
		return y + 18;
	}

	function buildPdfReport() {
		var res = state.lastData;
		if (!res || !window.jspdf) return;

		var doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'a4' });
		var pageW = doc.internal.pageSize.getWidth();
		var contentW = pageW - PDF_MARGIN * 2;
		var y = PDF_MARGIN;

		// Header
		doc.setFontSize(18);
		doc.setFont(undefined, 'bold');
		doc.text(TL_I18N.lang_Pdf_Title, PDF_MARGIN, y);
		doc.setFont(undefined, 'normal');
		doc.setFontSize(10);
		doc.setTextColor(120);
		var now = new Date();
		doc.text((RANGE_LABELS[state.range] || state.range) + '  -  ' + TL_I18N.lang_Pdf_Generated + ' ' + now.toLocaleDateString() + ' ' + now.toLocaleTimeString(), PDF_MARGIN, y + 16);
		doc.setTextColor(20);
		y += 40;

		// Stat summary
		var stats = res.stats;
		var statLine = tlFormat(TL_I18N.lang_Pdf_Stat_Line_Template, {
			total: stats.total, unique: stats.unique, today: stats.today, live: stats.live,
		});
		doc.setFontSize(10);
		doc.text(statLine, PDF_MARGIN, y);
		y += 24;

		pdfDrawFooter(doc);

		// Daily visitors chart, full width
		var dailyTitle = (res.daily_trend && res.daily_trend.granularity === 'hourly') ? TL_I18N.lang_Visitors_By_Hour : TL_I18N.lang_Daily_Visitors;
		y = pdfSectionTitle(doc, y, dailyTitle);
		y = pdfAddChartImage(doc, 'tl-chart-daily', y, contentW);

		// Device / Browser / OS donuts, three across.
		// Pre-compute each column's image height first, so we can reserve
		// space for the whole row (title + tallest image) in one check -
		// otherwise a title alone might fit at the bottom of a page while
		// its image gets cut off, or the three columns split across pages.
		var thirdW = (contentW - 20) / 3;
		var donutCols = [
			{ id: 'tl-chart-device',  title: TL_I18N.lang_Devices },
			{ id: 'tl-chart-browser', title: TL_I18N.lang_Browsers },
			{ id: 'tl-chart-os',      title: TL_I18N.lang_Operating_Systems },
		].map(function (col, i) {
			var chart = state.charts[col.id];
			if (!chart) return null;
			var dataUrl = chart.toBase64Image('image/png', 1);
			var props = doc.getImageProperties(dataUrl);
			var h = (props.height * thirdW) / props.width;
			return { x: PDF_MARGIN + i * (thirdW + 10), dataUrl: dataUrl, h: h, title: col.title };
		}).filter(Boolean);

		if (donutCols.length) {
			var maxDonutH = Math.max.apply(null, donutCols.map(function (c) { return c.h; }));
			y = pdfEnsureSpace(doc, y, 16 + maxDonutH + 20);
			donutCols.forEach(function (c) {
				pdfTitleOnly(doc, c.x, y, c.title);
				doc.addImage(c.dataUrl, 'PNG', c.x, y + 6, thirdW, c.h);
			});
			y += 16 + maxDonutH + 20;
		}

		// Peak hours / day of week, two across - same reserve-then-draw approach.
		var halfW = (contentW - 10) / 2;
		var barCols = [
			{ id: 'tl-chart-hourly', title: TL_I18N.lang_Peak_Hours },
			{ id: 'tl-chart-dow',    title: TL_I18N.lang_Day_Of_Week },
		].map(function (col, i) {
			var chart = state.charts[col.id];
			if (!chart) return null;
			var dataUrl = chart.toBase64Image('image/png', 1);
			var props = doc.getImageProperties(dataUrl);
			var h = (props.height * halfW) / props.width;
			return { x: PDF_MARGIN + i * (halfW + 10), dataUrl: dataUrl, h: h, title: col.title };
		}).filter(Boolean);

		if (barCols.length) {
			var maxBarH = Math.max.apply(null, barCols.map(function (c) { return c.h; }));
			y = pdfEnsureSpace(doc, y, 16 + maxBarH + 20);
			barCols.forEach(function (c) {
				pdfTitleOnly(doc, c.x, y, c.title);
				doc.addImage(c.dataUrl, 'PNG', c.x, y + 6, halfW, c.h);
			});
			y += 16 + maxBarH + 20;
		}

		// New vs. returning
		y = pdfNewVsReturning(doc, y, res.new_vs_returning);

		// Top countries / referrers
		// Note: flag emoji are deliberately omitted here - jsPDF's built-in fonts
		// have no emoji glyphs and render them as garbled bytes. Plain codes only.
		y = pdfTable(doc, y, [TL_I18N.lang_Pdf_Col_Country, TL_I18N.lang_Unique_Visitors], res.top_countries.map(function (c) {
			return [c.code, String(c.count)];
		}), TL_I18N.lang_Top_Countries);

		y = pdfTable(doc, y, [TL_I18N.lang_Pdf_Col_Referrer, TL_I18N.lang_Pdf_Col_Visits], res.top_referrers.map(function (r) {
			return [r.domain, String(r.count)];
		}), TL_I18N.lang_Top_Referrers);

		// Entry / exit pages + bounce rate
		var bounceNote = res.total_sessions > 0
			? tlFormat(TL_I18N.lang_Pdf_Bounce_Note_Template, { rate: res.bounce_rate, sessions: res.total_sessions })
			: '';
		y = pdfTable(doc, y, [TL_I18N.lang_Pdf_Col_Entry_Page, TL_I18N.lang_Pdf_Col_Sessions], res.entry_pages.map(function (r) {
			return [r.key, String(r.count)];
		}), TL_I18N.lang_Entry_Pages);

		y = pdfTable(doc, y, [TL_I18N.lang_Pdf_Col_Exit_Page, TL_I18N.lang_Pdf_Col_Sessions], res.exit_pages.map(function (r) {
			return [r.key, String(r.count)];
		}), TL_I18N.lang_Exit_Pages + bounceNote);

		// Top pages - fetch a fresh, larger, unpaginated-for-report list so the
		// PDF isn't limited to whatever page the on-screen table happens to be on.
		return tlGet('get_top_pages', {
			range: state.range, page: 1, per_page: 50, sort: 'views', dir: 'desc', search: '',
		}).then(function (pagesRes) {
			var rows = (pagesRes && pagesRes.ok) ? pagesRes.rows : [];
			pdfTable(doc, y, [TL_I18N.lang_Col_URL, TL_I18N.lang_Col_Views, TL_I18N.lang_Col_Uniques], rows.map(function (r) {
				return [r.url, String(r.views), String(r.uniques)];
			}), TL_I18N.lang_Top_Pages);

			var filename = 'trafficlens-report-' + state.range + '-' + now.toISOString().slice(0, 10) + '.pdf';
			doc.save(filename);
		});
	}

	function initPdfExport() {
		var btn = document.getElementById('tl-export-pdf');
		btn.addEventListener('click', function () {
			if (!state.lastData) return;
			var originalText = btn.textContent;
			btn.disabled = true;
			btn.textContent = TL_I18N.lang_Generating;
			Promise.resolve(buildPdfReport())
				.catch(function (err) {
					console.error('TrafficLens PDF export failed:', err);
					alert(TL_I18N.lang_Pdf_Export_Failed);
				})
				.then(function () {
					btn.disabled = false;
					btn.textContent = originalText;
				});
		});
	}

	// ── Range selector / refresh ───────────────────────────────────────────
	function initControls() {
		document.getElementById('tl-range').addEventListener('change', function (e) {
			state.range = e.target.value;
			state.pages.page = 1;
			loadDashboard();
			loadTopPages();
		});

		document.getElementById('tl-refresh').addEventListener('click', function () {
			loadDashboard();
			loadTopPages();
			loadLiveFeed();
		});
	}

	// ── Top pages controls (search + sort) ─────────────────────────────────
	function initPagesControls() {
		var searchTimer = null;
		document.getElementById('tl-pages-search').addEventListener('input', function (e) {
			var val = e.target.value;
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				state.pages.search = val;
				state.pages.page = 1;
				loadTopPages();
			}, 300);
		});

		document.querySelectorAll('.tl-table th[data-sort]').forEach(function (th) {
			th.addEventListener('click', function () {
				var col = th.getAttribute('data-sort');
				if (state.pages.sort === col) {
					state.pages.dir = (state.pages.dir === 'asc') ? 'desc' : 'asc';
				} else {
					state.pages.sort = col;
					state.pages.dir = 'desc';
				}
				state.pages.page = 1;
				loadTopPages();
			});
		});
	}

	// ── Init ────────────────────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function () {
		initControls();
		initPagesControls();
		initSettingsPanel();
		initExport();
		initPdfExport();

		loadDashboard();
		loadTopPages();
		loadLiveFeed();
		startLiveTimer();
	});
})();
