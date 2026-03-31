<?php
/**
 * id/admin/apps.php
 * CRUD for registered SSO applications.
 */

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../shared/ui.php';

$me = require_auth('id', true);

$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

/* ── Handle POST ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'create' || $post_action === 'update') {
        $id          = (int)($_POST['app_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'apps');
        $url         = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $sort_order  = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $slug === '' || $url === '') {
            flash('danger', 'Name, slug, and URL are required.');
        } else {
            try {
                if ($post_action === 'create') {
                    db()->prepare(
                        'INSERT INTO apps (name,slug,icon,url,description,is_active,sort_order)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([$name, $slug, $icon, $url, $description, $is_active, $sort_order]);
                    flash('success', "App \"{$name}\" created.");
                } else {
                    db()->prepare(
                        'UPDATE apps SET name=?,slug=?,icon=?,url=?,description=?,is_active=?,sort_order=?
                         WHERE id=?'
                    )->execute([$name,$slug,$icon,$url,$description,$is_active,$sort_order,$id]);
                    flash('success', "App \"{$name}\" updated.");
                }
                header('Location: ' . APP_URL . '/id/admin/apps.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash('danger', 'App slug already exists. Slugs must be unique.');
                } else {
                    flash('danger', 'DB error: ' . $e->getMessage());
                }
            }
        }
    }

    if ($post_action === 'delete') {
        $id = (int)($_POST['app_id'] ?? 0);
        db()->prepare('DELETE FROM apps WHERE id=?')->execute([$id]);
        flash('success', 'Application deleted.');
        header('Location: ' . APP_URL . '/id/admin/apps.php');
        exit;
    }
}

$edit_app = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM apps WHERE id=?');
    $stmt->execute([$edit_id]);
    $edit_app = $stmt->fetch();
    if (!$edit_app) {
        flash('danger', 'App not found.');
        header('Location: ' . APP_URL . '/id/admin/apps.php');
        exit;
    }
}

$all_apps = db()->query('SELECT a.*, COUNT(uaa.user_id) AS user_count
    FROM apps a
    LEFT JOIN user_app_access uaa ON uaa.app_id = a.id
    GROUP BY a.id ORDER BY a.sort_order, a.name')->fetchAll();

$nav_items = [
    ['icon' => 'dashboard', 'label' => 'Dashboard',    'href' => APP_URL . '/id/admin/'],
    ['icon' => 'group',     'label' => 'Users',        'href' => APP_URL . '/id/admin/users.php'],
    ['icon' => 'apps',      'label' => 'Applications', 'href' => APP_URL . '/id/admin/apps.php', 'active' => true],
    ['section' => 'System'],
    ['icon' => 'admin_panel_settings', 'label' => 'Global Admin', 'href' => APP_URL . '/admin/'],
];

$title   = $action === 'new' ? 'New Application' : ($action === 'edit' ? 'Edit Application' : 'Applications');
$actions = ($action === 'list') ?
    '<a href="?action=new" class="btn btn-primary btn-sm">
       <span class="material-symbols-outlined">add</span> Add App
     </a>' : '';

ui_head($title, 'id', 'ID Admin', 'manage_accounts');
ui_sidebar('ID Admin', 'manage_accounts', $nav_items, APP_URL . '/id/auth/logout.php');
ui_page_header($title, 'ID Admin → Applications', $actions);
?>

<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action === 'new' || $action === 'edit'): ?>

<?php
$a = $edit_app ?: [];
ui_card_open('apps', $action === 'new' ? 'New Application' : 'Edit ' . htmlspecialchars($a['name'] ?? ''));
?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
    <?php if ($action === 'edit'): ?>
    <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="name">App Name *</label>
        <input type="text" id="name" name="name" class="form-control"
               value="<?= htmlspecialchars($a['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="slug">Slug *
          <span class="form-hint" style="display:inline">(URL key, e.g. <code>edu</code>)</span>
        </label>
        <input type="text" id="slug" name="slug" class="form-control"
               pattern="[a-z0-9_-]+" title="Lowercase letters, numbers, hyphens, underscores"
               value="<?= htmlspecialchars($a['slug'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="icon">Icon (Material Symbol)</label>
        <input type="text" id="icon" name="icon" class="form-control"
               placeholder="e.g. school, dashboard, settings"
               value="<?= htmlspecialchars($a['icon'] ?? 'apps') ?>">
        <div class="form-hint">Preview: <span class="material-symbols-outlined" id="icon-preview" style="vertical-align:middle"><?= htmlspecialchars($a['icon'] ?? 'apps') ?></span></div>
      </div>
      <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control"
               value="<?= (int)($a['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="url">App URL *</label>
      <input type="text" id="url" name="url" class="form-control"
             placeholder="/edu/" value="<?= htmlspecialchars($a['url'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description" class="form-control" rows="2"><?= htmlspecialchars($a['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="is_active" value="1"
               <?= ($a['is_active'] ?? 1) ? 'checked' : '' ?>>
        App is active
      </label>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action === 'new' ? 'Create App' : 'Save Changes' ?>
      </button>
      <a href="<?= APP_URL ?>/id/admin/apps.php" class="btn">Cancel</a>
    </div>
  </form>

  <script>
  document.getElementById('icon').addEventListener('input', function(){
    document.getElementById('icon-preview').textContent = this.value || 'apps';
  });
  </script>
<?php ui_card_close(); ?>

<?php else: ?>

<?php ui_card_open('apps', 'Registered Applications'); ?>
  <div class="table-wrap">
    <table>
      <tr>
        <th>App</th>
        <th>Slug</th>
        <th>URL</th>
        <th>Users</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($all_apps as $a): ?>
      <tr>
        <td>
          <div class="flex gap-2" style="align-items:center">
            <span class="material-symbols-outlined" style="color:var(--primary)"><?= htmlspecialchars($a['icon']) ?></span>
            <div>
              <div style="font-weight:500"><?= htmlspecialchars($a['name']) ?></div>
              <?php if ($a['description']): ?>
              <div class="text-xs text-muted truncate" style="max-width:200px"><?= htmlspecialchars($a['description']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td><code class="text-sm"><?= htmlspecialchars($a['slug']) ?></code></td>
        <td><a href="<?= htmlspecialchars($a['url']) ?>" class="text-sm"><?= htmlspecialchars($a['url']) ?></a></td>
        <td class="text-sm text-muted"><?= (int)$a['user_count'] ?></td>
        <td><?= ui_badge($a['is_active'] ? 'Active' : 'Disabled', $a['is_active'] ? 'success' : 'danger') ?></td>
        <td>
          <div class="flex gap-1">
            <a href="?action=edit&id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">
              <span class="material-symbols-outlined">edit</span>
            </a>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                      data-confirm="Delete app &quot;<?= htmlspecialchars($a['name']) ?>&quot;? Access records will also be removed.">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$all_apps): ?>
      <tr><td colspan="6">
        <div class="empty-state">
          <span class="material-symbols-outlined">apps</span>
          <h3>No apps registered</h3>
          <p><a href="?action=new">Add the first app</a></p>
        </div>
      </td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php ui_card_close(); ?>

<?php endif; ?>

</div>
<?php ui_end(); ?>
