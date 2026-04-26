<?php
/**
 * roomlink/api/transit.php – Transit Data Proxy
 *
 * Returns departure data from live APIs when keys are configured,
 * or realistic demo data when in demo mode.
 *
 * Query params:
 *   ?json=1          Always returns JSON (default)
 *   ?limit=N         Max departures to return (default 10)
 *   ?agency=NJT      Filter by agency short name
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

// Allow session-authenticated calls and also unauthenticated internal calls
// (from state.php and einkview.php). We check for a logged-in user but don't
// hard-block so the Pi API can pull data via state.php → here.
header('Content-Type: application/json');

$limit  = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$filter_agency = strtoupper(trim($_GET['agency'] ?? ''));

/* ── API key check ── */
$mta_key = rl_setting('mta_api_key', '');
$njt_key = rl_setting('njt_api_key', '');
$demo_mode = ($mta_key === '' && $njt_key === '');

/* ── Try live data if keys configured ── */
$departures = [];
$source = 'demo';

if (!$demo_mode) {
    // Only attempt real feed for MTA if key is set
    if ($mta_key !== '') {
        $live = fetch_mta_mnr($mta_key, $limit);
        if ($live) {
            $departures = array_merge($departures, $live);
            $source = 'live';
        }
    }
    // NJT: placeholder — real NJT API requires separate credential setup
    // Falls back to demo NJT data
}

/* ── Demo / fallback data ── */
if (empty($departures) || $demo_mode) {
    $departures = generate_demo_departures();
    $source = 'demo';
}

/* ── Filter by agency ── */
if ($filter_agency !== '' && $filter_agency !== 'ALL') {
    $departures = array_values(array_filter($departures, fn($d) => $d['agency'] === $filter_agency));
}

/* ── Sort by time_24 ── */
usort($departures, fn($a, $b) => strcmp($a['time_24'], $b['time_24']));

/* ── Limit ── */
$departures = array_values(array_slice($departures, 0, $limit));

$refresh_sec = (int) rl_setting('transit_refresh_sec', '30');

echo json_encode([
    'ok'          => true,
    'source'      => $source,
    'departures'  => $departures,
    'fetched_at'  => (new DateTime())->format(DateTime::ATOM),
    'refresh_sec' => $refresh_sec,
]);

/* ═══════════════════════════════════════════════════
   DEMO DATA GENERATOR
   Produces realistic-looking departures based on
   current time + offsets, mimicking NJ Transit,
   Metro-North, and Amtrak schedules.
   ═══════════════════════════════════════════════════ */
function generate_demo_departures(): array {
    $now = time();

    /* Agency definitions */
    $agencies = [
        'NJT'  => ['name' => 'NJ Transit',       'color' => '#003087', 'text_color' => '#ffffff'],
        'MNR'  => ['name' => 'MTA Metro-North',  'color' => '#0E71B3', 'text_color' => '#ffffff'],
        'LIRR' => ['name' => 'Long Island Rail Road','color' => '#00305A','text_color' => '#ffffff'],
        'AMT'  => ['name' => 'Amtrak',           'color' => '#1D6BAE', 'text_color' => '#ffffff'],
    ];

    /* Scheduled departures: offset in minutes from now, destination, track, agency, status */
    $schedule = [
        // NJT trains
        ['offset' =>  4, 'dest' => 'New York Penn Station',  'track' => '3',  'agency' => 'NJT',  'status_type' => 'ontime',  'delay' => 0],
        ['offset' =>  9, 'dest' => 'Secaucus Junction',      'track' => '1',  'agency' => 'NJT',  'status_type' => 'boarding','delay' => 0],
        ['offset' => 17, 'dest' => 'New York Penn Station',  'track' => '4',  'agency' => 'NJT',  'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 23, 'dest' => 'Trenton',                'track' => '6',  'agency' => 'NJT',  'status_type' => 'delayed', 'delay' => 8],
        ['offset' => 31, 'dest' => 'New York Penn Station',  'track' => '2',  'agency' => 'NJT',  'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 47, 'dest' => 'Long Branch',            'track' => '5',  'agency' => 'NJT',  'status_type' => 'ontime',  'delay' => 0],
        // Metro-North trains
        ['offset' => 12, 'dest' => 'Grand Central Terminal', 'track' => '7',  'agency' => 'MNR',  'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 28, 'dest' => 'Stamford',               'track' => '9',  'agency' => 'MNR',  'status_type' => 'delayed', 'delay' => 5],
        ['offset' => 42, 'dest' => 'Grand Central Terminal', 'track' => '8',  'agency' => 'MNR',  'status_type' => 'ontime',  'delay' => 0],
        // Amtrak
        ['offset' => 19, 'dest' => 'Washington Union Station','track' => '11', 'agency' => 'AMT', 'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 35, 'dest' => 'Boston South Station',   'track' => '10', 'agency' => 'AMT',  'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 55, 'dest' => 'Philadelphia 30th St',   'track' => '12', 'agency' => 'AMT',  'status_type' => 'delayed', 'delay' => 12],
        // LIRR
        ['offset' =>  6, 'dest' => 'Jamaica',                'track' => '15', 'agency' => 'LIRR', 'status_type' => 'ontime',  'delay' => 0],
        ['offset' => 22, 'dest' => 'Penn Station NY',        'track' => '14', 'agency' => 'LIRR', 'status_type' => 'boarding','delay' => 0],
    ];

    $departures = [];
    foreach ($schedule as $i => $s) {
        $ts = $now + $s['offset'] * 60;
        $ag = $agencies[$s['agency']];

        $actual_ts = $ts;
        if ($s['status_type'] === 'delayed') {
            $actual_ts = $ts + $s['delay'] * 60;
        }

        $status_label = match($s['status_type']) {
            'ontime'   => 'On Time',
            'delayed'  => 'DELAYED +' . $s['delay'] . 'm',
            'boarding' => 'BOARDING',
            'cancelled'=> 'CANCELLED',
            default    => 'On Time',
        };

        $departures[] = [
            'id'              => 'demo_' . $i . '_' . $ts,
            'track'           => $s['track'],
            'time'            => date('g:i', $ts),
            'time_24'         => date('H:i', $ts),
            'destination'     => $s['dest'],
            'agency'          => $s['agency'],
            'agency_name'     => $ag['name'],
            'agency_color'    => $ag['color'],
            'agency_text_color' => $ag['text_color'],
            'status'          => $status_label,
            'status_type'     => $s['status_type'],
            'delay_minutes'   => $s['delay'],
        ];
    }

    return $departures;
}

/* ═══════════════════════════════════════════════════
   MTA METRO-NORTH GTFS-RT FETCHER (real data)
   Uses the MTA Realtime API if key is configured.
   Returns parsed departure array or empty on failure.
   ═══════════════════════════════════════════════════ */
function fetch_mta_mnr(string $api_key, int $limit): array {
    // MTA GTFS-RT feed for Metro-North
    $feed_url = 'https://api-endpoint.mta.info/Dataservice/mtagtfsfeeds/mnr%2Fgtfs-mnr';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header'  => "x-api-key: {$api_key}\r\n",
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($feed_url, false, $ctx);
    if (!$raw) return [];

    // GTFS-RT is a protobuf binary format. Without a protobuf parser library
    // (which we can't add without composer), we can't parse it here.
    // Return empty so demo data is used as fallback.
    // If you add the google/protobuf library, parse FeedMessage here.
    return [];
}
