<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
 * Phase 1 placeholder. Confirms the tracking hook + DB + geo lookup are
 * working before we build the real card/chart dashboard on top of this data.
 */

$db = TrafficLens_db();

$total_visits = (int)$db->querySingle('SELECT COUNT(*) FROM visits');
$unique_ips   = (int)$db->querySingle('SELECT COUNT(DISTINCT ip_hash) FROM visits');
$with_country = (int)$db->querySingle("SELECT COUNT(*) FROM visits WHERE country_code IS NOT NULL");

$mmdb_found = is_readable(TrafficLens_mmdb_path());

$recent = [];
$res = $db->query('SELECT * FROM visits ORDER BY id DESC LIMIT 15');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $recent[] = $row; }
?>
<div class="TrafficLens-wrap" style="padding:1.5rem;font-family:system-ui,sans-serif;max-width:900px;">
	<h2>TrafficLens - Foundation Check</h2>
	<p style="color:#666;">This is a temporary status page for testing the tracking layer. The real dashboard (cards, charts, top pages/countries/referrers) comes in the next phase.</p>

	<div style="display:flex;gap:1rem;margin:1.5rem 0;flex-wrap:wrap;">
		<div style="background:#f4f6f9;border-radius:6px;padding:1rem 1.5rem;">
			<div style="font-size:.8rem;color:#666;">Total page views</div>
			<div style="font-size:1.5rem;font-weight:700;"><?= $total_visits ?></div>
		</div>
		<div style="background:#f4f6f9;border-radius:6px;padding:1rem 1.5rem;">
			<div style="font-size:.8rem;color:#666;">Unique visitors</div>
			<div style="font-size:1.5rem;font-weight:700;"><?= $unique_ips ?></div>
		</div>
		<div style="background:#f4f6f9;border-radius:6px;padding:1rem 1.5rem;">
			<div style="font-size:.8rem;color:#666;">Visits with country resolved</div>
			<div style="font-size:1.5rem;font-weight:700;"><?= $with_country ?></div>
		</div>
	</div>

	<p>
		<strong>GeoIP database:</strong>
		<?php if ($mmdb_found): ?>
			<span style="color:#166534;">found</span> at <code><?= htmlspecialchars(TrafficLens_mmdb_path()) ?></code>
		<?php else: ?>
			<span style="color:#b91c1c;">not found</span> - country lookups will return null until you place
			<code>dbip-country-lite.mmdb</code> in <code><?= htmlspecialchars(TrafficLens_PLUGIN_DIR . 'vendor/') ?></code>
		<?php endif; ?>
	</p>

	<h3>Last 15 recorded visits</h3>
	<table style="width:100%;border-collapse:collapse;font-size:.875rem;">
		<thead>
			<tr style="text-align:left;border-bottom:2px solid #ddd;">
				<th style="padding:.4rem;">Time</th>
				<th style="padding:.4rem;">URL</th>
				<th style="padding:.4rem;">Country</th>
				<th style="padding:.4rem;">Browser</th>
				<th style="padding:.4rem;">OS</th>
				<th style="padding:.4rem;">Device</th>
				<th style="padding:.4rem;">Referrer</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($recent as $r): ?>
			<tr style="border-bottom:1px solid #eee;">
				<td style="padding:.4rem;"><?= date('Y-m-d H:i', $r['timestamp']) ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['url']) ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['country_code'] ?? '-') ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['browser'] ?? '-') ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['os'] ?? '-') ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['device_type'] ?? '-') ?></td>
				<td style="padding:.4rem;"><?= htmlspecialchars($r['referrer_domain'] ?? '-') ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if (empty($recent)): ?>
			<tr><td colspan="7" style="padding:.75rem;color:#666;">No visits recorded yet - browse the front-end of the site to generate a row.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
