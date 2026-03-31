<?php
/**
 * edu/tasks.php – Standalone task list (not tied to classes)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create' || $pa === 'update') {
        $id          = (int)($_POST['task_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date    = trim($_POST['due_date'] ?? '');
        $status      = in_array($_POST['status']??'',['pending','in_progress','completed'])?$_POST['status']:'pending';
        $priority    = in_array($_POST['priority']??'',['low','medium','high'])?$_POST['priority']:'medium';

        if ($title === '') { flash('danger','Task title required.'); }
        else {
            $due = $due_date !== '' ? date('Y-m-d H:i:s', strtotime($due_date)) : null;
            if ($pa === 'create') {
                db()->prepare('INSERT INTO edu_tasks (user_id,title,description,due_date,status,priority) VALUES (?,?,?,?,?,?)')
                    ->execute([$uid,$title,$description,$due,$status,$priority]);
                flash('success',"Task \"{$title}\" added.");
            } else {
                db()->prepare('UPDATE edu_tasks SET title=?,description=?,due_date=?,status=?,priority=? WHERE id=? AND user_id=?')
                    ->execute([$title,$description,$due,$status,$priority,$id,$uid]);
                flash('success',"Task \"{$title}\" updated.");
            }
            header('Location: '.APP_URL.'/edu/tasks.php'); exit;
        }
    }
    if ($pa === 'delete') {
        db()->prepare('DELETE FROM edu_tasks WHERE id=? AND user_id=?')->execute([(int)$_POST['task_id'],$uid]);
        flash('success','Task deleted.');
        header('Location: '.APP_URL.'/edu/tasks.php'); exit;
    }
    if ($pa === 'status') {
        $s = in_array($_POST['status']??'',['pending','in_progress','completed'])?$_POST['status']:'pending';
        db()->prepare('UPDATE edu_tasks SET status=? WHERE id=? AND user_id=?')
            ->execute([$s,(int)$_POST['task_id'],$uid]);
        header('Location: '.APP_URL.'/edu/tasks.php'); exit;
    }
    if ($pa === 'send_reminder') {
        $stmt = db()->prepare('SELECT * FROM edu_tasks WHERE id=? AND user_id=?');
        $stmt->execute([(int)$_POST['task_id'],$uid]);
        $item = $stmt->fetch();
        if ($item) {
            $fu = db()->prepare('SELECT * FROM users WHERE id=?'); $fu->execute([$uid]); $fu = $fu->fetch();
            send_due_notification($fu,$item,'task');
            flash('success','Reminder sent.');
        }
        header('Location: '.APP_URL.'/edu/tasks.php'); exit;
    }
}

$edit_task = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM edu_tasks WHERE id=? AND user_id=?');
    $stmt->execute([$edit_id,$uid]); $edit_task = $stmt->fetch();
    if (!$edit_task) { flash('danger','Task not found.'); header('Location: '.APP_URL.'/edu/tasks.php'); exit; }
}

$tasks = db()->prepare(
    "SELECT * FROM edu_tasks WHERE user_id=?
     ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,
     due_date ASC, created_at DESC"
);
$tasks->execute([$uid]); $tasks = $tasks->fetchAll();

$nav_items = [
    ['icon'=>'dashboard',    'label'=>'Dashboard',   'href'=>APP_URL.'/edu/'],
    ['icon'=>'school',       'label'=>'Classes',     'href'=>APP_URL.'/edu/classes.php'],
    ['icon'=>'assignment',   'label'=>'Assignments', 'href'=>APP_URL.'/edu/assignments.php'],
    ['icon'=>'task_alt',     'label'=>'Tasks',       'href'=>APP_URL.'/edu/tasks.php','active'=>true],
    ['icon'=>'sticky_note_2','label'=>'Notes',       'href'=>APP_URL.'/edu/notes.php'],
    ['icon'=>'calendar_month','label'=>'Schedule',   'href'=>APP_URL.'/edu/schedule.php'],
    ['section'=>'Account'],
    ['icon'=>'apps',         'label'=>'All Apps',    'href'=>APP_URL.'/'],
];

$title = $action==='new'?'New Task':($action==='edit'?'Edit Task':'Tasks');
$actions = $action==='list'?'<a href="?action=new" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> New Task</a>':'';

ui_head($title.' – EDU Hub','edu','EDU Hub','school');
ui_sidebar('EDU Hub','school',$nav_items,APP_URL.'/id/auth/logout.php');
ui_page_header($title,'EDU Hub → Tasks',$actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action==='new'||$action==='edit'): ?>
<?php $t=$edit_task?:[]; ui_card_open('task_alt',$action==='new'?'New Task':'Edit Task'); ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action==='new'?'create':'update' ?>">
    <?php if ($action==='edit'): ?><input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label for="title">Title *</label>
      <input type="text" id="title" name="title" class="form-control"
             value="<?= htmlspecialchars($t['title']??'') ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="due_date">Due Date</label>
        <input type="datetime-local" id="due_date" name="due_date" class="form-control"
               value="<?= $t['due_date']?date('Y-m-d\TH:i',strtotime($t['due_date'])):'' ?>">
      </div>
      <div class="form-group">
        <label>Priority</label>
        <select name="priority" class="form-control">
          <?php foreach(['low','medium','high'] as $p): ?>
          <option value="<?=$p?>" <?=($t['priority']??'medium')===$p?'selected':''?>><?=ucfirst($p)?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Status</label>
      <select name="status" class="form-control">
        <?php foreach(['pending','in_progress','completed'] as $s): ?>
        <option value="<?=$s?>" <?=($t['status']??'pending')===$s?'selected':''?>><?=ucwords(str_replace('_',' ',$s))?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="description">Notes</label>
      <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($t['description']??'') ?></textarea>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action==='new'?'Add Task':'Save' ?>
      </button>
      <a href="<?= APP_URL ?>/edu/tasks.php" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<?php
$groups = ['pending'=>[],'in_progress'=>[],'completed'=>[]];
foreach ($tasks as $t) $groups[$t['status']][] = $t;
foreach (['pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed'] as $status=>$label):
  if (!$groups[$status] && $status==='completed') continue;
  $icon = $status==='completed'?'check_circle':($status==='in_progress'?'pending':'task_alt');
?>
<?php ui_card_open($icon,$label.' ('.count($groups[$status]).')'); ?>
  <?php if ($groups[$status]): ?>
  <div class="table-wrap"><table>
    <tr><th>Task</th><th>Due</th><th>Priority</th><th>Actions</th></tr>
    <?php foreach ($groups[$status] as $t): ?>
    <?php $overdue=$t['due_date']&&strtotime($t['due_date'])<time()&&$t['status']!=='completed'; ?>
    <tr <?=$overdue?'style="background:rgba(239,68,68,.04)"':''?>>
      <td>
        <div style="font-weight:500"><?=htmlspecialchars($t['title'])?></div>
        <?php if($t['description']): ?><div class="text-xs text-muted"><?=htmlspecialchars(substr($t['description'],0,80))?></div><?php endif; ?>
      </td>
      <td class="text-sm" style="<?=$overdue?'color:var(--danger)':''?>">
        <?=$t['due_date']?date('M j, Y g:i A',strtotime($t['due_date'])):'—'?>
        <?php if($overdue): ?><div class="text-xs" style="color:var(--danger)">Overdue</div><?php endif; ?>
      </td>
      <td><?=ui_priority($t['priority'])?></td>
      <td>
        <div class="flex gap-1">
          <form method="POST" style="display:inline">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="task_id" value="<?=(int)$t['id']?>">
            <?php if($status!=='completed'): ?>
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--success)" title="Done"><span class="material-symbols-outlined">check</span></button>
            <?php else: ?>
            <input type="hidden" name="status" value="pending">
            <button type="submit" class="btn btn-ghost btn-sm" title="Reopen"><span class="material-symbols-outlined">undo</span></button>
            <?php endif; ?>
          </form>
          <a href="?action=edit&id=<?=(int)$t['id']?>" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined">edit</span></a>
          <form method="POST" style="display:inline">
            <?=csrf_field()?><input type="hidden" name="action" value="send_reminder"><input type="hidden" name="task_id" value="<?=(int)$t['id']?>">
            <button type="submit" class="btn btn-ghost btn-sm" title="Send reminder"><span class="material-symbols-outlined">notifications</span></button>
          </form>
          <form method="POST" style="display:inline">
            <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="task_id" value="<?=(int)$t['id']?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Delete this task?"><span class="material-symbols-outlined">delete</span></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php else: ?>
  <div class="empty-state" style="padding:1.5rem">
    <span class="material-symbols-outlined">task_alt</span>
    <h3>No <?=strtolower($label)?> tasks</h3>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>
<div style="margin-top:1rem"></div>
<?php endforeach; ?>

<?php endif; ?>
</div>
<?php ui_end(); ?>
