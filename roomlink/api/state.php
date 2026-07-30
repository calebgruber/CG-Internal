<?php
/**
 * roomlink/api/state.php – Pi Polling API
 *
 * GET  → returns current e-ink state + departures if tab=transit
 * POST → updates state (set_tab, set_mode, set_custom_text)
 *
 * No session auth — uses optional API token from settings (rl_api_token).
 * If no token is configured, all requests are allowed.
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ── Token check ── */
$configured_token = rl_setting('api_token', '');
if ($configured_token !== '') {
    $provided_token = $_SERVER['HTTP_X_TOKEN']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    // Strip "Bearer " prefix if present
    if (str_starts_with($provided_token, 'Bearer ')) {
        $provided_token = substr($provided_token, 7);
    }
    $provided_token = trim($provided_token);

    // Also check query string or JSON body token
    $body_token = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $body_token = $body['token'] ?? '';
    } else {
        $body_token = $_GET['token'] ?? '';
    }

    $token_match = ($provided_token !== '' && hash_equals($configured_token, $provided_token))
                || ($body_token     !== '' && hash_equals($configured_token, $body_token));

    if (!$token_match) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

/* ── Update Pi poll timestamp ── */
function touch_pi_poll(): void {
    try {
        db()->prepare(
            'INSERT INTO roomlink_eink_state (id, current_tab, last_pi_poll)
             VALUES (1, "transit", NOW())
             ON DUPLICATE KEY UPDATE last_pi_poll = NOW()'
        )->execute();
    } catch (PDOException $e) { /* non-critical */ }
}

/* ── Handle GET ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    touch_pi_poll();
    $eink = rl_eink_state();

    $payload = [
        'ok'         => true,
        'tab'        => $eink['current_tab'],
        'mode'       => $eink['display_mode'],
        'custom_text'=> $eink['custom_text'],
        'updated_at' => $eink['last_updated']
            ? (new DateTime($eink['last_updated']))->format(DateTime::ATOM)
            : null,
        'server_time'=> (new DateTime())->format(DateTime::ATOM),
    ];

    // Include departures when tab is transit
    if ($eink['current_tab'] === 'transit') {
        $payload['departures'] = fetch_departures_for_api(8);
    }

    echo json_encode($payload);
    exit;
}

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'set_tab') {
        $tab = $body['tab'] ?? '';
        $allowed = ['transit', 'clock', 'weather', 'custom'];
        if (!in_array($tab, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid tab. Allowed: ' . implode(', ', $allowed)]);
            exit;
        }
        rl_set_eink_tab($tab);
        touch_pi_poll();
        $eink = rl_eink_state();
        echo json_encode([
            'ok'         => true,
            'tab'        => $eink['current_tab'],
            'updated_at' => (new DateTime())->format(DateTime::ATOM),
        ]);
        exit;
    }

    if ($action === 'set_mode') {
        $mode = $body['mode'] ?? 'normal';
        $allowed_modes = ['normal', 'inverted', 'red-highlight'];
        if (!in_array($mode, $allowed_modes, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid mode.']);
            exit;
        }
        try {
            db()->prepare(
                'INSERT INTO roomlink_eink_state (id, current_tab, display_mode)
                 VALUES (1, "transit", ?)
                 ON DUPLICATE KEY UPDATE display_mode = VALUES(display_mode)'
            )->execute([$mode]);
        } catch (PDOException $e) {}
        echo json_encode(['ok' => true, 'mode' => $mode]);
        exit;
    }

    if ($action === 'set_custom_text') {
        $text = substr($body['text'] ?? '', 0, 2000);
        try {
            db()->prepare(
                'INSERT INTO roomlink_eink_state (id, current_tab, custom_text)
                 VALUES (1, "transit", ?)
                 ON DUPLICATE KEY UPDATE custom_text = VALUES(custom_text)'
            )->execute([$text]);
        } catch (PDOException $e) {}
        echo json_encode(['ok' => true, 'custom_text' => $text]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
exit;

/* ── Fetch departures for the API response ── */
function fetch_departures_for_api(int $limit = 8): array {
    // Try to call the transit API internally
    try {
        $url = APP_URL . '/roomlink/api/transit?json=1&limit=' . $limit;
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw) {
            $data = json_decode($raw, true);
            return $data['departures'] ?? [];
        }
    } catch (Throwable $e) {}
    return [];
}
