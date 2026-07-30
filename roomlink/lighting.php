<?php
/**
 * roomlink/lighting.php – WLED Lighting Control
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

try {
    $controllers = db()->query(
        'SELECT * FROM roomlink_wled_controllers WHERE is_active = 1 ORDER BY sort_order, name'
    )->fetchAll();
} catch (PDOException $e) {
    $controllers = [];
}

$nav_items = rl_nav_items('/roomlink/lighting');

ui_head('Lighting – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('WLED Lighting', 'Control all your WLED smart lights',
    '<button class="btn btn-sm" style="background:#450a0a;color:#fca5a5;border:1px solid #991b1b"
             onclick="globalOff()">
       <span class="material-symbols-outlined">power_settings_new</span> Global Off
     </button>'
);
?>
<div class="page-body">
<?php ui_flash(); ?>

<?php if (empty($controllers)): ?>
<div style="text-align:center;padding:3rem;background:var(--surface);border-radius:var(--radius);border:1px solid var(--border)">
  <span class="material-symbols-outlined" style="font-size:3rem;color:var(--text-muted)">lightbulb</span>
  <h3 style="margin:.75rem 0 .5rem">No WLED Controllers Configured</h3>
  <p style="color:var(--text-muted);margin-bottom:1rem">Add a WLED controller in Settings to get started.</p>
  <a href="<?= APP_URL ?>/roomlink/settings" class="btn btn-primary">
    <span class="material-symbols-outlined">settings</span> Open Settings
  </a>
</div>
<?php else: ?>

<div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
  <?php foreach ($controllers as $ctrl):
    $cid = (int)$ctrl['id'];
  ?>
  <div class="card" style="border-left:3px solid #f59e0b">
    <div class="card-top">
      <div class="card-tab">
        <span class="material-symbols-outlined" style="color:#f59e0b">lightbulb</span>
        <h3><?= htmlspecialchars($ctrl['name']) ?></h3>
      </div>
      <div class="card-header-actions">
        <span id="status-badge-<?= $cid ?>"
              style="font-size:.7rem;padding:.2rem .6rem;border-radius:3px;background:#1e293b;color:#64748b;font-weight:600">
          Checking…
        </span>
      </div>
    </div>
    <div class="card-body">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem">
        <?= htmlspecialchars($ctrl['ip_address']) ?>:<?= (int)$ctrl['port'] ?>
        <?php if ($ctrl['notes']): ?>
        <span style="margin-left:.5rem">· <?= htmlspecialchars($ctrl['notes']) ?></span>
        <?php endif; ?>
      </div>

      <!-- On/Off toggle -->
      <div style="display:flex;gap:.75rem;margin-bottom:1.25rem">
        <button class="btn" style="flex:1;height:52px;font-size:1rem;font-weight:700;
                background:rgba(16,185,129,.15);color:#10b981;border:2px solid #10b981"
                id="on-btn-<?= $cid ?>"
                onclick="setOnOff(<?= $cid ?>, true)">
          <span class="material-symbols-outlined">power</span> ON
        </button>
        <button class="btn" style="flex:1;height:52px;font-size:1rem;font-weight:700;
                background:rgba(239,68,68,.1);color:#ef4444;border:2px solid #ef4444"
                id="off-btn-<?= $cid ?>"
                onclick="setOnOff(<?= $cid ?>, false)">
          <span class="material-symbols-outlined">power_off</span> OFF
        </button>
      </div>

      <!-- Brightness -->
      <div style="margin-bottom:1.25rem">
        <label style="font-size:.8rem;font-weight:600;color:var(--text-muted);
                      display:flex;justify-content:space-between;margin-bottom:.4rem">
          <span>Brightness</span>
          <span id="bri-val-<?= $cid ?>">—</span>
        </label>
        <input type="range" id="bri-slider-<?= $cid ?>"
               min="1" max="255" value="128" style="width:100%"
               oninput="document.getElementById('bri-val-<?= $cid ?>').textContent=Math.round(this.value/2.55)+'%'"
               onchange="setBrightness(<?= $cid ?>, this.value)">
      </div>

      <!-- Presets -->
      <div>
        <div style="font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.6rem">Presets</div>
        <div id="presets-<?= $cid ?>" style="display:flex;flex-wrap:wrap;gap:.4rem">
          <span style="font-size:.8rem;color:var(--text-muted)">Loading presets…</span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<script>
const API = '<?= APP_URL ?>/roomlink/api/lighting';

async function loadController(id) {
  const badge = document.getElementById('status-badge-' + id);
  try {
    const resp = await fetch(`${API}?controller_id=${id}&action=state`);
    if (!resp.ok) throw new Error('offline');
    const state = await resp.json();
    if (state.error) throw new Error('error');

    badge.textContent = state.on ? 'Online · ON' : 'Online · OFF';
    badge.style.background = state.on ? 'rgba(16,185,129,.15)' : 'rgba(100,116,139,.15)';
    badge.style.color = state.on ? '#10b981' : '#94a3b8';

    const slider = document.getElementById('bri-slider-' + id);
    const briVal = document.getElementById('bri-val-' + id);
    if (slider && state.bri !== undefined) {
      slider.value = state.bri;
      briVal.textContent = Math.round(state.bri / 2.55) + '%';
    }

    loadPresets(id);
  } catch(e) {
    badge.textContent = 'Offline';
    badge.style.background = 'rgba(239,68,68,.1)';
    badge.style.color = '#f87171';
    const presetsEl = document.getElementById('presets-' + id);
    if (presetsEl) presetsEl.innerHTML = '<span style="font-size:.8rem;color:#ef4444">Controller unreachable</span>';
  }
}

async function loadPresets(id) {
  const el = document.getElementById('presets-' + id);
  try {
    const resp = await fetch(`${API}?controller_id=${id}&action=presets`);
    const data = await resp.json();
    if (!data || data.error || typeof data !== 'object') {
      el.innerHTML = '<span style="font-size:.8rem;color:var(--text-muted)">No presets found</span>';
      return;
    }
    const keys = Object.keys(data).filter(k => k !== '0' && data[k].n);
    if (!keys.length) {
      el.innerHTML = '<span style="font-size:.8rem;color:var(--text-muted)">No presets found</span>';
      return;
    }
    el.innerHTML = keys.slice(0, 12).map(k =>
      `<button class="btn btn-sm" style="font-size:.75rem;padding:.3rem .7rem;background:var(--surface-raised)"
               onclick="activatePreset(${id},${parseInt(k)})">
         ${escHtml(data[k].n)}
       </button>`
    ).join('');
  } catch(e) {
    el.innerHTML = '<span style="font-size:.8rem;color:var(--text-muted)">Presets unavailable</span>';
  }
}

async function setOnOff(id, on) {
  try {
    await fetch(`${API}?controller_id=${id}&action=set`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({on})
    });
    const badge = document.getElementById('status-badge-' + id);
    badge.textContent = on ? 'Online · ON' : 'Online · OFF';
    badge.style.background = on ? 'rgba(16,185,129,.15)' : 'rgba(100,116,139,.15)';
    badge.style.color = on ? '#10b981' : '#94a3b8';
  } catch(e) { alert('Failed to reach controller.'); }
}

async function setBrightness(id, bri) {
  try {
    await fetch(`${API}?controller_id=${id}&action=set`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({bri: parseInt(bri)})
    });
  } catch(e) {}
}

async function activatePreset(id, ps) {
  try {
    await fetch(`${API}?controller_id=${id}&action=preset`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ps})
    });
  } catch(e) { alert('Failed to activate preset.'); }
}

async function globalOff() {
  const ids = [<?= implode(',', array_column($controllers, 'id')) ?>];
  for (const id of ids) {
    await setOnOff(id, false);
  }
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s || '');
  return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
  <?php foreach ($controllers as $ctrl): ?>
  loadController(<?= (int)$ctrl['id'] ?>);
  <?php endforeach; ?>
});
</script>
<?php ui_end(); ?>
