<?php
/**
 * edu/index.php – EDU Hub Dashboard
 * URL: internal.calebgruber.me/edu/
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('edu');
$uid  = (int)$user['id'];

/* ── Stats ──────────────────────────────────────────── */
try {
    $total_classes_q = db()->prepare('SELECT COUNT(*) FROM edu_classes WHERE user_id=? AND is_active=1');
    $total_classes_q->execute([$uid]);
    $total_classes = (int)$total_classes_q->fetchColumn();

    $pending_assignments_q = db()->prepare(
        "SELECT COUNT(*) FROM edu_assignments WHERE user_id=? AND status!='completed'"
    );
    $pending_assignments_q->execute([$uid]);
    $pending_assignments = (int)$pending_assignments_q->fetchColumn();

    $pending_tasks_q = db()->prepare(
        "SELECT COUNT(*) FROM edu_tasks WHERE user_id=? AND status!='completed'"
    );
    $pending_tasks_q->execute([$uid]);
    $pending_tasks = (int)$pending_tasks_q->fetchColumn();

    $total_notes_q = db()->prepare('SELECT COUNT(*) FROM edu_notes WHERE user_id=?');
    $total_notes_q->execute([$uid]);
    $total_notes = (int)$total_notes_q->fetchColumn();

    /* ── Upcoming (next 7 days) ─────────────────────────── */
    $upcoming_q = db()->prepare(
        "SELECT a.id, a.title, a.due_date, a.priority, a.status, c.name AS class_name, c.color, 'assignment' AS type
         FROM edu_assignments a
         LEFT JOIN edu_classes c ON c.id = a.class_id
         WHERE a.user_id=? AND a.status != 'completed' AND a.due_date IS NOT NULL
           AND a.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
         UNION ALL
         SELECT t.id, t.title, t.due_date, t.priority, t.status, NULL, NULL, 'task' AS type
         FROM edu_tasks t
         WHERE t.user_id=? AND t.status != 'completed' AND t.due_date IS NOT NULL
           AND t.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
         ORDER BY due_date ASC LIMIT 10"
    );
    $upcoming_q->execute([$uid, $uid]);
    $upcoming = $upcoming_q->fetchAll();

    /* ── Today's schedule ───────────────────────────────── */
    $today_dow = (int)date('w');  // 0=Sun
    $schedule_q = db()->prepare(
        'SELECT s.start_time, s.end_time, s.location, c.name AS class_name, c.color
         FROM edu_schedule s JOIN edu_classes c ON c.id = s.class_id
         WHERE s.user_id=? AND s.day_of_week=?
         ORDER BY s.start_time'
    );
    $schedule_q->execute([$uid, $today_dow]);
    $schedule = $schedule_q->fetchAll();

    /* ── Recent notes ─────────────────────────────────────*/
    $recent_notes_q = db()->prepare(
        'SELECT n.id, n.title, n.updated_at, c.name AS class_name, c.color
         FROM edu_notes n LEFT JOIN edu_classes c ON c.id = n.class_id
         WHERE n.user_id=? ORDER BY n.updated_at DESC LIMIT 5'
    );
    $recent_notes_q->execute([$uid]);
    $recent_notes = $recent_notes_q->fetchAll();

    /* ── System alerts for this app ──────────────────────── */
    $sys_alerts = db()->query(
        "SELECT text, type, icon, dismissible FROM system_alerts WHERE is_active=1 ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();
} catch (PDOException $e) {
    $total_classes      = 0;
    $pending_assignments = 0;
    $pending_tasks      = 0;
    $total_notes        = 0;
    $upcoming           = [];
    $today_dow          = (int)date('w');
    $schedule           = [];
    $recent_notes       = [];
    $sys_alerts         = [];
}

$nav_items = [
    ['icon' => 'dashboard',    'label' => 'Dashboard',   'href' => APP_URL . '/edu/',                 'active' => true],
    ['icon' => 'school',       'label' => 'Classes',     'href' => APP_URL . '/edu/classes'],
    ['icon' => 'assignment',   'label' => 'Assignments', 'href' => APP_URL . '/edu/assignments'],
    ['icon' => 'task_alt',     'label' => 'Tasks',       'href' => APP_URL . '/edu/tasks'],
    ['icon' => 'sticky_note_2','label' => 'Notes',       'href' => APP_URL . '/edu/notes'],
    ['icon' => 'calendar_month','label' => 'Schedule',   'href' => APP_URL . '/edu/schedule'],
];

$actions = '
  <a href="' . APP_URL . '/edu/assignments?action=new" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> New Assignment
  </a>';

ui_head('EDU Hub', 'edu', 'EDU Hub', 'school');
ui_sidebar('EDU Hub', 'school', $nav_items, APP_URL . '/id/auth/logout');
ui_page_header('Dashboard', date('l, F j, Y'), $actions);
?>

<div class="page-body">

<?php ui_flash(); ?>

<!-- ── System alerts ── -->
<?php if ($sys_alerts): ?>
<div class="alerts">
  <?php foreach ($sys_alerts as $sa):
         $type = $sa['type'] ?? 'info';
         [$ac, $ar, $at] = _alert_accent($type);
         $avars = '--alert-accent:' . $ac . ';--alert-accent-rgb:' . $ar . ';--alert-text-on-solid:' . $at;
  ?>
  <div class="alert alert-<?= htmlspecialchars($type) ?>"
       style="<?= $avars ?>"<?= $sa['dismissible'] ? ' data-auto-dismiss="8000"' : '' ?>>
    <span class="material-symbols-outlined"><?= htmlspecialchars($sa['icon']) ?></span>
    <span class="alert-text"><?= htmlspecialchars($sa['text']) ?></span>
    <?php if ($sa['dismissible']): ?>
    <button class="alert-close"><span class="material-symbols-outlined">close</span></button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon"><span class="material-symbols-outlined">school</span></div>
    <div class="stat-label">Classes</div>
    <div class="stat-value"><?= $total_classes ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,.12);color:var(--danger)">
      <span class="material-symbols-outlined">assignment</span>
    </div>
    <div class="stat-label">Pending Assignments</div>
    <div class="stat-value"><?= $pending_assignments ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
      <span class="material-symbols-outlined">task_alt</span>
    </div>
    <div class="stat-label">Open Tasks</div>
    <div class="stat-value"><?= $pending_tasks ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
      <span class="material-symbols-outlined">sticky_note_2</span>
    </div>
    <div class="stat-label">Notes</div>
    <div class="stat-value"><?= $total_notes ?></div>
  </div>
</div>

<div class="card-grid card-grid-2">

  <!-- ── Upcoming due ── -->
  <?php
  ui_card_open('event_upcoming', 'Due in the Next 7 Days',
    '<a href="' . APP_URL . '/edu/assignments" class="btn btn-sm" style="margin-left:auto">View All</a>');
  ?>
    <?php if ($upcoming): ?>
    <div class="table-wrap">
      <table>
        <tr><th>Item</th><th>Type</th><th>Due</th><th>Priority</th><th>Status</th></tr>
        <?php foreach ($upcoming as $item): ?>
        <?php
          $overdue = $item['due_date'] && strtotime($item['due_date']) < time();
          $due_str = date('M j, g:i A', strtotime($item['due_date']));
        ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($item['title']) ?></div>
            <?php if ($item['class_name']): ?>
            <div class="text-xs" style="color:<?= htmlspecialchars($item['color'] ?? '#3b82f6') ?>">
              <?= htmlspecialchars($item['class_name']) ?>
            </div>
            <?php endif; ?>
          </td>
          <td><?= ui_badge(ucfirst($item['type']), $item['type'] === 'assignment' ? 'info' : 'neutral') ?></td>
          <td class="text-sm <?= $overdue ? 'text-muted' : '' ?>" style="<?= $overdue ? 'color:var(--danger)' : '' ?>">
            <?= htmlspecialchars($due_str) ?>
            <?php if ($overdue): ?><div class="text-xs" style="color:var(--danger)">Overdue</div><?php endif; ?>
          </td>
          <td><?= ui_priority($item['priority']) ?></td>
          <td><?= ui_badge(str_replace('_',' ',ucfirst($item['status'])),
                  $item['status']==='completed' ? 'success' : ($item['status']==='in_progress' ? 'warning' : 'neutral')) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <span class="material-symbols-outlined">event_available</span>
      <h3>All clear!</h3>
      <p>Nothing due in the next 7 days. Great job!</p>
    </div>
    <?php endif; ?>
  <?php ui_card_close(); ?>

  <!-- ── Today's schedule ── -->
  <?php
  $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  ui_card_open('calendar_today', "Today's Schedule (" . $days[$today_dow] . ")",
    '<a href="' . APP_URL . '/edu/schedule" class="btn btn-sm" style="margin-left:auto">Edit</a>');
  ?>
    <?php if ($schedule): ?>
    <div style="display:flex;flex-direction:column;gap:.5rem;">
      <?php foreach ($schedule as $slot): ?>
      <div style="display:flex;gap:.75rem;align-items:flex-start;padding:.625rem;
                  background:var(--surface-raised);border-radius:var(--radius);
                  border-left:4px solid <?= htmlspecialchars($slot['color'] ?? '#3b82f6') ?>">
        <div style="min-width:80px;font-size:.8125rem;font-weight:600;color:var(--text-muted)">
          <?= date('g:i A', strtotime($slot['start_time'])) ?><br>
          <span style="font-weight:400"><?= date('g:i A', strtotime($slot['end_time'])) ?></span>
        </div>
        <div>
          <div style="font-weight:500;font-size:.875rem"><?= htmlspecialchars($slot['class_name']) ?></div>
          <?php if ($slot['location']): ?>
          <div class="text-xs text-muted"><span class="material-symbols-outlined" style="font-size:.875rem;vertical-align:middle">location_on</span><?= htmlspecialchars($slot['location']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:1.5rem">
      <span class="material-symbols-outlined">calendar_today</span>
      <h3>No classes today</h3>
      <p><a href="<?= APP_URL ?>/edu/schedule">Set up your schedule</a></p>
    </div>
    <?php endif; ?>
  <?php ui_card_close(); ?>

  <!-- ── Recent notes ── -->
  <?php
  ui_card_open('sticky_note_2', 'Recent Notes',
    '<a href="' . APP_URL . '/edu/notes?action=new" class="btn btn-primary btn-sm" style="margin-left:auto">
       <span class="material-symbols-outlined">add</span> New Note
     </a>');
  ?>
    <?php if ($recent_notes): ?>
    <div style="display:flex;flex-direction:column;gap:.5rem;">
      <?php foreach ($recent_notes as $note): ?>
      <a href="<?= APP_URL ?>/edu/notes?action=edit&id=<?= (int)$note['id'] ?>"
         style="display:flex;gap:.75rem;align-items:center;padding:.625rem;
                background:var(--surface-raised);border-radius:var(--radius);
                text-decoration:none;color:var(--text)">
        <span class="material-symbols-outlined" style="color:var(--primary)">sticky_note_2</span>
        <div style="flex:1;min-width:0">
          <div style="font-weight:500;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($note['title']) ?>
          </div>
          <?php if ($note['class_name']): ?>
          <div class="text-xs" style="color:<?= htmlspecialchars($note['color'] ?? '#3b82f6') ?>">
            <?= htmlspecialchars($note['class_name']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="text-xs text-muted"><?= date('M j', strtotime($note['updated_at'])) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:1.5rem">
      <span class="material-symbols-outlined">sticky_note_2</span>
      <h3>No notes yet</h3>
      <p><a href="<?= APP_URL ?>/edu/notes?action=new">Create your first note</a></p>
    </div>
    <?php endif; ?>
  <?php ui_card_close(); ?>

</div><!-- .card-grid -->

</div><!-- .page-body -->
<?php ui_end(); ?>
