<?php
/**
 * id/admin/users.php
 * CRUD for users, including app access assignment.
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../shared/ui.php';

$me = require_auth('id', true);

$action = $_GET['action'] ?? 'list';
$target_username = $_GET['user'] ?? '';

/* ── Handle POST ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'create' || $post_action === 'update') {
        $uid       = (int)($_POST['user_id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $display   = trim($_POST['display_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password  = $_POST['password'] ?? '';
        $app_ids   = array_map('intval', (array)($_POST['app_access'] ?? []));

        if ($username === '' || $email === '') {
            flash('danger', 'Username and email are required.');
        } else {
            try {
                if ($post_action === 'create') {
                    if ($password === '') {
                        flash('danger', 'Password is required for new users.');
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = db()->prepare(
                            'INSERT INTO users (username,email,password_hash,display_name,phone,role,is_active)
                             VALUES (?,?,?,?,?,?,?)'
                        );
                        $stmt->execute([$username, $email, $hash, $display, $phone ?: null, $role, $is_active]);
                        $uid = (int) db()->lastInsertId();
                        sync_app_access($uid, $app_ids);
                        flash('success', "User @{$username} created.");
                        header('Location: ' . APP_URL . '/id/admin/users.php');
                        exit;
                    }
                } else {
                    $db = db();
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $db->prepare(
                            'UPDATE users SET username=?,email=?,password_hash=?,display_name=?,phone=?,role=?,is_active=?
                             WHERE id=?'
                        )->execute([$username,$email,$hash,$display,$phone?:null,$role,$is_active,$uid]);
                    } else {
                        $db->prepare(
                            'UPDATE users SET username=?,email=?,display_name=?,phone=?,role=?,is_active=?
                             WHERE id=?'
                        )->execute([$username,$email,$display,$phone?:null,$role,$is_active,$uid]);
                    }
                    sync_app_access($uid, $app_ids);
                    flash('success', "User @{$username} updated.");
                    header('Location: ' . APP_URL . '/id/admin/users.php');
                    exit;
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash('danger', 'Username or email already exists.');
                } else {
                    flash('danger', 'Database error: ' . $e->getMessage());
                }
            }
        }
    }

    if ($post_action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === (int)$me['id']) {
            flash('danger', 'You cannot delete your own account.');
        } else {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            flash('success', 'User deleted.');
        }
        header('Location: ' . APP_URL . '/id/admin/users.php');
        exit;
    }

    if ($post_action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        db()->prepare('UPDATE users SET is_active = NOT is_active WHERE id=?')->execute([$uid]);
        flash('success', 'User status toggled.');
        header('Location: ' . APP_URL . '/id/admin/users.php');
        exit;
    }
}

function sync_app_access(int $user_id, array $app_ids): void {
    $db = db();
    $db->prepare('DELETE FROM user_app_access WHERE user_id=?')->execute([$user_id]);
    if ($app_ids) {
        $placeholders = implode(',', array_fill(0, count($app_ids), '(?,?)'));
        $params = [];
        foreach ($app_ids as $app_id) { $params[] = $user_id; $params[] = $app_id; }
        $db->prepare("INSERT INTO user_app_access (user_id,app_id) VALUES {$placeholders}")->execute($params);
    }
}

/* ── Data for forms ──────────────────────────────────── */
$all_apps = db()->query('SELECT id, name, icon FROM apps WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();

$edit_user   = null;
$user_app_ids = [];
if ($action === 'edit' && $target_username !== '') {
    $stmt = db()->prepare('SELECT * FROM users WHERE username=?');
    $stmt->execute([$target_username]);
    $edit_user = $stmt->fetch();
    if ($edit_user) {
        $a_stmt = db()->prepare('SELECT app_id FROM user_app_access WHERE user_id=?');
        $a_stmt->execute([$edit_user['id']]);
        $user_app_ids = array_column($a_stmt->fetchAll(), 'app_id');
    }
}

$all_users = db()->query(
    'SELECT u.*, COUNT(uaa.app_id) AS app_count
     FROM users u
     LEFT JOIN user_app_access uaa ON uaa.user_id = u.id
     GROUP BY u.id ORDER BY u.created_at DESC'
)->fetchAll();

/* ── Nav ─────────────────────────────────────────────── */
$nav_items = [
    ['icon' => 'dashboard', 'label' => 'Dashboard',    'href' => APP_URL . '/id/admin/'],
    ['icon' => 'group',     'label' => 'Users',        'href' => APP_URL . '/id/admin/users.php', 'active' => true],
    ['icon' => 'apps',      'label' => 'Applications', 'href' => APP_URL . '/id/admin/apps.php'],
    ['section' => 'System'],
    ['icon' => 'admin_panel_settings', 'label' => 'Global Admin', 'href' => APP_URL . '/admin/'],
];

$title = $action === 'new' ? 'New User' : ($action === 'edit' ? 'Edit User' : 'Users');
$breadcrumb = 'ID Admin → Users';

$actions = ($action === 'list') ?
    '<a href="?action=new" class="btn btn-primary btn-sm">
       <span class="material-symbols-outlined">person_add</span> Add User
     </a>' : '';

ui_head($title, 'id', 'ID Admin', 'manage_accounts');
ui_sidebar('ID Admin', 'manage_accounts', $nav_items, APP_URL . '/id/auth/logout.php');
ui_page_header($title, $breadcrumb, $actions);
?>

<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action === 'new' || $action === 'edit'): ?>

<!-- ── User form ── -->
<?php
$u = $edit_user ?: [];
ui_card_open('person', $action === 'new' ? 'Create User' : 'Edit ' . htmlspecialchars($u['display_name'] ?? $u['username'] ?? ''));
?>
  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
    <?php if ($action === 'edit'): ?>
    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="username">Username <span style="color:var(--danger)">*</span></label>
        <input type="text" id="username" name="username" class="form-control"
               value="<?= htmlspecialchars($u['username'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Email <span style="color:var(--danger)">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= htmlspecialchars($u['email'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" id="display_name" name="display_name" class="form-control"
               value="<?= htmlspecialchars($u['display_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="phone">Phone (E.164 for SMS)</label>
        <input type="tel" id="phone" name="phone" class="form-control"
               placeholder="+12025551234"
               value="<?= htmlspecialchars($u['phone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="password"><?= $action === 'new' ? 'Password *' : 'New Password (leave blank to keep)' ?></label>
        <input type="password" id="password" name="password" class="form-control"
               <?= $action === 'new' ? 'required' : '' ?> autocomplete="new-password">
        <div class="progress-bar mt-1" id="pw-strength-bar" style="height:.25rem">
          <div class="progress-fill" style="width:0;transition:width .3s,background .3s"></div>
        </div>
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="role" class="form-control">
          <option value="user"  <?= ($u['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>User</option>
          <option value="admin" <?= ($u['role'] ?? '')      === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="is_active" value="1"
               <?= ($u['is_active'] ?? 1) ? 'checked' : '' ?>>
        Account active
      </label>
    </div>

    <?php if ($all_apps): ?>
    <div class="form-group">
      <label>App Access</label>
      <div class="check-group" style="flex-wrap:wrap;gap:.5rem 1.5rem;">
        <?php foreach ($all_apps as $app): ?>
        <label class="check-label" style="gap:.375rem">
          <input type="checkbox" name="app_access[]" value="<?= (int)$app['id'] ?>"
            <?= in_array($app['id'], $user_app_ids) ? 'checked' : '' ?>>
          <span class="material-symbols-outlined" style="font-size:1rem"><?= htmlspecialchars($app['icon']) ?></span>
          <?= htmlspecialchars($app['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="form-hint">Admins automatically have access to all apps.</div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action === 'new' ? 'Create User' : 'Save Changes' ?>
      </button>
      <a href="<?= APP_URL ?>/id/admin/users.php" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<!-- ── Users table ── -->
<?php ui_card_open('group', 'All Users'); ?>
  <div class="table-wrap">
    <table>
      <tr>
        <th>User</th>
        <th>Email</th>
        <th>Role</th>
        <th>Apps</th>
        <th>Last Login</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($all_users as $u): ?>
      <tr>
        <td>
          <div style="font-weight:500"><?= htmlspecialchars($u['display_name'] ?: $u['username']) ?></div>
          <div class="text-xs text-muted">@<?= htmlspecialchars($u['username']) ?></div>
        </td>
        <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
        <td><?= ui_badge(ucfirst($u['role']), $u['role'] === 'admin' ? 'info' : 'neutral') ?></td>
        <td class="text-sm text-muted"><?= (int)$u['app_count'] ?></td>
        <td class="text-sm text-muted">
          <?= $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never' ?>
        </td>
        <td><?= ui_badge($u['is_active'] ? 'Active' : 'Disabled', $u['is_active'] ? 'success' : 'danger') ?></td>
        <td>
          <div class="flex gap-1">
            <a href="?action=edit&user=<?= urlencode($u['username']) ?>" class="btn btn-ghost btn-sm" title="Edit">
              <span class="material-symbols-outlined">edit</span>
            </a>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      title="<?= $u['is_active'] ? 'Disable' : 'Enable' ?>">
                <span class="material-symbols-outlined"><?= $u['is_active'] ? 'block' : 'check_circle' ?></span>
              </button>
            </form>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                      data-confirm="Delete user @<?= htmlspecialchars($u['username']) ?>? This cannot be undone."
                      title="Delete">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$all_users): ?>
      <tr><td colspan="7">
        <div class="empty-state">
          <span class="material-symbols-outlined">group</span>
          <h3>No users yet</h3>
          <p><a href="?action=new">Create the first user</a></p>
        </div>
      </td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php ui_card_close(); ?>

<?php endif; ?>

</div><!-- .page-body -->
<?php ui_end(); ?>
