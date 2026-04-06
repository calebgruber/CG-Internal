-- Migration 005: Add login banner overlay settings
-- Adds two rows to the settings table for the banner overlay color and opacity.
-- Run via admin/migrations.php

SET NAMES utf8mb4;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('login_banner_overlay_color',   ''),
    ('login_banner_overlay_opacity', '0');

INSERT IGNORE INTO migrations (name) VALUES ('005_login_banner_overlay');
