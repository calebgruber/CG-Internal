<?php
/**
 * wmata/rolling-stock.php – Rolling stock tracker (6000 & 7000 series)
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('wmata');

$upload_dir  = __DIR__ . '/uploads/rolling-stock/';
$allowed_ext = ['jpg','jpeg','png','gif','webp'];
$max_size    = 50 * 1024 * 1024;

/* ── POST handlers ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'update_car') {
        $cid    = (int)($_POST['car_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['incomplete','in_progress','complete'])
                  ? $_POST['status'] : 'incomplete';
        $cb     = $_POST['car_blocks'] !== '' ? (int)$_POST['car_blocks'] : null;
        $gb     = $_POST['gap_blocks']  !== '' ? (int)$_POST['gap_blocks']  : null;
        $notes  = trim($_POST['notes'] ?? '');

        /* Handle diagram upload */
        $diag_fname = null;
        if (isset($_FILES['diagram']) && $_FILES['diagram']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['diagram'];
            $orig = basename($file['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                flash('danger', 'Diagram must be an image (jpg, png, gif, webp).');
                header('Location: ' . APP_URL . '/wmata/rolling-stock.php'); exit;
            }
            if ($file['size'] > $max_size) {
                flash('danger', 'Diagram file too large (max 50 MB).');
                header('Location: ' . APP_URL . '/wmata/rolling-stock.php'); exit;
            }
            $diag_fname = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $diag_fname)) {
                flash('danger', 'Failed to upload diagram.');
                $diag_fname = null;
            }
        }

        if ($diag_fname !== null) {
            // Delete old diagram
            $old = db()->prepare('SELECT diagram_filename FROM wmata_rolling_stock WHERE id=?');
            $old->execute([$cid]); $old = $old->fetchColumn();
            if ($old) @unlink($upload_dir . $old);
            db()->prepare('UPDATE wmata_rolling_stock SET status=?, car_blocks=?, gap_blocks=?, notes=?, diagram_filename=? WHERE id=?')
                ->execute([$status, $cb, $gb, $notes ?: null, $diag_fname, $cid]);
        } else {
            db()->prepare('UPDATE wmata_rolling_stock SET status=?, car_blocks=?, gap_blocks=?, notes=? WHERE id=?')
                ->execute([$status, $cb, $gb, $notes ?: null, $cid]);
        }
        flash('success', 'Car updated.');
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php#car-' . $cid); exit;
    }

    if ($pa === 'toggle_progress') {
        $pid    = (int)($_POST['progress_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['incomplete','in_progress','complete'])
                  ? $_POST['status'] : 'incomplete';
        db()->prepare('UPDATE wmata_rolling_stock_progress SET status=? WHERE id=?')->execute([$status, $pid]);
        $cid = (int)($_POST['car_id'] ?? 0);
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php#car-' . $cid); exit;
    }

    if ($pa === 'add_progress') {
        $cid  = (int)($_POST['car_id'] ?? 0);
        $name = trim($_POST['point_name'] ?? '');
        if ($name !== '' && $cid > 0) {
            $max_order = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM wmata_rolling_stock_progress WHERE rolling_stock_id=?');
            $max_order->execute([$cid]); $next = (int)$max_order->fetchColumn();
            db()->prepare('INSERT INTO wmata_rolling_stock_progress (rolling_stock_id, point_name, sort_order) VALUES (?,?,?)')
                ->execute([$cid, $name, $next]);
        }
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php#car-' . $cid); exit;
    }

    if ($pa === 'delete_progress') {
        $pid = (int)($_POST['progress_id'] ?? 0);
        $cid = (int)($_POST['car_id'] ?? 0);
        db()->prepare('DELETE FROM wmata_rolling_stock_progress WHERE id=?')->execute([$pid]);
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php#car-' . $cid); exit;
    }

    if ($pa === 'add_car') {
        $series = in_array($_POST['series'] ?? '', ['6000','7000']) ? $_POST['series'] : '7000';
        $cn     = trim($_POST['car_number'] ?? '');
        if ($cn !== '') {
            $next_order = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM wmata_rolling_stock WHERE series=?');
            $next_order->execute([$series]); $next = (int)$next_order->fetchColumn();
            db()->prepare('INSERT INTO wmata_rolling_stock (series, car_number, sort_order) VALUES (?,?,?)')
                ->execute([$series, $cn, $next]);
            flash('success', "Car #{$cn} added.");
        }
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php'); exit;
    }

    if ($pa === 'delete_car') {
        $cid = (int)($_POST['car_id'] ?? 0);
        $old = db()->prepare('SELECT diagram_filename FROM wmata_rolling_stock WHERE id=?');
        $old->execute([$cid]); $old = $old->fetchColumn();
        if ($old) @unlink($upload_dir . $old);
        db()->prepare('DELETE FROM wmata_rolling_stock WHERE id=?')->execute([$cid]);
        flash('success', 'Car deleted.');
        header('Location: ' . APP_URL . '/wmata/rolling-stock.php'); exit;
    }
}

/* ── Fetch data ─────────────────────────────────── */
$cars = db()->query('SELECT * FROM wmata_rolling_stock ORDER BY series DESC, sort_order, car_number')->fetchAll();
$progress_rows = db()->query('SELECT * FROM wmata_rolling_stock_progress ORDER BY rolling_stock_id, sort_order, id')->fetchAll();
$progress_by_car = [];
foreach ($progress_rows as $p) {
    $progress_by_car[$p['rolling_stock_id']][] = $p;
}

/* Group by series */
$series_groups = ['7000' => [], '6000' => []];
foreach ($cars as $c) {
    $series_groups[$c['series']][] = $c;
}

/* Series totals (block count) */
$series_totals = [];
foreach ($series_groups as $ser => $cars_in) {
    $total_blocks = 0;
    foreach ($cars_in as $c) {
        $total_blocks += (int)($c['car_blocks'] ?? 0) + (int)($c['gap_blocks'] ?? 0);
    }
    $cnt_done  = count(array_filter($cars_in, fn($c) => $c['status'] === 'complete'));
    $series_totals[$ser] = ['blocks' => $total_blocks, 'done' => $cnt_done, 'total' => count($cars_in)];
}

/* Active tab */
$active_tab = in_array($_GET['tab'] ?? '', ['6000','7000']) ? $_GET['tab'] : '7000';

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations.php'],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock.php', 'active' => true],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator.php'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files.php'],
];

ui_head('Rolling Stock – WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Rolling Stock', 'WMATA Tracker › Rolling Stock');
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Tab navigation ── -->
<div class="tabs" style="margin-bottom:1.5rem">
  <?php foreach (['7000' => '7000 Series', '6000' => '6000 Series'] as $ser => $label):
    $t = $series_totals[$ser];
    $pct = $t['total'] > 0 ? round($t['done']/$t['total']*100) : 0;
  ?>
  <a href="?tab=<?= $ser ?>" class="tab <?= $active_tab === $ser ? 'active' : '' ?>">
    <?= htmlspecialchars($label) ?>
    <?= ui_badge($t['done'].'/'.$t['total'], $pct===100?'success':($pct>0?'warning':'neutral')) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php foreach (['7000','6000'] as $ser):
  if ($ser !== $active_tab) continue;
  $cars_in = $series_groups[$ser];
  $t       = $series_totals[$ser];
  $pct     = $t['total'] > 0 ? round($t['done']/$t['total']*100) : 0;
?>

<!-- ── Series summary ── -->
<?php ui_card_open('bar_chart', $ser . ' Series Progress', '', '#919D9D'); ?>
<div style="display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:.75rem">
  <div><span class="text-muted text-sm">Cars Complete</span><br><strong><?= $t['done'] ?>/<?= $t['total'] ?></strong></div>
  <div><span class="text-muted text-sm">Progress</span><br><strong><?= $pct ?>%</strong></div>
  <div><span class="text-muted text-sm">Total Train Blocks</span><br><strong><?= number_format($t['blocks']) ?> blocks</strong></div>
</div>
<div class="progress-bar" style="height:12px;border-radius:6px">
  <div class="progress-fill" style="width:<?= $pct ?>%;background:#919D9D;border-radius:6px"></div>
</div>
<?php ui_card_close(); ?>

<!-- ── Add car form ── -->
<?php ui_card_open('add_circle', 'Add Car', '', '#10b981'); ?>
<form method="POST" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add_car">
  <input type="hidden" name="series" value="<?= htmlspecialchars($ser) ?>">
  <div class="form-group" style="margin-bottom:0">
    <label>Car Number</label>
    <input type="text" name="car_number" class="form-control" placeholder="e.g. <?= $ser ?>01" required>
  </div>
  <button type="submit" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> Add Car
  </button>
</form>
<?php ui_card_close(); ?>

<!-- ── Car grid ── -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr));margin-top:1.25rem">
<?php foreach ($cars_in as $car):
  $progs = $progress_by_car[$car['id']] ?? [];
  $progs_done  = count(array_filter($progs, fn($p) => $p['status'] === 'complete'));
  $progs_total = count($progs);
  $progs_pct   = $progs_total > 0 ? round($progs_done/$progs_total*100) : 0;
  $status_badge = match($car['status']) {
      'complete'    => ['success', 'Complete'],
      'in_progress' => ['warning', 'In Progress'],
      default       => ['danger',  'Incomplete'],
  };
  $diag_url = $car['diagram_filename']
              ? APP_URL . '/wmata/uploads/rolling-stock/' . rawurlencode($car['diagram_filename'])
              : null;
?>
<div class="card" id="car-<?= (int)$car['id'] ?>" style="border-left:3px solid #919D9D">
  <div class="card-top">
    <div class="card-tab">
      <span class="material-symbols-outlined" style="color:#919D9D">directions_railway</span>
      <h3>Car #<?= htmlspecialchars($car['car_number']) ?></h3>
    </div>
    <?= ui_badge($status_badge[1], $status_badge[0]) ?>
  </div>
  <div class="card-body">

    <?php if ($diag_url): ?>
    <img src="<?= htmlspecialchars($diag_url) ?>" alt="Diagram"
         style="width:100%;border-radius:var(--radius);margin-bottom:.75rem;border:1px solid var(--border)">
    <?php endif; ?>

    <!-- Stats row -->
    <div style="display:flex;gap:1rem;font-size:.8125rem;margin-bottom:.75rem;flex-wrap:wrap">
      <div><span class="text-muted">Car blocks:</span> <strong><?= $car['car_blocks'] !== null ? (int)$car['car_blocks'] : '—' ?></strong></div>
      <div><span class="text-muted">Gap:</span> <strong><?= $car['gap_blocks']  !== null ? (int)$car['gap_blocks']  : '—' ?></strong></div>
      <?php if ($progs_total > 0): ?>
      <div><span class="text-muted">Progress:</span> <strong><?= $progs_done ?>/<?= $progs_total ?></strong></div>
      <?php endif; ?>
    </div>

    <?php if ($progs_total > 0): ?>
    <div class="progress-bar" style="height:6px;border-radius:3px;margin-bottom:.75rem">
      <div class="progress-fill" style="width:<?= $progs_pct ?>%;background:#919D9D;border-radius:3px"></div>
    </div>
    <?php endif; ?>

    <!-- Edit form (collapsible) -->
    <details style="margin-bottom:.75rem">
      <summary style="cursor:pointer;font-size:.875rem;font-weight:600;color:var(--primary);user-select:none">
        Edit Car Details
      </summary>
      <form method="POST" enctype="multipart/form-data" style="margin-top:.75rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_car">
        <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <div class="form-group" style="flex:1;min-width:100px">
            <label>Status</label>
            <select name="status" class="form-control">
              <?php foreach (['incomplete'=>'Incomplete','in_progress'=>'In Progress','complete'=>'Complete'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $car['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:80px">
            <label>Car Blocks</label>
            <input type="number" name="car_blocks" class="form-control" min="0"
                   value="<?= $car['car_blocks'] !== null ? (int)$car['car_blocks'] : '' ?>">
          </div>
          <div class="form-group" style="flex:1;min-width:80px">
            <label>Gap Blocks</label>
            <input type="number" name="gap_blocks" class="form-control" min="0"
                   value="<?= $car['gap_blocks'] !== null ? (int)$car['gap_blocks'] : '' ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($car['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Diagram Image</label>
          <input type="file" name="diagram" class="form-control" accept="image/*">
        </div>
        <div style="display:flex;gap:.4rem">
          <button type="submit" class="btn btn-primary btn-sm">
            <span class="material-symbols-outlined">save</span> Save
          </button>
        </div>
      </form>
    </details>

    <!-- Progress points -->
    <details <?= $progs_total > 0 ? 'open' : '' ?>>
      <summary style="cursor:pointer;font-size:.875rem;font-weight:600;color:var(--text-muted);user-select:none;margin-bottom:.5rem">
        Progress Points (<?= $progs_done ?>/<?= $progs_total ?>)
      </summary>
      <?php if ($progs): ?>
      <div style="display:flex;flex-direction:column;gap:.3rem;margin-bottom:.5rem">
        <?php foreach ($progs as $p):
          $p_badge = match($p['status']) {
              'complete'    => 'success',
              'in_progress' => 'warning',
              default       => 'neutral',
          };
        ?>
        <div style="display:flex;align-items:center;gap:.4rem;padding:.35rem .5rem;
             background:var(--surface-raised);border-radius:calc(var(--radius)*0.75)">
          <form method="POST" style="display:flex;align-items:center;gap:.4rem;flex:1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_progress">
            <input type="hidden" name="progress_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
            <input type="checkbox" <?= $p['status']==='complete'?'checked':'' ?>
                   onchange="this.form.querySelector('[name=status]').value=this.checked?'complete':'incomplete';this.form.submit()"
                   style="cursor:pointer">
            <input type="hidden" name="status" value="<?= htmlspecialchars($p['status']) ?>">
            <span style="flex:1;font-size:.8125rem;<?= $p['status']==='complete'?'text-decoration:line-through;color:var(--text-muted)':'' ?>">
              <?= htmlspecialchars($p['point_name']) ?>
            </span>
          </form>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_progress">
            <input type="hidden" name="progress_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);padding:.1rem .25rem"
                    data-confirm="Remove this point?">
              <span class="material-symbols-outlined" style="font-size:.9rem">close</span>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="POST" style="display:flex;gap:.35rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_progress">
        <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
        <input type="text" name="point_name" class="form-control" placeholder="Add point…" required style="font-size:.8125rem">
        <button type="submit" class="btn btn-sm btn-primary">+</button>
      </form>
    </details>

    <!-- Delete car -->
    <form method="POST" style="margin-top:.75rem;text-align:right">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_car">
      <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
      <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
              data-confirm="Delete car #<?= htmlspecialchars($car['car_number']) ?>?">
        <span class="material-symbols-outlined">delete</span> Delete
      </button>
    </form>

  </div>
</div>
<?php endforeach; ?>
</div><!-- card-grid -->

<?php if (!$cars_in): ?>
<div class="empty-state">
  <span class="material-symbols-outlined">directions_railway</span>
  <h3>No <?= $ser ?> series cars yet</h3>
  <p>Add a car using the form above.</p>
</div>
<?php endif; ?>

<?php endforeach; // series loop ?>

</div>
<?php ui_end(); ?>
