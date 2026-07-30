<?php
/**
 * wmata/stations.php – Station list with search, filters, bulk status update
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');

/* ── POST handlers ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'status') {
        $sid    = (int)($_POST['station_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['incomplete','in_progress','complete'])
                  ? $_POST['status'] : 'incomplete';
        db()->prepare('UPDATE wmata_stations SET status=? WHERE id=?')->execute([$status, $sid]);
        flash('success', 'Status updated.');
    }

    if ($pa === 'bulk_status') {
        $ids    = array_filter(array_map('intval', (array)($_POST['station_ids'] ?? [])));
        $status = in_array($_POST['bulk_status'] ?? '', ['incomplete','in_progress','complete'])
                  ? $_POST['bulk_status'] : 'incomplete';
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt_bulk = db()->prepare("UPDATE wmata_stations SET status=? WHERE id IN ({$placeholders})");
            $stmt_bulk->execute(array_merge([$status], array_values($ids)));
            flash('success', count($ids) . ' station(s) updated to ' . str_replace('_',' ',$status) . '.');
        }
    }

    header('Location: ' . APP_URL . '/wmata/stations?' . http_build_query([
        'q'      => $_POST['q']      ?? '',
        'line'   => $_POST['line']   ?? '',
        'status' => $_POST['filter_status'] ?? '',
    ]));
    exit;
}

/* ── Filters ────────────────────────────────────── */
$q      = trim($_GET['q']      ?? '');
$f_line = (int)($_GET['line']  ?? 0);
$f_stat = $_GET['status'] ?? '';
if (!in_array($f_stat, ['incomplete','in_progress','complete'])) $f_stat = '';

/* ── Lines for filter dropdown ──────────────────── */
$lines_all = db()->query('SELECT * FROM wmata_lines ORDER BY sort_order')->fetchAll();

/* ── Build query ────────────────────────────────── */
$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = 's.name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($f_stat !== '') {
    $where[] = 's.status = ?';
    $params[] = $f_stat;
}

$join_line = '';
if ($f_line > 0) {
    $join_line = 'JOIN wmata_station_lines fsl ON fsl.station_id = s.id AND fsl.line_id = ?';
    array_unshift($params, $f_line);
}

$sql = "SELECT s.id, s.name, s.abbreviation, s.status, s.platform_blocks
        FROM wmata_stations s
        {$join_line}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.name";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$stations = $stmt->fetchAll();

/* ── Line badges per station ────────────────────── */
$station_lines = [];
if ($stations) {
    $sids = array_column($stations, 'id');
    $placeholders = implode(',', array_fill(0, count($sids), '?'));
    $sl_stmt = db()->prepare(
        "SELECT sl.station_id, l.abbreviation, l.color
         FROM wmata_station_lines sl JOIN wmata_lines l ON l.id = sl.line_id
         WHERE sl.station_id IN ({$placeholders}) ORDER BY l.sort_order"
    );
    $sl_stmt->execute($sids);
    $sl_rows = $sl_stmt->fetchAll();
    foreach ($sl_rows as $r) {
        $station_lines[$r['station_id']][] = $r;
    }
}

/* ── Status counts ──────────────────────────────── */
$counts_raw = db()->query(
    'SELECT status, COUNT(*) as cnt FROM wmata_stations GROUP BY status'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$cnt_complete    = (int)($counts_raw['complete']    ?? 0);
$cnt_in_progress = (int)($counts_raw['in_progress'] ?? 0);
$cnt_incomplete  = (int)($counts_raw['incomplete']  ?? 0);

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations', 'active' => true],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files'],
    ['icon' => 'extension',         'label' => 'Mods',          'href' => APP_URL . '/wmata/mods'],
];

ui_head('Stations – WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Stations', 'WMATA Tracker › Stations');
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Status stat chips ── -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
  <a href="?<?= http_build_query(['q'=>$q,'line'=>$f_line]) ?>" class="badge <?= $f_stat===''?'badge-info':'badge-neutral' ?>" style="text-decoration:none;cursor:pointer">All (<?= $cnt_complete+$cnt_in_progress+$cnt_incomplete ?>)</a>
  <a href="?<?= http_build_query(['q'=>$q,'line'=>$f_line,'status'=>'complete']) ?>" class="badge <?= $f_stat==='complete'?'badge-success':'badge-neutral' ?>" style="text-decoration:none;cursor:pointer">Complete (<?= $cnt_complete ?>)</a>
  <a href="?<?= http_build_query(['q'=>$q,'line'=>$f_line,'status'=>'in_progress']) ?>" class="badge <?= $f_stat==='in_progress'?'badge-warning':'badge-neutral' ?>" style="text-decoration:none;cursor:pointer">In Progress (<?= $cnt_in_progress ?>)</a>
  <a href="?<?= http_build_query(['q'=>$q,'line'=>$f_line,'status'=>'incomplete']) ?>" class="badge <?= $f_stat==='incomplete'?'badge-danger':'badge-neutral' ?>" style="text-decoration:none;cursor:pointer">Incomplete (<?= $cnt_incomplete ?>)</a>
</div>

<?php ui_card_open('train', 'All Stations (' . count($stations) . ')', '', '#003DA5'); ?>

<!-- ── Filters & search ── -->
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
  <input type="text" name="q" class="form-control" placeholder="Search station name…"
         value="<?= htmlspecialchars($q) ?>" style="max-width:280px">
  <select name="line" class="form-control" style="max-width:180px">
    <option value="">All Lines</option>
    <?php foreach ($lines_all as $l): ?>
    <option value="<?= (int)$l['id'] ?>" <?= $f_line===(int)$l['id']?'selected':'' ?>>
      <?= htmlspecialchars($l['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="form-control" style="max-width:160px">
    <option value="">All Statuses</option>
    <option value="incomplete"  <?= $f_stat==='incomplete' ?'selected':''?>>Incomplete</option>
    <option value="in_progress" <?= $f_stat==='in_progress'?'selected':''?>>In Progress</option>
    <option value="complete"    <?= $f_stat==='complete'   ?'selected':''?>>Complete</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="<?= APP_URL ?>/wmata/stations" class="btn btn-sm">Clear</a>
</form>

<?php if ($stations): ?>

<!-- ── Bulk update bar ── -->
<form method="POST" id="bulk-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="bulk_status">
  <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
  <input type="hidden" name="line" value="<?= $f_line ?>">
  <input type="hidden" name="filter_status" value="<?= htmlspecialchars($f_stat) ?>">
  <div id="bulk-bar" style="display:none;align-items:center;gap:.5rem;margin-bottom:.75rem;
       padding:.5rem .75rem;background:var(--surface-raised);border-radius:var(--radius);border:1px solid var(--border)">
    <span id="bulk-count" style="font-size:.875rem;color:var(--text-muted)">0 selected</span>
    <select name="bulk_status" class="form-control" style="max-width:160px">
      <option value="incomplete">Incomplete</option>
      <option value="in_progress">In Progress</option>
      <option value="complete">Complete</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Apply to Selected</button>
    <button type="button" class="btn btn-sm" onclick="clearBulk()">Clear</button>
  </div>

<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th><input type="checkbox" id="check-all" title="Select all"></th>
      <th>Station</th>
      <th>Lines</th>
      <th>Status</th>
      <th>Platform Blocks</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($stations as $s):
    $sl = $station_lines[$s['id']] ?? [];
    $status_badge = match($s['status']) {
        'complete'    => ['success', 'Complete'],
        'in_progress' => ['warning', 'In Progress'],
        default       => ['danger',  'Incomplete'],
    };
  ?>
  <tr>
    <td><input type="checkbox" name="station_ids[]" value="<?= (int)$s['id'] ?>" class="row-check"></td>
    <td>
      <a href="<?= APP_URL ?>/wmata/station?id=<?= (int)$s['id'] ?>" style="font-weight:600;text-decoration:none;color:var(--primary)">
        <?= htmlspecialchars($s['name']) ?>
      </a>
      <div class="text-xs text-muted"><?= htmlspecialchars($s['abbreviation']) ?></div>
    </td>
    <td>
      <div style="display:flex;gap:.25rem;flex-wrap:wrap;align-items:center">
        <?php foreach ($sl as $line): ?>
        <?= wmata_line_badge($line['abbreviation'], $line['color'], 20) ?>
        <?php endforeach; ?>
        <?php if (!$sl): ?><span class="text-muted text-xs">—</span><?php endif; ?>
      </div>
    </td>
    <td><?= ui_badge($status_badge[1], $status_badge[0]) ?></td>
    <td style="text-align:center"><?= $s['platform_blocks'] !== null ? (int)$s['platform_blocks'] : '—' ?></td>
    <td>
      <div style="display:flex;gap:.25rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/wmata/station?id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" title="View">
          <span class="material-symbols-outlined">open_in_new</span>
        </a>
        <!-- Quick status cycle -->
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="station_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
          <input type="hidden" name="line" value="<?= $f_line ?>">
          <input type="hidden" name="filter_status" value="<?= htmlspecialchars($f_stat) ?>">
          <?php
            $next = match($s['status']) { 'incomplete' => 'in_progress', 'in_progress' => 'complete', default => 'incomplete' };
            $next_label = match($next) { 'in_progress' => 'Mark In Progress', 'complete' => 'Mark Complete', default => 'Mark Incomplete' };
          ?>
          <input type="hidden" name="status" value="<?= $next ?>">
          <button type="submit" class="btn btn-ghost btn-sm" title="<?= htmlspecialchars($next_label) ?>">
            <span class="material-symbols-outlined">
              <?= $next === 'complete' ? 'check_circle' : ($next === 'in_progress' ? 'pending' : 'undo') ?>
            </span>
          </button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</form><!-- bulk-form -->

<?php else: ?>
<div class="empty-state">
  <span class="material-symbols-outlined">train</span>
  <h3>No stations found</h3>
  <p>Try adjusting your search or filters.</p>
</div>
<?php endif; ?>

<?php ui_card_close(); ?>
</div>

<script>
const allCheck = document.getElementById('check-all');
const bulkBar  = document.getElementById('bulk-bar');
const bulkCnt  = document.getElementById('bulk-count');

function updateBulk() {
    const checked = document.querySelectorAll('.row-check:checked');
    bulkBar.style.display = checked.length ? 'flex' : 'none';
    bulkCnt.textContent = checked.length + ' selected';
}
function clearBulk() {
    document.querySelectorAll('.row-check, #check-all').forEach(c => c.checked = false);
    updateBulk();
}
allCheck?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
    updateBulk();
});
document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', updateBulk));
</script>
<?php ui_end(); ?>
