<?php
/**
 * edu/classes.php – Manage academic classes
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('edu');
$uid  = (int)$user['id'];

$action  = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

/* ── POST handler ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create' || $pa === 'update') {
        $id         = (int)($_POST['class_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $code       = trim($_POST['code'] ?? '');
        $instructor = trim($_POST['instructor'] ?? '');
        $color      = trim($_POST['color'] ?? '#3b82f6');
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            flash('danger', 'Class name is required.');
        } else {
            if ($pa === 'create') {
                db()->prepare(
                    'INSERT INTO edu_classes (user_id,name,code,instructor,color,is_active)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$uid,$name,$code,$instructor,$color,$is_active]);
                flash('success', "Class \"{$name}\" added.");
            } else {
                db()->prepare(
                    'UPDATE edu_classes SET name=?,code=?,instructor=?,color=?,is_active=?
                     WHERE id=? AND user_id=?'
                )->execute([$name,$code,$instructor,$color,$is_active,$id,$uid]);
                flash('success', "Class \"{$name}\" updated.");
            }
            header('Location: ' . APP_URL . '/edu/classes');
            exit;
        }
    }

    if ($pa === 'delete') {
        $id = (int)($_POST['class_id'] ?? 0);
        db()->prepare('DELETE FROM edu_classes WHERE id=? AND user_id=?')->execute([$id,$uid]);
        flash('success', 'Class deleted.');
        header('Location: ' . APP_URL . '/edu/classes');
        exit;
    }
}

$edit_class = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM edu_classes WHERE id=? AND user_id=?');
    $stmt->execute([$edit_id, $uid]);
    $edit_class = $stmt->fetch();
    if (!$edit_class) { flash('danger','Class not found.'); header('Location: '.APP_URL.'/edu/classes'); exit; }
}

$all_classes = db()->prepare(
    'SELECT c.*, COUNT(a.id) AS assignment_count
     FROM edu_classes c
     LEFT JOIN edu_assignments a ON a.class_id = c.id
     WHERE c.user_id=? GROUP BY c.id ORDER BY c.is_active DESC, c.name'
);
$all_classes->execute([$uid]);
$all_classes = $all_classes->fetchAll();

$nav_items = [
    ['icon' => 'dashboard',     'label' => 'Dashboard',   'href' => APP_URL . '/edu/'],
    ['icon' => 'school',        'label' => 'Classes',     'href' => APP_URL . '/edu/classes',     'active' => true],
    ['icon' => 'assignment',    'label' => 'Assignments', 'href' => APP_URL . '/edu/assignments'],
    ['icon' => 'task_alt',      'label' => 'Tasks',       'href' => APP_URL . '/edu/tasks'],
    ['icon' => 'sticky_note_2', 'label' => 'Notes',       'href' => APP_URL . '/edu/notes'],
    ['icon' => 'calendar_month','label' => 'Schedule',    'href' => APP_URL . '/edu/schedule'],
    ['section' => 'Account'],
    ['icon' => 'apps',          'label' => 'All Apps',    'href' => APP_URL . '/'],
];

$title   = $action === 'new' ? 'New Class' : ($action === 'edit' ? 'Edit Class' : 'Classes');
$actions = $action === 'list' ?
    '<a href="?action=new" class="btn btn-primary btn-sm">
       <span class="material-symbols-outlined">add</span> Add Class
     </a>' : '';

ui_head($title . ' – EDU Hub', 'edu', 'EDU Hub', 'school');
ui_sidebar('EDU Hub', 'school', $nav_items, APP_URL . '/id/auth/logout');
ui_page_header($title, 'EDU Hub → Classes', $actions);
?>

<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<?php
$c = $edit_class ?: [];
ui_card_open('school', $action === 'new' ? 'Add New Class' : 'Edit ' . htmlspecialchars($c['name'] ?? ''));
?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
    <?php if ($action === 'edit'): ?>
    <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="name">Class Name *</label>
        <input type="text" id="name" name="name" class="form-control"
               value="<?= htmlspecialchars($c['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="code">Course Code</label>
        <input type="text" id="code" name="code" class="form-control"
               placeholder="e.g. CS 101"
               value="<?= htmlspecialchars($c['code'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="instructor">Instructor</label>
        <input type="text" id="instructor" name="instructor" class="form-control"
               value="<?= htmlspecialchars($c['instructor'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="color">Class Color</label>
        <div class="flex gap-2" style="align-items:center">
          <input type="color" id="color" name="color" data-preview="color-preview"
                 value="<?= htmlspecialchars($c['color'] ?? '#3b82f6') ?>"
                 style="width:3rem;height:2.25rem;cursor:pointer;border-radius:var(--radius);border:1px solid var(--border)">
          <div id="color-preview" style="width:2rem;height:2rem;border-radius:50%;
               background:<?= htmlspecialchars($c['color'] ?? '#3b82f6') ?>;border:2px solid var(--border)"></div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="is_active" value="1"
               <?= ($c['is_active'] ?? 1) ? 'checked' : '' ?>>
        Class is active this semester
      </label>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action === 'new' ? 'Add Class' : 'Save Changes' ?>
      </button>
      <a href="<?= APP_URL ?>/edu/classes" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<?php ui_card_open('school', 'My Classes'); ?>
  <?php if ($all_classes): ?>
  <div class="card-grid" style="padding:0">
    <?php foreach ($all_classes as $c): ?>
    <div class="card" style="border-left:4px solid <?= htmlspecialchars($c['color']) ?>">
      <div class="card-header" style="background:var(--surface)">
        <span class="material-symbols-outlined" style="color:<?= htmlspecialchars($c['color']) ?>">school</span>
        <h3 style="flex:1"><?= htmlspecialchars($c['name']) ?></h3>
        <?= $c['is_active'] ? ui_badge('Active','success') : ui_badge('Inactive','neutral') ?>
      </div>
      <div class="card-body">
        <?php if ($c['code']): ?>
        <p class="text-xs text-muted" style="margin-bottom:.25rem">
          <strong>Code:</strong> <?= htmlspecialchars($c['code']) ?>
        </p>
        <?php endif; ?>
        <?php if ($c['instructor']): ?>
        <p class="text-xs text-muted" style="margin-bottom:.25rem">
          <span class="material-symbols-outlined" style="font-size:.875rem;vertical-align:middle">person</span>
          <?= htmlspecialchars($c['instructor']) ?>
        </p>
        <?php endif; ?>
        <p class="text-xs text-muted"><?= (int)$c['assignment_count'] ?> assignment<?= $c['assignment_count'] != 1 ? 's' : '' ?></p>
      </div>
      <div class="card-footer">
        <a href="<?= APP_URL ?>/edu/assignments?class=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">
          <span class="material-symbols-outlined">assignment</span> Assignments
        </a>
        <a href="?action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">
          <span class="material-symbols-outlined">edit</span> Edit
        </a>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                  data-confirm="Delete class &quot;<?= htmlspecialchars($c['name']) ?>&quot;?">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">school</span>
    <h3>No classes yet</h3>
    <p>Add your first class to get started tracking assignments and schedules.</p>
    <a href="?action=new" class="btn btn-primary">
      <span class="material-symbols-outlined">add</span> Add First Class
    </a>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>

<?php endif; ?>
</div>
<?php ui_end(); ?>
