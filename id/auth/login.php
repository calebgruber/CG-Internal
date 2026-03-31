<?php
/**
 * id/auth/login.php
 * Central SSO login page.
 * URL: internal.calebgruber.me/id/auth/login.php
 *
 * Flow:
 *  GET  → show login form (with optional ?app=slug&return_url=...)
 *  POST → validate credentials → set session → redirect
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';

$app_slug   = trim($_GET['app'] ?? '');
$return_url = trim($_GET['return_url'] ?? '');

// If already logged in and has access → bounce straight back
if (is_logged_in()) {
    $dest = valid_return_url($return_url) ?: APP_URL . '/id/admin/';
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
            'SELECT id, username, email, password_hash, display_name, role, is_active, phone
             FROM users WHERE (username = ? OR email = ?) LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Introduce a small delay to slow brute-force attempts
            usleep(random_int(200000, 400000));
            $error = 'Invalid username or password.';
        } elseif (!$user['is_active']) {
            $error = 'Your account is disabled. Contact your administrator.';
        } else {
            login_user($user);

            // Determine redirect target
            $dest = APP_URL . '/id/admin/';
            if ($app_slug !== '' && valid_return_url($return_url)) {
                $dest = $return_url;
            } elseif (valid_return_url($return_url)) {
                $dest = $return_url;
            }

            header('Location: ' . $dest);
            exit;
        }
    }
}

/**
 * Only allow redirects to our own domain.
 */
function valid_return_url(string $url): string {
    if ($url === '') return '';
    $parsed = parse_url($url);
    $host   = $parsed['host'] ?? '';
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
  <script>
    (function(){var t=localStorage.getItem('cg-theme')||
    (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);})();
  </script>
</head>
<body>

<div class="login-page">
  <div class="login-card">

    <div class="login-header">
      <span class="material-symbols-outlined logo-icon">lock</span>
      <h1><?= APP_NAME ?></h1>
      <p>Sign in to continue<?= $app_slug ? ' to ' . htmlspecialchars(strtoupper($app_slug)) : '' ?></p>
    </div>

    <div class="login-body">
      <?php if ($error): ?>
      <div class="alerts">
        <div class="alert alert-danger">
          <span class="material-symbols-outlined">error</span>
          <span class="alert-text"><?= htmlspecialchars($error) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <?php if ($app_slug !== ''): ?>
        <input type="hidden" name="app" value="<?= htmlspecialchars($app_slug) ?>">
        <?php endif; ?>
        <?php if ($return_url !== ''): ?>
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
        <?php endif; ?>

        <div class="form-group">
          <label for="username">Username or Email</label>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control"
            autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autofocus
            required>
        </div>

        <div class="form-group">
          <label for="pwd">Password</label>
          <input
            type="password"
            id="pwd"
            name="password"
            class="form-control"
            autocomplete="current-password"
            required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary w-full">
            <span class="material-symbols-outlined">login</span>
            Sign In
          </button>
        </div>
      </form>

      <p style="margin-top:1.25rem;text-align:center;font-size:.8125rem;color:var(--text-muted)">
        Forgot your password? Contact <a href="mailto:admin@calebgruber.me">your administrator</a>.
      </p>
    </div>

  </div>
</div>

<div style="position:fixed;bottom:1rem;right:1rem;">
  <button id="theme-toggle" class="btn btn-ghost btn-sm" title="Toggle theme">
    <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
  </button>
</div>

<script src="<?= APP_URL ?>/shared/assets/app.js"></script>
</body>
</html>
