<?php
/**
 * shared/config.php
 * Central configuration – loaded by every page.
 * Override any value in /config.local.php (gitignored).
 */

// ── Database ─────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     getenv('DB_PORT')     ?: 3306);
define('DB_NAME',     getenv('DB_NAME')     ?: 'cg_internal');
define('DB_USER',     getenv('DB_USER')     ?: 'cg_user');
define('DB_PASS',     getenv('DB_PASS')     ?: 'change_me');
define('DB_CHARSET',  'utf8mb4');

// ── Application ───────────────────────────────────
define('APP_NAME',    'CG Internal');
define('APP_URL',     'https://internal.calebgruber.me');
define('APP_VERSION', '1.0.0');

// ── Session ───────────────────────────────────────
define('SESSION_NAME',    'cg_sess');
define('SESSION_LIFETIME', 7200);   // 2 hours (seconds)
define('CSRF_TOKEN_KEY',   'csrf_token');

// ── SSO ───────────────────────────────────────────
define('SSO_TOKEN_TTL',  300);      // 5 minutes until token expires
define('SSO_SECRET',     getenv('SSO_SECRET') ?: 'replace_with_random_secret_min_32_chars');

// ── Email (SMTP via PHP mail() or SMTP) ───────────
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'no-reply@calebgruber.me');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'CG Internal');
define('SMTP_HOST',      getenv('SMTP_HOST')      ?: '');
define('SMTP_PORT',      getenv('SMTP_PORT')      ?: 587);
define('SMTP_USER',      getenv('SMTP_USER')      ?: '');
define('SMTP_PASS',      getenv('SMTP_PASS')      ?: '');
define('SMTP_SECURE',    getenv('SMTP_SECURE')    ?: 'tls');   // tls | ssl | ''

// ── Twilio (SMS) ─────────────────────────────────
define('TWILIO_SID',   getenv('TWILIO_SID')   ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM',  getenv('TWILIO_FROM')  ?: '');

// ── App-level feature flags ───────────────────────
define('MAINTENANCE_MODE', false);   // overridden by DB settings at runtime

// ── Local overrides (never committed) ────────────
$local_config = __DIR__ . '/../config.local.php';
if (file_exists($local_config)) {
    require_once $local_config;
}
