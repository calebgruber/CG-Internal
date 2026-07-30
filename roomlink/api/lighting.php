<?php
/**
 * roomlink/api/lighting.php – WLED Proxy
 *
 * Proxies requests to WLED controllers to avoid browser CORS issues.
 *
 * GET  ?controller_id=1&action=state    → WLED /json/state
 * GET  ?controller_id=1&action=presets  → WLED /json/presets
 * POST ?controller_id=1&action=set      → POST to WLED /json/state
 * POST ?controller_id=1&action=preset   → POST to WLED /json/state (preset)
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

// Require session auth
if (!is_logged_in()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$ctrl_id = (int)($_GET['controller_id'] ?? 0);
$action  = $_GET['action'] ?? 'state';

if ($ctrl_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing controller_id']);
    exit;
}

/* ── Look up controller ── */
try {
    $ctrl = db()->prepare(
        'SELECT id, name, ip_address, port FROM roomlink_wled_controllers WHERE id = ? AND is_active = 1'
    );
    $ctrl->execute([$ctrl_id]);
    $controller = $ctrl->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

if (!$controller) {
    http_response_code(404);
    echo json_encode(['error' => 'Controller not found']);
    exit;
}

/* ── Validate controller IP/host to prevent SSRF ── */
$raw_ip = $controller['ip_address'];
// Allow only IPv4, IPv6, or simple hostnames (no path, query, or scheme injection)
if (!filter_var($raw_ip, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9\-\.]+$/', $raw_ip)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid controller IP address']);
    exit;
}
$port = (int)$controller['port'];
if ($port < 1 || $port > 65535) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid controller port']);
    exit;
}
$base_url = 'http://' . $raw_ip . ':' . $port;

/* ── Proxy helper ── */
function wled_get(string $url): string|false {
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function wled_post(string $url, string $body): string|false {
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
            'content'       => $body,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);
    return @file_get_contents($url, false, $ctx);
}

/* ── Handle actions ── */
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if ($action === 'state') {
        $resp = wled_get($base_url . '/json/state');
        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Controller unreachable', 'ip' => $controller['ip_address']]);
            exit;
        }
        $data = json_decode($resp, true);
        if (!$data) {
            http_response_code(502);
            echo json_encode(['error' => 'Invalid response from controller']);
            exit;
        }
        echo json_encode($data);
        exit;
    }

    if ($action === 'presets') {
        $resp = wled_get($base_url . '/json/presets');
        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Controller unreachable']);
            exit;
        }
        $data = json_decode($resp, true);
        echo json_encode($data ?? new stdClass());
        exit;
    }

    if ($action === 'info') {
        $resp = wled_get($base_url . '/json/info');
        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Controller unreachable']);
            exit;
        }
        echo $resp;
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown GET action. Use: state, presets, info']);
    exit;
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');

    // Validate the body is JSON
    $parsed = json_decode($body, true);
    if ($parsed === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    if ($action === 'set') {
        // POST to WLED /json/state — e.g. {"on":true,"bri":128}
        $resp = wled_post($base_url . '/json/state', $body);
        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Controller unreachable']);
            exit;
        }
        $data = json_decode($resp, true);
        echo json_encode($data ?? ['ok' => true]);
        exit;
    }

    if ($action === 'preset') {
        // Activate a preset: body should be {"ps": N}
        if (!isset($parsed['ps'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing ps (preset ID) in body']);
            exit;
        }
        $safe_body = json_encode(['ps' => (int)$parsed['ps']]);
        $resp = wled_post($base_url . '/json/state', $safe_body);
        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Controller unreachable']);
            exit;
        }
        $data = json_decode($resp, true);
        echo json_encode($data ?? ['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown POST action. Use: set, preset']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
