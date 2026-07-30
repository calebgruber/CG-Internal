<?php
/**
 * shared/ui.php
 * Reusable UI rendering helpers.
 * Every page should call ui_head() / ui_sidebar() / ui_end().
 */

/* ── Internal helpers ────────────────────────────── */

/**
 * Static store for current app context (heading + icon).
 * ui_sidebar() writes; ui_page_header() reads.
 */
function _ui_context(?string $set_heading = null, ?string $set_icon = null): array {
    static $heading = '', $icon = '';
    if ($set_heading !== null) $heading = $set_heading;
    if ($set_icon    !== null) $icon    = $set_icon;
    return ['heading' => $heading, 'icon' => $icon];
}

/**
 * Map a Material icon name → a hex accent color for cards.
 */
function _card_accent_color(string $icon): string {
    static $map = [
        // blue — identity / info
        'apps'                 => '#3b82f6',
        'person'               => '#3b82f6',
        'manage_accounts'      => '#3b82f6',
        'mail'                 => '#3b82f6',
        'sticky_note_2'        => '#3b82f6',
        // green — education / success / users
        'school'               => '#10b981',
        'check_circle'         => '#10b981',
        'group'                => '#10b981',
        // amber — actions / attention / notifications
        'bolt'                 => '#f59e0b',
        'add_alert'            => '#f59e0b',
        'engineering'          => '#f59e0b',
        'calendar_today'       => '#f59e0b',
        'task_alt'             => '#f59e0b',
        'pending'              => '#f59e0b',
        // indigo — settings / data
        'settings'             => '#6366f1',
        'storage'              => '#6366f1',
        'history'              => '#6366f1',
        // violet — time / calendar
        'calendar_month'       => '#8b5cf6',
        'event_upcoming'       => '#8b5cf6',
        // red — urgency / danger / assignments
        'notifications_active' => '#ef4444',
        'assignment'           => '#ef4444',
        // slate — neutral lists
        'list'                 => '#64748b',
        // WMATA colors
        'train'                => '#003DA5',
        'directions_transit'   => '#BF0D3E',
        'calculate'            => '#10b981',
        'folder'               => '#f59e0b',
        'dashboard'            => '#6366f1',
        'directions_railway'   => '#919D9D',
        'bar_chart'            => '#003DA5',
        'upload_file'          => '#f59e0b',
        'extension'            => '#6366f1',
        'add_circle'           => '#6366f1',
    ];
    return $map[$icon] ?? '#3b82f6';
}

/**
 * Convert a hex color to "R,G,B" string + pick white/black contrast text.
 * Returns [rgb_string, '#ffffff' or '#000000'].
 */
function _hex_to_rgb_and_text(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    // Perceived luminance (ITU-R BT.601)
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return [$r . ',' . $g . ',' . $b, $lum > 0.55 ? '#000000' : '#ffffff'];
}

/**
 * Return accent color info for a given alert type.
 * Returns [hex_color, rgb_string, text_on_solid].
 */
function _alert_accent(string $type): array {
    static $map = [
        'info'    => ['#3b82f6', '59,130,246',  '#ffffff'],
        'success' => ['#10b981', '16,185,129',  '#ffffff'],
        'warning' => ['#f59e0b', '245,158,11',  '#000000'],
        'danger'  => ['#ef4444', '239,68,68',   '#ffffff'],
    ];
    return $map[$type] ?? $map['info'];
}

/**
 * Render the full <head> and opening <body> / app shell.
 *
 * @param string $page_title   Title shown in <title> and page header
 * @param string $app_slug     Which app we're in (for sidebar active state)
 * @param string $app_heading  H2 shown in sidebar header (defaults to app name)
 * @param string $header_icon  Material icon for the sidebar header logo
 */
function ui_head(
    string $page_title,
    string $app_slug    = '',
    string $app_heading = '',
    string $header_icon = 'home'
): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= htmlspecialchars($page_title) ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/shared/assets/style.css">
  <script>
    /* Apply saved theme before first paint to prevent flash */
    (function(){var t=localStorage.getItem('cg-theme')||
    (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);})();
    /* Complete page loader when DOM is ready */
    document.addEventListener('DOMContentLoaded',function(){var l=document.getElementById('page-loader');if(l)l.classList.add('pg-done');});
  </script>
</head>
<body>
<div id="page-loader"></div>
<div class="app">
<?php
}

/**
 * Render the sidebar — also outputs the fixed top navbar above everything.
 */
function ui_sidebar(
    string $app_heading,
    string $header_icon,
    array  $nav_items,
    string $user_logout_url = ''
): void {
    // Store context so ui_page_header() can show the app badge
    _ui_context($app_heading, $header_icon);

    $user     = current_user();
    $initials = strtoupper(mb_substr($user['display_name'] ?? $user['username'] ?? 'U', 0, 2, 'UTF-8'));
    $logout   = htmlspecialchars($user_logout_url ?: APP_URL . '/id/auth/logout');
?>
  <!-- ── Top navigation bar ─────────────────────────────── -->
  <div class="topbar">
    <button id="mobile-menu-btn" class="topbar-btn mobile-menu-btn" title="Open menu" aria-label="Toggle navigation">
      <span class="material-symbols-outlined">menu</span>
    </button>
    <div class="topbar-left">
      <a href="<?= APP_URL ?>/" class="topbar-launcher">
        <span class="material-symbols-outlined">home</span>
        Launcher
      </a>
      <span class="topbar-sep">›</span>
      <span class="topbar-app">
        <span class="material-symbols-outlined"><?= htmlspecialchars($header_icon) ?></span>
        <?= htmlspecialchars($app_heading) ?>
      </span>
    </div>

    <div class="topbar-right">
      <button id="theme-toggle" class="topbar-btn" title="Toggle theme">
        <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
      </button>
      <?php if ($user): ?>
      <span class="topbar-user">
        <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
        <span class="topbar-username"><?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></span>
      </span>
      <a href="<?= $logout ?>" class="topbar-btn topbar-logout" title="Sign out">
        <span class="material-symbols-outlined">logout</span>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mobile overlay -->
  <div id="sidebar-overlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:49;top:2.75rem;"></div>

  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>
        <span class="material-symbols-outlined app-logo"><?= htmlspecialchars($header_icon) ?></span>
        <?= htmlspecialchars($app_heading) ?>
      </h2>
    </div>

    <nav>
<?php foreach ($nav_items as $item):
    $active = $item['active'] ?? false;
?>
<?php if (isset($item['section'])): ?>
      <div class="sidebar-section"><?= htmlspecialchars($item['section']) ?></div>
<?php else: ?>
      <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>"
         class="nav-item<?= $active ? ' active' : '' ?>">
        <span class="material-symbols-outlined"><?= htmlspecialchars($item['icon'] ?? 'circle') ?></span>
        <?= htmlspecialchars($item['label'] ?? '') ?>
      </a>
<?php endif; ?>
<?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <?php if ($user): ?>
      <div class="user-info" style="cursor:default;">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-details">
          <div class="user-name truncate"><?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></div>
          <div class="user-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="content">
<?php
}

/**
 * Render the page header (inside main.content).
 *
 * @param string $title
 * @param string $breadcrumb  Optional subtitle / breadcrumb text
 * @param string $extra_html  HTML to inject on the right side (action buttons etc.)
 */
function ui_page_header(string $title, string $breadcrumb = '', string $extra_html = ''): void {
    $ctx         = _ui_context();
    $app_heading = $ctx['heading'];
    $app_icon    = $ctx['icon'];
?>
    <div class="page-header">
      <div>
        <?php if ($app_heading): ?>
        <div class="app-context-badge">
          <span class="material-symbols-outlined"><?= htmlspecialchars($app_icon) ?></span>
          <?= htmlspecialchars($app_heading) ?>
        </div>
        <?php endif; ?>
        <h1><?= htmlspecialchars($title) ?></h1>
        <?php if ($breadcrumb): ?>
        <div class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></div>
        <?php endif; ?>
      </div>
      <div class="header-actions">
        <?= $extra_html ?>
      </div>
    </div>
<?php
}

/**
 * Render flash/session alert messages.
 * Reads from $_SESSION['flash'] array and clears it.
 */
function ui_flash(): void {
    if (empty($_SESSION['flash'])) return;
    echo '<div class="alerts">';
    foreach ($_SESSION['flash'] as $flash) {
        $type    = in_array($flash['type'] ?? '', ['info','success','warning','danger'])
                   ? $flash['type'] : 'info';
        $message = htmlspecialchars($flash['message'] ?? '');
        $icons   = ['info' => 'info', 'success' => 'check_circle',
                    'warning' => 'warning', 'danger' => 'error'];
        $icon    = $icons[$type] ?? 'info';
        [$color, $rgb, $text_on] = _alert_accent($type);
        $vars = 'style="--alert-accent:' . $color
              . ';--alert-accent-rgb:' . $rgb
              . ';--alert-text-on-solid:' . $text_on . '"';
        echo "<div class=\"alert alert-{$type}\" {$vars} data-auto-dismiss=\"5000\">";
        echo "  <span class=\"material-symbols-outlined\">{$icon}</span>";
        echo "  <span class=\"alert-text\">{$message}</span>";
        echo "  <button class=\"alert-close\"><span class=\"material-symbols-outlined\">close</span></button>";
        echo '</div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

/**
 * Queue a flash message for the next request.
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Render an inline alert (not from session).
 */
function ui_alert(string $type, string $message, bool $dismissible = false, string $icon = ''): void {
    $type = in_array($type, ['info','success','warning','danger']) ? $type : 'info';
    $icons = ['info' => 'info', 'success' => 'check_circle',
              'warning' => 'warning', 'danger' => 'error'];
    $icon  = $icon ?: ($icons[$type] ?? 'info');
    [$color, $rgb, $text_on] = _alert_accent($type);
    $vars = 'style="--alert-accent:' . $color
          . ';--alert-accent-rgb:' . $rgb
          . ';--alert-text-on-solid:' . $text_on . '"';
    echo "<div class=\"alert alert-{$type}\" {$vars}>";
    echo "  <span class=\"material-symbols-outlined\">" . htmlspecialchars($icon) . "</span>";
    echo "  <span class=\"alert-text\">" . htmlspecialchars($message) . "</span>";
    if ($dismissible) {
        echo "  <button class=\"alert-close\"><span class=\"material-symbols-outlined\">close</span></button>";
    }
    echo "</div>";
}

/**
 * Open a card.
 *
 * @param string $icon              Material Symbol icon name
 * @param string $title             Card title (shown in tab)
 * @param string $extra_header_html HTML injected to the right of the tab (action buttons etc.)
 * @param string $color             Hex accent color (auto-selected from icon map if empty)
 */
function ui_card_open(string $icon, string $title, string $extra_header_html = '', string $color = ''): void {
    if ($color === '') $color = _card_accent_color($icon);
    [$rgb, $text_on] = _hex_to_rgb_and_text($color);
    $card_style = 'border-left:3px solid ' . $color
                . ';--card-accent:' . $color
                . ';--card-accent-rgb:' . $rgb
                . ';--card-text-on-solid:' . $text_on;
    echo '<div class="card" style="' . htmlspecialchars($card_style) . '">';
    echo '  <div class="card-top">';
    echo '    <div class="card-tab">';
    echo '      <span class="material-symbols-outlined">' . htmlspecialchars($icon) . '</span>';
    echo '      <h3>' . htmlspecialchars($title) . '</h3>';
    echo '    </div>';
    if ($extra_header_html) {
        echo '    <div class="card-header-actions">' . $extra_header_html . '</div>';
    }
    echo '  </div>';
    echo '  <div class="card-body">';
}

/**
 * Close a card.
 */
function ui_card_close(string $footer_html = ''): void {
    echo '  </div>';
    if ($footer_html) {
        echo '<div class="card-footer">' . $footer_html . '</div>';
    }
    echo '</div>';
}

/**
 * Close the app shell and write the shared script tag.
 */
function ui_end(): void {
?>
  </main>
</div>
<script src="<?= APP_URL ?>/shared/assets/app.js"></script>
</body>
</html>
<?php
}

/**
 * Render a status badge.
 */
function ui_badge(string $text, string $type = 'neutral'): string {
    return '<span class="badge badge-' . htmlspecialchars($type) . '">' . htmlspecialchars($text) . '</span>';
}

/**
 * Render a priority indicator.
 */
function ui_priority(string $priority): string {
    $labels = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
    $label  = $labels[$priority] ?? ucfirst($priority);
    return '<span class="flex gap-1" style="align-items:center">
              <span class="priority-dot priority-' . htmlspecialchars($priority) . '"></span>
              ' . htmlspecialchars($label) . '
            </span>';
}
