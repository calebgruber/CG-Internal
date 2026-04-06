<?php
/**
 * id/auth/transit.php
 * SSO transit / app-launch loading screen.
 *
 * Shows a "Logging you into {app name}…" loading animation (same split-panel
 * layout as login.php), then auto-redirects to the target app URL.
 *
 * URL params:
 *   app_name    – Display name of the app (e.g. "WMATA Tracker")
 *   redirect    – Validated absolute URL on this origin to redirect to
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';

// Must be logged in — else bounce to login
if (!is_logged_in()) {
    $qs = http_build_query([
        'return_url' => APP_URL . '/id/auth/transit.php?' . $_SERVER['QUERY_STRING'],
    ]);
    header('Location: ' . APP_URL . '/id/auth/login.php?' . $qs);
    exit;
}

$app_name    = trim($_GET['app_name'] ?? '');
$redirect_to = trim($_GET['redirect']  ?? '');

// Validate the redirect URL is on our own origin
function _transit_valid_url(string $url): string {
    if ($url === '') return '';
    $allowed_host = parse_url(APP_URL, PHP_URL_HOST);
    $parsed       = parse_url($url);
    $host         = $parsed['host'] ?? '';
    // Allow same-host or relative paths
    if ($host === $allowed_host || $host === '') return $url;
    return '';
}

$safe_redirect = _transit_valid_url($redirect_to);
if ($safe_redirect === '') {
    // Nothing valid to redirect to — just go to launcher
    $safe_redirect = APP_URL . '/';
}

if ($app_name === '') $app_name = 'the app';

// Load banner settings (same as login.php)
$login_banner_img = '';
$login_banner_bg  = '';
try {
    $rows = db()->query("SELECT `key`,`value` FROM settings WHERE `key` = 'login_banner'")->fetchAll();
    $cfg  = array_column($rows, 'value', 'key');
    $raw  = $cfg['login_banner'] ?? '';
    if ($raw !== '' && (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://'))) {
        $login_banner_img = $raw;
    } elseif ($raw !== '' && preg_match('/^[\w\s#(),.\/%\-+:\']+$/', $raw)) {
        $login_banner_bg = $raw;
    }
} catch (Throwable $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Launching <?= htmlspecialchars($app_name) ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/shared/assets/style.css">
  <?php if ($login_banner_bg): ?>
  <style>.login-banner { background: <?= $login_banner_bg ?> !important; }</style>
  <?php endif; ?>
  <style>
    /* Spinning loader ring */
    .transit-spinner {
      width: 3.25rem;
      height: 3.25rem;
      border-radius: 50%;
      border: 3px solid rgba(59,130,246,0.2);
      border-top-color: var(--primary, #3b82f6);
      animation: spin 0.9s linear infinite;
      margin: 0 auto 1.5rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .transit-panel {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .transit-app-icon {
      width: 3rem;
      height: 3rem;
      border-radius: 10px;
      background: rgba(59,130,246,0.1);
      border: 1px solid rgba(59,130,246,0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.25rem;
    }

    .transit-app-icon .material-symbols-outlined {
      font-size: 1.5rem;
      color: var(--primary, #3b82f6);
    }

    .transit-dot-row {
      display: flex;
      gap: 0.375rem;
      justify-content: center;
      margin-top: 1.5rem;
    }

    .transit-dot {
      width: 0.375rem;
      height: 0.375rem;
      border-radius: 50%;
      background: var(--primary, #3b82f6);
      opacity: 0.3;
      animation: dot-pulse 1.2s ease-in-out infinite;
    }

    .transit-dot:nth-child(2) { animation-delay: 0.2s; }
    .transit-dot:nth-child(3) { animation-delay: 0.4s; }

    @keyframes dot-pulse {
      0%, 80%, 100% { opacity: 0.3; transform: scale(1); }
      40%           { opacity: 1;   transform: scale(1.3); }
    }
  </style>
  <script>
    /* Apply saved theme before first paint */
    (function(){
      var t = localStorage.getItem('cg-theme') ||
              (window.matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
</head>
<body>

<div class="login-page">

  <!-- ── Left banner (3/4) ─────────────────────── -->
  <div class="login-banner">
    <?php if ($login_banner_img): ?>
    <img src="<?= htmlspecialchars($login_banner_img) ?>" alt="" class="login-banner-img">
    <?php endif; ?>
  </div>

  <!-- ── Right panel (1/4) ─────────────────────── -->
  <div class="login-form-panel">

    <div class="login-form-logo">
      <div class="login-form-logo-icon">
        <span class="material-symbols-outlined">rocket_launch</span>
      </div>
      <div>
        <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars(APP_NAME) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);font-weight:400">App Launcher</div>
      </div>
    </div>

    <div class="transit-panel">
      <div class="transit-spinner"></div>

      <h2 style="font-size:1.375rem;font-weight:700;letter-spacing:-0.02em;color:var(--text);margin-bottom:0.375rem">
        Logging you in
      </h2>
      <p style="font-size:0.9375rem;color:var(--text-muted);line-height:1.5;margin:0">
        Opening <strong><?= htmlspecialchars($app_name) ?></strong>&hellip;
      </p>

      <div class="transit-dot-row">
        <span class="transit-dot"></span>
        <span class="transit-dot"></span>
        <span class="transit-dot"></span>
      </div>
    </div>

    <div class="login-form-footer">
      Not redirecting?
      <a href="<?= htmlspecialchars($safe_redirect) ?>">Click here</a>.
    </div>

  </div>

</div>

<script>
  // Auto-redirect after a brief moment to show the loading screen
  setTimeout(function () {
    window.location.href = <?= json_encode($safe_redirect) ?>;
  }, 3000);
</script>

</body>
</html>
