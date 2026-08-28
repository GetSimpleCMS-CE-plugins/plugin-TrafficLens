(function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────
	var state = {
		range: '30d',
		pages: { page: 1, perPage: 10, sort: 'views', dir: 'desc', search: '' },
		charts: {},
		liveTimer: null,
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
		upsertChart('tl-chart-daily', {
			type: 'line',
			data: {
				labels: daily.labels,
				datasets: [
					{
						label: 'Unique visitors',
						data: daily.uniques,
						borderColor: CHART_COLORS[0],
						backgroundColor: 'rgba(39,100,231,.08)',
						fill: true,
						tension: .25,
						pointRadius: 0,
					},
					{
						label: 'Page views',
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
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
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
			el.innerHTML = '<li class="tl-muted">No data for this range.</li>';
			return;
		}
		var html = items.map(function (it) {
			var label = formatKey ? formatKey(it) : esc(it[keyField]);
			return '<li><span class="tl-list-key" title="' + esc(it[keyField]) + '">' + label + '</span><span class="tl-list-count">' + it.count + '</span></li>';
		}).join('');
		el.innerHTML = html;
	}

	function renderNewVsReturning(nr) {
		var el = document.getElementById('tl-newret-body');
		if (!nr.available) {
			el.innerHTML = '<div class="tl-muted">Select a shorter time range (not "All time") to see new vs. returning visitors.</div>';
			return;
		}
		var total = nr.new + nr.returning;
		var newPct = total > 0 ? Math.round((nr.new / total) * 100) : 0;
		var retPct = 100 - newPct;
		el.innerHTML =
			'<div class="tl-newret-bar"><span class="new" style="width:' + newPct + '%"></span><span class="returning" style="width:' + retPct + '%"></span></div>' +
			'<div class="tl-newret-legend">' +
			'<span><span class="dot new"></span>New: ' + nr.new + ' (' + newPct + '%)</span>' +
			'<span><span class="dot returning"></span>Returning: ' + nr.returning + ' (' + retPct + '%)</span>' +
			'</div>';
	}

	// ── Main dashboard load ────────────────────────────────────────────────
	function loadDashboard() {
		tlGet('get_dashboard_data', { range: state.range }).then(function (res) {
			if (!res.ok) return;

			document.getElementById('tl-stat-total').textContent  = res.stats.total;
			document.getElementById('tl-stat-unique').textContent = res.stats.unique;
			document.getElementById('tl-stat-today').textContent  = res.stats.today;
			document.getElementById('tl-stat-live').textContent   = res.stats.live;

			renderDailyChart(res.daily_trend);
			renderDonut('tl-chart-device', res.device_breakdown, 0);
			renderDonut('tl-chart-browser', res.browser_breakdown, 2);
			renderDonut('tl-chart-os', res.os_breakdown, 4);
			renderHourlyChart(res.hourly);
			renderDowChart(res.day_of_week);
			renderNewVsReturning(res.new_vs_returning);

			renderKeyCountList(document.getElementById('tl-list-countries'), res.top_countries, 'code', function (it) {
				return (it.flag ? it.flag + ' ' : '') + esc(it.code);
			});
			renderKeyCountList(document.getElementById('tl-list-referrers'), res.top_referrers.map(function (r) { return { key: r.domain, count: r.count }; }), 'key');
			renderKeyCountList(document.getElementById('tl-list-entry'), res.entry_pages.map(function (r) { return { key: r.key, count: r.count }; }), 'key');
			renderKeyCountList(document.getElementById('tl-list-exit'), res.exit_pages.map(function (r) { return { key: r.key, count: r.count }; }), 'key');

			document.getElementById('tl-bounce-rate').textContent =
				res.total_sessions > 0 ? '(' + res.bounce_rate + '% bounce rate)' : '';
		});
	}

	// ── Top pages table ────────────────────────────────────────────────────
	function loadTopPages() {
		var tbody = document.getElementById('tl-pages-body');
		tbody.innerHTML = '<tr><td colspan="3" class="tl-muted">Loading...</td></tr>';

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
				tbody.innerHTML = '<tr><td colspan="3" class="tl-muted">No pages found.</td></tr>';
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
			'<button type="button" id="tl-pg-prev" ' + (page <= 1 ? 'disabled' : '') + '>&laquo; Prev</button>' +
			'<span>Page ' + page + ' of ' + totalPages + ' (' + total + ' pages tracked)</span>' +
			'<button type="button" id="tl-pg-next" ' + (page >= totalPages ? 'disabled' : '') + '>Next &raquo;</button>';

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
	function loadLiveFeed() {
		tlGet('get_live_feed', {}).then(function (res) {
			if (!res.ok) return;

			var label = document.getElementById('tl-live-now-label');
			label.textContent = res.live_now > 0 ? '(' + res.live_now + ' online now)' : '';

			var el = document.getElementById('tl-live-feed');
			if (!res.feed.length) {
				el.innerHTML = '<li class="tl-muted">No recent activity.</li>';
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
		document.getElementById('tl-set-retention').value       = TL_INITIAL_SETTINGS.retention_days;
		document.getElementById('tl-set-refresh').value         = TL_INITIAL_SETTINGS.refresh_seconds;

		toggle.addEventListener('click', function () {
			panel.hidden = !panel.hidden;
		});

		document.getElementById('tl-settings-save').addEventListener('click', function () {
			var status = document.getElementById('tl-settings-status');
			var body = 'exclude_admin=' + (document.getElementById('tl-set-exclude-admin').checked ? '1' : '0') +
				'&retention_days=' + encodeURIComponent(document.getElementById('tl-set-retention').value) +
				'&refresh_seconds=' + encodeURIComponent(document.getElementById('tl-set-refresh').value);

			status.textContent = 'Saving...';
			tlPost('save_settings', body).then(function (res) {
				if (res.ok) {
					status.textContent = 'Saved.';
					TL_REFRESH_SECONDS = res.settings.refresh_seconds;
					startLiveTimer();
					setTimeout(function () { status.textContent = ''; }, 2000);
				} else {
					status.textContent = 'Save failed.';
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

		loadDashboard();
		loadTopPages();
		loadLiveFeed();
		startLiveTimer();
	});
})();
