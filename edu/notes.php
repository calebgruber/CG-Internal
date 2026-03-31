<?php
/**
 * edu/notes.php – Notes with Markdown-style text areas
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
        $id       = (int)($_POST['note_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0) ?: null;
        $title    = trim($_POST['title'] ?? '');
        $content  = $_POST['content'] ?? '';

        if ($title === '') { flash('danger','Note title required.'); }
        else {
            if ($pa === 'create') {
                db()->prepare('INSERT INTO edu_notes (user_id,class_id,title,content) VALUES (?,?,?,?)')
                    ->execute([$uid,$class_id,$title,$content]);
                flash('success',"Note \"{$title}\" saved.");
            } else {
                db()->prepare('UPDATE edu_notes SET class_id=?,title=?,content=? WHERE id=? AND user_id=?')
                    ->execute([$class_id,$title,$content,$id,$uid]);
                flash('success',"Note updated.");
            }
            header('Location: '.APP_URL.'/edu/notes.php'); exit;
        }
    }
    if ($pa === 'delete') {
        db()->prepare('DELETE FROM edu_notes WHERE id=? AND user_id=?')->execute([(int)$_POST['note_id'],$uid]);
        flash('success','Note deleted.');
        header('Location: '.APP_URL.'/edu/notes.php'); exit;
    }
}

$edit_note = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = db()->prepare('SELECT * FROM edu_notes WHERE id=? AND user_id=?');
    $stmt->execute([$edit_id,$uid]); $edit_note = $stmt->fetch();
    if (!$edit_note) { flash('danger','Note not found.'); header('Location: '.APP_URL.'/edu/notes.php'); exit; }
}

$classes = db()->prepare('SELECT id,name,color FROM edu_classes WHERE user_id=? ORDER BY name');
$classes->execute([$uid]); $classes = $classes->fetchAll();

$notes = db()->prepare(
    'SELECT n.*,c.name AS class_name,c.color AS class_color
     FROM edu_notes n LEFT JOIN edu_classes c ON c.id=n.class_id
     WHERE n.user_id=? ORDER BY n.updated_at DESC'
);
$notes->execute([$uid]); $notes = $notes->fetchAll();

$nav_items = [
    ['icon'=>'dashboard',    'label'=>'Dashboard',   'href'=>APP_URL.'/edu/'],
    ['icon'=>'school',       'label'=>'Classes',     'href'=>APP_URL.'/edu/classes.php'],
    ['icon'=>'assignment',   'label'=>'Assignments', 'href'=>APP_URL.'/edu/assignments.php'],
    ['icon'=>'task_alt',     'label'=>'Tasks',       'href'=>APP_URL.'/edu/tasks.php'],
    ['icon'=>'sticky_note_2','label'=>'Notes',       'href'=>APP_URL.'/edu/notes.php','active'=>true],
    ['icon'=>'calendar_month','label'=>'Schedule',   'href'=>APP_URL.'/edu/schedule.php'],
    ['section'=>'Account'],
    ['icon'=>'apps',         'label'=>'All Apps',    'href'=>APP_URL.'/'],
];

$title = $action==='new'?'New Note':($action==='edit'?'Edit Note':'Notes');
$actions = $action==='list'?'<a href="?action=new" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> New Note</a>':'';

ui_head($title.' – EDU Hub','edu','EDU Hub','school');
ui_sidebar('EDU Hub','school',$nav_items,APP_URL.'/id/auth/logout.php');
ui_page_header($title,'EDU Hub → Notes',$actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php if ($action==='new'||$action==='edit'): ?>
<?php $n=$edit_note?:[]; ui_card_open('sticky_note_2',$action==='new'?'New Note':'Edit Note'); ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $action==='new'?'create':'update' ?>">
    <?php if($action==='edit'): ?><input type="hidden" name="note_id" value="<?=(int)$n['id']?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="title">Title *</label>
        <input type="text" id="title" name="title" class="form-control"
               value="<?= htmlspecialchars($n['title']??'') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="class_id">Class (optional)</label>
        <select id="class_id" name="class_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=($n['class_id']??0)==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="content">Content</label>
      <textarea id="content" name="content" class="form-control"
                style="min-height:350px;font-family:monospace;font-size:.875rem"><?= htmlspecialchars($n['content']??'') ?></textarea>
      <div class="form-hint">Supports plain text, markdown-style formatting.</div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <?= $action==='new'?'Save Note':'Update Note' ?>
      </button>
      <a href="<?= APP_URL ?>/edu/notes.php" class="btn">Cancel</a>
    </div>
  </form>
<?php ui_card_close(); ?>

<?php else: ?>

<?php ui_card_open('sticky_note_2','All Notes ('.count($notes).')'); ?>
  <?php if ($notes): ?>
  <div class="card-grid">
    <?php foreach ($notes as $note): ?>
    <div class="card" style="<?= $note['class_color'] ? 'border-top:3px solid '.htmlspecialchars($note['class_color']) : '' ?>">
      <div class="card-header">
        <span class="material-symbols-outlined" style="color:var(--primary)">sticky_note_2</span>
        <h3 style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($note['title'])?></h3>
      </div>
      <div class="card-body">
        <?php if ($note['class_name']): ?>
        <div class="text-xs mb-4" style="color:<?=htmlspecialchars($note['class_color']??'#3b82f6')?>">
          <span class="material-symbols-outlined" style="font-size:.875rem;vertical-align:middle">school</span>
          <?=htmlspecialchars($note['class_name'])?>
        </div>
        <?php endif; ?>
        <?php if ($note['content']): ?>
        <p style="white-space:pre-wrap;font-size:.8125rem;line-height:1.6;max-height:5rem;overflow:hidden"><?=htmlspecialchars(substr($note['content'],0,200))?><?=strlen($note['content'])>200?'…':''?></p>
        <?php else: ?>
        <p class="text-muted text-sm">Empty note</p>
        <?php endif; ?>
        <div class="text-xs text-muted mt-2">Updated <?=date('M j, Y',strtotime($note['updated_at']))?></div>
      </div>
      <div class="card-footer">
        <a href="?action=edit&id=<?=(int)$note['id']?>" class="btn btn-ghost btn-sm">
          <span class="material-symbols-outlined">edit</span> Edit
        </a>
        <form method="POST" style="display:inline">
          <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?=(int)$note['id']?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Delete this note?">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">sticky_note_2</span>
    <h3>No notes yet</h3>
    <p>Create your first note to capture class content, ideas, or anything else.</p>
    <a href="?action=new" class="btn btn-primary">
      <span class="material-symbols-outlined">add</span> Create First Note
    </a>
  </div>
  <?php endif; ?>
<?php ui_card_close(); ?>

<?php endif; ?>
</div>
<?php ui_end(); ?>
