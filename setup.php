<?php
/**
 * setup.php – First-run setup wizard
 * Creates the initial admin account and runs the schema.
 * DELETE THIS FILE AFTER SETUP.
 */

define('APP_NAME', 'CG Internal');
define('APP_URL',  'https://internal.calebgruber.me');

// ── Check if already set up (tables exist) ───────────────
function db_connect(): ?PDO {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: 3306;
    $name = getenv('DB_NAME') ?: 'cg_internal';
    $user = getenv('DB_USER') ?: 'cg_user';
    $pass = getenv('DB_PASS') ?: 'change_me';
    try {
        return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        return null;
    }
}

$pdo = db_connect();
$db_ok = $pdo !== null;

$already_setup = false;
if ($db_ok) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $already_setup = $count > 0;
    } catch (PDOException $e) { /* tables don't exist yet */ }
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_setup) {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $display  = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$pdo) {
        $error = 'Cannot connect to database. Check DB_HOST, DB_NAME, DB_USER, DB_PASS environment variables.';
    } elseif ($username === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            // Run schema
            $schema = file_get_contents(__DIR__ . '/db/schema.sql');
            // Split by semicolons (crude but works for our schema)
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $stmt) {
                if ($stmt !== '') $pdo->exec($stmt);
            }

            // Create admin user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO users (username,email,password_hash,display_name,role,is_active)
                 VALUES (?,?,?,?,\'admin\',1) ON DUPLICATE KEY UPDATE id=id'
            )->execute([$username, $email, $hash, $display ?: $username]);

            $success = 'Setup complete! Your admin account has been created.';
        } catch (PDOException $e) {
            $error = 'Setup error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/shared/assets/style.css">
  <script>
    (function(){var t=localStorage.getItem('cg-theme')||
    (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);})();
  </script>
</head>
<body>

<div class="login-page">
  <div class="login-card" style="max-width:480px">
    <div class="login-header">
      <span class="material-symbols-outlined logo-icon">settings</span>
      <h1><?= APP_NAME ?> – Setup</h1>
      <p>First-run installation wizard</p>
    </div>
    <div class="login-body">

      <?php if ($success): ?>
      <div class="alerts">
        <div class="alert alert-success">
          <span class="material-symbols-outlined">check_circle</span>
          <span class="alert-text"><?= htmlspecialchars($success) ?></span>
        </div>
      </div>
      <p style="text-align:center;margin-top:1rem">
        <strong>⚠ Delete <code>setup.php</code> from your server immediately.</strong>
      </p>
      <div class="form-actions" style="justify-content:center;margin-top:1rem">
        <a href="<?= APP_URL ?>/id/auth/login" class="btn btn-primary">
          <span class="material-symbols-outlined">login</span> Go to Login
        </a>
      </div>

      <?php elseif ($already_setup): ?>
      <div class="alerts">
        <div class="alert alert-warning">
          <span class="material-symbols-outlined">warning</span>
          <span class="alert-text">Setup has already been completed. Delete this file.</span>
        </div>
      </div>
      <div class="form-actions" style="justify-content:center;margin-top:1rem">
        <a href="<?= APP_URL ?>/id/auth/login" class="btn btn-primary">
          <span class="material-symbols-outlined">login</span> Go to Login
        </a>
      </div>

      <?php else: ?>

      <?php if ($error): ?>
      <div class="alerts">
        <div class="alert alert-danger">
          <span class="material-symbols-outlined">error</span>
          <span class="alert-text"><?= htmlspecialchars($error) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$db_ok): ?>
      <div class="alerts">
        <div class="alert alert-warning">
          <span class="material-symbols-outlined">warning</span>
          <span class="alert-text">Cannot connect to database. Please set DB environment variables and reload.</span>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label for="username">Admin Username</label>
          <input type="text" id="username" name="username" class="form-control"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label for="email">Admin Email</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="display_name">Display Name</label>
          <input type="text" id="display_name" name="display_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="password">Password (min 8 chars)</label>
          <input type="password" id="password" name="password" class="form-control" required>
          <div class="progress-bar mt-1" id="pw-strength-bar">
            <div class="progress-fill" style="width:0"></div>
          </div>
        </div>
        <div class="form-group">
          <label for="password2">Confirm Password</label>
          <input type="password" id="password2" name="password2" class="form-control" required>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary w-full" <?= !$db_ok ? 'disabled' : '' ?>>
            <span class="material-symbols-outlined">rocket_launch</span>
            Install & Create Admin Account
          </button>
        </div>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/shared/assets/app.js"></script>
</body>
</html>
