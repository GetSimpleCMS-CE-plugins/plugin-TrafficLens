<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

function TrafficLens_handle_ajax($action) {
	header('Content-Type: application/json');

	$allowed_ranges = TrafficLens_allowed_ranges();

	switch ($action) {

		case 'get_dashboard_data': {
			$range = $_GET['range'] ?? '30d';
			if (!in_array($range, $allowed_ranges, true)) $range = '30d';
			echo json_encode(['ok' => true] + TrafficLens_dashboard_payload($range));
			exit;
		}

		case 'get_top_pages': {
			$range = $_GET['range'] ?? '30d';
			if (!in_array($range, $allowed_ranges, true)) $range = '30d';
			$page     = (int)($_GET['page'] ?? 1);
			$per_page = (int)($_GET['per_page'] ?? 10);
			$sort     = $_GET['sort'] ?? 'views';
			$dir      = $_GET['dir'] ?? 'desc';
			$search   = trim((string)($_GET['search'] ?? ''));

			$result = TrafficLens_top_pages($range, $page, $per_page, $sort, $dir, $search);
			echo json_encode(['ok' => true] + $result);
			exit;
		}

		case 'get_live_feed': {
			echo json_encode(['ok' => true] + TrafficLens_live_feed(20));
			exit;
		}

		case 'export_csv': {
			$range = $_GET['range'] ?? '30d';
			if (!in_array($range, $allowed_ranges, true)) $range = '30d';
			TrafficLens_export_csv($range); // streams the file and exits internally
			exit;
		}

		case 'get_settings': {
			echo json_encode(['ok' => true, 'settings' => TrafficLens_settings()]);
			exit;
		}

		case 'save_settings': {
			$bot_mode = $_POST['bot_mode'] ?? TrafficLens_settings()['bot_mode'];
			if (!in_array($bot_mode, TrafficLens_bot_modes(), true)) $bot_mode = 'separate';

			$new = [
				'exclude_admin'   => !empty($_POST['exclude_admin']),
				'bot_mode'        => $bot_mode,
				'retention_days'  => max(1, (int)($_POST['retention_days'] ?? 400)),
				'refresh_seconds' => max(15, (int)($_POST['refresh_seconds'] ?? 60)),
			];
			$saved = TrafficLens_save_settings($new);
			echo json_encode(['ok' => true, 'settings' => $saved]);
			exit;
		}

		default:
			echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
			exit;
	}
}
