<?php
/**
 * wmata/inc/helpers.php
 * Shared helper functions for the WMATA Minecraft Tracker app.
 *
 * Include at the top of every WMATA page (after shared/config.php):
 *   require_once __DIR__ . '/inc/helpers.php';        (from wmata/)
 *   require_once __DIR__ . '/../inc/helpers.php';     (from wmata/sub-dir)
 */

/**
 * Sanitised icon name (alphanumeric, hyphens, underscores only).
 */
function _wmata_safe_name(string $name): string {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
}

/**
 * Full filesystem path for a named .icon file.
 */
function _wmata_icon_path(string $name): string {
    return __DIR__ . '/../assets/icons/' . _wmata_safe_name($name) . '.icon';
}

/**
 * Returns true if the icon file exists on disk.
 */
function wmata_icon_exists(string $name): bool {
    return is_file(_wmata_icon_path($name));
}

/**
 * URL that serves the icon through icon.php (correct MIME type, cached).
 */
function wmata_icon_url(string $name): string {
    return APP_URL . '/wmata/icon?name=' . urlencode(_wmata_safe_name($name));
}

/**
 * Render an <img> tag for a named icon at the given pixel size.
 * Returns empty string if the icon file does not exist.
 *
 * @param string $name   Icon slug, e.g. 'metro', 'line-rd'
 * @param int    $size   Width/height in pixels
 * @param string $alt    Alt text (defaults to $name)
 * @param string $class  Extra CSS classes on the <img>
 * @param string $style  Extra inline CSS
 */
function wmata_icon_img(
    string $name,
    int    $size  = 24,
    string $alt   = '',
    string $class = '',
    string $style = ''
): string {
    if (!wmata_icon_exists($name)) return '';
    $url   = htmlspecialchars(wmata_icon_url($name));
    $alt   = htmlspecialchars($alt ?: $name);
    $cls   = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
    $inl   = 'width:' . $size . 'px;height:' . $size . 'px;object-fit:contain;vertical-align:middle;' . $style;
    return '<img src="' . $url . '" alt="' . $alt . '"' . $cls
         . ' style="' . htmlspecialchars($inl, ENT_QUOTES) . '" loading="lazy">';
}

/**
 * Render a WMATA line badge for a given abbreviation.
 *
 * If `assets/icons/line-{lc_abbr}.icon` exists → shows the icon image only,
 * no bubble or pill wrapper (the icon itself carries the branding).
 * Otherwise → falls back to a solid colored pill with the abbreviation text.
 *
 * @param string $abbr   Line abbreviation, e.g. 'RD', 'BL'
 * @param string $color  Hex color for the line, e.g. '#BF0D3E'
 * @param int    $size   Icon size in px (used when icon file exists)
 */
function wmata_line_badge(string $abbr, string $color, int $size = 22): string {
    $slug   = 'line-' . strtolower($abbr);
    $safe_a = htmlspecialchars($abbr);

    if (wmata_icon_exists($slug)) {
        // Icon exists → display it bare, no bubble
        return wmata_icon_img($slug, $size, $abbr . ' Line', '',
            'vertical-align:middle;flex-shrink:0');
    }

    // Fallback: solid colored pill
    $safe_c = htmlspecialchars($color);
    return '<span style="display:inline-flex;align-items:center;'
         . 'padding:2px 7px;border-radius:4px;'
         . 'background:' . $safe_c . ';color:#fff;'
         . 'font-size:.6875rem;font-weight:700;line-height:1.4;'
         . 'vertical-align:middle">' . $safe_a . '</span>';
}

/**
 * Render the WMATA metro logo.
 *
 * If `assets/icons/metro.icon` exists → returns an <img> tag.
 * Otherwise → returns a Material Symbols 'train' icon span.
 *
 * @param int    $size   Pixel size
 * @param string $style  Extra inline CSS on the element
 */
function wmata_metro_logo(int $size = 28, string $style = ''): string {
    if (wmata_icon_exists('metro')) {
        return wmata_icon_img('metro', $size, 'WMATA Metro', '', $style);
    }
    $sz  = round($size * 0.05, 4);   // px → approximate rem
    $css = 'font-size:' . $size . 'px;vertical-align:middle;' . $style;
    return '<span class="material-symbols-outlined" style="'
         . htmlspecialchars($css, ENT_QUOTES) . '">train</span>';
}

/**
 * Generate an SVG platform diagram automatically from station data.
 *
 * Renders a top-down architectural/drafting style view of a WMATA station
 * platform, including:
 *  - Track rails on both sides
 *  - Platform surface with block grid overlay
 *  - Measurement annotation arrows (total length, block count)
 *  - Standard feature markers (entrance, elevator, escalator positions)
 *  - Station name label
 *
 * @param string $station_name    Station name for the title
 * @param int    $platform_blocks Number of platform blocks (default 40)
 * @param array  $lines           Array of line rows from wmata_lines (for color strip)
 * @return string                 Complete SVG markup
 */
function wmata_platform_diagram(
    string $station_name,
    int    $platform_blocks = 40,
    array  $lines           = []
): string {
    $blocks    = max(4, $platform_blocks);
    $blockPx   = 14;          // SVG units per block
    $platW     = $blocks * $blockPx;
    $platH     = 40;          // platform width in SVG units
    $trackH    = 10;          // height of each track band
    $margin    = 60;          // left/right margin for annotations
    $topPad    = 70;          // top padding for title + line strip
    $bottomPad = 55;          // bottom for measurement row
    $totalW    = $platW + $margin * 2;
    $totalH    = $topPad + $trackH + $platH + $trackH + $bottomPad;

    // Y-coordinates
    $yTrackTop    = $topPad;
    $yPlatTop     = $yTrackTop + $trackH;
    $yPlatBot     = $yPlatTop + $platH;
    $yTrackBot    = $yPlatBot;
    $yMeasurement = $yTrackBot + $trackH + 18;
    $xPlatLeft    = $margin;
    $xPlatRight   = $margin + $platW;

    // Pre-computed legend row values
    $legendY     = $totalH - 12;
    $legendTextY = $totalH - 9;
    $legendEdgeY = $totalH - 17;

    // Feature markers at each end and midpoint
    $featureY  = $yPlatTop + $platH / 2;
    $features  = [
        ['x' => $xPlatLeft + 4,            'type' => 'entrance',  'label' => 'ENT'],
        ['x' => $xPlatLeft + $platW * 0.25,'type' => 'escalator', 'label' => 'ESC'],
        ['x' => $xPlatLeft + $platW * 0.30,'type' => 'elevator',  'label' => 'ELV'],
        ['x' => $xPlatLeft + $platW * 0.50,'type' => 'kiosk',     'label' => 'KSK'],
        ['x' => $xPlatLeft + $platW * 0.70,'type' => 'escalator', 'label' => 'ESC'],
        ['x' => $xPlatLeft + $platW * 0.75,'type' => 'elevator',  'label' => 'ELV'],
        ['x' => $xPlatRight - 4,           'type' => 'entrance',  'label' => 'ENT'],
    ];
    $featureColors = [
        'entrance'  => '#003DA5',
        'escalator' => '#ED8B00',
        'elevator'  => '#10b981',
        'kiosk'     => '#6366f1',
    ];

    // Build legend items programmatically
    $legendItems = [
        ['color' => '#003DA5', 'label' => 'ENT=Entrance',   'shape' => 'circle'],
        ['color' => '#ED8B00', 'label' => 'ESC=Escalator',  'shape' => 'circle'],
        ['color' => '#10b981', 'label' => 'ELV=Elevator',   'shape' => 'circle'],
        ['color' => '#6366f1', 'label' => 'KSK=Kiosk',      'shape' => 'circle'],
        ['color' => '#FFD100', 'label' => '= Platform Edge', 'shape' => 'rect'],
    ];

    // Line color strip
    $lineStrip = '';
    if ($lines) {
        $stripW = $platW / count($lines);
        foreach ($lines as $i => $l) {
            $lineStrip .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="5" fill="%s"/>',
                $xPlatLeft + $i * $stripW,
                $yPlatTop - 7,
                $stripW,
                htmlspecialchars($l['color'])
            );
        }
    }

    // Block grid lines
    $gridLines = '';
    for ($b = 0; $b <= $blocks; $b++) {
        $x       = $xPlatLeft + $b * $blockPx;
        $opacity = ($b % 5 === 0) ? '0.25' : '0.10';
        $strokeW = ($b % 5 === 0) ? '0.8'  : '0.4';
        $gridLines .= sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#1e3a5f" stroke-width="%s" opacity="%s"/>',
            $x, $yPlatTop, $x, $yPlatBot, $strokeW, $opacity
        );
    }
    for ($row = 0; $row <= $platH; $row += 5) {
        $y = $yPlatTop + $row;
        $gridLines .= sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#1e3a5f" stroke-width="0.4" opacity="0.10"/>',
            $xPlatLeft, $y, $xPlatRight, $y
        );
    }

    // Feature markers
    $featureMarkup = '';
    foreach ($features as $f) {
        $fc = $featureColors[$f['type']] ?? '#64748b';
        $featureMarkup .= sprintf(
            '<circle cx="%.2f" cy="%.2f" r="5" fill="%s" opacity="0.9"/>',
            $f['x'], $featureY, $fc
        );
        $featureMarkup .= sprintf(
            '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="monospace" font-size="4" fill="white" font-weight="bold">%s</text>',
            $f['x'], $featureY + 1.5, $f['label']
        );
    }

    // Measurement annotation
    $midX        = $xPlatLeft + $platW / 2;
    $measurement = sprintf(
        '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#1e3a5f" stroke-width="1" marker-start="url(#arr)" marker-end="url(#arr)"/>',
        $xPlatLeft, $yMeasurement, $xPlatRight, $yMeasurement
    );
    $measurement .= sprintf(
        '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#1e3a5f" stroke-width="1"/>',
        $xPlatLeft, $yMeasurement - 4, $xPlatLeft, $yMeasurement + 4
    );
    $measurement .= sprintf(
        '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#1e3a5f" stroke-width="1"/>',
        $xPlatRight, $yMeasurement - 4, $xPlatRight, $yMeasurement + 4
    );
    $measurement .= sprintf(
        '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="monospace" font-size="7.5" fill="#1e3a5f" font-weight="bold">%d blocks</text>',
        $midX, $yMeasurement + 12, $blocks
    );

    // Block ruler labels every 5 blocks
    $blockLabels = '';
    for ($b = 0; $b <= $blocks; $b += 5) {
        $blockLabels .= sprintf(
            '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="monospace" font-size="5" fill="#64748b">%d</text>',
            $xPlatLeft + $b * $blockPx, $yPlatTop - 10, $b
        );
    }

    // Legend – positions computed from item index × step
    $legendStep  = 90;
    $legendStart = $xPlatLeft;
    $legendMarkup = '';
    foreach ($legendItems as $i => $item) {
        $lx = $legendStart + $i * $legendStep;
        if ($item['shape'] === 'rect') {
            $legendMarkup .= sprintf(
                '<rect x="%.2f" y="%d" width="10" height="8" fill="%s" opacity="0.8"/>',
                $lx, $legendEdgeY, $item['color']
            );
        } else {
            $legendMarkup .= sprintf(
                '<circle cx="%.2f" cy="%d" r="4" fill="%s"/>',
                $lx + 4, $legendY, $item['color']
            );
        }
        $legendMarkup .= sprintf(
            '<text x="%.2f" y="%d" font-family="monospace" font-size="6" fill="#334155">%s</text>',
            $lx + 12, $legendTextY, htmlspecialchars($item['label'])
        );
    }

    $safeName  = htmlspecialchars($station_name);
    $trackTopY = $yTrackTop + 7;
    $trackBotY = $yTrackBot + 7;

    // Assemble SVG using concatenation (no heredoc/str_replace)
    $svg  = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d"', $totalW, $totalH, $totalW);
    $svg .= ' style="max-width:100%;height:auto;display:block;font-family:monospace">';
    $svg .= '<defs>';
    $svg .= '<marker id="arr" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">';
    $svg .= '<path d="M0,0 L6,3 L0,6 Z" fill="#1e3a5f"/></marker>';
    $svg .= '<pattern id="hatch" patternUnits="userSpaceOnUse" width="4" height="4" patternTransform="rotate(45)">';
    $svg .= '<line x1="0" y1="0" x2="0" y2="4" stroke="#64748b" stroke-width="0.5" opacity="0.3"/>';
    $svg .= '</pattern></defs>';
    // Background
    $svg .= sprintf('<rect width="%d" height="%d" fill="#f8fafc"/>', $totalW, $totalH);
    // Title
    $svg .= sprintf('<text x="%d" y="18" font-family="sans-serif" font-size="11" font-weight="700" fill="#0f172a">%s \u{2013} Platform Diagram</text>', $xPlatLeft, $safeName);
    $svg .= sprintf('<text x="%d" y="30" font-family="monospace" font-size="7.5" fill="#64748b">Auto-generated \u{00B7} Scale: 1 block = %dpx \u{00B7} Length: %d blocks</text>', $xPlatLeft, $blockPx, $blocks);
    // Direction labels
    $svg .= sprintf('<text x="%d" y="44" text-anchor="end" font-family="sans-serif" font-size="7.5" fill="#64748b">\u{2190} Westbound / Northbound</text>', $xPlatRight);
    $svg .= sprintf('<text x="%d" y="44" text-anchor="start" font-family="sans-serif" font-size="7.5" fill="#64748b">Eastbound / Southbound \u{2192}</text>', $xPlatLeft);
    // Ruler labels
    $svg .= $blockLabels;
    // Line color strip
    $svg .= $lineStrip;
    // North track
    $svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="url(#hatch)" stroke="#334155" stroke-width="0.75"/>', $xPlatLeft, $yTrackTop, $platW, $trackH);
    $svg .= sprintf('<text x="%d" y="%d" font-family="monospace" font-size="5.5" fill="#475569">TRACK (NB/EB)</text>', $xPlatLeft, $trackTopY);
    // Platform surface
    $svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="#e2e8f0" stroke="#334155" stroke-width="1"/>', $xPlatLeft, $yPlatTop, $platW, $platH);
    // Safety edge strips
    $svg .= sprintf('<rect x="%d" y="%d" width="%d" height="3" fill="#FFD100" opacity="0.8"/>', $xPlatLeft, $yPlatTop, $platW);
    $svg .= sprintf('<rect x="%d" y="%d" width="%d" height="3" fill="#FFD100" opacity="0.8"/>', $xPlatLeft, $yPlatBot - 3, $platW);
    // Block grid
    $svg .= $gridLines;
    // Feature markers
    $svg .= $featureMarkup;
    // South track
    $svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="url(#hatch)" stroke="#334155" stroke-width="0.75"/>', $xPlatLeft, $yTrackBot, $platW, $trackH);
    $svg .= sprintf('<text x="%d" y="%d" font-family="monospace" font-size="5.5" fill="#475569">TRACK (SB/WB)</text>', $xPlatLeft, $trackBotY);
    // Measurement
    $svg .= $measurement;
    // Legend
    $svg .= $legendMarkup;
    $svg .= '</svg>';

    return $svg;
}
