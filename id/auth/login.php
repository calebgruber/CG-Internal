<?php
/**
 * id/auth/login.php
 * Central SSO login page — modern split layout.
 * URL: internal.calebgruber.me/id/auth/login.php
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';

$app_slug   = trim($_GET['app'] ?? '');
$return_url = trim($_GET['return_url'] ?? '');

// Read banner background from settings
$login_banner_img             = '';
$login_banner_bg              = '';
$login_banner_overlay_color   = '';
$login_banner_overlay_opacity = '';
try {
    $rows = db()->query("SELECT `key`,`value` FROM settings WHERE `key` IN
        ('login_banner','login_banner_overlay_color','login_banner_overlay_opacity')")->fetchAll();
    $cfg  = array_column($rows, 'value', 'key');
    $raw_img = $cfg['login_banner'] ?? '';
    // login_banner can be an image URL (http/https) or a CSS gradient/color
    if ($raw_img !== '' && (str_starts_with($raw_img, 'http://') || str_starts_with($raw_img, 'https://'))) {
        $login_banner_img = $raw_img;
    } elseif ($raw_img !== '' && preg_match('/^[\w\s#(),.\/%\-+:\']+$/', $raw_img)) {
        $login_banner_bg = $raw_img;
    }
    $login_banner_overlay_color   = $cfg['login_banner_overlay_color']   ?? '';
    $login_banner_overlay_opacity = $cfg['login_banner_overlay_opacity'] ?? '';
} catch (Throwable $e) { /* ignore */ }

// If already logged in → bounce to app launcher
if (is_logged_in()) {
    $dest = valid_return_url($return_url) ?: APP_URL . '/';
    header('Location: ' . $dest);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, email, password_hash, display_name, role, is_active
             FROM users WHERE (username = ? OR email = ?) LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(random_int(200000, 400000));
            $error = 'Invalid username or password.';
        } elseif (!$user['is_active']) {
            $error = 'Your account is disabled. Contact your administrator.';
        } else {
            login_user($user);
            $dest = valid_return_url($return_url) ?: APP_URL . '/';
            header('Location: ' . $dest);
            exit;
        }
    }
}

function valid_return_url(string $url): string {
    if ($url === '') return '';
    $parsed  = parse_url($url);
    $host    = $parsed['host'] ?? '';
    $allowed = parse_url(APP_URL, PHP_URL_HOST);
    return ($host === $allowed || $host === '') ? $url : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Sign In | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/shared/assets/style.css">
  <?php if ($login_banner_bg): ?>
  <style>.login-banner { background: <?= $login_banner_bg ?> !important; }</style>
  <?php endif; ?>
  <script>
    (function(){var t=localStorage.getItem('cg-theme')||
    (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);})();
    document.addEventListener('DOMContentLoaded',function(){
      var l=document.getElementById('page-loader'); if(l) l.classList.add('pg-done');
    });
  </script>
</head>
<body>
<div id="page-loader"></div>

<div class="login-page">

  <!-- ── Left banner (3/4) ─────────────────────── -->
  <div class="login-banner">
    <?php if ($login_banner_img): ?>
    <img src="<?= htmlspecialchars($login_banner_img) ?>" alt="" class="login-banner-img">
    <?php endif; ?>
    <?php if ($login_banner_overlay_color !== ''): ?>
    <?php
      $ov_color   = preg_replace('/[^#a-zA-Z0-9(),.\s%]/', '', $login_banner_overlay_color);
      $ov_opacity = is_numeric($login_banner_overlay_opacity)
          ? max(0, min(1, (float)$login_banner_overlay_opacity))
          : 0.5;
    ?>
    <div class="login-banner-overlay" style="background:<?= $ov_color ?>;opacity:<?= $ov_opacity ?>;"></div>
    <?php endif; ?>
  </div>

  <!-- ── Right form panel (1/4) ───────────────── -->
  <div class="login-form-panel">

    <button id="theme-toggle" class="topbar-btn login-theme-toggle" title="Toggle theme">
      <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
    </button>

    <div class="login-form-logo">
      <div class="login-form-logo-icon">
        <span class="material-symbols-outlined">lock</span>
      </div>
      <div>
        <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars(APP_NAME) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);font-weight:400">Secure Access</div>
      </div>
    </div>

    <h2>Welcome back</h2>
    <p class="login-sub">
      <?= $app_slug ? 'Sign in to continue to <strong>' . htmlspecialchars(strtoupper($app_slug)) . '</strong>' : 'Sign in to your account' ?>
    </p>

    <?php if ($error): ?>
    <div class="alerts" style="margin-bottom:1.25rem">
      <div class="alert alert-danger" style="--alert-accent:#ef4444;--alert-accent-rgb:239,68,68;--alert-text-on-solid:#ffffff">
        <span class="material-symbols-outlined">error</span>
        <span class="alert-text"><?= htmlspecialchars($error) ?></span>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="login-form" autocomplete="on">
      <?= csrf_field() ?>
      <?php if ($app_slug !== ''): ?>
      <input type="hidden" name="app" value="<?= htmlspecialchars($app_slug) ?>">
      <?php endif; ?>
      <?php if ($return_url !== ''): ?>
      <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="username">Username or Email</label>
        <div class="input-icon-wrap">
          <span class="material-symbols-outlined input-icon">person</span>
          <input type="text" id="username" name="username" class="form-control form-control-icon"
                 autocomplete="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 autofocus required placeholder="Enter your username or email">
        </div>
      </div>

      <div class="form-group">
        <label for="pwd">Password</label>
        <div class="input-icon-wrap">
          <span class="material-symbols-outlined input-icon">lock</span>
          <input type="password" id="pwd" name="password" class="form-control form-control-icon"
                 autocomplete="current-password" required placeholder="Enter your password">
        </div>
      </div>

      <div class="form-actions" style="margin-top:1.5rem">
        <button type="submit" class="btn btn-primary w-full" style="padding:.625rem 1rem;font-size:.9375rem">
          <span class="material-symbols-outlined">login</span>
          Sign In
        </button>
      </div>
    </form>

    <div class="login-form-footer">
      Forgot your password? Contact
      <a href="mailto:admin@calebgruber.me">your administrator</a>.
    </div>

  </div><!-- .login-form-panel -->

</div><!-- .login-page -->

<script src="<?= APP_URL ?>/shared/assets/app.js"></script>
</body>
</html>
