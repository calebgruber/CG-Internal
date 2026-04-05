<?php
/**
 * wmata/index.php – WMATA Minecraft Tracker Dashboard
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');

/* ── Stats ─────────────────────────────────────── */
try {
    $station_stats = db()->query(
        "SELECT status, COUNT(*) as cnt FROM wmata_stations GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $total_stations   = array_sum($station_stats);
    $complete         = (int)($station_stats['complete']    ?? 0);
    $in_progress      = (int)($station_stats['in_progress'] ?? 0);
    $incomplete       = (int)($station_stats['incomplete']  ?? 0);

    /* Per-line progress */
    $lines = db()->query(
        "SELECT l.id, l.name, l.abbreviation, l.color,
                COUNT(sl.station_id) AS total,
                SUM(CASE WHEN s.status='complete' THEN 1 ELSE 0 END) AS done
         FROM wmata_lines l
         LEFT JOIN wmata_station_lines sl ON sl.line_id = l.id
         LEFT JOIN wmata_stations s ON s.id = sl.station_id
         GROUP BY l.id ORDER BY l.sort_order"
    )->fetchAll();

    /* Rolling stock summary */
    $rs_stats = db()->query(
        "SELECT series, status, COUNT(*) AS cnt FROM wmata_rolling_stock GROUP BY series, status"
    )->fetchAll();
    $rs = ['6000' => ['total' => 0, 'complete' => 0], '7000' => ['total' => 0, 'complete' => 0]];
    foreach ($rs_stats as $r) {
        $rs[$r['series']]['total'] += (int)$r['cnt'];
        if ($r['status'] === 'complete') $rs[$r['series']]['complete'] += (int)$r['cnt'];
    }
} catch (PDOException $e) {
    $total_stations = $complete = $in_progress = $incomplete = 0;
    $lines = []; $rs = ['6000' => ['total' => 0, 'complete' => 0], '7000' => ['total' => 0, 'complete' => 0]];
}

$overall_pct = $total_stations > 0 ? round($complete / $total_stations * 100) : 0;

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/',                     'active' => true],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations'],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files'],
];

ui_head('WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Dashboard', 'WMATA Minecraft Recreation Tracker');
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Stat cards ── -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card" style="border-left:3px solid #003DA5">
    <div class="stat-icon" style="background:rgba(0,61,165,.12);color:#003DA5">
      <?php if (wmata_icon_exists('metro')): ?>
      <?= wmata_metro_logo(28) ?>
      <?php else: ?>
      <span class="material-symbols-outlined">train</span>
      <?php endif; ?>
    </div>
    <div class="stat-label">Total Stations</div>
    <div class="stat-value"><?= $total_stations ?></div>
  </div>
  <div class="stat-card" style="border-left:3px solid var(--success)">
    <div class="stat-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
      <span class="material-symbols-outlined">check_circle</span>
    </div>
    <div class="stat-label">Complete</div>
    <div class="stat-value"><?= $complete ?></div>
    <div class="progress-bar" style="margin-top:.5rem">
      <div class="progress-fill" style="width:<?= $total_stations > 0 ? round($complete/$total_stations*100) : 0 ?>%;background:var(--success)"></div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid var(--warning)">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
      <span class="material-symbols-outlined">pending</span>
    </div>
    <div class="stat-label">In Progress</div>
    <div class="stat-value"><?= $in_progress ?></div>
    <div class="progress-bar" style="margin-top:.5rem">
      <div class="progress-fill" style="width:<?= $total_stations > 0 ? round($in_progress/$total_stations*100) : 0 ?>%;background:var(--warning)"></div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid var(--danger)">
    <div class="stat-icon" style="background:rgba(239,68,68,.12);color:var(--danger)">
      <span class="material-symbols-outlined">radio_button_unchecked</span>
    </div>
    <div class="stat-label">Incomplete</div>
    <div class="stat-value"><?= $incomplete ?></div>
    <div class="progress-bar" style="margin-top:.5rem">
      <div class="progress-fill" style="width:<?= $total_stations > 0 ? round($incomplete/$total_stations*100) : 0 ?>%;background:var(--danger)"></div>
    </div>
  </div>
</div>

<div class="card-grid card-grid-2">

  <!-- ── Overall progress ── -->
  <?php ui_card_open('bar_chart', 'Overall Station Progress', '', '#003DA5'); ?>
  <div style="margin-bottom:1rem">
    <div style="display:flex;justify-content:space-between;margin-bottom:.4rem">
      <span style="font-weight:600"><?= $complete ?> / <?= $total_stations ?> stations complete</span>
      <span style="font-weight:700;color:#003DA5"><?= $overall_pct ?>%</span>
    </div>
    <div class="progress-bar" style="height:14px;border-radius:8px">
      <div class="progress-fill" style="width:<?= $overall_pct ?>%;background:#003DA5;border-radius:8px"></div>
    </div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="<?= APP_URL ?>/wmata/stations" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined">train</span> View Stations
    </a>
    <a href="<?= APP_URL ?>/wmata/stations?status=incomplete" class="btn btn-sm">Incomplete</a>
    <a href="<?= APP_URL ?>/wmata/stations?status=in_progress" class="btn btn-sm">In Progress</a>
  </div>
  <?php ui_card_close(); ?>

  <!-- ── Per-line progress ── -->
  <?php ui_card_open('directions_transit', 'Progress by Line', '', '#BF0D3E'); ?>
  <?php if ($lines): ?>
  <div style="display:flex;flex-direction:column;gap:.75rem">
    <?php foreach ($lines as $line):
      $pct = $line['total'] > 0 ? round($line['done'] / $line['total'] * 100) : 0;
    ?>
    <div>
      <div style="display:flex;justify-content:space-between;margin-bottom:.25rem">
        <span style="display:flex;align-items:center;gap:.5rem">
          <?= wmata_line_badge($line['abbreviation'], $line['color'], 20) ?>
          <span style="font-weight:500;font-size:.875rem"><?= htmlspecialchars($line['name']) ?></span>
        </span>
        <span style="font-size:.8125rem;color:var(--text-muted)"><?= (int)$line['done'] ?>/<?= (int)$line['total'] ?> (<?= $pct ?>%)</span>
      </div>
      <div class="progress-bar" style="height:8px;border-radius:4px">
        <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($line['color']) ?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><span class="material-symbols-outlined">train</span><p>No line data.</p></div>
  <?php endif; ?>
  <?php ui_card_close(); ?>

  <!-- ── Rolling stock ── -->
  <?php ui_card_open('directions_railway', 'Rolling Stock Progress', '<a href="' . APP_URL . '/wmata/rolling-stock" class="btn btn-sm" style="margin-left:auto">View All</a>', '#919D9D'); ?>
  <div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach (['7000' => '7000 Series', '6000' => '6000 Series'] as $ser => $label):
      $serTotal = (int)$rs[$ser]['total'];
      $serDone  = (int)$rs[$ser]['complete'];
      $serPct   = $serTotal > 0 ? round($serDone / $serTotal * 100) : 0;
    ?>
    <div>
      <div style="display:flex;justify-content:space-between;margin-bottom:.25rem">
        <span style="font-weight:600"><?= htmlspecialchars($label) ?></span>
        <span style="font-size:.8125rem;color:var(--text-muted)"><?= $serDone ?>/<?= $serTotal ?> complete (<?= $serPct ?>%)</span>
      </div>
      <div class="progress-bar" style="height:10px;border-radius:5px">
        <div class="progress-fill" style="width:<?= $serPct ?>%;background:#919D9D;border-radius:5px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php ui_card_close(); ?>

  <!-- ── Quick links ── -->
  <?php ui_card_open('apps', 'Quick Links'); ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
    <?php
    $links = [
      ['train','Stations','wmata/stations.php','#003DA5'],
      ['directions_transit','Rolling Stock','wmata/rolling-stock.php','#BF0D3E'],
      ['calculate','Calculator','wmata/calculator.php','#10b981'],
      ['folder','Files','wmata/files.php','#f59e0b'],
    ];
    foreach ($links as [$icon,$label,$path,$color]):
    ?>
    <a href="<?= APP_URL ?>/<?= $path ?>"
       style="display:flex;align-items:center;gap:.6rem;padding:.75rem;
              background:var(--surface-raised);border-radius:var(--radius);
              text-decoration:none;color:var(--text);border:1px solid var(--border)">
      <span class="material-symbols-outlined" style="color:<?= $color ?>"><?= $icon ?></span>
      <span style="font-weight:500"><?= htmlspecialchars($label) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php ui_card_close(); ?>

</div>
</div>
<?php ui_end(); ?>
