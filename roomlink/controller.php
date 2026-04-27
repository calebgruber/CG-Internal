<?php
/**
 * roomlink/controller.php – 7" Touchscreen Controller UI
 *
 * Standalone full-screen UI, no standard CG Internal sidebar.
 * Optimized for 7" touch displays (800×480 / 1024×600).
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

/* ── Data ── */
try {
    $controllers = db()->query(
        'SELECT * FROM roomlink_wled_controllers WHERE is_active = 1 ORDER BY sort_order, name'
    )->fetchAll();
} catch (PDOException $e) {
    $controllers = [];
}

$eink = rl_eink_state();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title>RoomLink Controller</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/shared/assets/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; background: #070e1f; color: #f1f5f9;
                 font-family: 'Inter', system-ui, sans-serif; overflow-x: hidden; }

    /* ── Header ── */
    .ctrl-header {
      background: #0d1b2a;
      border-bottom: 2px solid #1e3a5f;
      padding: .6rem 1rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      height: 56px;
    }
    .ctrl-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: #f8fafc;
      letter-spacing: -.01em;
      display: flex;
      align-items: center;
      gap: .4rem;
    }
    .ctrl-title .dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: #3b82f6;
    }
    #ctrl-clock {
      font-size: 1.3rem;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
      color: #94a3b8;
      margin-left: auto;
    }
    .ctrl-back {
      color: #64748b;
      font-size: .8rem;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: .3rem;
      padding: .3rem .6rem;
      border-radius: 4px;
      border: 1px solid #1e293b;
    }
    .ctrl-back:hover { background: #1e293b; color: #94a3b8; text-decoration: none; }

    /* ── Main layout ── */
    .ctrl-body {
      padding: .75rem;
      display: flex;
      flex-direction: column;
      gap: .75rem;
      max-height: calc(100vh - 56px);
      overflow-y: auto;
    }

    /* ── Section cards ── */
    .ctrl-section {
      background: #0d1b2a;
      border: 1px solid #1e3a5f;
      border-radius: 8px;
      overflow: hidden;
    }
    .ctrl-section-header {
      background: #071323;
      padding: .5rem .9rem;
      font-size: .7rem;
      font-weight: 700;
      color: #64748b;
      letter-spacing: .1em;
      text-transform: uppercase;
      border-bottom: 1px solid #1e3a5f;
    }
    .ctrl-section-body { padding: .75rem; }

    /* ── Touch buttons ── */
    .ctrl-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .4rem;
      min-height: 60px;
      padding: .5rem 1rem;
      border-radius: 6px;
      border: 2px solid transparent;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
    }
    .ctrl-btn:active { transform: scale(0.96); opacity: .8; }

    .ctrl-btn-on  { background: rgba(16,185,129,.2); color: #10b981; border-color: #10b981; flex: 1; }
    .ctrl-btn-off { background: rgba(239,68,68,.15); color: #ef4444; border-color: #ef4444; flex: 1; }
    .ctrl-btn-tab {
      background: #1e293b;
      color: #94a3b8;
      border-color: #334155;
      font-size: .9rem;
      min-height: 64px;
      flex: 1;
    }
    .ctrl-btn-tab.active {
      background: rgba(59,130,246,.2);
      color: #60a5fa;
      border-color: #3b82f6;
    }

    /* ── Controller row ── */
    .ctrl-light-row {
      display: flex;
      flex-direction: column;
      gap: .5rem;
      padding-bottom: .75rem;
      margin-bottom: .75rem;
      border-bottom: 1px solid #1e3a5f;
    }
    .ctrl-light-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .ctrl-light-name {
      font-size: .85rem;
      font-weight: 600;
      color: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .ctrl-light-btns { display: flex; gap: .5rem; }

    /* ── Brightness slider ── */
    input[type=range].ctrl-slider {
      width: 100%;
      height: 40px;
      -webkit-appearance: none;
      appearance: none;
      background: #1e293b;
      border-radius: 20px;
      outline: none;
      cursor: pointer;
      border: 1px solid #334155;
    }
    input[type=range].ctrl-slider::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #3b82f6;
      cursor: pointer;
      border: 3px solid #f8fafc;
      box-shadow: 0 2px 8px rgba(0,0,0,.4);
    }

    /* ── Transit rows ── */
    .ctrl-departure {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .6rem .5rem;
      border-bottom: 1px solid #112236;
    }
    .ctrl-departure:last-child { border-bottom: none; }
    .ctrl-dep-time {
      font-size: 1.2rem;
      font-weight: 800;
      color: #fff;
      min-width: 55px;
    }
    .ctrl-dep-dest {
      flex: 1;
      font-size: .95rem;
      font-weight: 600;
      color: #e2e8f0;
    }
    .ctrl-dep-status {
      font-size: .8rem;
      font-weight: 700;
    }

    /* ── Footer ── */
    .ctrl-footer {
      text-align: center;
      padding: .6rem;
      font-size: .7rem;
      color: #334155;
    }
    .ctrl-footer a { color: #475569; text-decoration: none; }
    .ctrl-footer a:hover { color: #64748b; }
  </style>
</head>
<body>

<!-- ── Header ── -->
<div class="ctrl-header">
  <div class="ctrl-title">
    <div class="dot"></div>
    RoomLink
  </div>
  <a href="<?= APP_URL ?>/roomlink/" class="ctrl-back">
    ← Full Site
  </a>
  <div id="ctrl-clock">--:--</div>
</div>

<!-- ── Body ── -->
<div class="ctrl-body">

  <!-- ── Lighting section ── -->
  <div class="ctrl-section">
    <div class="ctrl-section-header">💡 Lighting</div>
    <div class="ctrl-section-body">
      <?php if (empty($controllers)): ?>
      <div style="color:#64748b;font-size:.85rem;text-align:center;padding:.5rem">
        No WLED controllers configured.
        <a href="<?= APP_URL ?>/roomlink/settings" style="color:#3b82f6">Configure</a>
      </div>
      <?php else: ?>
      <?php foreach ($controllers as $ctrl):
        $cid = (int)$ctrl['id'];
      ?>
      <div class="ctrl-light-row">
        <div class="ctrl-light-name">
          <span><?= htmlspecialchars($ctrl['name']) ?></span>
          <span id="c-status-<?= $cid ?>" style="font-size:.7rem;color:#64748b">…</span>
        </div>
        <div class="ctrl-light-btns">
          <button class="ctrl-btn ctrl-btn-on" onclick="ctrlSetOnOff(<?= $cid ?>, true)">
            ⚡ ON
          </button>
          <button class="ctrl-btn ctrl-btn-off" onclick="ctrlSetOnOff(<?= $cid ?>, false)">
            ✕ OFF
          </button>
        </div>
        <input type="range" class="ctrl-slider" id="c-bri-<?= $cid ?>"
               min="1" max="255" value="128"
               onchange="ctrlSetBri(<?= $cid ?>, this.value)">
      </div>
      <?php endforeach; ?>
      <div style="margin-top:.5rem">
        <button class="ctrl-btn" style="width:100%;background:rgba(239,68,68,.1);color:#ef4444;
                border:2px solid #ef4444;min-height:52px" onclick="ctrlGlobalOff()">
          ⏹ Global Off
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── E-Ink Tab Control ── -->
  <div class="ctrl-section">
    <div class="ctrl-section-header">📺 E-Ink Display Tab</div>
    <div class="ctrl-section-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem" id="tab-btns">
        <?php foreach (['transit','clock','weather','custom'] as $t): ?>
        <button class="ctrl-btn ctrl-btn-tab <?= $eink['current_tab'] === $t ? 'active' : '' ?>"
                onclick="ctrlSetTab('<?= $t ?>')" data-tab="<?= $t ?>">
          <?= ['transit'=>'🚆','clock'=>'🕐','weather'=>'🌤','custom'=>'✏️'][$t] ?>
          <?= ucfirst($t) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:.5rem;font-size:.75rem;color:#475569;text-align:center">
        Current: <strong id="current-tab-label" style="color:#60a5fa"><?= htmlspecialchars($eink['current_tab']) ?></strong>
      </div>
    </div>
  </div>

  <!-- ── Transit Quick View ── -->
  <div class="ctrl-section">
    <div class="ctrl-section-header" style="display:flex;justify-content:space-between;align-items:center">
      <span>🚉 Next Departures</span>
      <span id="dep-updated" style="font-size:.65rem;color:#475569">Loading…</span>
    </div>
    <div class="ctrl-section-body" id="ctrl-departures" style="padding:0">
      <div style="padding:1rem;text-align:center;color:#475569;font-size:.85rem">Loading…</div>
    </div>
  </div>

</div>

<!-- ── Footer ── -->
<div class="ctrl-footer">
  <a href="<?= APP_URL ?>/roomlink/">← Full RoomLink Dashboard</a>
  &nbsp;·&nbsp;
  <a href="<?= APP_URL ?>/roomlink/einkview">E-Ink Preview</a>
</div>

<script>
const RL_API   = '<?= APP_URL ?>/roomlink/api';
const CSRF_TOK = '<?= htmlspecialchars($csrf) ?>';

/* ── Clock ── */
function updateCtrlClock() {
  const now = new Date();
  const h = now.getHours() % 12 || 12;
  const m = String(now.getMinutes()).padStart(2,'0');
  const ampm = now.getHours() >= 12 ? 'PM' : 'AM';
  document.getElementById('ctrl-clock').textContent = `${h}:${m} ${ampm}`;
}
setInterval(updateCtrlClock, 1000);
updateCtrlClock();

/* ── WLED controls ── */
async function ctrlSetOnOff(id, on) {
  try {
    await fetch(`${RL_API}/lighting?controller_id=${id}&action=set`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({on})
    });
    const s = document.getElementById('c-status-' + id);
    if (s) { s.textContent = on ? 'ON' : 'OFF'; s.style.color = on ? '#10b981' : '#64748b'; }
  } catch(e) {}
}

async function ctrlSetBri(id, bri) {
  try {
    await fetch(`${RL_API}/lighting?controller_id=${id}&action=set`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({bri: parseInt(bri)})
    });
  } catch(e) {}
}

async function ctrlGlobalOff() {
  const ids = [<?= implode(',', array_column($controllers, 'id')) ?>];
  for (const id of ids) await ctrlSetOnOff(id, false);
}

async function ctrlLoadStatus(id) {
  try {
    const r = await fetch(`${RL_API}/lighting?controller_id=${id}&action=state`);
    const s = await r.json();
    if (s.error) throw new Error();
    const el = document.getElementById('c-status-' + id);
    if (el) { el.textContent = s.on ? 'ON' : 'OFF'; el.style.color = s.on ? '#10b981' : '#64748b'; }
    const sl = document.getElementById('c-bri-' + id);
    if (sl && s.bri !== undefined) sl.value = s.bri;
  } catch(e) {
    const el = document.getElementById('c-status-' + id);
    if (el) { el.textContent = 'Offline'; el.style.color = '#ef4444'; }
  }
}

/* ── E-Ink tab ── */
async function ctrlSetTab(tab) {
  try {
    await fetch(`${RL_API}/state`, {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-Token': CSRF_TOK},
      body: JSON.stringify({action:'set_tab', tab})
    });
    document.querySelectorAll('.ctrl-btn-tab').forEach(b => {
      b.classList.toggle('active', b.dataset.tab === tab);
    });
    const label = document.getElementById('current-tab-label');
    if (label) label.textContent = tab;
  } catch(e) { alert('Failed to update e-ink tab.'); }
}

/* ── Transit departures ── */
async function ctrlLoadDepartures() {
  const el = document.getElementById('ctrl-departures');
  const upd = document.getElementById('dep-updated');
  try {
    const r = await fetch(`${RL_API}/transit?json=1&limit=3`);
    const data = await r.json();
    if (!data.ok || !data.departures.length) {
      el.innerHTML = '<div style="padding:1rem;text-align:center;color:#475569;font-size:.85rem">No departures available.</div>';
      return;
    }
    el.innerHTML = data.departures.slice(0,3).map(d => {
      const sc = {'ontime':'#10b981','delayed':'#f59e0b','boarding':'#34d399','cancelled':'#f87171'}[d.status_type] || '#10b981';
      return `<div class="ctrl-departure">
        <div class="ctrl-dep-time">${escHtml(d.time)}</div>
        <div>
          <div class="ctrl-dep-dest">${escHtml(d.destination)}</div>
          <div style="font-size:.7rem;color:#64748b">${escHtml(d.agency_name)}</div>
        </div>
        <div class="ctrl-dep-status" style="color:${sc}">${escHtml(d.status)}</div>
      </div>`;
    }).join('');
    const now = new Date();
    upd.textContent = `${now.getHours()%12||12}:${String(now.getMinutes()).padStart(2,'0')} ${now.getHours()>=12?'PM':'AM'}`;
  } catch(e) {
    el.innerHTML = '<div style="padding:1rem;text-align:center;color:#ef4444;font-size:.85rem">Failed to load.</div>';
  }
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s || '');
  return d.innerHTML;
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  <?php foreach ($controllers as $ctrl): ?>
  ctrlLoadStatus(<?= (int)$ctrl['id'] ?>);
  <?php endforeach; ?>
  ctrlLoadDepartures();
  setInterval(ctrlLoadDepartures, 30000);
});
</script>
</body>
</html>
