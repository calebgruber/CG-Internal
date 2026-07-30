<?php
/**
 * roomlink/inc/helpers.php
 * Shared helpers for the RoomLink app.
 */

/**
 * Read a RoomLink setting (rl_* prefix) from the global settings table.
 */
function rl_setting(string $key, string $default = ''): string {
    return setting('rl_' . $key, $default);
}

/**
 * Build nav items array for the RoomLink sidebar.
 * Marks the item whose href matches $active.
 */
function rl_nav_items(string $active): array {
    $items = [
        ['icon' => 'dashboard',       'label' => 'Dashboard',     'href' => APP_URL . '/roomlink/'],
        ['icon' => 'departure_board', 'label' => 'Transit',       'href' => APP_URL . '/roomlink/transit'],
        ['icon' => 'lightbulb',       'label' => 'Lighting',      'href' => APP_URL . '/roomlink/lighting'],
        ['icon' => 'e_ink',           'label' => 'E-Ink Preview', 'href' => APP_URL . '/roomlink/einkview'],
        ['icon' => 'touch_app',       'label' => 'Controller',    'href' => APP_URL . '/roomlink/controller'],
        ['icon' => 'settings',        'label' => 'Settings',      'href' => APP_URL . '/roomlink/settings'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['href'] === APP_URL . $active);
    }
    unset($item);
    return $items;
}

/**
 * Returns an HTML badge for an agency (circular badge with initials).
 */
function rl_agency_badge(string $short, string $color, string $text_color): string {
    $s = htmlspecialchars($short);
    $c = htmlspecialchars($color);
    $t = htmlspecialchars($text_color);
    return '<span style="display:inline-flex;align-items:center;justify-content:center;'
         . 'width:36px;height:36px;border-radius:50%;background:' . $c . ';color:' . $t . ';'
         . 'font-weight:800;font-size:0.65rem;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">'
         . $s . '</span>';
}

/**
 * Return the brand color hex for a known agency short name.
 */
function rl_transit_color(string $agency_short): string {
    static $map = [
        'NJT'  => '#003087',
        'MNR'  => '#0E71B3',
        'LIRR' => '#00305A',
        'AMT'  => '#1D6BAE',
        'MTA'  => '#0039A6',
    ];
    return $map[strtoupper($agency_short)] ?? '#3b82f6';
}

/**
 * Fetch the current e-ink state row from the DB.
 * Returns array with keys: id, current_tab, display_mode, custom_text, last_updated, last_pi_poll
 */
function rl_eink_state(): array {
    try {
        $row = db()->query('SELECT * FROM roomlink_eink_state WHERE id = 1')->fetch();
        return $row ?: [
            'id'           => 1,
            'current_tab'  => 'transit',
            'display_mode' => 'normal',
            'custom_text'  => null,
            'last_updated' => null,
            'last_pi_poll' => null,
        ];
    } catch (PDOException $e) {
        return [
            'id'           => 1,
            'current_tab'  => 'transit',
            'display_mode' => 'normal',
            'custom_text'  => null,
            'last_updated' => null,
            'last_pi_poll' => null,
        ];
    }
}

/**
 * Return the URL that serves a named transit agency .icon file
 * through roomlink/icon.php (correct MIME type, cached).
 *
 * Icon files live in roomlink/assets/icons/<NAME>.icon
 * Expected names: MTA, NJT, AMTK, LIRR
 */
function rl_transit_icon_url(string $name): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
    return APP_URL . '/roomlink/icon?name=' . urlencode($safe);
}

/**
 * Returns true when the named icon file exists on disk.
 */
function rl_transit_icon_exists(string $name): bool {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
    return is_file(__DIR__ . '/../assets/icons/' . $safe . '.icon');
}

/**
 * Render an <img> tag for a transit agency icon.
 * Returns empty string when the icon file does not exist.
 *
 * @param string $name   Icon slug: MTA, NJT, AMTK, LIRR
 * @param int    $size   Width/height in pixels
 * @param string $alt    Alt text (defaults to $name)
 */
function rl_transit_icon_img(string $name, int $size = 44, string $alt = ''): string {
    if (!rl_transit_icon_exists($name)) return '';
    $url = htmlspecialchars(rl_transit_icon_url($name));
    $alt = htmlspecialchars($alt ?: $name);
    $css = 'width:' . $size . 'px;height:' . $size . 'px;object-fit:contain;vertical-align:middle;';
    return '<img src="' . $url . '" alt="' . $alt . '" style="' . $css . '" loading="lazy">';
}

/**
 * Map agency code used in transit feed → icon file name.
 * MNR trains are branded MTA; NJT → NJT; AMT/Amtrak → AMTK; LIRR → LIRR.
 */
function rl_agency_to_icon(string $agency): string {
    static $map = [
        'MNR'  => 'MTA',
        'MTA'  => 'MTA',
        'NJT'  => 'NJT',
        'AMT'  => 'AMTK',
        'AMTK' => 'AMTK',
        'LIRR' => 'LIRR',
    ];
    return $map[strtoupper($agency)] ?? strtoupper($agency);
}

/**
 * Update the current e-ink tab.
 */
function rl_set_eink_tab(string $tab): void {
    $allowed = ['transit', 'clock', 'weather', 'custom'];
    if (!in_array($tab, $allowed, true)) return;
    try {
        db()->prepare(
            'INSERT INTO roomlink_eink_state (id, current_tab) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE current_tab = VALUES(current_tab)'
        )->execute([$tab]);
    } catch (PDOException $e) { /* non-critical */ }
}
