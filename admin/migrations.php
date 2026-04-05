<?php
/**
 * admin/migrations.php
 * Run pending DB migration SQL files from db/migrations/.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('admin', true);

$migrations_dir = __DIR__ . '/../db/migrations';

function get_migration_files(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.sql');
    sort($files);
    return $files;
}

function get_applied_migrations(): array {
    try {
        $rows = db()->query('SELECT name FROM migrations')->fetchAll();
        return array_column($rows, 'name');
    } catch (PDOException $e) {
        return [];
    }
}

$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'run_all') {
        $files   = get_migration_files($migrations_dir);
        $applied = get_applied_migrations();
        $ran     = 0;

        foreach ($files as $file) {
            $name = basename($file, '.sql');
            if (in_array($name, $applied)) continue;

            try {
                $sql = file_get_contents($file);
                // Execute each statement separately
                db()->exec($sql);
                db()->prepare('INSERT IGNORE INTO migrations (name) VALUES (?)')->execute([$name]);
                $log[] = ['status' => 'success', 'name' => $name, 'msg' => 'Applied successfully.'];
                $ran++;
            } catch (PDOException $e) {
                $log[] = ['status' => 'danger', 'name' => $name, 'msg' => 'Error: ' . $e->getMessage()];
            }
        }

        if ($ran === 0) {
            flash('info', 'No pending migrations to apply.');
        } else {
            flash('success', "Applied {$ran} migration(s).");
        }
    }

    if ($pa === 'run_single') {
        $name = basename($_POST['migration_name'] ?? '');
        $file = $migrations_dir . '/' . $name . '.sql';
        if (!file_exists($file)) {
            flash('danger', 'Migration file not found.');
        } else {
            try {
                db()->exec(file_get_contents($file));
                db()->prepare('INSERT IGNORE INTO migrations (name) VALUES (?)')->execute([$name]);
                flash('success', "Migration \"{$name}\" applied.");
            } catch (PDOException $e) {
                flash('danger', 'Migration error: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/admin/migrations');
        exit;
    }

    if ($pa === 'rollback') {
        // Mark as un-applied (does not reverse SQL – that would need down migrations)
        $name = basename($_POST['migration_name'] ?? '');
        db()->prepare('DELETE FROM migrations WHERE name=?')->execute([$name]);
        flash('warning', "Migration \"{$name}\" marked as not applied. SQL was NOT reversed.");
        header('Location: ' . APP_URL . '/admin/migrations');
        exit;
    }
}

$files   = get_migration_files($migrations_dir);
$applied = get_applied_migrations();

$nav_items = [
    ['icon'=>'dashboard',       'label'=>'Overview',    'href'=>APP_URL.'/admin/'],
    ['icon'=>'settings',        'label'=>'Settings',    'href'=>APP_URL.'/admin/settings'],
    ['icon'=>'storage',         'label'=>'Migrations',  'href'=>APP_URL.'/admin/migrations', 'active'=>true],
    ['icon'=>'engineering',     'label'=>'Maintenance', 'href'=>APP_URL.'/admin/maintenance'],
    ['icon'=>'add_alert',       'label'=>'Alerts',      'href'=>APP_URL.'/admin/alerts'],
    ['section'=>'Sub-systems'],
    ['icon'=>'manage_accounts', 'label'=>'ID Admin',    'href'=>APP_URL.'/id/admin/'],
    ['icon'=>'school',          'label'=>'EDU Hub',     'href'=>APP_URL.'/edu/'],
];

$pending_count = count(array_filter($files, fn($f) => !in_array(basename($f,'.sql'), $applied)));

ui_head('Migrations – System Admin','admin','System Admin','admin_panel_settings');
ui_sidebar('System Admin','admin_panel_settings',$nav_items,APP_URL.'/id/auth/logout');

$actions = $pending_count > 0 ?
    '<form method="POST" style="display:inline">' . csrf_field() .
    '<input type="hidden" name="action" value="run_all">
     <button type="submit" class="btn btn-primary btn-sm">
       <span class="material-symbols-outlined">play_arrow</span>
       Run ' . $pending_count . ' Pending
     </button></form>' : '';

ui_page_header('DB Migrations','System Admin → Migrations',$actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php foreach ($log as $entry): ?>
<div class="alerts">
  <div class="alert alert-<?=htmlspecialchars($entry['status'])?>">
    <span class="material-symbols-outlined"><?=$entry['status']==='success'?'check_circle':'error'?></span>
    <span class="alert-text"><strong><?=htmlspecialchars($entry['name'])?></strong>: <?=htmlspecialchars($entry['msg'])?></span>
  </div>
</div>
<?php endforeach; ?>

<?php ui_card_open('storage', 'Migration Files (' . count($files) . ' total, ' . $pending_count . ' pending)'); ?>
  <?php if ($files): ?>
  <div class="table-wrap">
    <table>
      <tr><th>Migration</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($files as $file): ?>
      <?php
        $name     = basename($file, '.sql');
        $is_done  = in_array($name, $applied);
      ?>
      <tr>
        <td>
          <div style="font-weight:500;font-family:monospace"><?=htmlspecialchars($name)?></div>
          <div class="text-xs text-muted"><?=basename($file)?></div>
        </td>
        <td><?=ui_badge($is_done?'Applied':'Pending', $is_done?'success':'warning')?></td>
        <td>
          <div class="flex gap-1">
            <?php if (!$is_done): ?>
            <form method="POST" style="display:inline">
              <?=csrf_field()?>
              <input type="hidden" name="action" value="run_single">
              <input type="hidden" name="migration_name" value="<?=htmlspecialchars($name)?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--success)">
                <span class="material-symbols-outlined">play_arrow</span> Apply
              </button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline">
              <?=csrf_field()?>
              <input type="hidden" name="action" value="rollback">
              <input type="hidden" name="migration_name" value="<?=htmlspecialchars($name)?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--warning)"
                      data-confirm="Mark &quot;<?=htmlspecialchars($name)?>&quot; as not applied? This does NOT reverse the SQL.">
                <span class="material-symbols-outlined">undo</span> Un-apply
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">storage</span>
    <h3>No migration files found</h3>
    <p>Place <code>.sql</code> files in <code>db/migrations/</code> to manage schema changes.</p>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>

<div style="margin-top:1.5rem"></div>
<?php ui_card_open('history','Applied Migrations (DB record)'); ?>
  <?php
  try {
      $applied_rows = db()->query('SELECT name, applied_at FROM migrations ORDER BY applied_at DESC')->fetchAll();
  } catch (PDOException $e) { $applied_rows = []; }
  ?>
  <?php if ($applied_rows): ?>
  <div class="table-wrap"><table>
    <tr><th>Name</th><th>Applied At</th></tr>
    <?php foreach ($applied_rows as $r): ?>
    <tr>
      <td style="font-family:monospace"><?=htmlspecialchars($r['name'])?></td>
      <td class="text-sm text-muted"><?=date('M j, Y g:i A',strtotime($r['applied_at']))?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php else: ?>
  <p class="text-muted text-sm" style="padding:.5rem">No migrations recorded yet.</p>
  <?php endif; ?>
<?php ui_card_close(); ?>

</div>
<?php ui_end(); ?>
