-- Migration 003: WMATA Minecraft Tracker + login banner setting
-- Run via admin/migrations.php

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── WMATA Lines ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_lines (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    abbreviation VARCHAR(6)  NOT NULL UNIQUE,
    color       VARCHAR(7)   NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO wmata_lines (name, abbreviation, color, sort_order) VALUES
    ('Red Line',    'RD', '#BF0D3E', 1),
    ('Orange Line', 'OR', '#ED8B00', 2),
    ('Silver Line', 'SV', '#919D9D', 3),
    ('Blue Line',   'BL', '#003DA5', 4),
    ('Yellow Line', 'YL', '#FFD100', 5),
    ('Green Line',  'GR', '#00B140', 6);

-- ── WMATA Stations ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_stations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(160) NOT NULL,
    abbreviation        VARCHAR(6)   NOT NULL UNIQUE,
    lat                 DECIMAL(10,7) DEFAULT NULL,
    lng                 DECIMAL(10,7) DEFAULT NULL,
    status              ENUM('incomplete','in_progress','complete') NOT NULL DEFAULT 'incomplete',
    platform_blocks     INT          DEFAULT NULL COMMENT 'Number of blocks for the platform',
    google_maps_url     VARCHAR(500) DEFAULT NULL,
    google_earth_url    VARCHAR(500) DEFAULT NULL,
    notes               TEXT         DEFAULT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed all WMATA stations
INSERT IGNORE INTO wmata_stations (name, abbreviation, lat, lng) VALUES
-- Red Line
('Shady Grove',                     'SHD', 39.1197405, -77.1645621),
('Rockville',                       'ROK', 39.0847316, -77.1527154),
('Twinbrook',                       'TWI', 39.0626462, -77.1222953),
('White Flint',                     'WHT', 39.0487489, -77.1119024),
('Grosvenor-Strathmore',            'GRO', 39.0290387, -77.1067066),
('Medical Center',                  'MED', 38.9998249, -77.0970018),
('Bethesda',                        'BET', 38.9845064, -77.0968768),
('Friendship Heights',              'FRN', 38.9603977, -77.0870053),
('Tenleytown-AU',                   'TEN', 38.9477276, -77.0794107),
('Van Ness-UDC',                    'VAN', 38.9441231, -77.0633059),
('Cleveland Park',                  'CLE', 38.9344248, -77.0581353),
('Woodley Park-Zoo/Adams Morgan',   'WOD', 38.9248054, -77.0525534),
('Dupont Circle',                   'DUP', 38.9096523, -77.0434906),
('Farragut North',                  'FRG', 38.9017286, -77.0396287),
('Metro Center',                    'MTC', 38.8983144, -77.0278527),
('Gallery Pl-Chinatown',            'GAL', 38.8985021, -77.0219153),
('Judiciary Square',                'JUD', 38.8970432, -77.0164799),
('Union Station',                   'UNI', 38.8972334, -77.0058369),
('NoMa-Gallaudet U',                'NOM', 38.9068451, -76.9988201),
('Rhode Island Ave-Brentwood',      'RIA', 38.9205209, -76.9946159),
('Brookland-CUA',                   'BRK', 38.9332312, -76.9942145),
('Fort Totten',                     'FTT', 38.9514271, -76.9984695),
('Takoma',                          'TAK', 38.9766818, -77.0023122),
('Silver Spring',                   'SVS', 38.9940804, -77.0311198),
('Forest Glen',                     'FGL', 39.0148413, -77.0521924),
('Wheaton',                         'WHE', 39.0345778, -77.0581927),
('Glenmont',                        'GLE', 39.0581246, -77.0538694),
-- Blue/Orange/Silver shared trunk (Arlington)
('McLean',                          'MCL', 38.9340498, -77.1196578),
('Tysons Corner',                   'TYS', 38.9204537, -77.2233988),
('Greensboro',                      'GSB', 38.9225327, -77.2350497),
('Spring Hill',                     'SPH', 38.9278756, -77.2430408),
('Wiehle-Reston East',              'WIE', 38.9478131, -77.3404453),
('Reston Town Center',              'RTC', 38.9594671, -77.3592455),
('Herndon',                         'HER', 38.9675547, -77.3856834),
('Innovation Center',               'INN', 38.9724803, -77.4178028),
('Washington Dulles Intl Airport',  'IAD', 38.9531282, -77.4479237),
('Loudoun Gateway',                 'LDG', 28.9397855, -77.4697266),
('Ashburn',                         'ASH', 39.0022583, -77.4870529),
('East Falls Church',               'EFC', 38.8852282, -77.1567612),
('West Falls Church',               'WFC', 38.8993266, -77.1874568),
('Dunn Loring-Merrifield',          'DLN', 38.8849289, -77.2280399),
('Vienna/Fairfax-GMU',              'VIN', 38.8781414, -77.2707701),
('Ballston-MU',                     'BAL', 38.8822087, -77.1117026),
('Virginia Square-GMU',             'VSQ', 38.8846408, -77.1032717),
('Clarendon',                       'CLA', 38.8863254, -77.0959626),
('Court House',                     'CTH', 38.8907428, -77.0821551),
('Rosslyn',                         'ROS', 38.8964357, -77.0705741),
('Foggy Bottom-GWU',                'FOG', 38.9000651, -77.0501116),
('Farragut West',                   'FGW', 38.9007627, -77.0401266),
('McPherson Square',                'MCP', 38.9015102, -77.0333484),
('Federal Triangle',                'FDT', 38.8933007, -77.0279942),
('Smithsonian',                     'SMI', 38.8887956, -77.0279780),
('L\'Enfant Plaza',                 'LEN', 38.8845583, -77.0217018),
('Capitol South',                   'CAP', 38.8853985, -77.0047175),
('Eastern Market',                  'EMK', 38.8840684, -76.9966764),
('Potomac Ave',                     'POT', 38.8792019, -76.9875584),
('Stadium-Armory',                  'STA', 38.8886682, -76.9727706),
('Benning Road',                    'BEN', 38.8904278, -76.9384155),
('Capitol Heights',                 'CPH', 38.8893099, -76.9126428),
('Addison Road-Seat Pleasant',      'ADD', 38.8865726, -76.8938049),
('Morgan Boulevard',                'MOG', 38.8864701, -76.8710027),
('Largo Town Center',               'LGO', 38.9004067, -76.8423283),
('New Carrollton',                  'NCR', 38.9479099, -76.8720497),
('Arlington Cemetery',              'ARL', 38.8838773, -77.0636628),
-- Blue Line only
('Van Dorn Street',                 'VDS', 38.7982882, -77.1277040),
('Franconia-Springfield',           'FRX', 38.7660614, -77.1682248),
-- Yellow Line
('Huntington',                      'HUN', 38.7957100, -77.0714985),
('Eisenhower Avenue',               'EIS', 38.8005673, -77.0769296),
('King Street-Old Town',            'KIN', 38.8063068, -77.0751819),
('Braddock Road',                   'BRD', 38.8197067, -77.0542026),
('Ronald Reagan Washington Natl Airport', 'DCA', 38.8526049, -77.0433598),
('Crystal City',                    'CRY', 38.8576437, -77.0519861),
('Pentagon City',                   'PNT', 38.8635350, -77.0591373),
('Pentagon',                        'PEN', 38.8693117, -77.0532649),
('Archives-Navy Memorial-Penn Qtr', 'ARC', 38.8933454, -77.0219929),
('Mt Vernon Sq/7th St-Convention Ctr','MTV', 38.9050813, -77.0222279),
('Shaw-Howard U',                   'SHA', 38.9127921, -77.0222017),
('Columbia Heights',                'COL', 38.9286534, -77.0326199),
('Georgia Ave-Petworth',            'GEO', 38.9369843, -77.0226225),
-- Green Line south
('Navy Yard-Ballpark',              'NAV', 38.8764927, -77.0051072),
('Waterfront',                      'WAT', 38.8762609, -77.0173826),
('Congress Heights',                'CGH', 38.8456327, -76.9975268),
('Southern Avenue',                 'SOA', 38.8403668, -76.9756166),
('Naylor Road',                     'NAY', 38.8508716, -76.9583432),
('Suitland',                        'SUI', 38.8436649, -76.9200239),
('Branch Avenue',                   'BRA', 38.8266009, -76.9124888),
-- Green Line north shared (with Yellow)
('West Hyattsville',                'WHY', 38.9538437, -76.9706116),
('Prince George\'s Plaza',          'PGP', 38.9611004, -76.9596519),
('College Park-U of MD',            'CPK', 39.0020949, -76.9282649),
('Greenbelt',                       'GRB', 39.0110141, -76.9108657);

-- ── WMATA Station ↔ Line junction ───────────────────────────
CREATE TABLE IF NOT EXISTS wmata_station_lines (
    station_id  INT UNSIGNED NOT NULL,
    line_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (station_id, line_id),
    FOREIGN KEY (station_id) REFERENCES wmata_stations (id) ON DELETE CASCADE,
    FOREIGN KEY (line_id)    REFERENCES wmata_lines    (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed station-line associations (based on WMATA system map)
INSERT IGNORE INTO wmata_station_lines (station_id, line_id)
SELECT s.id, l.id FROM wmata_stations s, wmata_lines l
WHERE (s.abbreviation IN ('SHD','ROK','TWI','WHT','GRO','MED','BET','FRN','TEN','VAN','CLE','WOD','DUP','FRG','TAK','SVS','FGL','WHE','GLE','JUD','UNI','NOM','RIA','BRK') AND l.abbreviation = 'RD')
   OR (s.abbreviation IN ('MTC','GAL','FTT') AND l.abbreviation IN ('RD'))
   OR (s.abbreviation IN ('VIN','DLN','WFC','EFC','BAL','VSQ','CLA','CTH','ROS','FOG','FGW','MCP','MTC','FDT','SMI','LEN','CAP','EMK','POT','STA','BEN','CPH','ADD','MOG','NCR') AND l.abbreviation = 'OR')
   OR (s.abbreviation IN ('ASH','LDG','IAD','INN','HER','RTC','WIE','SPH','GSB','TYS','MCL','EFC','BAL','VSQ','CLA','CTH','ROS','FOG','FGW','MCP','MTC','FDT','SMI','LEN','CAP','EMK','POT','STA','BEN','CPH','ADD','MOG','LGO') AND l.abbreviation = 'SV')
   OR (s.abbreviation IN ('FRX','VDS','KIN','BRD','DCA','CRY','PNT','PEN','ARL','ROS','FOG','FGW','MCP','MTC','FDT','SMI','LEN','CAP','EMK','POT','STA','BEN','CPH','ADD','MOG','LGO') AND l.abbreviation = 'BL')
   OR (s.abbreviation IN ('HUN','EIS','KIN','BRD','DCA','CRY','PNT','PEN','LEN','ARC','GAL','MTV','SHA','COL','GEO','FTT','WHY','PGP','CPK','GRB') AND l.abbreviation = 'YL')
   OR (s.abbreviation IN ('BRA','SUI','NAY','SOA','CGH','NAV','WAT','LEN','ARC','MTV','SHA','COL','GEO','FTT','GAL','WHY','PGP','CPK','GRB') AND l.abbreviation = 'GR');

-- ── Station Checks ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_station_checks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id  INT UNSIGNED NOT NULL,
    check_name  VARCHAR(255) NOT NULL,
    is_checked  TINYINT(1)   NOT NULL DEFAULT 0,
    notes       TEXT         DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES wmata_stations (id) ON DELETE CASCADE,
    INDEX idx_station (station_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Station Files ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_station_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id    INT UNSIGNED NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(120) DEFAULT NULL,
    file_size     INT UNSIGNED DEFAULT NULL,
    file_type     ENUM('texture','model','diagram','screenshot','other') NOT NULL DEFAULT 'other',
    notes         TEXT         DEFAULT NULL,
    uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES wmata_stations (id) ON DELETE CASCADE,
    INDEX idx_station (station_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Rolling Stock ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_rolling_stock (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series          ENUM('6000','7000') NOT NULL,
    car_number      VARCHAR(20)  NOT NULL,
    diagram_filename VARCHAR(255) DEFAULT NULL,
    car_blocks      INT          DEFAULT NULL COMMENT 'Block length of car',
    gap_blocks      INT          DEFAULT NULL COMMENT 'Gap between this and next car',
    notes           TEXT         DEFAULT NULL,
    status          ENUM('incomplete','in_progress','complete') NOT NULL DEFAULT 'incomplete',
    sort_order      INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uq_series_car (series, car_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed 7000-series cars (8 cars typical, car numbers 7001-7008 as example set)
INSERT IGNORE INTO wmata_rolling_stock (series, car_number, sort_order) VALUES
('7000','7001',1),('7000','7002',2),('7000','7003',3),('7000','7004',4),
('7000','7005',5),('7000','7006',6),('7000','7007',7),('7000','7008',8);

-- Seed 6000-series cars (car numbers 6001-6006 as example set)
INSERT IGNORE INTO wmata_rolling_stock (series, car_number, sort_order) VALUES
('6000','6001',1),('6000','6002',2),('6000','6003',3),
('6000','6004',4),('6000','6005',5),('6000','6006',6);

-- ── Rolling Stock Progress Points ────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_rolling_stock_progress (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rolling_stock_id INT UNSIGNED NOT NULL,
    point_name      VARCHAR(255) NOT NULL,
    status          ENUM('incomplete','in_progress','complete') NOT NULL DEFAULT 'incomplete',
    notes           TEXT         DEFAULT NULL,
    sort_order      INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (rolling_stock_id) REFERENCES wmata_rolling_stock (id) ON DELETE CASCADE,
    INDEX idx_stock (rolling_stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default progress points for each car
INSERT IGNORE INTO wmata_rolling_stock_progress (rolling_stock_id, point_name, sort_order)
SELECT rs.id, pts.point_name, pts.sort_order
FROM wmata_rolling_stock rs
CROSS JOIN (
    SELECT 1 AS sort_order, 'Body shell built'      AS point_name UNION ALL
    SELECT 2, 'Interior floor placed' UNION ALL
    SELECT 3, 'Walls & windows'       UNION ALL
    SELECT 4, 'Ceiling / roof'        UNION ALL
    SELECT 5, 'Doors placed'          UNION ALL
    SELECT 6, 'Seat placement'        UNION ALL
    SELECT 7, 'Lighting'              UNION ALL
    SELECT 8, 'Exterior textures'     UNION ALL
    SELECT 9, 'Interior textures'     UNION ALL
    SELECT 10,'Signage & branding'
) pts;

-- ── Block Calculations (feet-to-blocks converter) ────────────
CREATE TABLE IF NOT EXISTS wmata_block_calculations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    label       VARCHAR(255) NOT NULL DEFAULT '',
    feet_value  DECIMAL(12,4) NOT NULL,
    multiplier  DECIMAL(10,6) NOT NULL DEFAULT 0.0625 COMMENT 'blocks-per-foot ratio',
    block_count DECIMAL(12,4) NOT NULL,
    notes       TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Global WMATA Files ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wmata_global_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(120) DEFAULT NULL,
    file_size     INT UNSIGNED DEFAULT NULL,
    file_type     ENUM('texture','model','diagram','screenshot','reference','other') NOT NULL DEFAULT 'other',
    category      VARCHAR(120) DEFAULT NULL,
    notes         TEXT         DEFAULT NULL,
    uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Settings seeds ────────────────────────────────────────────
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('login_banner',       ''),
    ('login_banner_text',  ''),
    ('login_banner_subtext', '');

-- ── Seed WMATA app ────────────────────────────────────────────
INSERT IGNORE INTO apps (name, slug, icon, url, description, sort_order) VALUES
    ('WMATA Tracker', 'wmata', 'train', '/wmata/', 'WMATA Minecraft Recreation Tracker', 4);

SET foreign_key_checks = 1;

INSERT IGNORE INTO migrations (name) VALUES ('003_wmata_login_banner');
