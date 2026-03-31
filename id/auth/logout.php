<?php
/**
 * id/auth/logout.php
 * Destroys the current session and redirects to the login page.
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';

logout_user();

header('Location: ' . APP_URL . '/id/auth/login.php');
exit;
