<?php
/**
 * id/admin/index.php
 * Identity admin dashboard – users & apps overview.
 * URL: internal.calebgruber.me/id/admin/
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../shared/ui.php';

$user = require_auth('id', true);   // admin-only

// ── Stats ───────────────────────────────────────────────
$total_users = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$active_users = db()->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
$total_apps  = db()->query('SELECT COUNT(*) FROM apps WHERE is_active=1')->fetchColumn();
$recent_users = db()->query(
    'SELECT username, display_name, email, role, is_active, last_login
     FROM users ORDER BY created_at DESC LIMIT 8'
)->fetchAll();

$nav_items = [
    ['icon' => 'dashboard',       'label' => 'Dashboard',    'href' => APP_URL . '/id/admin/',         'active' => true],
    ['icon' => 'group',           'label' => 'Users',        'href' => APP_URL . '/id/admin/users.php'],
    ['icon' => 'apps',            'label' => 'Applications', 'href' => APP_URL . '/id/admin/apps.php'],
    ['section' => 'System'],
    ['icon' => 'admin_panel_settings', 'label' => 'Global Admin', 'href' => APP_URL . '/admin/'],
];

$actions = '<a href="' . APP_URL . '/id/admin/users.php?action=new" class="btn btn-primary btn-sm">
  <span class="material-symbols-outlined">person_add</span> Add User
</a>';

ui_head('ID Admin', 'id', 'ID Admin', 'manage_accounts');
ui_sidebar('ID Admin', 'manage_accounts', $nav_items, APP_URL . '/id/auth/logout.php');
ui_page_header('Dashboard', 'Identity & Access Management', $actions);
?>

<div class="page-body">

<?php ui_flash(); ?>

<!-- ── Stats row ── -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon"><span class="material-symbols-outlined">group</span></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?= (int)$total_users ?></div>
    <div class="stat-sub"><?= (int)$active_users ?> active</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
      <span class="material-symbols-outlined">apps</span>
    </div>
    <div class="stat-label">Registered Apps</div>
    <div class="stat-value"><?= (int)$total_apps ?></div>
  </div>
</div>

<!-- ── Application launcher ── -->
<?php
$apps = db()->query('SELECT * FROM apps WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
ui_card_open('apps', 'Registered Applications',
  '<a href="' . APP_URL . '/id/admin/apps.php" class="btn btn-sm" style="margin-left:auto">
    <span class="material-symbols-outlined">open_in_new</span> Manage
  </a>');
?>
  <?php if ($apps): ?>
  <div class="apps-grid">
    <?php foreach ($apps as $app): ?>
    <a href="<?= htmlspecialchars($app['url']) ?>" class="app-tile">
      <span class="material-symbols-outlined"><?= htmlspecialchars($app['icon']) ?></span>
      <span class="app-tile-name"><?= htmlspecialchars($app['name']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">apps</span>
    <h3>No applications yet</h3>
    <p>Add applications in the <a href="<?= APP_URL ?>/id/admin/apps.php">Apps section</a>.</p>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>

<div style="margin-top:1.5rem;"></div>

<!-- ── Recent users ── -->
<?php
ui_card_open('group', 'Recent Users',
  '<a href="' . APP_URL . '/id/admin/users.php" class="btn btn-sm" style="margin-left:auto">
    <span class="material-symbols-outlined">open_in_new</span> All Users
  </a>');
?>
  <div class="table-wrap">
    <table>
      <tr>
        <th>User</th>
        <th>Email</th>
        <th>Role</th>
        <th>Last Login</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($recent_users as $u): ?>
      <tr>
        <td>
          <div style="font-weight:500"><?= htmlspecialchars($u['display_name'] ?: $u['username']) ?></div>
          <div class="text-xs text-muted">@<?= htmlspecialchars($u['username']) ?></div>
        </td>
        <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
        <td><?= ui_badge(ucfirst($u['role']), $u['role'] === 'admin' ? 'info' : 'neutral') ?></td>
        <td class="text-sm text-muted">
          <?= $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never' ?>
        </td>
        <td><?= ui_badge($u['is_active'] ? 'Active' : 'Disabled', $u['is_active'] ? 'success' : 'danger') ?></td>
        <td>
          <a href="<?= APP_URL ?>/id/admin/users.php?action=edit&user=<?= urlencode($u['username']) ?>"
             class="btn btn-ghost btn-sm">
            <span class="material-symbols-outlined">edit</span>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent_users): ?>
      <tr><td colspan="6">
        <div class="empty-state" style="padding:1.5rem">
          <span class="material-symbols-outlined">group</span>
          <h3>No users yet</h3>
          <p><a href="<?= APP_URL ?>/id/admin/users.php?action=new">Create the first user</a></p>
        </div>
      </td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php ui_card_close(); ?>

</div><!-- .page-body -->
<?php ui_end(); ?>
