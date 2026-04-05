<?php
/**
 * admin/alerts.php
 * Manage system-wide alerts shown on all dashboards.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('admin', true);
$action  = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create') {
        $text        = trim($_POST['text'] ?? '');
        $type        = in_array($_POST['type']??'',['info','warning','danger','success'])?$_POST['type']:'info';
        $icon        = trim($_POST['icon'] ?? 'info');
        $dismissible = isset($_POST['dismissible']) ? 1 : 0;

        if ($text === '') { flash('danger','Alert text required.'); }
        else {
            db()->prepare('INSERT INTO system_alerts (text,type,icon,dismissible) VALUES (?,?,?,?)')
                ->execute([$text,$type,$icon,$dismissible]);
            flash('success','Alert created.');
            header('Location: '.APP_URL.'/admin/alerts'); exit;
        }
    }
    if ($pa === 'toggle') {
        $id = (int)$_POST['alert_id'];
        db()->prepare('UPDATE system_alerts SET is_active = NOT is_active WHERE id=?')->execute([$id]);
        flash('success','Alert toggled.');
        header('Location: '.APP_URL.'/admin/alerts'); exit;
    }
    if ($pa === 'delete') {
        db()->prepare('DELETE FROM system_alerts WHERE id=?')->execute([(int)$_POST['alert_id']]);
        flash('success','Alert deleted.');
        header('Location: '.APP_URL.'/admin/alerts'); exit;
    }
}

$alerts = db()->query('SELECT * FROM system_alerts ORDER BY is_active DESC, created_at DESC')->fetchAll();

$nav_items = [
    ['icon'=>'dashboard',       'label'=>'Overview',    'href'=>APP_URL.'/admin/'],
    ['icon'=>'settings',        'label'=>'Settings',    'href'=>APP_URL.'/admin/settings'],
    ['icon'=>'storage',         'label'=>'Migrations',  'href'=>APP_URL.'/admin/migrations'],
    ['icon'=>'engineering',     'label'=>'Maintenance', 'href'=>APP_URL.'/admin/maintenance'],
    ['icon'=>'add_alert',       'label'=>'Alerts',      'href'=>APP_URL.'/admin/alerts', 'active'=>true],
    ['section'=>'Sub-systems'],
    ['icon'=>'manage_accounts', 'label'=>'ID Admin',    'href'=>APP_URL.'/id/admin/'],
    ['icon'=>'school',          'label'=>'EDU Hub',     'href'=>APP_URL.'/edu/'],
];

ui_head('Alerts – System Admin','admin','System Admin','admin_panel_settings');
ui_sidebar('System Admin','admin_panel_settings',$nav_items,APP_URL.'/id/auth/logout');
ui_page_header('System Alerts','System Admin → Alerts');
?>
<div class="page-body">
<?php ui_flash(); ?>

<div class="card-grid card-grid-2">

  <!-- Create alert -->
  <?php ui_card_open('add_alert','Create Alert'); ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="form-group">
        <label>Alert Text *</label>
        <textarea name="text" class="form-control" rows="2" required></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="type" class="form-control">
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="danger">Danger</option>
            <option value="success">Success</option>
          </select>
        </div>
        <div class="form-group">
          <label>Icon (Material Symbol)</label>
          <input type="text" name="icon" class="form-control" value="info"
                 placeholder="e.g. info, warning, check_circle">
        </div>
      </div>
      <div class="form-group">
        <label>
          <input type="checkbox" name="dismissible" value="1" checked>
          Users can dismiss this alert
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined">add</span> Add Alert
        </button>
      </div>
    </form>
  <?php ui_card_close(); ?>

  <!-- Existing alerts -->
  <?php ui_card_open('notifications_active','Active Alerts (' . count(array_filter($alerts, fn($a) => $a['is_active'])) . ')'); ?>
    <?php if ($alerts): ?>
    <div style="display:flex;flex-direction:column;gap:.75rem;">
      <?php foreach ($alerts as $a):
            $type = $a['type'] ?? 'info';
            [$ac, $ar, $at] = _alert_accent($type);
            $avars = '--alert-accent:' . $ac . ';--alert-accent-rgb:' . $ar . ';--alert-text-on-solid:' . $at;
      ?>
      <div>
        <div class="alert alert-<?=htmlspecialchars($type)?>"
             style="<?=$avars?>;<?=!$a['is_active']?'opacity:.4':''?>">
          <span class="material-symbols-outlined"><?=htmlspecialchars($a['icon'])?></span>
          <span class="alert-text"><?=htmlspecialchars($a['text'])?></span>
        </div>
        <div class="flex gap-1 mt-1">
          <?=ui_badge($a['is_active']?'Active':'Inactive',$a['is_active']?'success':'neutral')?>
          <?=ui_badge($a['dismissible']?'Dismissible':'Permanent','neutral')?>
          <span style="flex:1"></span>
          <form method="POST" style="display:inline">
            <?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="alert_id" value="<?=(int)$a['id']?>">
            <button type="submit" class="btn btn-ghost btn-sm">
              <span class="material-symbols-outlined"><?=$a['is_active']?'visibility_off':'visibility'?></span>
              <?=$a['is_active']?'Hide':'Show'?>
            </button>
          </form>
          <form method="POST" style="display:inline">
            <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="alert_id" value="<?=(int)$a['id']?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Delete this alert?">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:1.5rem">
      <span class="material-symbols-outlined">notifications_off</span>
      <h3>No alerts</h3>
      <p>Create an alert to broadcast a message to all users.</p>
    </div>
    <?php endif; ?>
  <?php ui_card_close(); ?>

</div>
</div>
<?php ui_end(); ?>
