<?php
/**
 * shared/auth.php
 * SSO session management and access-control helpers.
 *
 * Flow:
 *  1. User visits /id/auth/login.php  → authenticates → session created
 *  2. User visits /edu/index.php      → require_auth('edu') called
 *     a. If already authed AND has access → continue
 *     b. Otherwise → redirect to /id/auth/login?app=edu&return_url=<current>
 *  3. After /id login, redirect back with a short-lived SSO token
 *  4. App validates token → sets own local session
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Regenerate ID periodically to avoid session fixation
    if (empty($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }
}

/* ── Current user helpers ──────────────────────── */

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

function is_admin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

/* ── CSRF ───────────────────────────────────────── */

function csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

/* ── App access control ─────────────────────────── */

/**
 * Ensure the user is authenticated and has access to $app_slug.
 * If not, redirect to /id/auth/login with the appropriate return URL.
 *
 * @param string $app_slug   e.g. 'edu', 'admin'
 * @param bool   $admin_only Require admin role
 */
function require_auth(string $app_slug = '', bool $admin_only = false): array {
    // Maintenance check first (skip for admin)
    if ($app_slug !== 'admin' && is_maintenance()) {
        maintenance_page();
    }

    if (!is_logged_in()) {
        redirect_to_login($app_slug);
    }

    if ($admin_only && !is_admin()) {
        forbidden();
    }

    if ($app_slug !== '' && !user_has_app_access($app_slug)) {
        forbidden('You do not have access to this application.');
    }

    return current_user();
}

function user_has_app_access(string $app_slug): bool {
    $user = current_user();
    if (!$user) return false;
    if (($user['role'] ?? '') === 'admin') return true;   // admins have access to everything

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM user_app_access uaa
             JOIN apps a ON a.id = uaa.app_id
             WHERE uaa.user_id = ? AND a.slug = ? AND a.is_active = 1'
        );
        $stmt->execute([$user['id'], $app_slug]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function redirect_to_login(string $app_slug = ''): never {
    $return_url = APP_URL . $_SERVER['REQUEST_URI'];
    $params     = ['return_url' => $return_url];
    if ($app_slug !== '') $params['app'] = $app_slug;
    header('Location: ' . APP_URL . '/id/auth/login.php?' . http_build_query($params));
    exit;
}

function forbidden(string $message = 'Access denied.'): never {
    http_response_code(403);
    echo render_error_page(403, $message);
    exit;
}

function maintenance_page(): never {
    http_response_code(503);
    echo render_error_page(503, 'The site is currently under maintenance. Please check back soon.');
    exit;
}

/* ── SSO token helpers ──────────────────────────── */

/**
 * Generate a one-time SSO token for a validated user redirect.
 */
function create_sso_token(int $user_id, string $app_slug): string {
    $token = bin2hex(random_bytes(32));
    db()->prepare(
        'INSERT INTO sso_tokens (user_id, token, app_slug, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    )->execute([$user_id, $token, $app_slug, SSO_TOKEN_TTL]);
    return $token;
}

/**
 * Validate an SSO token. Returns user array on success, null on failure.
 * Marks the token as used (one-time).
 */
function consume_sso_token(string $token, string $app_slug): ?array {
    $db = db();
    $stmt = $db->prepare(
        'SELECT sso_tokens.user_id, users.username, users.display_name,
                users.email, users.role, users.is_active
         FROM sso_tokens
         JOIN users ON users.id = sso_tokens.user_id
         WHERE sso_tokens.token = ?
           AND sso_tokens.app_slug = ?
           AND sso_tokens.used = 0
           AND sso_tokens.expires_at > NOW()
           AND users.is_active = 1'
    );
    $stmt->execute([$token, $app_slug]);
    $user = $stmt->fetch();

    if ($user) {
        $db->prepare('UPDATE sso_tokens SET used = 1 WHERE token = ?')->execute([$token]);
        // Map user_id → id for consistency
        $user['id'] = $user['user_id'];
        unset($user['user_id']);
        return $user;
    }
    return null;
}

/**
 * Log in a user locally (set session).
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'           => $user['id'],
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
    ];
    // Update last_login
    try {
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
            ->execute([$user['id']]);
    } catch (Throwable $e) { /* non-critical */ }
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

/* ── Error pages ────────────────────────────────── */

function render_error_page(int $code, string $message): string {
    $titles = [403 => 'Forbidden', 404 => 'Not Found', 503 => 'Maintenance'];
    $title  = $titles[$code] ?? 'Error';
    $icons  = [403 => 'block', 404 => 'search_off', 503 => 'engineering'];
    $icon   = $icons[$code] ?? 'error';
    $base   = base_path_for_assets();
    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $code . ' – ' . htmlspecialchars($title) ?> | <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= $base ?>shared/assets/style.css">
  <script>
    (function(){var t=localStorage.getItem('cg-theme')||
    (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);})();
    document.addEventListener('DOMContentLoaded',function(){var l=document.getElementById('page-loader');if(l)l.classList.add('pg-done');});
  </script>
</head>
<body>
<div id="page-loader"></div>
<div class="login-page">
  <div class="login-card">
    <div class="login-header">
      <span class="material-symbols-outlined logo-icon"><?= $icon ?></span>
      <h1><?= $code . ' – ' . htmlspecialchars($title) ?></h1>
      <p><?= htmlspecialchars($message) ?></p>
    </div>
    <div class="login-body" style="text-align:center;">
      <a href="<?= APP_URL ?>" class="btn btn-primary" style="margin-top:.5rem">
        <span class="material-symbols-outlined">home</span> Go Home
      </a>
    </div>
  </div>
</div>
<script src="<?= $base ?>shared/assets/app.js"></script>
</body>
</html>
<?php return ob_get_clean();
}

/**
 * Helper: figure out the relative path to the repo root for asset links.
 * This is approximate – for production use a configured APP_URL constant.
 */
function base_path_for_assets(): string {
    return APP_URL . '/';
}
