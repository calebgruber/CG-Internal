<?php
/**
 * shared/db.php
 * Returns a singleton PDO instance.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Fetch a single setting value from the settings table.
 * Returns $default if the key does not exist.
 */
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query('SELECT `key`, `value` FROM settings')->fetchAll();
            $cache = array_column($rows, 'value', 'key');
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Upsert a setting.
 */
function set_setting(string $key, string $value): void {
    db()->prepare(
        'INSERT INTO settings (`key`,`value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()'
    )->execute([$key, $value]);
}

/**
 * Check whether the application is in maintenance mode.
 * Priority: DB > constant MAINTENANCE_MODE.
 */
function is_maintenance(): bool {
    $db_val = setting('maintenance_mode', '0');
    return $db_val === '1' || (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE);
}
