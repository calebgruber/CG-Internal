<?php
/**
 * roomlink/transit.php – Animated Transit Departure Board
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

$refresh_sec = (int) rl_setting('transit_refresh_sec', '30');

/* ── Initial agencies for filter bar ── */
try {
    $agencies = db()->query(
        'SELECT * FROM roomlink_transit_agencies WHERE is_active = 1 ORDER BY sort_order'
    )->fetchAll();
} catch (PDOException $e) {
    $agencies = [];
}

$nav_items = rl_nav_items('/roomlink/transit');

ui_head('Transit Board – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('Transit Departure Board', 'Live departures');
?>
<div class="page-body">

<!-- ── Filter bar ── -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem">
  <button class="btn btn-sm agency-filter active" data-agency="ALL"
          style="background:#1e3a5f;color:#e2e8f0;border:1px solid #2d5a8e">All Agencies</button>
  <?php foreach ($agencies as $ag): ?>
  <button class="btn btn-sm agency-filter" data-agency="<?= htmlspecialchars($ag['short_name']) ?>"
          style="background:<?= htmlspecialchars($ag['color']) ?>22;color:<?= htmlspecialchars($ag['color']) ?>;
                 border:1px solid <?= htmlspecialchars($ag['color']) ?>55">
    <?= htmlspecialchars($ag['short_name']) ?>
  </button>
  <?php endforeach; ?>

  <div style="margin-left:auto;display:flex;align-items:center;gap:.75rem">
    <!-- Live indicator -->
    <span id="live-indicator" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:700;color:#10b981">
      <span id="live-dot" style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block"></span>
      LIVE
    </span>
    <!-- Clock -->
    <span id="board-clock" style="font-variant-numeric:tabular-nums;font-weight:600;color:var(--text-muted);font-size:.875rem"></span>
    <!-- Refresh countdown -->
    <span id="refresh-countdown" style="font-size:.75rem;color:var(--text-muted)"></span>
  </div>
</div>

<!-- ── Departure Board ── -->
<div id="departure-board"
     style="background:#0d1b2a;border-radius:8px;overflow:hidden;border:1px solid #1e3a5f;
            font-family:var(--font)">

  <!-- Header -->
  <div style="display:grid;grid-template-columns:80px 1fr 160px;gap:0;
              padding:.6rem 1.25rem;background:#071323;border-bottom:2px solid #1e3a5f">
    <div style="font-size:.75rem;font-weight:700;color:#94a3b8;letter-spacing:.08em;text-transform:uppercase">Track</div>
    <div style="font-size:.75rem;font-weight:700;color:#94a3b8;letter-spacing:.08em;text-transform:uppercase">Departing Train</div>
    <div style="font-size:.75rem;font-weight:700;color:#94a3b8;letter-spacing:.08em;text-transform:uppercase;text-align:right">Status</div>
  </div>

  <!-- Rows container -->
  <div id="board-rows">
    <div style="padding:3rem;text-align:center;color:#475569">
      <span class="material-symbols-outlined" style="font-size:2.5rem;display:block;margin-bottom:.5rem">departure_board</span>
      Loading departures…
    </div>
  </div>
</div>

<!-- ── No results state ── -->
<div id="board-empty" style="display:none;padding:3rem;text-align:center;
     background:#0d1b2a;border-radius:8px;border:1px solid #1e3a5f;color:#475569;margin-top:1rem">
  <span class="material-symbols-outlined" style="font-size:2.5rem;display:block;margin-bottom:.5rem">search_off</span>
  No departures match the current filter.
</div>

</div><!-- .page-body -->

<style>
@keyframes slideInRow {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes pulseDot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .5; transform: scale(1.4); }
}
#live-dot { animation: pulseDot 2s ease-in-out infinite; }

.board-row {
  animation: slideInRow 0.3s ease forwards;
  display: grid;
  grid-template-columns: 80px 1fr 160px;
  gap: 0;
  padding: .65rem 1.25rem;
  border-bottom: 1px solid #112236;
  align-items: center;
  transition: background 0.2s ease;
}
.board-row:hover { background: #0f2133; }
.board-row:last-child { border-bottom: none; }

.train-strip {
  display: flex;
  align-items: center;
  border-radius: 6px;
  overflow: visible;
  height: 52px;
  position: relative;
}
.train-strip-time {
  padding: 0 16px 0 12px;
  font-weight: 700;
  font-size: 1.1rem;
  color: #fff;
  background: rgba(0,0,0,0.25);
  height: 100%;
  display: flex;
  align-items: center;
  border-radius: 6px 0 0 6px;
  white-space: nowrap;
  flex-shrink: 0;
  z-index: 1;
}
.train-strip-dest {
  flex: 1;
  text-align: center;
  font-weight: 600;
  font-size: 1.05rem;
  color: #fff;
  padding: 0 12px;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.train-strip-logo {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.7rem;
  margin-right: -4px;
  flex-shrink: 0;
  border: 2px solid rgba(255,255,255,0.3);
  text-align: center;
  line-height: 1.1;
}
.track-num {
  font-size: 1.5rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
}
.track-label {
  font-size: .65rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.status-ontime   { color: #ffffff; font-weight: 600; }
.status-delayed  { color: #fbbf24; font-weight: 700; }
.status-boarding { color: #34d399; font-weight: 700; animation: pulseDot 1.5s ease-in-out infinite; }
.status-cancelled{ color: #f87171; font-weight: 700; text-decoration: line-through; }

.agency-filter.active {
  background: #2d5a8e !important;
  color: #ffffff !important;
  border-color: #3b82f6 !important;
}
</style>

<script>
const REFRESH_SEC = <?= $refresh_sec ?>;
const API_URL = '<?= APP_URL ?>/roomlink/api/transit?json=1';
let currentFilter = 'ALL';
let allDepartures = [];
let countdownInterval, refreshTimeout;

/* ── Clock ── */
function updateClock() {
  const now = new Date();
  const h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hh = h % 12 || 12;
  document.getElementById('board-clock').textContent =
    `${hh}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')} ${ampm}`;
}
setInterval(updateClock, 1000);
updateClock();

/* ── Countdown ── */
function startCountdown(secs) {
  clearInterval(countdownInterval);
  let remaining = secs;
  const el = document.getElementById('refresh-countdown');
  function tick() {
    el.textContent = `Refresh in ${remaining}s`;
    if (remaining <= 0) {
      clearInterval(countdownInterval);
      fetchDepartures();
    }
    remaining--;
  }
  tick();
  countdownInterval = setInterval(tick, 1000);
}

/* ── Fetch departures ── */
async function fetchDepartures() {
  setLiveStatus('loading');
  try {
    const resp = await fetch(API_URL);
    const data = await resp.json();
    if (!data.ok) throw new Error('API error');
    allDepartures = data.departures || [];
    renderBoard(currentFilter);
    setLiveStatus('live');
    startCountdown(REFRESH_SEC);
  } catch(e) {
    setLiveStatus('error');
    startCountdown(REFRESH_SEC);
  }
}

function setLiveStatus(state) {
  const dot = document.getElementById('live-dot');
  const txt = document.getElementById('live-indicator');
  if (state === 'live') {
    dot.style.background = '#10b981';
    txt.style.color = '#10b981';
    txt.querySelector('span:last-child') && (txt.lastChild.textContent = ' LIVE');
  } else if (state === 'loading') {
    dot.style.background = '#f59e0b';
    txt.style.color = '#f59e0b';
  } else {
    dot.style.background = '#ef4444';
    txt.style.color = '#ef4444';
  }
}

/* ── Render board ── */
function renderBoard(filter) {
  const rows = document.getElementById('board-rows');
  const empty = document.getElementById('board-empty');
  const filtered = filter === 'ALL'
    ? allDepartures
    : allDepartures.filter(d => d.agency === filter);

  if (!filtered.length) {
    rows.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  rows.innerHTML = filtered.map((d, i) => buildRow(d, i)).join('');
}

function buildRow(d, idx) {
  const statusClass = {
    ontime:    'status-ontime',
    delayed:   'status-delayed',
    boarding:  'status-boarding',
    cancelled: 'status-cancelled',
  }[d.status_type] || 'status-ontime';

  const delay = d.status_type === 'delayed' && d.delay_minutes > 0
    ? `<div style="font-size:.7rem;color:#94a3b8">+${d.delay_minutes} min</div>`
    : '';

  const color = escAttr(d.agency_color || '#3b82f6');
  const textColor = escAttr(d.agency_text_color || '#ffffff');

  return `<div class="board-row" data-agency="${escAttr(d.agency)}" style="animation-delay:${idx * 0.04}s">
    <div>
      <div class="track-num">${escHtml(d.track || '—')}</div>
      <div class="track-label">Track</div>
    </div>
    <div>
      <div class="train-strip" style="background:${color}">
        <div class="train-strip-time">${escHtml(d.time)}</div>
        <div class="train-strip-dest">${escHtml(d.destination)}</div>
        <div class="train-strip-logo" style="color:${color}">${escHtml(d.agency)}</div>
      </div>
    </div>
    <div style="text-align:right">
      <div class="${statusClass}">${escHtml(d.status)}</div>
      ${delay}
    </div>
  </div>`;
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s || '');
  return d.innerHTML;
}
function escAttr(s) {
  return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ── Agency filters ── */
document.querySelectorAll('.agency-filter').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.agency-filter').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    currentFilter = this.dataset.agency;
    renderBoard(currentFilter);
  });
});

/* ── Init ── */
fetchDepartures();
</script>
<?php ui_end(); ?>
