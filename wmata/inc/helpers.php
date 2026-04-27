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
 * If `assets/icons/line-{lc_abbr}.icon` exists → shows the icon image
 * scaled to $size px, wrapped in a small pill with the line color border.
 * Otherwise → falls back to a solid colored pill with the abbreviation text.
 *
 * @param string $abbr   Line abbreviation, e.g. 'RD', 'BL'
 * @param string $color  Hex color for the line, e.g. '#BF0D3E'
 * @param int    $size   Icon size in px (used when icon file exists)
 */
function wmata_line_badge(string $abbr, string $color, int $size = 22): string {
    $slug   = 'line-' . strtolower($abbr);
    $safe_c = htmlspecialchars($color);
    $safe_a = htmlspecialchars($abbr);

    if (wmata_icon_exists($slug)) {
        // Icon present — render bare image only, no pill/border wrapper
        return wmata_icon_img($slug, $size, $abbr . ' Line', '',
            'border-radius:2px;flex-shrink:0;vertical-align:middle');
    }

    // Fallback: solid colored pill with abbreviation text
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
