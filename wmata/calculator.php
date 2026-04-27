<?php
/**
 * wmata/calculator.php – Feet-to-Minecraft-blocks calculator
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');
$uid  = (int)$user['id'];

/* ── POST: save calculation ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'save') {
        $label      = trim($_POST['label']      ?? '');
        $feet       = (float)($_POST['feet']       ?? 0);
        $multiplier = (float)($_POST['multiplier'] ?? 0.0625);
        $blocks     = round($feet * $multiplier, 4);
        $notes      = trim($_POST['notes'] ?? '');

        if ($label === '') {
            flash('danger', 'Label is required.');
        } elseif ($feet <= 0) {
            flash('danger', 'Feet value must be positive.');
        } elseif ($multiplier <= 0) {
            flash('danger', 'Multiplier must be positive.');
        } else {
            db()->prepare(
                'INSERT INTO wmata_block_calculations (user_id, label, feet_value, multiplier, block_count, notes)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$uid, $label, $feet, $multiplier, $blocks, $notes ?: null]);
            flash('success', "Saved: {$label} = {$blocks} blocks.");
        }
        header('Location: ' . APP_URL . '/wmata/calculator');
        exit;
    }

    if ($pa === 'delete') {
        $cid = (int)($_POST['calc_id'] ?? 0);
        db()->prepare('DELETE FROM wmata_block_calculations WHERE id=? AND user_id=?')->execute([$cid, $uid]);
        flash('success', 'Calculation deleted.');
        header('Location: ' . APP_URL . '/wmata/calculator');
        exit;
    }
}

/* ── Fetch saved calculations ───────────────────── */
$search = trim($_GET['q'] ?? '');
$params = [$uid];
$where  = 'user_id=?';
if ($search !== '') {
    $where    .= ' AND (label LIKE ? OR notes LIKE ?)';
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}
$calcs_stmt = db()->prepare(
    "SELECT * FROM wmata_block_calculations WHERE {$where} ORDER BY created_at DESC LIMIT 50"
);
$calcs_stmt->execute($params);
$calcs = $calcs_stmt->fetchAll();

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations'],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator', 'active' => true],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files'],
    ['icon' => 'extension',         'label' => 'Mods',          'href' => APP_URL . '/wmata/mods'],
];

ui_head('Calculator – WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Feet → Blocks Calculator', 'WMATA Tracker › Calculator');
?>
<div class="page-body">
<?php ui_flash(); ?>

<div class="card-grid card-grid-2">

<!-- ── Calculator form ── -->
<?php ui_card_open('calculate', 'Calculate', '', '#10b981'); ?>
<p class="text-sm text-muted" style="margin-bottom:1rem">
  Convert real-world measurements to Minecraft block counts using a scale multiplier.
</p>

<!-- Preset buttons -->
<div style="margin-bottom:1rem">
  <label style="font-size:.8125rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.4rem">Quick Presets</label>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <button type="button" class="btn btn-sm" onclick="setPreset(0.0625,'1:16 scale (1 ft = 1/16 block)')">1:16 Scale</button>
    <button type="button" class="btn btn-sm" onclick="setPreset(0.3048,'1:1 metric (1 ft ≈ 0.3048 blocks)')">1:1 Metric</button>
    <button type="button" class="btn btn-sm" onclick="setPreset(1,'1:1 feet (1 ft = 1 block)')">1:1 Feet</button>
  </div>
</div>

<form method="POST" id="calc-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <div class="form-group">
    <label for="label">Label *</label>
    <input type="text" id="label" name="label" class="form-control" required
           placeholder="e.g. Metro Center platform length">
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:120px">
      <label for="feet">Feet *</label>
      <input type="number" id="feet" name="feet" class="form-control" step="any" min="0"
             placeholder="e.g. 600" oninput="recalc()">
    </div>
    <div class="form-group" style="flex:1;min-width:120px">
      <label for="multiplier">Multiplier</label>
      <input type="number" id="multiplier" name="multiplier" class="form-control"
             step="any" min="0.000001" value="0.0625" oninput="recalc()">
    </div>
    <div class="form-group" style="flex:1;min-width:120px">
      <label>Result (blocks)</label>
      <input type="number" id="block_display" class="form-control" readonly
             style="background:var(--surface-raised);font-weight:700;color:var(--primary)">
    </div>
  </div>
  <div class="form-group">
    <label for="notes">Notes</label>
    <input type="text" id="notes" name="notes" class="form-control" placeholder="Optional notes…">
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <span class="material-symbols-outlined">save</span> Save Calculation
    </button>
  </div>
</form>

<!-- Current preset label -->
<p id="preset-label" style="font-size:.8125rem;color:var(--text-muted);margin-top:.5rem"></p>

<?php ui_card_close(); ?>

<!-- ── Info card ── -->
<?php ui_card_open('info', 'Scale Reference', '', '#6366f1'); ?>
<div style="font-size:.875rem;display:flex;flex-direction:column;gap:.6rem">
  <div style="padding:.6rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>1:16 scale</strong> — 1 real foot = 1/16 of a block (multiplier: 0.0625)<br>
    <span class="text-muted">e.g. 600 ft platform → 37.5 blocks</span>
  </div>
  <div style="padding:.6rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>1:1 metric</strong> — 1 Minecraft block = 1 meter = 3.28084 ft (multiplier: ≈ 0.3048)<br>
    <span class="text-muted">e.g. 600 ft platform → 182.9 blocks</span>
  </div>
  <div style="padding:.6rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>1:1 feet</strong> — 1 real foot = 1 block (multiplier: 1.0)<br>
    <span class="text-muted">e.g. 600 ft platform → 600 blocks</span>
  </div>
  <div style="padding:.6rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>WMATA platform lengths</strong> — Most WMATA platforms are 600 ft (183 m) long.
  </div>
</div>
<?php ui_card_close(); ?>

</div>

<!-- ── Saved calculations ── -->
<?php ui_card_open('history', 'Saved Calculations', '', '#6366f1'); ?>

<form method="GET" style="display:flex;gap:.5rem;margin-bottom:1rem">
  <input type="text" name="q" class="form-control" placeholder="Search label or notes…"
         value="<?= htmlspecialchars($search) ?>" style="max-width:300px">
  <button type="submit" class="btn btn-sm btn-primary">Search</button>
  <?php if ($search): ?>
  <a href="<?= APP_URL ?>/wmata/calculator" class="btn btn-sm">Clear</a>
  <?php endif; ?>
</form>

<?php if ($calcs): ?>
<div class="table-wrap">
<table>
  <thead>
    <tr><th>Label</th><th>Feet</th><th>Multiplier</th><th>Blocks</th><th>Notes</th><th>Date</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($calcs as $c): ?>
  <tr>
    <td style="font-weight:500"><?= htmlspecialchars($c['label']) ?></td>
    <td class="text-sm"><?= number_format((float)$c['feet_value'], 2) ?></td>
    <td class="text-sm text-muted"><?= number_format((float)$c['multiplier'], 6) ?></td>
    <td style="font-weight:700;color:var(--primary)"><?= number_format((float)$c['block_count'], 4) ?></td>
    <td class="text-sm text-muted"><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
    <td class="text-sm text-muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
    <td>
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="calc_id" value="<?= (int)$c['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                data-confirm="Delete this calculation?">
          <span class="material-symbols-outlined">delete</span>
        </button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state" style="padding:1rem">
  <span class="material-symbols-outlined">calculate</span>
  <h3><?= $search ? 'No results found' : 'No saved calculations yet' ?></h3>
  <p>Use the form above to save a calculation.</p>
</div>
<?php endif; ?>
<?php ui_card_close(); ?>

</div>

<script>
function recalc() {
    const feet = parseFloat(document.getElementById('feet').value) || 0;
    const mult = parseFloat(document.getElementById('multiplier').value) || 0;
    const result = Math.round(feet * mult * 10000) / 10000;
    document.getElementById('block_display').value = result || '';
}
function setPreset(mult, label) {
    document.getElementById('multiplier').value = mult;
    document.getElementById('preset-label').textContent = label;
    recalc();
}
recalc();
</script>
<?php ui_end(); ?>
