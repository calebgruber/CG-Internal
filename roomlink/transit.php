<?php
/**
 * roomlink/transit.php – Transit Departure Board
 * Station tabs: Stamford | Grand Central | White Plains | Penn Station | Metropark
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

$refresh_sec = (int) rl_setting('transit_refresh_sec', '30');
$nav_items   = rl_nav_items('/roomlink/transit');

ui_head('Transit Board – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('Transit Departure Board', 'Live departures');
?>
<div class="page-body transit-page">

<!-- ══════════════════════════════════════════════════
     STATION TABS
════════════════════════════════════════════════════ -->
<div class="station-tabs-wrap">
  <div class="station-tabs">
    <?php
    $station_tabs = [
        'stamford'      => ['label' => 'Stamford',        'icon' => 'train'],
        'grand-central' => ['label' => 'Grand Central',   'icon' => 'train'],
        'white-plains'  => ['label' => 'White Plains',    'icon' => 'train'],
        'penn-station'  => ['label' => 'Penn Station',    'icon' => 'train'],
        'metropark'     => ['label' => 'Metropark',       'icon' => 'train'],
    ];
    foreach ($station_tabs as $slug => $tab):
    ?>
    <button class="station-tab" data-station="<?= htmlspecialchars($slug) ?>">
      <span class="material-symbols-outlined"><?= $tab['icon'] ?></span>
      <?= htmlspecialchars($tab['label']) ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     STATUS BAR
════════════════════════════════════════════════════ -->
<div class="transit-status-bar">
  <div class="transit-status-left">
    <span id="live-indicator" class="live-badge">
      <span id="live-dot" class="live-dot"></span>
      <span id="live-label">LIVE</span>
    </span>
    <span id="board-clock" class="board-clock"></span>
  </div>
  <div class="transit-status-right">
    <span id="source-badge" class="source-badge" style="display:none"></span>
    <span id="refresh-countdown" class="refresh-text"></span>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     DEPARTURE CARDS
════════════════════════════════════════════════════ -->
<div id="departure-board" class="departure-board">
  <div class="board-loading">
    <span class="material-symbols-outlined">departure_board</span>
    <p>Loading departures…</p>
  </div>
</div>

<div id="board-empty" class="board-empty" style="display:none">
  <span class="material-symbols-outlined">search_off</span>
  <p>No upcoming departures found.</p>
</div>

</div><!-- .page-body -->

<style>
/* ── Station Tabs ─────────────────────────────────── */
.transit-page { padding-top: 0 !important; }

.station-tabs-wrap {
  margin: -1rem -1.5rem 0;
  background: #071323;
  border-bottom: 2px solid #1e3a5f;
  overflow-x: auto;
  scrollbar-width: none;
}
.station-tabs-wrap::-webkit-scrollbar { display: none; }

.station-tabs {
  display: flex;
  min-width: max-content;
}

.station-tab {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: 1.1rem 1.75rem;
  font-size: 1rem;
  font-weight: 700;
  color: #64748b;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: color .2s, border-color .2s, background .2s;
  letter-spacing: .01em;
}
.station-tab .material-symbols-outlined {
  font-size: 1.2rem;
  opacity: .7;
}
.station-tab:hover {
  color: #94a3b8;
  background: rgba(255,255,255,.04);
}
.station-tab.active {
  color: #38bdf8;
  border-bottom-color: #38bdf8;
  background: rgba(56,189,248,.06);
}
.station-tab.active .material-symbols-outlined {
  opacity: 1;
}

/* ── Status Bar ───────────────────────────────────── */
.transit-status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .85rem 0;
  margin-bottom: .5rem;
}
.transit-status-left,
.transit-status-right {
  display: flex;
  align-items: center;
  gap: .75rem;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .78rem;
  font-weight: 800;
  color: #10b981;
  letter-spacing: .06em;
}
.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  animation: pulseDot 2s ease-in-out infinite;
}
.board-clock {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: var(--text-muted);
  font-size: .9rem;
}
.refresh-text {
  font-size: .75rem;
  color: var(--text-muted);
}
.source-badge {
  font-size: .7rem;
  font-weight: 700;
  padding: .15rem .5rem;
  border-radius: 999px;
  border: 1px solid;
}
.source-badge.live  { color: #10b981; border-color: #10b98166; background: #10b98111; }
.source-badge.demo  { color: #f59e0b; border-color: #f59e0b66; background: #f59e0b11; }

/* ── Departure Board ──────────────────────────────── */
.departure-board {
  display: flex;
  flex-direction: column;
  gap: .65rem;
}

.board-loading,
.board-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3.5rem 2rem;
  color: #475569;
  gap: .75rem;
  background: #0d1b2a;
  border-radius: 12px;
  border: 1px solid #1e3a5f;
}
.board-loading .material-symbols-outlined,
.board-empty  .material-symbols-outlined { font-size: 2.5rem; }

/* ── Train Card ───────────────────────────────────── */
@keyframes slideInCard {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes pulseDot {
  0%,100% { opacity:1; transform:scale(1); }
  50%     { opacity:.5; transform:scale(1.4); }
}

.train-card {
  display: flex;
  align-items: stretch;
  border-radius: 14px;
  overflow: hidden;
  height: 74px;
  animation: slideInCard .3s ease forwards;
  box-shadow: 0 2px 8px rgba(0,0,0,.35);
  flex-shrink: 0;
}

/* Time section: darker shade + right-pointing arrow via clip-path */
.train-time-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0 32px 0 18px;
  flex-shrink: 0;
  background: rgba(0,0,0,.32);
  clip-path: polygon(0 0, calc(100% - 22px) 0, 100% 50%, calc(100% - 22px) 100%, 0 100%);
  min-width: 110px;
  line-height: 1;
}
.train-time-hm {
  font-size: 1.45rem;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
  letter-spacing: .01em;
}
.train-time-ampm {
  font-size: .68rem;
  font-weight: 600;
  color: rgba(255,255,255,.65);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-top: 2px;
}

/* Info section */
.train-info-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0 14px 0 8px;
  min-width: 0;
}
.train-destination {
  font-size: 1.08rem;
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.25;
}
.train-meta {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-top: 3px;
  flex-wrap: wrap;
}
.train-line-name {
  font-size: .72rem;
  color: rgba(255,255,255,.72);
  font-weight: 500;
}
.train-track {
  font-size: .7rem;
  font-weight: 700;
  color: rgba(255,255,255,.55);
  background: rgba(0,0,0,.2);
  padding: .1rem .4rem;
  border-radius: 4px;
}
.train-status {
  font-size: .72rem;
  font-weight: 700;
  padding: .1rem .45rem;
  border-radius: 4px;
}
.status-ontime   { background: rgba(16,185,129,.2); color: #6ee7b7; }
.status-delayed  { background: rgba(251,191,36,.2);  color: #fde68a; }
.status-boarding { background: rgba(52,211,153,.2);  color: #6ee7b7; animation: pulseDot 1.5s ease-in-out infinite; }
.status-cancelled{ background: rgba(248,113,113,.2); color: #fca5a5; text-decoration: line-through; }

/* Agency badge section */
.train-agency-section {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  flex-shrink: 0;
}
.agency-badge {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  border: 2px solid rgba(255,255,255,.35);
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-weight: 900;
  font-size: .62rem;
  color: #fff;
  line-height: 1.2;
  letter-spacing: .02em;
  text-transform: uppercase;
  flex-shrink: 0;
}
</style>

<script>
const REFRESH_SEC = <?= $refresh_sec ?>;
const API_BASE    = '<?= APP_URL ?>/roomlink/api/transit?json=1&limit=12';

let currentStation   = 'grand-central';
let allDepartures    = [];
let countdownHandle  = null;
let refreshHandle    = null;

/* ── Agency badge text ── */
const AGENCY_LABELS = {
  MNR: 'METRO\nNORTH',
  NJT: 'NJ\nTRANSIT',
  AMT: 'AMTRAK',
  LIRR:'LIRR',
};

/* ── Clock ── */
function updateClock() {
  const n = new Date();
  const h = n.getHours(), m = n.getMinutes(), s = n.getSeconds();
  const ap = h >= 12 ? 'PM' : 'AM';
  const hh = h % 12 || 12;
  document.getElementById('board-clock').textContent =
    `${hh}:${pad(m)}:${pad(s)} ${ap}`;
}
setInterval(updateClock, 1000);
updateClock();

function pad(n) { return String(n).padStart(2, '0'); }

/* ── Countdown ── */
function startCountdown(secs) {
  clearInterval(countdownHandle);
  clearTimeout(refreshHandle);
  let remaining = secs;
  const el = document.getElementById('refresh-countdown');
  function tick() {
    el.textContent = `Refresh in ${remaining}s`;
    if (remaining <= 0) {
      clearInterval(countdownHandle);
      fetchDepartures(currentStation);
      return;
    }
    remaining--;
  }
  tick();
  countdownHandle = setInterval(tick, 1000);
}

/* ── Live status ── */
function setLiveStatus(state, source) {
  const dot   = document.getElementById('live-dot');
  const badge = document.getElementById('live-label');
  const src   = document.getElementById('source-badge');
  const colors = { live:'#10b981', loading:'#f59e0b', error:'#ef4444' };
  const c = colors[state] || '#10b981';
  dot.style.background = c;
  document.getElementById('live-indicator').style.color = c;

  if (source) {
    src.style.display = 'inline-flex';
    src.className = 'source-badge ' + source;
    src.textContent = source === 'live' ? '⚡ Live Data' : '⚠ Demo Data';
  }
}

/* ── Fetch departures for a station ── */
async function fetchDepartures(station) {
  setLiveStatus('loading', null);
  try {
    const resp = await fetch(`${API_BASE}&station=${encodeURIComponent(station)}`);
    const data = await resp.json();
    if (!data.ok) throw new Error('API error');
    allDepartures = data.departures || [];
    renderBoard(allDepartures);
    setLiveStatus('live', data.source || 'live');
    startCountdown(data.refresh_sec || REFRESH_SEC);
  } catch(e) {
    setLiveStatus('error', null);
    renderError();
    startCountdown(REFRESH_SEC);
  }
}

/* ── Render board ── */
function renderBoard(deps) {
  const board = document.getElementById('departure-board');
  const empty = document.getElementById('board-empty');

  if (!deps.length) {
    board.innerHTML = '';
    empty.style.display = 'flex';
    return;
  }
  empty.style.display = 'none';
  board.innerHTML = deps.map((d, i) => buildCard(d, i)).join('');
}

function renderError() {
  const board = document.getElementById('departure-board');
  board.innerHTML = `
    <div class="board-loading">
      <span class="material-symbols-outlined" style="color:#ef4444">wifi_off</span>
      <p style="color:#ef4444">Could not load departures. Retrying…</p>
    </div>`;
}

/* ── Build train card HTML ── */
function buildCard(d, idx) {
  const color     = esc(d.agency_color || '#3b82f6');
  const timeParts = d.time.split(':');
  const hm = timeParts.slice(0, 2).join(':');
  // Derive AM/PM from time_24
  const h24 = d.time_24 ? parseInt(d.time_24.split(':')[0]) : 0;
  const ampm = h24 >= 12 ? 'PM' : 'AM';

  const statusClass = {
    ontime:    'status-ontime',
    delayed:   'status-delayed',
    boarding:  'status-boarding',
    cancelled: 'status-cancelled',
  }[d.status_type] || 'status-ontime';

  const agencyLabel = (AGENCY_LABELS[d.agency] || d.agency || '').replace(/\\n/g, '\n');
  const agencyLines = agencyLabel.split('\n').map(escHtml).join('<br>');

  const track = d.track ? `<span class="train-track">Track ${escHtml(d.track)}</span>` : '';

  return `
<div class="train-card" style="background:${color};animation-delay:${idx*0.05}s">
  <div class="train-time-section">
    <span class="train-time-hm">${escHtml(hm)}</span>
    <span class="train-time-ampm">${ampm}</span>
  </div>
  <div class="train-info-section">
    <div class="train-destination">${escHtml(d.destination)}</div>
    <div class="train-meta">
      ${d.line_name ? `<span class="train-line-name">${escHtml(d.line_name)}</span>` : ''}
      ${track}
      <span class="train-status ${statusClass}">${escHtml(d.status)}</span>
    </div>
  </div>
  <div class="train-agency-section">
    <div class="agency-badge">${agencyLines}</div>
  </div>
</div>`;
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s || '');
  return d.innerHTML;
}
function esc(s) {
  return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ── Station tab switching ── */
document.querySelectorAll('.station-tab').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.station-tab').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    currentStation = this.dataset.station;
    clearInterval(countdownHandle);
    clearTimeout(refreshHandle);
    document.getElementById('departure-board').innerHTML =
      `<div class="board-loading">
         <span class="material-symbols-outlined">departure_board</span>
         <p>Loading departures…</p>
       </div>`;
    fetchDepartures(currentStation);
  });
});

/* ── Init: activate first tab and fetch ── */
const firstTab = document.querySelector('.station-tab');
if (firstTab) {
  firstTab.classList.add('active');
  currentStation = firstTab.dataset.station;
}
fetchDepartures(currentStation);
</script>
<?php ui_end(); ?>
