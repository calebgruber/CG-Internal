<?php
/**
 * admin/maintenance.php
 * Toggle maintenance mode on/off.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('admin', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'enable') {
        set_setting('maintenance_mode', '1');
        flash('warning', 'Maintenance mode ENABLED. Non-admin users will see the maintenance page.');
    } elseif ($pa === 'disable') {
        set_setting('maintenance_mode', '0');
        flash('success', 'Maintenance mode disabled. Site is back online.');
    }
    header('Location: ' . APP_URL . '/admin/maintenance.php');
    exit;
}

$is_on = setting('maintenance_mode', '0') === '1';

$nav_items = [
    ['icon'=>'dashboard',       'label'=>'Overview',    'href'=>APP_URL.'/admin/'],
    ['icon'=>'settings',        'label'=>'Settings',    'href'=>APP_URL.'/admin/settings.php'],
    ['icon'=>'storage',         'label'=>'Migrations',  'href'=>APP_URL.'/admin/migrations.php'],
    ['icon'=>'engineering',     'label'=>'Maintenance', 'href'=>APP_URL.'/admin/maintenance.php', 'active'=>true],
    ['icon'=>'add_alert',       'label'=>'Alerts',      'href'=>APP_URL.'/admin/alerts.php'],
    ['section'=>'Sub-systems'],
    ['icon'=>'manage_accounts', 'label'=>'ID Admin',    'href'=>APP_URL.'/id/admin/'],
    ['icon'=>'school',          'label'=>'EDU Hub',     'href'=>APP_URL.'/edu/'],
];

ui_head('Maintenance – System Admin','admin','System Admin','admin_panel_settings');
ui_sidebar('System Admin','admin_panel_settings',$nav_items,APP_URL.'/id/auth/logout.php');
ui_page_header('Maintenance Mode','System Admin → Maintenance');
?>
<div class="page-body">
<?php ui_flash(); ?>

<div class="alerts">
  <?php if ($is_on): ?>
  <div class="alert alert-warning">
    <span class="material-symbols-outlined">engineering</span>
    <span class="alert-text"><strong>Maintenance mode is currently ON.</strong>
      All non-admin users see the maintenance page. You (as admin) can still browse normally.</span>
  </div>
  <?php else: ?>
  <div class="alert alert-success">
    <span class="material-symbols-outlined">check_circle</span>
    <span class="alert-text">Site is <strong>online</strong>. All users have normal access.</span>
  </div>
  <?php endif; ?>
</div>

<?php ui_card_open('engineering','Maintenance Control'); ?>
  <p class="text-sm text-muted" style="margin-bottom:1.25rem">
    When maintenance mode is active, visitors who are not logged in as admin will see a
    "Under Maintenance" page. Admins continue to have full access to all features.
  </p>

  <?php if ($is_on): ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="disable">
    <button type="submit" class="btn btn-success">
      <span class="material-symbols-outlined">power_settings_new</span>
      Disable Maintenance Mode – Bring Site Online
    </button>
  </form>
  <?php else: ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="enable">
    <button type="submit" class="btn btn-danger"
            data-confirm="Enable maintenance mode? Non-admin users will see a maintenance page.">
      <span class="material-symbols-outlined">engineering</span>
      Enable Maintenance Mode
    </button>
  </form>
  <?php endif; ?>
<?php ui_card_close(); ?>

</div>
<?php ui_end(); ?>
