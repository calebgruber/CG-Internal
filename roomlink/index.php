<?php
/**
 * roomlink/index.php – RoomLink Dashboard
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

/* ── Handle POST ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'set_tab') {
        $tab = $_POST['tab'] ?? 'transit';
        rl_set_eink_tab($tab);
        flash('success', 'E-Ink display tab updated.');
        header('Location: ' . APP_URL . '/roomlink/');
        exit;
    }
}

/* ── Data ──────────────────────────────────────── */
try {
    $wled_controllers = db()->query(
        'SELECT * FROM roomlink_wled_controllers WHERE is_active = 1 ORDER BY sort_order, name'
    )->fetchAll();
} catch (PDOException $e) {
    $wled_controllers = [];
}

try {
    $destinations = db()->query(
        'SELECT d.*, a.name AS agency_name, a.short_name, a.color AS agency_color, a.text_color
         FROM roomlink_transit_destinations d
         LEFT JOIN roomlink_transit_agencies a ON a.id = d.agency_id
         WHERE d.is_active = 1 ORDER BY d.sort_order, d.label LIMIT 3'
    )->fetchAll();
} catch (PDOException $e) {
    $destinations = [];
}

$eink = rl_eink_state();
$ctrl_count = count($wled_controllers);

try {
    $dest_count = (int) db()->query(
        'SELECT COUNT(*) FROM roomlink_transit_destinations WHERE is_active = 1'
    )->fetchColumn();
} catch (PDOException $e) {
    $dest_count = 0;
}

$nav_items = rl_nav_items('/roomlink/');

ui_head('Dashboard – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('Dashboard', 'RoomLink Smart Home Hub',
    '<a href="' . APP_URL . '/roomlink/controller" class="btn btn-sm" style="background:#1e293b;color:#f1f5f9;border:1px solid #334155">
       <span class="material-symbols-outlined">touch_app</span> Controller Mode
     </a>
     <a href="' . APP_URL . '/roomlink/einkview" class="btn btn-sm btn-primary">
       <span class="material-symbols-outlined">e_ink</span> E-Ink Preview
     </a>'
);
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Stat Cards ── -->
<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(99,102,241,.12);color:#6366f1">
      <span class="material-symbols-outlined">lightbulb</span>
    </div>
    <div class="stat-label">WLED Controllers</div>
    <div class="stat-value"><?= $ctrl_count ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,113,179,.12);color:#0E71B3">
      <span class="material-symbols-outlined">departure_board</span>
    </div>
    <div class="stat-label">Transit Routes</div>
    <div class="stat-value"><?= $dest_count ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
      <span class="material-symbols-outlined">e_ink</span>
    </div>
    <div class="stat-label">E-Ink Tab</div>
    <div class="stat-value" style="font-size:1.1rem;text-transform:capitalize"><?= htmlspecialchars($eink['current_tab']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
      <span class="material-symbols-outlined">schedule</span>
    </div>
    <div class="stat-label">Pi Last Seen</div>
    <div class="stat-value" style="font-size:.9rem;color:var(--text-muted)">
      <?= $eink['last_pi_poll'] ? htmlspecialchars(date('g:i a', strtotime($eink['last_pi_poll']))) : 'Never' ?>
    </div>
  </div>
</div>

<div class="card-grid card-grid-2">

  <!-- ── WLED Controllers ── -->
  <?php ui_card_open('lightbulb', 'WLED Controllers',
    '<a href="' . APP_URL . '/roomlink/lighting" class="btn btn-sm">Manage</a>', '#f59e0b'); ?>
  <?php if ($wled_controllers): ?>
  <div style="display:flex;flex-direction:column;gap:.75rem">
    <?php foreach ($wled_controllers as $ctrl): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:.75rem;background:var(--surface-raised);border-radius:var(--radius);
                border:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:.75rem">
        <span class="material-symbols-outlined" style="color:#f59e0b">lightbulb</span>
        <div>
          <div style="font-weight:600"><?= htmlspecialchars($ctrl['name']) ?></div>
          <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($ctrl['ip_address']) ?>:<?= (int)$ctrl['port'] ?></div>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="badge" id="ctrl-status-<?= (int)$ctrl['id'] ?>" style="background:#1e293b;color:#64748b">...</span>
        <button class="btn btn-sm" onclick="wledQuickToggle(<?= (int)$ctrl['id'] ?>)"
                id="ctrl-btn-<?= (int)$ctrl['id'] ?>" style="min-width:72px">
          Toggle
        </button>
      </div>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-sm" style="background:#450a0a;color:#fca5a5;border:1px solid #991b1b"
            onclick="wledGlobalOff()">
      <span class="material-symbols-outlined" style="font-size:1rem">power_settings_new</span>
      Global Off
    </button>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <span class="material-symbols-outlined">lightbulb</span>
    <p>No controllers configured.</p>
    <a href="<?= APP_URL ?>/roomlink/settings" class="btn btn-sm btn-primary">Add Controller</a>
  </div>
  <?php endif; ?>
  <?php ui_card_close(); ?>

  <!-- ── E-Ink Tab Control ── -->
  <?php ui_card_open('e_ink', 'E-Ink Display', '', '#10b981'); ?>
  <div style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
      <span style="font-size:.875rem;color:var(--text-muted)">Current tab:</span>
      <span style="font-weight:700;text-transform:capitalize;color:#10b981"><?= htmlspecialchars($eink['current_tab']) ?></span>
      <?php if ($eink['last_updated']): ?>
      <span style="font-size:.75rem;color:var(--text-muted);margin-left:auto">
        Updated <?= htmlspecialchars(date('g:i a', strtotime($eink['last_updated']))) ?>
      </span>
      <?php endif; ?>
    </div>
    <form method="POST" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_tab">
      <select name="tab" class="form-control" style="flex:1;min-width:120px">
        <?php foreach (['transit','clock','weather','custom'] as $t): ?>
        <option value="<?= $t ?>" <?= $eink['current_tab'] === $t ? 'selected' : '' ?>>
          <?= ucfirst($t) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined">send</span> Set Tab
      </button>
    </form>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="<?= APP_URL ?>/roomlink/einkview" class="btn btn-sm">
      <span class="material-symbols-outlined">visibility</span> Preview
    </a>
    <a href="<?= APP_URL ?>/roomlink/einkview?bare=1" class="btn btn-sm" target="_blank">
      <span class="material-symbols-outlined">open_in_new</span> Bare View
    </a>
  </div>
  <?php ui_card_close(); ?>

  <!-- ── Transit Preview ── -->
  <?php ui_card_open('departure_board', 'Upcoming Departures',
    '<a href="' . APP_URL . '/roomlink/transit" class="btn btn-sm">Full Board</a>', '#0E71B3'); ?>
  <div id="dash-transit-preview">
    <?php if ($destinations): ?>
    <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.75rem">
      Loading live data…
    </div>
    <?php else: ?>
    <div class="empty-state">
      <span class="material-symbols-outlined">departure_board</span>
      <p>No transit destinations configured.</p>
      <a href="<?= APP_URL ?>/roomlink/settings" class="btn btn-sm btn-primary">Configure</a>
    </div>
    <?php endif; ?>
  </div>
  <?php ui_card_close(); ?>

  <!-- ── Quick Actions ── -->
  <?php ui_card_open('apps', 'Quick Actions', '', '#6366f1'); ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
    <?php
    $links = [
      ['touch_app',       'Controller Mode', '/roomlink/controller',  '#6366f1'],
      ['e_ink',           'E-Ink Preview',   '/roomlink/einkview',    '#10b981'],
      ['departure_board', 'Transit Board',   '/roomlink/transit',     '#0E71B3'],
      ['lightbulb',       'Lighting',        '/roomlink/lighting',    '#f59e0b'],
      ['settings',        'Settings',        '/roomlink/settings',    '#64748b'],
      ['api',             'Pi API',          '/roomlink/api/state',   '#8b5cf6'],
    ];
    foreach ($links as [$icon, $label, $path, $color]):
    ?>
    <a href="<?= APP_URL . htmlspecialchars($path) ?>"
       style="display:flex;align-items:center;gap:.6rem;padding:.75rem;
              background:var(--surface-raised);border-radius:var(--radius);
              text-decoration:none;color:var(--text);border:1px solid var(--border)">
      <span class="material-symbols-outlined" style="color:<?= $color ?>"><?= $icon ?></span>
      <span style="font-weight:500;font-size:.875rem"><?= htmlspecialchars($label) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php ui_card_close(); ?>

</div>
</div>

<script>
/* ── Dashboard transit preview ── */
async function loadDashTransit() {
  try {
    const resp = await fetch('<?= APP_URL ?>/roomlink/api/transit?json=1&limit=3');
    const data = await resp.json();
    if (!data.ok || !data.departures.length) return;
    const el = document.getElementById('dash-transit-preview');
    let html = '<div style="display:flex;flex-direction:column;gap:.5rem">';
    data.departures.slice(0, 3).forEach(d => {
      const statusColor = d.status_type === 'ontime' ? '#10b981' :
                          d.status_type === 'delayed' ? '#f59e0b' : '#ef4444';
      html += `<div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .75rem;
                            background:#0d1b2a;border-radius:4px;border:1px solid #1e3a5f">
        <div style="font-weight:700;color:#fff;min-width:2.5rem;font-size:.9rem">${escHtml(d.time)}</div>
        <div style="flex:1;font-weight:500;color:#e2e8f0;font-size:.875rem">${escHtml(d.destination)}</div>
        <div style="font-size:.75rem;font-weight:700;color:${statusColor}">${escHtml(d.status)}</div>
        <span style="display:inline-flex;align-items:center;justify-content:center;
                     width:30px;height:30px;border-radius:50%;background:${escHtml(d.agency_color)};
                     color:${escHtml(d.agency_text_color)};font-weight:800;font-size:.6rem;flex-shrink:0;">
          ${escHtml(d.agency)}
        </span>
      </div>`;
    });
    html += '</div>';
    el.innerHTML = html;
  } catch(e) { /* silently fail */ }
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

/* ── WLED quick controls ── */
async function wledQuickToggle(id) {
  const btn = document.getElementById('ctrl-btn-' + id);
  const statusEl = document.getElementById('ctrl-status-' + id);
  try {
    const stateResp = await fetch(`<?= APP_URL ?>/roomlink/api/lighting?controller_id=${id}&action=state`);
    const state = await stateResp.json();
    const isOn = state.on ?? false;
    const body = JSON.stringify({on: !isOn, bri: state.bri ?? 128});
    await fetch(`<?= APP_URL ?>/roomlink/api/lighting?controller_id=${id}&action=set`, {
      method: 'POST', headers: {'Content-Type':'application/json'}, body
    });
    statusEl.textContent = !isOn ? 'ON' : 'OFF';
    statusEl.style.background = !isOn ? 'rgba(16,185,129,.15)' : '#1e293b';
    statusEl.style.color = !isOn ? '#10b981' : '#64748b';
  } catch(e) {
    statusEl.textContent = 'ERR';
    statusEl.style.color = '#ef4444';
  }
}

async function wledGlobalOff() {
  const ids = [<?= implode(',', array_column($wled_controllers, 'id')) ?>];
  for (const id of ids) {
    try {
      await fetch(`<?= APP_URL ?>/roomlink/api/lighting?controller_id=${id}&action=set`, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({on: false})
      });
      const s = document.getElementById('ctrl-status-' + id);
      if (s) { s.textContent = 'OFF'; s.style.color = '#64748b'; }
    } catch(e) {}
  }
}

/* ── Controller status poll ── */
async function pollControllerStatus(id) {
  const statusEl = document.getElementById('ctrl-status-' + id);
  try {
    const resp = await fetch(`<?= APP_URL ?>/roomlink/api/lighting?controller_id=${id}&action=state`);
    if (!resp.ok) throw new Error('offline');
    const state = await resp.json();
    if (state.error) throw new Error('error');
    statusEl.textContent = state.on ? 'ON' : 'OFF';
    statusEl.style.background = state.on ? 'rgba(16,185,129,.15)' : '#1e293b';
    statusEl.style.color = state.on ? '#10b981' : '#64748b';
  } catch(e) {
    statusEl.textContent = 'Offline';
    statusEl.style.color = '#ef4444';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadDashTransit();
  <?php foreach ($wled_controllers as $ctrl): ?>
  pollControllerStatus(<?= (int)$ctrl['id'] ?>);
  <?php endforeach; ?>
});
</script>
<?php ui_end(); ?>
