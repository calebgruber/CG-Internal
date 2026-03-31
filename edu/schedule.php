<?php
/**
 * edu/schedule.php – Weekly class schedule builder
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('edu');
$uid  = (int)$user['id'];
$action  = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create' || $pa === 'update') {
        $id         = (int)($_POST['slot_id'] ?? 0);
        $class_id   = (int)($_POST['class_id'] ?? 0);
        $dow        = (int)($_POST['day_of_week'] ?? 1);
        $start      = trim($_POST['start_time'] ?? '');
        $end        = trim($_POST['end_time'] ?? '');
        $location   = trim($_POST['location'] ?? '');

        if (!$class_id || $start === '' || $end === '') {
            flash('danger','Class, start time, and end time are required.');
        } else {
            if ($pa === 'create') {
                db()->prepare('INSERT INTO edu_schedule (user_id,class_id,day_of_week,start_time,end_time,location) VALUES (?,?,?,?,?,?)')
                    ->execute([$uid,$class_id,$dow,$start,$end,$location]);
                flash('success','Schedule slot added.');
            } else {
                db()->prepare('UPDATE edu_schedule SET class_id=?,day_of_week=?,start_time=?,end_time=?,location=? WHERE id=? AND user_id=?')
                    ->execute([$class_id,$dow,$start,$end,$location,$id,$uid]);
                flash('success','Schedule slot updated.');
            }
            header('Location: '.APP_URL.'/edu/schedule.php'); exit;
        }
    }
    if ($pa === 'delete') {
        db()->prepare('DELETE FROM edu_schedule WHERE id=? AND user_id=?')->execute([(int)$_POST['slot_id'],$uid]);
        flash('success','Slot removed.');
        header('Location: '.APP_URL.'/edu/schedule.php'); exit;
    }
}

$edit_slot = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM edu_schedule WHERE id=? AND user_id=?');
    $stmt->execute([$edit_id,$uid]); $edit_slot = $stmt->fetch();
    if (!$edit_slot) { flash('danger','Slot not found.'); header('Location: '.APP_URL.'/edu/schedule.php'); exit; }
}

$classes = db()->prepare('SELECT id,name,color FROM edu_classes WHERE user_id=? AND is_active=1 ORDER BY name');
$classes->execute([$uid]); $classes = $classes->fetchAll();

// All slots, keyed by day
$all_slots = db()->prepare(
    'SELECT s.*,c.name AS class_name,c.color FROM edu_schedule s JOIN edu_classes c ON c.id=s.class_id
     WHERE s.user_id=? ORDER BY s.day_of_week,s.start_time'
);
$all_slots->execute([$uid]); $all_slots = $all_slots->fetchAll();
$by_day = array_fill(0, 7, []);
foreach ($all_slots as $s) $by_day[$s['day_of_week']][] = $s;

$days  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$today = (int)date('w');

$nav_items = [
    ['icon'=>'dashboard',    'label'=>'Dashboard',   'href'=>APP_URL.'/edu/'],
    ['icon'=>'school',       'label'=>'Classes',     'href'=>APP_URL.'/edu/classes.php'],
    ['icon'=>'assignment',   'label'=>'Assignments', 'href'=>APP_URL.'/edu/assignments.php'],
    ['icon'=>'task_alt',     'label'=>'Tasks',       'href'=>APP_URL.'/edu/tasks.php'],
    ['icon'=>'sticky_note_2','label'=>'Notes',       'href'=>APP_URL.'/edu/notes.php'],
    ['icon'=>'calendar_month','label'=>'Schedule',   'href'=>APP_URL.'/edu/schedule.php','active'=>true],
    ['section'=>'Account'],
    ['icon'=>'apps',         'label'=>'All Apps',    'href'=>APP_URL.'/'],
];

$title   = $action==='new'?'Add Slot':($action==='edit'?'Edit Slot':'Weekly Schedule');
$actions = $action==='list'?'<a href="?action=new" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> Add Slot</a>':'';

ui_head($title.' – EDU Hub','edu','EDU Hub','school');
ui_sidebar('EDU Hub','school',$nav_items,APP_URL.'/id/auth/logout.php');
ui_page_header($title,'EDU Hub → Schedule',$actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action==='new'||$action==='edit'): ?>
<?php $sl=$edit_slot?:[]; ui_card_open('calendar_month',$action==='new'?'Add Schedule Slot':'Edit Slot'); ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action==='new'?'create':'update' ?>">
    <?php if ($action==='edit'): ?><input type="hidden" name="slot_id" value="<?=(int)$sl['id']?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="class_id">Class *</label>
        <select id="class_id" name="class_id" class="form-control" required>
          <option value="">— Select class —</option>
          <?php foreach($classes as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=($sl['class_id']??0)==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="day_of_week">Day *</label>
        <select id="day_of_week" name="day_of_week" class="form-control">
          <?php foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i=>$d): ?>
          <option value="<?=$i?>" <?=($sl['day_of_week']??1)==$i?'selected':''?>><?=$d?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="start_time">Start Time *</label>
        <input type="time" id="start_time" name="start_time" class="form-control"
               value="<?=htmlspecialchars($sl['start_time']??'')?>" required>
      </div>
      <div class="form-group">
        <label for="end_time">End Time *</label>
        <input type="time" id="end_time" name="end_time" class="form-control"
               value="<?=htmlspecialchars($sl['end_time']??'')?>" required>
      </div>
    </div>

    <div class="form-group">
      <label for="location">Location / Room</label>
      <input type="text" id="location" name="location" class="form-control"
             value="<?=htmlspecialchars($sl['location']??'')?>">
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Save</button>
      <a href="<?= APP_URL ?>/edu/schedule.php" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<?php
if (!$classes) {
    echo '<div class="alerts"><div class="alert alert-info">
        <span class="material-symbols-outlined">info</span>
        <span class="alert-text">Add classes first before building your schedule.</span>
        <a href="' . APP_URL . '/edu/classes.php?action=new" class="btn btn-primary btn-sm" style="margin-left:auto">Add Class</a>
    </div></div>';
}
ui_card_open('calendar_month','Weekly Schedule');
?>
  <div class="schedule-grid">
    <?php foreach ($days as $i => $day): ?>
    <div class="schedule-day">
      <div class="schedule-day-header" <?=$i===$today?'style="background:rgba(59,130,246,.12);color:var(--primary)"':''?>>
        <?=$day?>
        <?php if ($i === $today): ?><span style="font-size:.625rem;display:block;text-transform:none;font-weight:400">Today</span><?php endif; ?>
      </div>
      <div class="schedule-day-body">
        <?php foreach($by_day[$i] as $slot): ?>
        <div class="schedule-class" style="background:<?=htmlspecialchars($slot['color'])?>22;color:<?=htmlspecialchars($slot['color'])?>;border-left-color:<?=htmlspecialchars($slot['color'])?>">
          <div><?=htmlspecialchars($slot['class_name'])?></div>
          <div style="font-weight:400;opacity:.8"><?=date('g:ia',strtotime($slot['start_time']))?>–<?=date('g:ia',strtotime($slot['end_time']))?></div>
          <?php if($slot['location']): ?><div style="font-weight:400;opacity:.7"><?=htmlspecialchars($slot['location'])?></div><?php endif; ?>
          <div class="flex gap-1 mt-1">
            <a href="?action=edit&id=<?=(int)$slot['id']?>" class="btn btn-ghost btn-sm" style="padding:.125rem .25rem">
              <span class="material-symbols-outlined" style="font-size:.75rem">edit</span>
            </a>
            <form method="POST" style="display:inline">
              <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?=(int)$slot['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="padding:.125rem .25rem;color:var(--danger)" data-confirm="Remove this slot?">
                <span class="material-symbols-outlined" style="font-size:.75rem">delete</span>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$by_day[$i]): ?>
        <div style="font-size:.6875rem;color:var(--text-muted);text-align:center;padding:.5rem 0">—</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php ui_card_close(); ?>

<!-- List view for editing -->
<?php if ($all_slots): ?>
<div style="margin-top:1.5rem"></div>
<?php ui_card_open('list','All Slots'); ?>
  <div class="table-wrap"><table>
    <tr><th>Class</th><th>Day</th><th>Time</th><th>Location</th><th></th></tr>
    <?php foreach($all_slots as $slot): ?>
    <tr>
      <td><span style="color:<?=htmlspecialchars($slot['color'])?>"><?=htmlspecialchars($slot['class_name'])?></span></td>
      <td><?=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$slot['day_of_week']]?></td>
      <td class="text-sm"><?=date('g:i A',strtotime($slot['start_time']))?> – <?=date('g:i A',strtotime($slot['end_time']))?></td>
      <td class="text-sm text-muted"><?=htmlspecialchars($slot['location']??'—')?></td>
      <td>
        <div class="flex gap-1">
          <a href="?action=edit&id=<?=(int)$slot['id']?>" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined">edit</span></a>
          <form method="POST" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?=(int)$slot['id']?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Remove slot?"><span class="material-symbols-outlined">delete</span></button></form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
<?php ui_card_close(); ?>
<?php endif; ?>

<?php endif; ?>
</div>
<?php ui_end(); ?>
