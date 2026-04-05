-- Migration 005: Add default check items to all stations that have none
-- Run via admin/migrations.php

SET NAMES utf8mb4;

INSERT INTO wmata_station_checks (station_id, check_name, sort_order)
SELECT s.id, pts.check_name, pts.sort_order
FROM wmata_stations s
CROSS JOIN (
    SELECT  1 AS sort_order, 'Platform – Structure & Surface'       AS check_name UNION ALL
    SELECT  2,               'Platform – Edge Safety'               UNION ALL
    SELECT  3,               'Platform – Lighting'                  UNION ALL
    SELECT  4,               'Platform – Signage & Wayfinding'      UNION ALL
    SELECT  5,               'Entrances – Canopies & Doors'         UNION ALL
    SELECT  6,               'Entrances – Accessible Ramps'         UNION ALL
    SELECT  7,               'Elevators – Functionality'            UNION ALL
    SELECT  8,               'Elevators – Interior Finishes'        UNION ALL
    SELECT  9,               'Escalators – Treads & Handrails'      UNION ALL
    SELECT 10,               'Escalators – Mechanical Components'   UNION ALL
    SELECT 11,               'Signage – Station ID Pylons'          UNION ALL
    SELECT 12,               'Signage – Directional & Regulatory'   UNION ALL
    SELECT 13,               'Plants & Landscaping'                 UNION ALL
    SELECT 14,               'Tunnel – Walls & Ceiling'             UNION ALL
    SELECT 15,               'Tunnel – Track & Roadbed'             UNION ALL
    SELECT 16,               'Tunnel – Lighting'                    UNION ALL
    SELECT 17,               'Fare Gates & Turnstiles'              UNION ALL
    SELECT 18,               'Ticket Machines (SmarTrip)'           UNION ALL
    SELECT 19,               'Customer Information Displays (CID)'  UNION ALL
    SELECT 20,               'Intercoms & Emergency Phones'         UNION ALL
    SELECT 21,               'Closed-Circuit TV (CCTV)'             UNION ALL
    SELECT 22,               'Restrooms (if present)'               UNION ALL
    SELECT 23,               'Manager's Kiosk / Station Office'     UNION ALL
    SELECT 24,               'Mechanical Rooms & Utilities'         UNION ALL
    SELECT 25,               'Fire Suppression & Safety Equipment'
) pts
WHERE NOT EXISTS (
    SELECT 1 FROM wmata_station_checks c WHERE c.station_id = s.id
);

-- Add sort_order column if it doesn't already exist
ALTER TABLE wmata_station_checks
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER notes;

INSERT IGNORE INTO migrations (name) VALUES ('005_wmata_default_checks');
