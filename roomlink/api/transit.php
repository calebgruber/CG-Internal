<?php
/**
 * roomlink/api/transit.php – Transit Data Proxy
 *
 * Returns departure data from live APIs (MTA Metro-North GTFS-RT,
 * NJ Transit Rail API) keyed by station.
 *
 * Query params:
 *   ?json=1                Always returns JSON (default)
 *   ?limit=N               Max departures to return (default 12)
 *   ?station=<slug>        Station slug: stamford | grand-central | white-plains |
 *                          penn-station | metropark  (default: grand-central)
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

header('Content-Type: application/json');

$limit   = max(1, min(20, (int)($_GET['limit'] ?? 12)));
$station = strtolower(trim($_GET['station'] ?? 'grand-central'));

/* ── Station configuration ── */
$stations = [
    'stamford'      => ['label' => 'Stamford',            'agency' => 'MNR', 'stop_id' => '110'],
    'grand-central' => ['label' => 'Grand Central',       'agency' => 'MNR', 'stop_id' => '1'],
    'white-plains'  => ['label' => 'White Plains',        'agency' => 'MNR', 'stop_id' => '117'],
    'penn-station'  => ['label' => 'Penn Station NY',     'agency' => 'NJT', 'njt_code' => 'NY'],
    'metropark'     => ['label' => 'Metropark',           'agency' => 'NJT', 'njt_code' => 'MP'],
];

if (!isset($stations[$station])) {
    $station = 'grand-central';
}
$station_cfg = $stations[$station];

/* ── Fetch live data ── */
$departures = [];
$source = 'demo';

if ($station_cfg['agency'] === 'MNR') {
    $mta_key = rl_setting('mta_api_key', '');
    $live = fetch_mta_mnr_departures($station_cfg['stop_id'], $limit, $mta_key);
    if (!empty($live)) {
        $departures = $live;
        $source = 'live';
    }
} elseif ($station_cfg['agency'] === 'NJT') {
    $njt_user = rl_setting('njt_username', '');
    $njt_pass = rl_setting('njt_password', '');
    if ($njt_user !== '' && $njt_pass !== '') {
        $live = fetch_njt_departures($station_cfg['njt_code'], $njt_user, $njt_pass, $limit);
        if (!empty($live)) {
            $departures = $live;
            $source = 'live';
        }
    }
}

/* ── Demo / fallback data ── */
if (empty($departures)) {
    $departures = generate_demo_departures($station);
    $source = 'demo';
}

/* ── Sort by time_24 ── */
usort($departures, fn($a, $b) => strcmp($a['time_24'], $b['time_24']));
$departures = array_values(array_slice($departures, 0, $limit));

echo json_encode([
    'ok'          => true,
    'source'      => $source,
    'station'     => $station,
    'station_label' => $station_cfg['label'],
    'departures'  => $departures,
    'fetched_at'  => (new DateTime())->format(DateTime::ATOM),
    'refresh_sec' => (int) rl_setting('transit_refresh_sec', '30'),
]);
exit;

/* ═══════════════════════════════════════════════════
   LIGHTWEIGHT PROTOBUF / GTFS-RT READER
   Parses MTA Metro-North GTFS-RT binary feed.
   ═══════════════════════════════════════════════════ */

class ProtoReader {
    private string $buf;
    private int $pos;
    private int $len;

    public function __construct(string $buf) {
        $this->buf = $buf;
        $this->pos = 0;
        $this->len = strlen($buf);
    }

    public function eof(): bool { return $this->pos >= $this->len; }

    public function readVarint(): int {
        $result = 0;
        $shift  = 0;
        do {
            if ($this->eof()) break;
            $b = ord($this->buf[$this->pos++]);
            $result |= ($b & 0x7F) << $shift;
            $shift += 7;
        } while ($b & 0x80);
        return $result;
    }

    public function readBytes(): string {
        $n   = $this->readVarint();
        $out = $n > 0 ? substr($this->buf, $this->pos, $n) : '';
        $this->pos += $n;
        return (string)$out;
    }

    public function skip(int $wire): void {
        switch ($wire) {
            case 0: $this->readVarint(); break;
            case 1: $this->pos += 8;    break;
            case 2: $n = $this->readVarint(); $this->pos += $n; break;
            case 5: $this->pos += 4;    break;
        }
    }

    public function readTag(): ?array {
        if ($this->eof()) return null;
        $v = $this->readVarint();
        return ['field' => $v >> 3, 'wire' => $v & 7];
    }

    public function sub(): self {
        return new self($this->readBytes());
    }
}

/* ── Parse FeedMessage → array of entities ── */
function proto_parse_feed(string $raw): array {
    $r = new ProtoReader($raw);
    $entities = [];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 2 && $tag['wire'] === 2) {
            $entities[] = proto_parse_entity($r->sub());
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $entities;
}

function proto_parse_entity(ProtoReader $r): array {
    $e = ['id' => '', 'trip_update' => null];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 2) {
            $e['id'] = $r->readBytes();
        } elseif ($tag['field'] === 4 && $tag['wire'] === 2) {
            $e['trip_update'] = proto_parse_trip_update($r->sub());
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $e;
}

function proto_parse_trip_update(ProtoReader $r): array {
    $tu = ['trip' => [], 'stop_time_updates' => []];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 2) {
            $tu['trip'] = proto_parse_trip_descriptor($r->sub());
        } elseif ($tag['field'] === 2 && $tag['wire'] === 2) {
            $tu['stop_time_updates'][] = proto_parse_stu($r->sub());
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $tu;
}

function proto_parse_trip_descriptor(ProtoReader $r): array {
    $td = ['trip_id' => '', 'route_id' => '', 'direction_id' => 0];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 2) {
            $td['trip_id'] = $r->readBytes();
        } elseif ($tag['field'] === 5 && $tag['wire'] === 2) {
            $td['route_id'] = $r->readBytes();
        } elseif ($tag['field'] === 6 && $tag['wire'] === 0) {
            $td['direction_id'] = $r->readVarint();
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $td;
}

function proto_parse_stu(ProtoReader $r): array {
    $stu = ['stop_sequence' => null, 'stop_id' => '', 'arrival' => null, 'departure' => null, 'mta_extension' => null];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 0) {
            $stu['stop_sequence'] = $r->readVarint();
        } elseif ($tag['field'] === 4 && $tag['wire'] === 2) {
            $stu['stop_id'] = $r->readBytes();
        } elseif ($tag['field'] === 2 && $tag['wire'] === 2) {
            $stu['arrival'] = proto_parse_ste($r->sub());
        } elseif ($tag['field'] === 3 && $tag['wire'] === 2) {
            $stu['departure'] = proto_parse_ste($r->sub());
        } elseif ($tag['field'] === 1005 && $tag['wire'] === 2) {
            // MtaRailroadStopTimeUpdate extension — contains track + trainStatus
            $stu['mta_extension'] = proto_parse_mta_stu_ext($r->sub());
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $stu;
}

function proto_parse_ste(ProtoReader $r): array {
    $e = ['delay' => 0, 'time' => 0];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 0) {
            $raw = $r->readVarint();
            // Protobuf zigzag-encoded sint32: decode (n >> 1) ^ -(n & 1)
            $e['delay'] = ($raw >> 1) ^ (-($raw & 1));
        } elseif ($tag['field'] === 2 && $tag['wire'] === 0) {
            $e['time'] = $r->readVarint();
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $e;
}

/**
 * Parse MtaRailroadStopTimeUpdate extension (field 1005 inside StopTimeUpdate).
 * Returns ['track' => string, 'trainStatus' => string].
 */
function proto_parse_mta_stu_ext(ProtoReader $r): array {
    $ext = ['track' => '', 'trainStatus' => ''];
    while (!$r->eof()) {
        $tag = $r->readTag();
        if (!$tag) break;
        if ($tag['field'] === 1 && $tag['wire'] === 2) {
            $ext['track'] = $r->readBytes();
        } elseif ($tag['field'] === 2 && $tag['wire'] === 2) {
            $ext['trainStatus'] = $r->readBytes();
        } else {
            $r->skip($tag['wire']);
        }
    }
    return $ext;
}

/* ── MNR route info (color + name) ── */
function mnr_route_info(string $route_id): array {
    static $routes = [
        // Metro-North route IDs from public GTFS
        '1'  => ['name' => 'New Haven Line',   'color' => '#0057A9'],
        '2'  => ['name' => 'Harlem Line',       'color' => '#5B2C8D'],
        '3'  => ['name' => 'Hudson Line',       'color' => '#009B3A'],
        '4'  => ['name' => 'New Haven Line',    'color' => '#0057A9'],  // express branch
        '5'  => ['name' => 'Harlem Line',       'color' => '#5B2C8D'],
        '6'  => ['name' => 'Hudson Line',       'color' => '#009B3A'],
        'GH' => ['name' => 'New Haven Line',   'color' => '#0057A9'],
        'ME' => ['name' => 'New Haven Line',   'color' => '#0057A9'],
        'H'  => ['name' => 'Harlem Line',       'color' => '#5B2C8D'],
        'HU' => ['name' => 'Hudson Line',       'color' => '#009B3A'],
    ];
    $info = $routes[$route_id] ?? $routes[strtoupper($route_id)] ?? null;
    return $info ?? ['name' => 'Metro-North', 'color' => '#0E71B3'];
}

/* ── Map stop_id → station name ── */
function mnr_stop_name(string $stop_id): string {
    static $stops = [
        '1'   => 'Grand Central Terminal',
        '2'   => 'Harlem-125th St',
        '3'   => 'Fordham',
        '4'   => 'Tremont',
        '5'   => 'Melrose',
        '6'   => 'Williams Bridge',
        '7'   => 'Woodlawn',
        '8'   => 'Wakefield',
        '9'   => 'Fleetwood',
        '10'  => 'Mount Vernon East',
        '11'  => 'Pelham',
        '12'  => 'New Rochelle',
        '13'  => 'Larchmont',
        '14'  => 'Mamaroneck',
        '15'  => 'Harrison',
        '16'  => 'Rye',
        '17'  => 'Port Chester',
        '100' => 'Greenwich',
        '101' => 'Cos Cob',
        '102' => 'Riverside',
        '103' => 'Old Greenwich',
        '104' => 'Stamford',
        '105' => 'Glenbrook',
        '106' => 'Springdale',
        '107' => 'Talmadge Hill',
        '108' => 'New Canaan',
        '109' => 'Noroton Heights',
        '110' => 'Stamford',
        '111' => 'Darien',
        '112' => 'Rowayton',
        '113' => 'South Norwalk',
        '114' => 'East Norwalk',
        '115' => 'Westport',
        '116' => 'Green\'s Farms',
        '117' => 'White Plains',
        '118' => 'Southport',
        '119' => 'Fairfield',
        '120' => 'Bridgeport',
        '121' => 'Stratford',
        '122' => 'Milford',
        '123' => 'West Haven',
        '124' => 'New Haven',
        '125' => 'Bronxville',
        '126' => 'Tuckahoe',
        '127' => 'Crestwood',
        '128' => 'Scarsdale',
        '129' => 'Hartsdale',
        '130' => 'White Plains',
        '131' => 'North White Plains',
        '132' => 'Valhalla',
        '133' => 'Mount Pleasant',
        '134' => 'Hawthorne',
        '135' => 'Pleasantville',
        '136' => 'Chappaqua',
        '137' => 'Pleasantville',
        '138' => 'Briarcliff Manor',
        '139' => 'Ossining',
        '140' => 'Croton-Harmon',
        '141' => 'Cortlandt',
        '142' => 'Peekskill',
        '143' => 'Garrison',
        '144' => 'Cold Spring',
        '145' => 'Breakneck Ridge',
        '146' => 'Poughkeepsie',
        '147' => 'Beacon',
        '148' => 'New Hamburg',
        '149' => 'Poughkeepsie',
        '150' => 'Southeast',
        '151' => 'Brewster',
        '152' => 'Patterson',
        '153' => 'Appalachian Trail',
        '154' => 'Pawling',
        '155' => 'Harlem Valley-Wingdale',
        '156' => 'Dover Plains',
        '157' => 'Tenmile River',
        '158' => 'Wassaic',
    ];
    return $stops[$stop_id] ?? "Stop #$stop_id";
}

/* ═══════════════════════════════════════════════════
   MTA METRO-NORTH GTFS-RT FETCHER
   An API key (x-api-key) is required — obtain one free
   at developer.mta.info and save it in RoomLink Settings.
   ═══════════════════════════════════════════════════ */
function fetch_mta_mnr_departures(string $stop_id, int $limit, string $api_key = ''): array {
    $feed_url = 'https://api-endpoint.mta.info/Dataservice/mtagtfsfeeds/mnr%2Fgtfs-mnr';

    $headers = "Accept: application/octet-stream\r\n";
    if ($api_key !== '') {
        $headers .= "x-api-key: {$api_key}\r\n";
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 8,
            'ignore_errors' => true,
            'header'        => $headers,
        ],
    ]);
    $raw = @file_get_contents($feed_url, false, $ctx);
    if (!$raw || strlen($raw) < 10) return [];

    try {
        $entities = proto_parse_feed($raw);
    } catch (Throwable $e) {
        return [];
    }

    $now  = time();
    $deps = [];

    foreach ($entities as $entity) {
        if (!$entity['trip_update']) continue;
        $tu = $entity['trip_update'];
        $stus = $tu['stop_time_updates'];

        $target_stu = null;
        $last_stu   = null;

        foreach ($stus as $stu) {
            $last_stu = $stu;
            if ((string)$stu['stop_id'] === (string)$stop_id) {
                $target_stu = $stu;
            }
        }

        if (!$target_stu) continue;

        $dep = $target_stu['departure'] ?? $target_stu['arrival'] ?? null;
        if (!$dep || $dep['time'] < $now) continue;

        $dep_ts    = (int)$dep['time'];
        $delay_sec = (int)($dep['delay'] ?? 0);

        // Track comes from MtaRailroadStopTimeUpdate extension field 1005
        $mta_ext   = $target_stu['mta_extension'] ?? null;
        $track     = $mta_ext['track'] ?? '';

        // trainStatus from extension can override computed status
        $ext_status = strtolower($mta_ext['trainStatus'] ?? '');

        $route_info  = mnr_route_info($tu['trip']['route_id'] ?? '');
        $destination = $last_stu ? mnr_stop_name($last_stu['stop_id']) : 'Unknown';

        $status_type  = 'ontime';
        $status_label = 'On Time';

        if (str_contains($ext_status, 'cancel')) {
            $status_type  = 'cancelled';
            $status_label = 'CANCELLED';
        } elseif (str_contains($ext_status, 'board') || ($dep_ts - $now < 120 && $delay_sec <= 120)) {
            $status_type  = 'boarding';
            $status_label = 'BOARDING';
        } elseif ($delay_sec > 120) {
            $delay_min    = (int)round($delay_sec / 60);
            $status_type  = 'delayed';
            $status_label = "DELAYED +{$delay_min}m";
        }

        $deps[] = [
            'id'               => $entity['id'],
            'track'            => $track,
            'time'             => date('g:i', $dep_ts),
            'time_24'          => date('H:i', $dep_ts),
            'destination'      => $destination,
            'agency'           => 'MNR',
            'agency_name'      => 'Metro-North',
            'agency_color'     => $route_info['color'],
            'agency_text_color'=> '#ffffff',
            'line_name'        => $route_info['name'],
            'status'           => $status_label,
            'status_type'      => $status_type,
            'delay_minutes'    => max(0, (int)round($delay_sec / 60)),
        ];
    }

    usort($deps, fn($a, $b) => strcmp($a['time_24'], $b['time_24']));
    return array_values(array_slice($deps, 0, $limit));
}

/* ═══════════════════════════════════════════════════
   NJ TRANSIT RAIL API v2 FETCHER
   Uses NJT developer portal credentials.
   ═══════════════════════════════════════════════════ */
function fetch_njt_departures(string $njt_station_code, string $username, string $password, int $limit): array {
    // Step 1: Obtain access token
    $token = njt_get_token($username, $password);
    if (!$token) return [];

    // Step 2: Fetch departures
    $url = 'https://raildata.njtransit.com/api/TrainData/getTrainScheduleJSON'
         . '?station=' . urlencode($njt_station_code);
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 6,
            'ignore_errors' => true,
            'header'        => "Authorization: Bearer {$token}\r\n"
                             . "Accept: application/json\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];

    $data = json_decode($raw, true);
    if (!is_array($data)) return [];

    return njt_parse_departures($data, $limit);
}

function njt_get_token(string $username, string $password): ?string {
    // NJT Rail Data API v2 uses form-encoded POST for token
    $url  = 'https://raildata.njtransit.com/api/Account/token';
    $body = http_build_query(['username' => $username, 'password' => $password]);
    $ctx  = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'timeout'       => 8,
            'ignore_errors' => true,
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content'       => $body,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    // v2 API may return token in different keys
    return $data['access_token'] ?? $data['token'] ?? $data['Token'] ?? null;
}

function njt_line_info(string $line_code): array {
    static $lines = [
        'NEC'  => ['name' => 'Northeast Corridor',    'color' => '#DA291C'],
        'M&E'  => ['name' => 'Morris & Essex Lines',  'color' => '#00A550'],
        'NJCL' => ['name' => 'North Jersey Coast',    'color' => '#0039A6'],
        'ML'   => ['name' => 'Main Line',              'color' => '#F77F00'],
        'BCL'  => ['name' => 'Bergen County Line',    'color' => '#F77F00'],
        'PVL'  => ['name' => 'Pascack Valley Line',   'color' => '#7B3F00'],
        'RVL'  => ['name' => 'Raritan Valley Line',   'color' => '#FFCC00'],
        'MCB'  => ['name' => 'Montclair-Boonton',     'color' => '#006CB7'],
        'SLS'  => ['name' => 'Atlantic City Line',    'color' => '#9B5EA2'],
    ];
    $up = strtoupper($line_code);
    return $lines[$up] ?? ['name' => 'NJ Transit', 'color' => '#003087'];
}

function njt_parse_departures(array $data, int $limit): array {
    $now  = time();
    $deps = [];

    // NJT API v2 wraps results; handle multiple known shapes
    $items = $data['TRAIN_LINE']   // v2 schedule endpoint
          ?? $data['getTrainScheduleJSONResult']
          ?? $data['trains']
          ?? $data['departures']
          ?? (isset($data[0]) ? $data : []);
    if (!is_array($items)) return [];

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        // Departure time — NJT v2 uses "SCHED_DEP_DATE" as "MM/DD/YYYY HH:MM:SS AM" or unix
        $dep_time_raw = $item['SCHED_DEP_DATE'] ?? $item['departureTime'] ?? $item['DEP_TIME'] ?? '';
        if (!$dep_time_raw) continue;

        $dep_ts = is_numeric($dep_time_raw)
            ? (int)$dep_time_raw
            : @strtotime($dep_time_raw);

        if (!$dep_ts || $dep_ts < $now) continue;

        $line_code  = $item['TRAIN_LINE'] ?? $item['line'] ?? $item['LINE_CODE'] ?? 'NEC';
        $dest       = $item['DESTINATION'] ?? $item['destination'] ?? $item['LAST_STOP'] ?? 'Unknown';
        $status_raw = strtolower($item['STATUS'] ?? $item['status'] ?? 'on time');
        $track      = $item['TRACK'] ?? $item['track'] ?? $item['PTRACKID'] ?? '';
        $train_id   = $item['TRAIN_ID'] ?? $item['TRAINID'] ?? uniqid('njt_');

        $line_info = njt_line_info($line_code);

        $status_type  = 'ontime';
        $status_label = 'On Time';
        $delay_min    = 0;

        if (str_contains($status_raw, 'cancel')) {
            $status_type  = 'cancelled';
            $status_label = 'CANCELLED';
        } elseif (str_contains($status_raw, 'board')) {
            $status_type  = 'boarding';
            $status_label = 'BOARDING';
        } elseif (str_contains($status_raw, 'late') || str_contains($status_raw, 'delay')) {
            preg_match('/(\d+)/', $status_raw, $m);
            $delay_min    = (int)($m[1] ?? 0);
            $status_type  = 'delayed';
            $status_label = $delay_min ? "DELAYED +{$delay_min}m" : 'DELAYED';
        }

        $deps[] = [
            'id'               => (string)$train_id,
            'track'            => (string)$track,
            'time'             => date('g:i', $dep_ts),
            'time_24'          => date('H:i', $dep_ts),
            'destination'      => $dest,
            'agency'           => 'NJT',
            'agency_name'      => 'NJ Transit',
            'agency_color'     => $line_info['color'],
            'agency_text_color'=> '#ffffff',
            'line_name'        => $line_info['name'],
            'status'           => $status_label,
            'status_type'      => $status_type,
            'delay_minutes'    => $delay_min,
        ];
    }

    usort($deps, fn($a, $b) => strcmp($a['time_24'], $b['time_24']));
    return array_values(array_slice($deps, 0, $limit));
}

/* ═══════════════════════════════════════════════════
   DEMO DATA GENERATOR — realistic per-station data
   ═══════════════════════════════════════════════════ */
function generate_demo_departures(string $station): array {
    $now = time();

    $by_station = [
        'stamford' => [
            ['offset' =>  3, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'boarding', 'delay' => 0],
            ['offset' =>  9, 'dest' => 'New Haven',              'track' => '2', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 18, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 24, 'dest' => 'Bridgeport',             'track' => '2', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'delayed',  'delay' => 7],
            ['offset' => 35, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 52, 'dest' => 'New Haven',              'track' => '2', 'agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
        ],
        'grand-central' => [
            ['offset' =>  4, 'dest' => 'Stamford',               'track' => '21','agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
            ['offset' =>  7, 'dest' => 'White Plains',           'track' => '33','agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'boarding', 'delay' => 0],
            ['offset' => 11, 'dest' => 'Poughkeepsie',           'track' => '28','agency' => 'MNR', 'line' => 'Hudson Line',    'color' => '#009B3A', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 19, 'dest' => 'New Haven',              'track' => '22','agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'delayed',  'delay' => 5],
            ['offset' => 23, 'dest' => 'Southeast',              'track' => '34','agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 30, 'dest' => 'Croton-Harmon',          'track' => '27','agency' => 'MNR', 'line' => 'Hudson Line',    'color' => '#009B3A', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 38, 'dest' => 'Stamford',               'track' => '23','agency' => 'MNR', 'line' => 'New Haven Line', 'color' => '#0057A9', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 45, 'dest' => 'North White Plains',     'track' => '35','agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'delayed',  'delay' => 3],
        ],
        'white-plains' => [
            ['offset' =>  5, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 14, 'dest' => 'Southeast',              'track' => '2', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 22, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'boarding', 'delay' => 0],
            ['offset' => 31, 'dest' => 'Wassaic',                'track' => '2', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'delayed',  'delay' => 6],
            ['offset' => 44, 'dest' => 'Grand Central Terminal', 'track' => '1', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 58, 'dest' => 'North White Plains',     'track' => '2', 'agency' => 'MNR', 'line' => 'Harlem Line',    'color' => '#5B2C8D', 'st' => 'ontime',   'delay' => 0],
        ],
        'penn-station' => [
            ['offset' =>  3, 'dest' => 'Trenton',                'track' => '3', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'boarding', 'delay' => 0],
            ['offset' =>  8, 'dest' => 'Long Branch',            'track' => '6', 'agency' => 'NJT', 'line' => 'North Jersey Coast',  'color' => '#0039A6', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 15, 'dest' => 'Dover',                  'track' => '9', 'agency' => 'NJT', 'line' => 'Morris & Essex',      'color' => '#00A550', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 21, 'dest' => 'Metropark',              'track' => '4', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'delayed',  'delay' => 8],
            ['offset' => 28, 'dest' => 'Bay Head',               'track' => '7', 'agency' => 'NJT', 'line' => 'North Jersey Coast',  'color' => '#0039A6', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 35, 'dest' => 'Princeton Junction',     'track' => '3', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 42, 'dest' => 'Gladstone',              'track' => '9', 'agency' => 'NJT', 'line' => 'Morris & Essex',      'color' => '#00A550', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 49, 'dest' => 'Trenton',                'track' => '4', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'delayed',  'delay' => 12],
        ],
        'metropark' => [
            ['offset' =>  6, 'dest' => 'New York Penn Station',  'track' => '1', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 13, 'dest' => 'Trenton',                'track' => '2', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 24, 'dest' => 'New York Penn Station',  'track' => '1', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'boarding', 'delay' => 0],
            ['offset' => 37, 'dest' => 'Princeton Junction',     'track' => '2', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'delayed',  'delay' => 5],
            ['offset' => 48, 'dest' => 'New York Penn Station',  'track' => '1', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'ontime',   'delay' => 0],
            ['offset' => 60, 'dest' => 'Trenton',                'track' => '2', 'agency' => 'NJT', 'line' => 'Northeast Corridor',  'color' => '#DA291C', 'st' => 'ontime',   'delay' => 0],
        ],
    ];

    $schedule = $by_station[$station] ?? $by_station['grand-central'];
    $deps     = [];

    foreach ($schedule as $i => $s) {
        $ts = $now + $s['offset'] * 60;
        $status_label = match($s['st']) {
            'ontime'   => 'On Time',
            'delayed'  => 'DELAYED +' . $s['delay'] . 'm',
            'boarding' => 'BOARDING',
            'cancelled'=> 'CANCELLED',
            default    => 'On Time',
        };
        $deps[] = [
            'id'               => 'demo_' . $i . '_' . $ts,
            'track'            => $s['track'],
            'time'             => date('g:i', $ts),
            'time_24'          => date('H:i', $ts),
            'destination'      => $s['dest'],
            'agency'           => $s['agency'],
            'agency_name'      => $s['agency'] === 'MNR' ? 'Metro-North' : 'NJ Transit',
            'agency_color'     => $s['color'],
            'agency_text_color'=> '#ffffff',
            'line_name'        => $s['line'],
            'status'           => $status_label,
            'status_type'      => $s['st'],
            'delay_minutes'    => $s['delay'],
        ];
    }

    return $deps;
}
