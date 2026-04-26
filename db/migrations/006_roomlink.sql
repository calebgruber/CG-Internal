-- Migration 006: RoomLink smart home hub
-- Tables: roomlink_wled_controllers, roomlink_transit_destinations, roomlink_transit_agencies, roomlink_eink_state

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roomlink_wled_controllers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    port        SMALLINT UNSIGNED NOT NULL DEFAULT 80,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    notes       TEXT DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roomlink_transit_agencies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    short_name  VARCHAR(30)  NOT NULL,
    color       VARCHAR(7)   NOT NULL DEFAULT '#3b82f6',
    text_color  VARCHAR(7)   NOT NULL DEFAULT '#ffffff',
    gtfs_rt_url VARCHAR(500) DEFAULT NULL COMMENT 'GTFS-RT trip updates feed URL',
    api_key     VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roomlink_transit_agencies (name, short_name, color, text_color, sort_order) VALUES
    ('NJ Transit', 'NJT', '#003087', '#ffffff', 1),
    ('MTA Metro-North', 'MNR', '#0E71B3', '#ffffff', 2),
    ('MTA Long Island Rail Road', 'LIRR', '#00305A', '#ffffff', 3),
    ('Amtrak', 'AMT', '#1D6BAE', '#ffffff', 4),
    ('MTA Subway', 'MTA', '#0039A6', '#ffffff', 5);

CREATE TABLE IF NOT EXISTS roomlink_transit_destinations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(120) NOT NULL COMMENT 'Display name for this destination',
    from_station VARCHAR(120) NOT NULL,
    to_station  VARCHAR(120) NOT NULL,
    agency_id   INT UNSIGNED DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    FOREIGN KEY (agency_id) REFERENCES roomlink_transit_agencies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roomlink_eink_state (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    current_tab     VARCHAR(60) NOT NULL DEFAULT 'transit' COMMENT 'Which tab: transit, clock, weather, custom',
    display_mode    VARCHAR(30) NOT NULL DEFAULT 'normal' COMMENT 'normal, inverted, red-highlight',
    custom_text     TEXT DEFAULT NULL,
    last_updated    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_pi_poll    DATETIME DEFAULT NULL COMMENT 'When the Pi last polled the API'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roomlink_eink_state (id, current_tab) VALUES (1, 'transit');

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('rl_transit_refresh_sec',  '30'),
    ('rl_eink_auto_refresh',    '1'),
    ('rl_mta_api_key',          ''),
    ('rl_njt_api_key',          ''),
    ('rl_default_origin',       ''),
    ('rl_wled_global_ip',       '');

INSERT IGNORE INTO apps (name, slug, icon, url, description, sort_order) VALUES
    ('RoomLink', 'roomlink', 'home_iot_device', '/roomlink/', 'Dorm room smart hub — lights, transit, e-ink display', 5);

INSERT IGNORE INTO migrations (name) VALUES ('006_roomlink');
