<?php
/**
 * index.php – Root page / App Launcher
 * Redirects to login if not authenticated, otherwise shows app launcher.
 */

require_once __DIR__ . '/shared/config.php';
require_once __DIR__ . '/shared/db.php';
require_once __DIR__ . '/shared/auth.php';
require_once __DIR__ . '/shared/ui.php';

if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/id/auth/login.php');
    exit;
}

$user = current_user();
$uid  = (int)$user['id'];

// Load apps this user can access
if ($user['role'] === 'admin') {
    $apps = db()->query('SELECT * FROM apps WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();
} else {
    $stmt = db()->prepare(
        'SELECT a.* FROM apps a
         JOIN user_app_access uaa ON uaa.app_id = a.id
         WHERE uaa.user_id = ? AND a.is_active = 1
         ORDER BY a.sort_order, a.name'
    );
    $stmt->execute([$uid]);
    $apps = $stmt->fetchAll();
}

$sys_alerts = db()->query(
    "SELECT * FROM system_alerts WHERE is_active=1 ORDER BY created_at DESC LIMIT 3"
)->fetchAll();

$nav_items = [
    ['icon'=>'home',     'label'=>'Launcher', 'href'=>APP_URL.'/', 'active'=>true],
    ['section'=>'Apps'],
];
foreach ($apps as $app) {
    $nav_items[] = ['icon'=>$app['icon'],'label'=>$app['name'],'href'=>$app['url']];
}
if ($user['role'] === 'admin') {
    $nav_items[] = ['section'=>'Admin'];
    $nav_items[] = ['icon'=>'manage_accounts','label'=>'ID Admin','href'=>APP_URL.'/id/admin/'];
    $nav_items[] = ['icon'=>'admin_panel_settings','label'=>'System Admin','href'=>APP_URL.'/admin/'];
}

ui_head('App Launcher','','CG Internal','home');
ui_sidebar('CG Internal','home',$nav_items,APP_URL.'/id/auth/logout.php');
ui_page_header('App Launcher','Welcome back, ' . htmlspecialchars($user['display_name'] ?? $user['username']));
?>
<div class="page-body">

<?php if ($sys_alerts): ?>
<div class="alerts">
  <?php foreach ($sys_alerts as $sa): ?>
  <div class="alert alert-<?=htmlspecialchars($sa['type'])?>"<?=$sa['dismissible']?' data-auto-dismiss="8000"':''?>>
    <span class="material-symbols-outlined"><?=htmlspecialchars($sa['icon'])?></span>
    <span class="alert-text"><?=htmlspecialchars($sa['text'])?></span>
    <?php if ($sa['dismissible']): ?><button class="alert-close"><span class="material-symbols-outlined">close</span></button><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php ui_card_open('apps','Your Applications'); ?>
  <?php if ($apps): ?>
  <div class="apps-grid">
    <?php foreach ($apps as $app): ?>
    <a href="<?=htmlspecialchars($app['url'])?>" class="app-tile">
      <span class="material-symbols-outlined"><?=htmlspecialchars($app['icon'])?></span>
      <span class="app-tile-name"><?=htmlspecialchars($app['name'])?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">apps</span>
    <h3>No apps assigned</h3>
    <p>Contact your administrator to get access to applications.</p>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>

</div>
<?php ui_end(); ?>
