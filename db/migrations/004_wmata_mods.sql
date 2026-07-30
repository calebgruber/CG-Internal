-- Migration 004: WMATA Mods tracking + mod file-type support
-- Run via admin/migrations.php

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── WMATA Mods ────────────────────────────────────────────────
-- Stores Minecraft mods used in the WMATA recreation project.
-- Icons are fetched from the Modrinth API and cached in icon_url.
CREATE TABLE IF NOT EXISTS wmata_mods (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modrinth_slug   VARCHAR(120)  NOT NULL COMMENT 'Modrinth project slug or ID',
    name            VARCHAR(255)  NOT NULL,
    mod_version     VARCHAR(80)   NOT NULL,
    mc_version      VARCHAR(80)   NOT NULL,
    download_url    VARCHAR(500)  NOT NULL,
    icon_url        VARCHAR(500)  DEFAULT NULL,
    description     TEXT          DEFAULT NULL,
    notes           TEXT          DEFAULT NULL,
    sort_order      INT           NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (modrinth_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Extend file type enums to include 'mod' ───────────────────
-- wmata_global_files
ALTER TABLE wmata_global_files
    MODIFY COLUMN file_type
        ENUM('texture','model','diagram','screenshot','reference','mod','other')
        NOT NULL DEFAULT 'other';

-- wmata_station_files
ALTER TABLE wmata_station_files
    MODIFY COLUMN file_type
        ENUM('texture','model','diagram','screenshot','mod','other')
        NOT NULL DEFAULT 'other';

SET foreign_key_checks = 1;

INSERT IGNORE INTO migrations (name) VALUES ('004_wmata_mods');
