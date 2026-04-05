-- =========================================================
-- CG Internal – Database Schema
-- Run via: mysql -u root -p cg_internal < db/schema.sql
-- Or apply through /admin/migrations.php
-- =========================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Users ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(255) NOT NULL DEFAULT '',
    phone         VARCHAR(30)  DEFAULT NULL,
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login    DATETIME     DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Apps (SSO-registered applications) ───────────────────
CREATE TABLE IF NOT EXISTS apps (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    icon        VARCHAR(80)  NOT NULL DEFAULT 'apps',
    url         VARCHAR(500) NOT NULL,
    description TEXT         DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── User ↔ App access ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_app_access (
    user_id INT UNSIGNED NOT NULL,
    app_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, app_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (app_id)  REFERENCES apps  (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SSO tokens ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sso_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(80)  NOT NULL UNIQUE,
    app_slug   VARCHAR(80)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── System settings (key-value) ───────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(120) PRIMARY KEY,
    `value`     TEXT         DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── System alerts (shown on dashboards) ───────────────────
CREATE TABLE IF NOT EXISTS system_alerts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text        TEXT         NOT NULL,
    type        ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
    icon        VARCHAR(80)  NOT NULL DEFAULT 'info',
    dismissible TINYINT(1)   NOT NULL DEFAULT 1,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── DB migrations tracking ────────────────────────────────
CREATE TABLE IF NOT EXISTS migrations (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EDU Classes ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS edu_classes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(255) NOT NULL,
    code        VARCHAR(50)  DEFAULT NULL,
    instructor  VARCHAR(255) DEFAULT NULL,
    color       VARCHAR(7)   NOT NULL DEFAULT '#3b82f6',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EDU Assignments ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS edu_assignments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    class_id    INT UNSIGNED DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT         DEFAULT NULL,
    due_date    DATETIME     DEFAULT NULL,
    status      ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    notify_sent TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users       (id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES edu_classes (id) ON DELETE SET NULL,
    INDEX idx_due (due_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EDU Tasks ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS edu_tasks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    assignment_id INT UNSIGNED DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT         DEFAULT NULL,
    due_date    DATETIME     DEFAULT NULL,
    status      ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    notify_sent TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users            (id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES edu_assignments  (id) ON DELETE SET NULL,
    INDEX idx_due (due_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EDU Notes ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS edu_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    class_id    INT UNSIGNED DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    content     LONGTEXT     DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users       (id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES edu_classes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EDU Schedule (recurring class slots) ─────────────────
CREATE TABLE IF NOT EXISTS edu_schedule (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    class_id     INT UNSIGNED NOT NULL,
    day_of_week  TINYINT      NOT NULL COMMENT '0=Sun 1=Mon … 6=Sat',
    start_time   TIME         NOT NULL,
    end_time     TIME         NOT NULL,
    location     VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users       (id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES edu_classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- ── Seed default settings ────────────────────────────────
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('maintenance_mode', '0'),
    ('site_name',        'CG Internal'),
    ('smtp_host',        ''),
    ('smtp_port',        '587'),
    ('smtp_user',        ''),
    ('smtp_pass',        ''),
    ('smtp_secure',      'tls'),
    ('mail_from',        'no-reply@calebgruber.me'),
    ('mail_from_name',   'CG Internal'),
    ('twilio_sid',       ''),
    ('twilio_token',     ''),
    ('twilio_from',      ''),
    ('notify_email',     ''),
    ('notify_phone',     ''),
    ('remind_days_before', '3');

-- ── Seed default apps ────────────────────────────────────
INSERT IGNORE INTO apps (name, slug, icon, url, description, sort_order) VALUES
    ('EDU Hub',       'edu',   'school',      '/edu/',       'Academic tracking hub',    1),
    ('ID Admin',      'id',    'manage_accounts', '/id/admin/', 'Identity & access admin',  2),
    ('System Admin',  'admin', 'admin_panel_settings', '/admin/', 'Global system administration', 3);

-- Record this migration as applied
INSERT IGNORE INTO migrations (name) VALUES ('001_initial_schema');
