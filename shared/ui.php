<?php
/**
 * shared/ui.php
 * Reusable UI rendering helpers.
 * Every page should call ui_head() / ui_sidebar() / ui_end().
 */

/**
 * Render the full <head> and opening <body> / app shell.
 *
 * @param string $page_title   Title shown in <title> and page header
 * @param string $app_slug     Which app we're in (for sidebar active state)
 * @param array  $nav_items    Sidebar nav: [['icon'=>'','label'=>'','href'=>'','active'=>bool], …]
 * @param string $app_heading  H2 shown in sidebar header (defaults to app name)
 * @param string $header_icon  Material icon for the sidebar header logo
 */
function ui_head(
    string $page_title,
    string $app_slug    = '',
    string $app_heading = '',
    string $header_icon = 'home'
): void {
    $theme = ''; // JS will apply from localStorage before paint
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
  </script>
</head>
<body>
<div class="app">
<?php
}

/**
 * Render the sidebar.
 */
function ui_sidebar(
    string $app_heading,
    string $header_icon,
    array  $nav_items,
    string $user_logout_url = ''
): void {
    $user = current_user();
    $initials = strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'U', 0, 2));
?>
  <!-- Mobile overlay -->
  <div id="sidebar-overlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:49;"></div>

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
      <a href="<?= htmlspecialchars($user_logout_url ?: APP_URL . '/id/auth/logout.php') ?>"
         class="user-info" style="text-decoration:none;">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-details">
          <div class="user-name truncate"><?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></div>
          <div class="user-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></div>
        </div>
        <span class="material-symbols-outlined" style="color:#475569;font-size:1rem;margin-left:auto;">logout</span>
      </a>
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
?>
    <div class="page-header">
      <div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <?php if ($breadcrumb): ?>
        <div class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></div>
        <?php endif; ?>
      </div>
      <div class="header-actions">
        <button id="theme-toggle" class="btn btn-ghost btn-sm" title="Toggle theme">
          <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
        </button>
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
        $type    = htmlspecialchars($flash['type'] ?? 'info');
        $message = htmlspecialchars($flash['message'] ?? '');
        $icons   = ['info' => 'info', 'success' => 'check_circle',
                    'warning' => 'warning', 'danger' => 'error'];
        $icon    = $icons[$flash['type'] ?? 'info'] ?? 'info';
        echo "<div class=\"alert alert-{$type}\" data-auto-dismiss=\"5000\">";
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
    $icons = ['info' => 'info', 'success' => 'check_circle',
              'warning' => 'warning', 'danger' => 'error'];
    $icon  = $icon ?: ($icons[$type] ?? 'info');
    echo "<div class=\"alert alert-{$type}\">";
    echo "  <span class=\"material-symbols-outlined\">" . htmlspecialchars($icon) . "</span>";
    echo "  <span class=\"alert-text\">" . htmlspecialchars($message) . "</span>";
    if ($dismissible) {
        echo "  <button class=\"alert-close\"><span class=\"material-symbols-outlined\">close</span></button>";
    }
    echo "</div>";
}

/**
 * Open a card.
 */
function ui_card_open(string $icon, string $title, string $extra_header_html = ''): void {
    echo '<div class="card">';
    echo '  <div class="card-header">';
    echo '    <span class="material-symbols-outlined">' . htmlspecialchars($icon) . '</span>';
    echo '    <h3>' . htmlspecialchars($title) . '</h3>';
    if ($extra_header_html) echo $extra_header_html;
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
