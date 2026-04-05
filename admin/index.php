<?php
/**
 * admin/index.php – Global System Administration
 * URL: internal.calebgruber.me/admin/
 * Requires admin role.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('admin', true);

// ── Quick stats ─────────────────────────────────────────
try {
    $stats = [
        'users'       => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'active_apps' => (int) db()->query("SELECT COUNT(*) FROM apps WHERE is_active=1")->fetchColumn(),
        'assignments' => (int) db()->query("SELECT COUNT(*) FROM edu_assignments WHERE status != 'completed'")->fetchColumn(),
        'migrations'  => (int) db()->query('SELECT COUNT(*) FROM migrations')->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = ['users'=>0,'active_apps'=>0,'assignments'=>0,'migrations'=>0];
}

$maintenance = setting('maintenance_mode','0') === '1';

$nav_items = [
    ['icon'=>'dashboard',          'label'=>'Overview',    'href'=>APP_URL.'/admin/',                   'active'=>true],
    ['icon'=>'settings',           'label'=>'Settings',    'href'=>APP_URL.'/admin/settings.php'],
    ['icon'=>'storage',            'label'=>'Migrations',  'href'=>APP_URL.'/admin/migrations.php'],
    ['icon'=>'engineering',        'label'=>'Maintenance', 'href'=>APP_URL.'/admin/maintenance.php'],
    ['icon'=>'add_alert',          'label'=>'Alerts',      'href'=>APP_URL.'/admin/alerts.php'],
    ['section'=>'Sub-systems'],
    ['icon'=>'manage_accounts',    'label'=>'ID Admin',    'href'=>APP_URL.'/id/admin/'],
    ['icon'=>'school',             'label'=>'EDU Hub',     'href'=>APP_URL.'/edu/'],
];

ui_head('System Admin','admin','System Admin','admin_panel_settings');
ui_sidebar('System Admin','admin_panel_settings',$nav_items,APP_URL.'/id/auth/logout.php');

$actions = $maintenance ?
    '<span class="badge badge-danger" style="font-size:.875rem;padding:.375rem .75rem">
       <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle">engineering</span>
       Maintenance ON
     </span>' :
    '<span class="badge badge-success" style="font-size:.875rem;padding:.375rem .75rem">
       <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle">check_circle</span>
       Online
     </span>';

ui_page_header('System Overview','Global Administration',$actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php if ($maintenance): ?>
<div class="alerts">
  <div class="alert alert-warning" style="--alert-accent:#f59e0b;--alert-accent-rgb:245,158,11;--alert-text-on-solid:#000000">
    <span class="material-symbols-outlined">engineering</span>
    <span class="alert-text"><strong>Maintenance mode is active.</strong> Non-admin users are shown a maintenance page.</span>
    <a href="<?=APP_URL?>/admin/maintenance.php" class="btn btn-sm btn-ghost" style="margin-left:auto">Disable</a>
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon"><span class="material-symbols-outlined">group</span></div>
    <div class="stat-label">Users</div>
    <div class="stat-value"><?=$stats['users']?></div>
    <div class="stat-sub"><a href="<?=APP_URL?>/id/admin/users.php">Manage</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
      <span class="material-symbols-outlined">apps</span>
    </div>
    <div class="stat-label">Active Apps</div>
    <div class="stat-value"><?=$stats['active_apps']?></div>
    <div class="stat-sub"><a href="<?=APP_URL?>/id/admin/apps.php">Configure</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,.12);color:var(--danger)">
      <span class="material-symbols-outlined">assignment</span>
    </div>
    <div class="stat-label">Open Assignments</div>
    <div class="stat-value"><?=$stats['assignments']?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
      <span class="material-symbols-outlined">storage</span>
    </div>
    <div class="stat-label">DB Migrations</div>
    <div class="stat-value"><?=$stats['migrations']?></div>
    <div class="stat-sub"><a href="<?=APP_URL?>/admin/migrations.php">Run</a></div>
  </div>
</div>

<!-- Quick actions -->
<?php ui_card_open('bolt','Quick Actions'); ?>
  <div class="apps-grid">
    <a href="<?=APP_URL?>/admin/settings.php" class="app-tile">
      <span class="material-symbols-outlined">settings</span>
      <span class="app-tile-name">Settings</span>
    </a>
    <a href="<?=APP_URL?>/admin/migrations.php" class="app-tile">
      <span class="material-symbols-outlined">storage</span>
      <span class="app-tile-name">DB Migrations</span>
    </a>
    <a href="<?=APP_URL?>/admin/maintenance.php" class="app-tile">
      <span class="material-symbols-outlined">engineering</span>
      <span class="app-tile-name">Maintenance</span>
    </a>
    <a href="<?=APP_URL?>/admin/alerts.php" class="app-tile">
      <span class="material-symbols-outlined">add_alert</span>
      <span class="app-tile-name">Manage Alerts</span>
    </a>
    <a href="<?=APP_URL?>/id/admin/users.php" class="app-tile">
      <span class="material-symbols-outlined">group</span>
      <span class="app-tile-name">Users</span>
    </a>
    <a href="<?=APP_URL?>/id/admin/apps.php" class="app-tile">
      <span class="material-symbols-outlined">apps</span>
      <span class="app-tile-name">Apps</span>
    </a>
  </div>
<?php ui_card_close(); ?>

</div>
<?php ui_end(); ?>
