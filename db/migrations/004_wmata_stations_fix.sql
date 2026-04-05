-- Migration 004: Add missing WMATA stations, wmata_url column, and fill station WMATA URLs
-- Run via admin/migrations.php

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Add wmata_url column ──────────────────────────────────────
ALTER TABLE wmata_stations
    ADD COLUMN IF NOT EXISTS wmata_url VARCHAR(500) DEFAULT NULL
        COMMENT 'URL on wmata.com rider-guide/stations/' AFTER google_earth_url;

-- ── Add missing stations ──────────────────────────────────────
-- 6 confirmed missing stations (90 → 96; note 2 more are unconfirmed pending research)

INSERT IGNORE INTO wmata_stations (name, abbreviation, lat, lng) VALUES
-- Blue/Orange/Silver: Federal Center SW (between L'Enfant Plaza and Capitol South)
('Federal Center SW',     'FCT', 38.8892600, -77.0165600),
-- Green: Anacostia (between Congress Heights and Navy Yard-Ballpark)
('Anacostia',             'ANA', 38.8624100, -76.9951600),
-- Blue/Yellow: Potomac Yard (opened 2023, between Braddock Road and Reagan Airport)
('Potomac Yard',          'PTY', 38.8352900, -77.0598600),
-- Orange/Silver only: between Benning Road and Capitol Heights
('Minnesota Avenue',      'MNA', 38.8950200, -76.9437100),
('Deanwood',              'DEA', 38.8963800, -76.9342800),
('Cheverly',              'CHV', 38.8967200, -76.9199500);

-- ── Station-line associations for new stations ────────────────

-- Federal Center SW: Blue, Orange, Silver
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'FCT'
  AND l.abbreviation IN ('BL','OR','SV');

-- Anacostia: Green only
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'ANA'
  AND l.abbreviation = 'GR';

-- Potomac Yard: Blue and Yellow
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'PTY'
  AND l.abbreviation IN ('BL','YL');

-- Minnesota Avenue: Orange and Silver
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'MNA'
  AND l.abbreviation IN ('OR','SV');

-- Deanwood: Orange and Silver
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'DEA'
  AND l.abbreviation IN ('OR','SV');

-- Cheverly: Orange and Silver
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE s.abbreviation = 'CHV'
  AND l.abbreviation IN ('OR','SV');

-- ── Fix Loudoun Gateway latitude (was 28.xxx, should be 39.xxx) ──
UPDATE wmata_stations SET lat = 38.9930000 WHERE abbreviation = 'LDG' AND lat < 30;

-- ── Fill wmata_url for all stations ──────────────────────────
-- Format: https://www.wmata.com/rider-guide/stations/{slug}.cfm

-- Red Line
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/shady-grove.cfm'                       WHERE abbreviation = 'SHD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/rockville.cfm'                         WHERE abbreviation = 'ROK';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/twinbrook.cfm'                         WHERE abbreviation = 'TWI';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/white-flint.cfm'                       WHERE abbreviation = 'WHT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/grosvenor-strathmore.cfm'              WHERE abbreviation = 'GRO';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/medical-center.cfm'                    WHERE abbreviation = 'MED';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/bethesda.cfm'                          WHERE abbreviation = 'BET';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/friendship-heights.cfm'                WHERE abbreviation = 'FRN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/tenleytown-au.cfm'                     WHERE abbreviation = 'TEN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/van-ness-udc.cfm'                      WHERE abbreviation = 'VAN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/cleveland-park.cfm'                    WHERE abbreviation = 'CLE';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/woodley-park-zoo-adams-morgan.cfm'     WHERE abbreviation = 'WOD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/dupont-circle.cfm'                     WHERE abbreviation = 'DUP';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/farragut-north.cfm'                    WHERE abbreviation = 'FRG';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/metro-center.cfm'                      WHERE abbreviation = 'MTC';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/gallery-pl-chinatown.cfm'              WHERE abbreviation = 'GAL';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/judiciary-square.cfm'                  WHERE abbreviation = 'JUD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/union-station.cfm'                     WHERE abbreviation = 'UNI';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/noma-gallaudet.cfm'                    WHERE abbreviation = 'NOM';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/rhode-island-ave-brentwood.cfm'        WHERE abbreviation = 'RIA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/brookland-cua.cfm'                     WHERE abbreviation = 'BRK';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/fort-totten.cfm'                       WHERE abbreviation = 'FTT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/takoma.cfm'                            WHERE abbreviation = 'TAK';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/silver-spring.cfm'                     WHERE abbreviation = 'SVS';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/forest-glen.cfm'                       WHERE abbreviation = 'FGL';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/wheaton.cfm'                           WHERE abbreviation = 'WHE';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/glenmont.cfm'                          WHERE abbreviation = 'GLE';
-- Silver Line
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/mclean.cfm'                           WHERE abbreviation = 'MCL';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/tysons-corner.cfm'                     WHERE abbreviation = 'TYS';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/greensboro.cfm'                        WHERE abbreviation = 'GSB';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/spring-hill.cfm'                       WHERE abbreviation = 'SPH';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/wiehle-reston-east.cfm'                WHERE abbreviation = 'WIE';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/reston-town-center.cfm'                WHERE abbreviation = 'RTC';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/herndon.cfm'                           WHERE abbreviation = 'HER';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/innovation-center.cfm'                 WHERE abbreviation = 'INN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/dulles-airport.cfm'                    WHERE abbreviation = 'IAD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/loudoun-gateway.cfm'                   WHERE abbreviation = 'LDG';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/ashburn.cfm'                           WHERE abbreviation = 'ASH';
-- Orange/Silver shared VA
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/east-falls-church.cfm'                 WHERE abbreviation = 'EFC';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/west-falls-church.cfm'                 WHERE abbreviation = 'WFC';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/dunn-loring-merrifield.cfm'            WHERE abbreviation = 'DLN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/vienna-fairfax-gmu.cfm'                WHERE abbreviation = 'VIN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/ballston-mu.cfm'                       WHERE abbreviation = 'BAL';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/virginia-square-gmu.cfm'               WHERE abbreviation = 'VSQ';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/clarendon.cfm'                         WHERE abbreviation = 'CLA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/court-house.cfm'                       WHERE abbreviation = 'CTH';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/rosslyn.cfm'                           WHERE abbreviation = 'ROS';
-- Shared DC trunk
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/foggy-bottom-gwu.cfm'                  WHERE abbreviation = 'FOG';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/farragut-west.cfm'                     WHERE abbreviation = 'FGW';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/mcpherson-square.cfm'                  WHERE abbreviation = 'MCP';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/federal-triangle.cfm'                  WHERE abbreviation = 'FDT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/smithsonian.cfm'                       WHERE abbreviation = 'SMI';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/lenfant-plaza.cfm'                     WHERE abbreviation = 'LEN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/federal-center-sw.cfm'                 WHERE abbreviation = 'FCT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/capitol-south.cfm'                     WHERE abbreviation = 'CAP';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/eastern-market.cfm'                    WHERE abbreviation = 'EMK';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/potomac-ave.cfm'                       WHERE abbreviation = 'POT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/stadium-armory.cfm'                    WHERE abbreviation = 'STA';
-- Maryland east
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/benning-road.cfm'                      WHERE abbreviation = 'BEN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/minnesota-ave.cfm'                     WHERE abbreviation = 'MNA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/deanwood.cfm'                          WHERE abbreviation = 'DEA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/cheverly.cfm'                          WHERE abbreviation = 'CHV';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/capitol-heights.cfm'                   WHERE abbreviation = 'CPH';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/addison-road-seat-pleasant.cfm'        WHERE abbreviation = 'ADD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/morgan-boulevard.cfm'                  WHERE abbreviation = 'MOG';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/largo-town-center.cfm'                 WHERE abbreviation = 'LGO';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/new-carrollton.cfm'                    WHERE abbreviation = 'NCR';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/arlington-cemetery.cfm'                WHERE abbreviation = 'ARL';
-- Blue south VA
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/van-dorn-street.cfm'                   WHERE abbreviation = 'VDS';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/franconia-springfield.cfm'             WHERE abbreviation = 'FRX';
-- Yellow/Blue shared VA
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/huntington.cfm'                        WHERE abbreviation = 'HUN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/eisenhower-avenue.cfm'                 WHERE abbreviation = 'EIS';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/king-street-old-town.cfm'              WHERE abbreviation = 'KIN';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/braddock-road.cfm'                     WHERE abbreviation = 'BRD';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/potomac-yard.cfm'                      WHERE abbreviation = 'PTY';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/reagan-national-airport.cfm'           WHERE abbreviation = 'DCA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/crystal-city.cfm'                      WHERE abbreviation = 'CRY';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/pentagon-city.cfm'                     WHERE abbreviation = 'PNT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/pentagon.cfm'                          WHERE abbreviation = 'PEN';
-- Yellow/Green shared DC
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/archives-navy-memorial.cfm'            WHERE abbreviation = 'ARC';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/mt-vernon-sq-convention-ctr.cfm'       WHERE abbreviation = 'MTV';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/shaw-howard.cfm'                       WHERE abbreviation = 'SHA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/columbia-heights.cfm'                  WHERE abbreviation = 'COL';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/georgia-ave-petworth.cfm'              WHERE abbreviation = 'GEO';
-- Green south
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/anacostia.cfm'                         WHERE abbreviation = 'ANA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/navy-yard-ballpark.cfm'                WHERE abbreviation = 'NAV';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/waterfront.cfm'                        WHERE abbreviation = 'WAT';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/congress-heights.cfm'                  WHERE abbreviation = 'CGH';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/southern-avenue.cfm'                   WHERE abbreviation = 'SOA';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/naylor-road.cfm'                       WHERE abbreviation = 'NAY';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/suitland.cfm'                          WHERE abbreviation = 'SUI';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/branch-avenue.cfm'                     WHERE abbreviation = 'BRA';
-- Yellow/Green north MD
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/west-hyattsville.cfm'                  WHERE abbreviation = 'WHY';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/prince-georges-plaza.cfm'              WHERE abbreviation = 'PGP';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/college-park-u-of-md.cfm'              WHERE abbreviation = 'CPK';
UPDATE wmata_stations SET wmata_url = 'https://www.wmata.com/rider-guide/stations/greenbelt.cfm'                         WHERE abbreviation = 'GRB';

-- ── Settings: maps API key ────────────────────────────────────
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('google_maps_api_key', ''),
    ('google_earth_api_key', '');

SET foreign_key_checks = 1;

INSERT IGNORE INTO migrations (name) VALUES ('004_wmata_stations_fix');
