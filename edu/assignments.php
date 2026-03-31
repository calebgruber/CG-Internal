<?php
/**
 * edu/assignments.php – Track assignments with email/SMS reminders
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/../shared/email.php';

$user = require_auth('edu');
$uid  = (int)$user['id'];

$action  = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);
$filter_class = (int)($_GET['class'] ?? 0);

/* ── POST handler ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create' || $pa === 'update') {
        $id          = (int)($_POST['assignment_id'] ?? 0);
        $class_id    = (int)($_POST['class_id'] ?? 0) ?: null;
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date    = trim($_POST['due_date'] ?? '');
        $status      = in_array($_POST['status']??'', ['pending','in_progress','completed']) ? $_POST['status'] : 'pending';
        $priority    = in_array($_POST['priority']??'', ['low','medium','high']) ? $_POST['priority'] : 'medium';

        if ($title === '') {
            flash('danger', 'Assignment title is required.');
        } else {
            $due = $due_date !== '' ? date('Y-m-d H:i:s', strtotime($due_date)) : null;
            if ($pa === 'create') {
                db()->prepare(
                    'INSERT INTO edu_assignments (user_id,class_id,title,description,due_date,status,priority)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$uid,$class_id,$title,$description,$due,$status,$priority]);
                flash('success', "Assignment \"{$title}\" added.");
            } else {
                db()->prepare(
                    'UPDATE edu_assignments SET class_id=?,title=?,description=?,due_date=?,status=?,priority=?
                     WHERE id=? AND user_id=?'
                )->execute([$class_id,$title,$description,$due,$status,$priority,$id,$uid]);
                flash('success', "Assignment \"{$title}\" updated.");
            }
            header('Location: ' . APP_URL . '/edu/assignments.php');
            exit;
        }
    }

    if ($pa === 'delete') {
        $id = (int)($_POST['assignment_id'] ?? 0);
        db()->prepare('DELETE FROM edu_assignments WHERE id=? AND user_id=?')->execute([$id,$uid]);
        flash('success', 'Assignment deleted.');
        header('Location: ' . APP_URL . '/edu/assignments.php');
        exit;
    }

    if ($pa === 'status') {
        $id     = (int)($_POST['assignment_id'] ?? 0);
        $status = in_array($_POST['status']??'', ['pending','in_progress','completed']) ? $_POST['status'] : 'pending';
        db()->prepare('UPDATE edu_assignments SET status=? WHERE id=? AND user_id=?')
            ->execute([$status,$id,$uid]);
        header('Location: ' . APP_URL . '/edu/assignments.php');
        exit;
    }

    if ($pa === 'send_reminder') {
        $id   = (int)($_POST['assignment_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM edu_assignments WHERE id=? AND user_id=?');
        $stmt->execute([$id,$uid]);
        $item = $stmt->fetch();
        if ($item) {
            $full_user = db()->prepare('SELECT * FROM users WHERE id=?');
            $full_user->execute([$uid]);
            $full_user = $full_user->fetch();
            send_due_notification($full_user, $item, 'assignment');
            flash('success', 'Reminder sent.');
        }
        header('Location: ' . APP_URL . '/edu/assignments.php');
        exit;
    }
}

$edit_assignment = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM edu_assignments WHERE id=? AND user_id=?');
    $stmt->execute([$edit_id, $uid]);
    $edit_assignment = $stmt->fetch();
    if (!$edit_assignment) { flash('danger','Assignment not found.'); header('Location: '.APP_URL.'/edu/assignments.php'); exit; }
}

$classes = db()->prepare('SELECT id, name, color FROM edu_classes WHERE user_id=? AND is_active=1 ORDER BY name');
$classes->execute([$uid]);
$classes = $classes->fetchAll();

$where  = 'a.user_id = ?';
$params = [$uid];
if ($filter_class > 0) { $where .= ' AND a.class_id = ?'; $params[] = $filter_class; }

$assignments = db()->prepare(
    "SELECT a.*, c.name AS class_name, c.color AS class_color
     FROM edu_assignments a
     LEFT JOIN edu_classes c ON c.id = a.class_id
     WHERE {$where} ORDER BY
       CASE a.status WHEN 'pending' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,
       a.due_date ASC, a.created_at DESC"
);
$assignments->execute($params);
$assignments = $assignments->fetchAll();

$nav_items = [
    ['icon' => 'dashboard',     'label' => 'Dashboard',   'href' => APP_URL . '/edu/'],
    ['icon' => 'school',        'label' => 'Classes',     'href' => APP_URL . '/edu/classes.php'],
    ['icon' => 'assignment',    'label' => 'Assignments', 'href' => APP_URL . '/edu/assignments.php', 'active' => true],
    ['icon' => 'task_alt',      'label' => 'Tasks',       'href' => APP_URL . '/edu/tasks.php'],
    ['icon' => 'sticky_note_2', 'label' => 'Notes',       'href' => APP_URL . '/edu/notes.php'],
    ['icon' => 'calendar_month','label' => 'Schedule',    'href' => APP_URL . '/edu/schedule.php'],
    ['section' => 'Account'],
    ['icon' => 'apps',          'label' => 'All Apps',    'href' => APP_URL . '/'],
];

$title   = $action === 'new' ? 'New Assignment' : ($action === 'edit' ? 'Edit Assignment' : 'Assignments');
$actions = $action === 'list' ?
    '<a href="?action=new" class="btn btn-primary btn-sm">
       <span class="material-symbols-outlined">add</span> New Assignment
     </a>' : '';

ui_head($title . ' – EDU Hub', 'edu', 'EDU Hub', 'school');
ui_sidebar('EDU Hub', 'school', $nav_items, APP_URL . '/id/auth/logout.php');
ui_page_header($title, 'EDU Hub → Assignments', $actions);
?>

<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<?php
$a = $edit_assignment ?: [];
ui_card_open('assignment', $action === 'new' ? 'Add Assignment' : 'Edit Assignment');
?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
    <?php if ($action === 'edit'): ?><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-group" style="grid-column:1/-1">
        <label for="title">Title *</label>
        <input type="text" id="title" name="title" class="form-control"
               value="<?= htmlspecialchars($a['title'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id" class="form-control">
          <option value="">— No class —</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
            <?= ($a['class_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="due_date">Due Date & Time</label>
        <input type="datetime-local" id="due_date" name="due_date" class="form-control"
               value="<?= $a['due_date'] ? date('Y-m-d\TH:i', strtotime($a['due_date'])) : '' ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <?php foreach (['pending','in_progress','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= ($a['status'] ?? 'pending') === $s ? 'selected' : '' ?>>
            <?= ucwords(str_replace('_',' ',$s)) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Priority</label>
        <select name="priority" class="form-control">
          <?php foreach (['low','medium','high'] as $p): ?>
          <option value="<?= $p ?>" <?= ($a['priority'] ?? 'medium') === $p ? 'selected' : '' ?>>
            <?= ucfirst($p) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Description / Notes</label>
      <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($a['description'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action === 'new' ? 'Add Assignment' : 'Save Changes' ?>
      </button>
      <a href="<?= APP_URL ?>/edu/assignments.php" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<!-- Filter bar -->
<?php if ($classes): ?>
<div class="flex gap-2 mb-4" style="flex-wrap:wrap">
  <a href="<?= APP_URL ?>/edu/assignments.php" class="btn btn-sm <?= !$filter_class ? 'btn-primary' : '' ?>">All</a>
  <?php foreach ($classes as $c): ?>
  <a href="?class=<?= (int)$c['id'] ?>" class="btn btn-sm <?= $filter_class === (int)$c['id'] ? 'btn-primary' : '' ?>"
     style="border-left:3px solid <?= htmlspecialchars($c['color']) ?>">
    <?= htmlspecialchars($c['name']) ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
/* Group by status */
$groups = ['pending' => [], 'in_progress' => [], 'completed' => []];
foreach ($assignments as $a) $groups[$a['status']][] = $a;

foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $status => $label):
  if (!$groups[$status] && $status === 'completed') continue;
  $icon = $status === 'completed' ? 'check_circle' : ($status === 'in_progress' ? 'pending' : 'assignment');
?>

<?php ui_card_open($icon, $label . ' (' . count($groups[$status]) . ')'); ?>
  <?php if ($groups[$status]): ?>
  <div class="table-wrap">
    <table>
      <tr><th>Assignment</th><th>Class</th><th>Due</th><th>Priority</th><th>Actions</th></tr>
      <?php foreach ($groups[$status] as $a): ?>
      <?php $overdue = $a['due_date'] && strtotime($a['due_date']) < time() && $a['status'] !== 'completed'; ?>
      <tr <?= $overdue ? 'style="background:rgba(239,68,68,.04)"' : '' ?>>
        <td>
          <div style="font-weight:500"><?= htmlspecialchars($a['title']) ?></div>
          <?php if ($a['description']): ?>
          <div class="text-xs text-muted truncate" style="max-width:300px"><?= htmlspecialchars(substr($a['description'],0,100)) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($a['class_name']): ?>
          <span class="class-chip" style="background:<?= htmlspecialchars($a['class_color']) ?>22;color:<?= htmlspecialchars($a['class_color']) ?>">
            <?= htmlspecialchars($a['class_name']) ?>
          </span>
          <?php else: ?>
          <span class="text-muted text-xs">—</span>
          <?php endif; ?>
        </td>
        <td class="text-sm" style="<?= $overdue ? 'color:var(--danger)' : '' ?>">
          <?= $a['due_date'] ? date('M j, Y g:i A', strtotime($a['due_date'])) : '—' ?>
          <?php if ($overdue): ?><div class="text-xs" style="color:var(--danger)">Overdue</div><?php endif; ?>
        </td>
        <td><?= ui_priority($a['priority']) ?></td>
        <td>
          <div class="flex gap-1">
            <!-- Quick status change -->
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
              <?php if ($status !== 'completed'): ?>
              <input type="hidden" name="status" value="completed">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--success)" title="Mark complete">
                <span class="material-symbols-outlined">check</span>
              </button>
              <?php else: ?>
              <input type="hidden" name="status" value="pending">
              <button type="submit" class="btn btn-ghost btn-sm" title="Mark pending">
                <span class="material-symbols-outlined">undo</span>
              </button>
              <?php endif; ?>
            </form>
            <a href="?action=edit&id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">
              <span class="material-symbols-outlined">edit</span>
            </a>
            <!-- Send reminder -->
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="send_reminder">
              <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="Send email/SMS reminder">
                <span class="material-symbols-outlined">notifications</span>
              </button>
            </form>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                      data-confirm="Delete this assignment?">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state" style="padding:1.5rem">
    <span class="material-symbols-outlined">assignment</span>
    <h3>No <?= strtolower($label) ?> assignments</h3>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>
<div style="margin-top:1rem"></div>
<?php endforeach; ?>

<?php endif; ?>
</div>
<?php ui_end(); ?>
