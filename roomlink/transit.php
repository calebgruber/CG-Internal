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
<div class="page-body">

<!-- ══════════════════════════════════════════════════
     STATION TABS  (kept outside the card — same look as before)
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
     CARD WRAPPER  (puts board back into the standard UI)
════════════════════════════════════════════════════ -->
<?php
$status_bar_html = '
<div class="transit-status-bar" style="margin-left:auto">
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
</div>';
ui_card_open('departure_board', 'Departures', $status_bar_html, '#0E71B3');
?>

<!-- ══════════════════════════════════════════════════
     DEPARTURE CARDS  (rendered inside the card body)
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

<?php ui_card_close(); ?>

</div><!-- .page-body -->

<style>
/* ── Station Tabs ─────────────────────────────────── */
.station-tabs-wrap {
  margin: -1.5rem -1.5rem 1rem;
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
  padding: 1rem 1.5rem;
  font-size: .9375rem;
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
  font-size: 1.1rem;
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
.station-tab.active .material-symbols-outlined { opacity: 1; }

/* ── Status Bar (inside card header actions) ─────── */
.transit-status-bar {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}
.transit-status-left,
.transit-status-right {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .72rem;
  font-weight: 800;
  color: #10b981;
  letter-spacing: .06em;
}
.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  animation: pulseDot 2s ease-in-out infinite;
}
.board-clock {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: var(--text-muted);
  font-size: .82rem;
}
.refresh-text {
  font-size: .72rem;
  color: var(--text-muted);
}
.source-badge {
  font-size: .68rem;
  font-weight: 700;
  padding: .12rem .45rem;
  border-radius: 999px;
  border: 1px solid;
}
.source-badge.live  { color: #10b981; border-color: #10b98166; background: #10b98111; }
.source-badge.demo  { color: #f59e0b; border-color: #f59e0b66; background: #f59e0b11; }

/* ── Departure Board ──────────────────────────────── */
.departure-board {
  display: flex;
  flex-direction: column;
  gap: .6rem;
}

.board-loading,
.board-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  color: var(--text-muted);
  gap: .75rem;
  background: var(--surface-raised);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
}
.board-loading .material-symbols-outlined,
.board-empty  .material-symbols-outlined { font-size: 2.25rem; opacity: .5; }

/* ── Train Card ───────────────────────────────────── */
@keyframes slideInCard {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes pulseDot {
  0%,100% { opacity:1; transform:scale(1); }
  50%     { opacity:.5; transform:scale(1.4); }
}

.train-card {
  display: flex;
  align-items: stretch;
  border-radius: 10px;
  overflow: hidden;
  height: 72px;
  animation: slideInCard .25s ease forwards;
  box-shadow: 0 2px 6px rgba(0,0,0,.25);
  flex-shrink: 0;
}

/* Time section */
.train-time-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0 28px 0 16px;
  flex-shrink: 0;
  background: rgba(0,0,0,.28);
  clip-path: polygon(0 0, calc(100% - 20px) 0, 100% 50%, calc(100% - 20px) 100%, 0 100%);
  min-width: 105px;
  line-height: 1;
}
.train-time-hm {
  font-size: 1.4rem;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
  letter-spacing: .01em;
}
.train-time-ampm {
  font-size: .65rem;
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
  padding: 0 12px 0 8px;
  min-width: 0;
}
.train-destination {
  font-size: 1.05rem;
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
  gap: .45rem;
  margin-top: 3px;
  flex-wrap: wrap;
}
.train-line-name {
  font-size: .7rem;
  color: rgba(255,255,255,.72);
  font-weight: 500;
}
.train-track {
  font-size: .68rem;
  font-weight: 700;
  color: rgba(255,255,255,.55);
  background: rgba(0,0,0,.22);
  padding: .1rem .38rem;
  border-radius: 4px;
}
.train-status {
  font-size: .68rem;
  font-weight: 700;
  padding: .1rem .42rem;
  border-radius: 4px;
}
.status-ontime    { background: rgba(16,185,129,.2);  color: #6ee7b7; }
.status-delayed   { background: rgba(251,191,36,.2);  color: #fde68a; }
.status-boarding  { background: rgba(52,211,153,.2);  color: #6ee7b7;
                    animation: flash 1.2s ease-in-out infinite; }
.status-cancelled { background: rgba(248,113,113,.2); color: #fca5a5;
                    text-decoration: line-through; }

/* Agency logo section (right end of card) */
.train-agency-section {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  flex-shrink: 0;
  background: rgba(0,0,0,.18);
}
.agency-logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 8px;
  background: rgba(255,255,255,.18);
  border: 1.5px solid rgba(255,255,255,.32);
  flex-shrink: 0;
  overflow: hidden;
}
.agency-logo svg { width: 42px; height: 42px; }
</style>

<script>
const REFRESH_SEC = <?= $refresh_sec ?>;
const API_BASE    = '<?= APP_URL ?>/roomlink/api/transit?json=1&limit=14';

let currentStation   = 'grand-central';
let countdownHandle  = null;

/* ── Inline SVG agency logos ── */
const AGENCY_SVG = {
  MNR: `<svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
    <rect width="42" height="42" rx="6" fill="#003087"/>
    <text x="21" y="16" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="900" font-size="9.5" letter-spacing=".5">METRO</text>
    <text x="21" y="27" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="900" font-size="9.5" letter-spacing=".5">NORTH</text>
    <rect x="6" y="30" width="30" height="3" rx="1.5" fill="#e31837"/>
  </svg>`,
  NJT: `<svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
    <rect width="42" height="42" rx="6" fill="#003087"/>
    <text x="21" y="17" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="900" font-size="13" letter-spacing="1">NJ</text>
    <rect x="6" y="21" width="30" height="2.5" rx="1.25" fill="#e31837"/>
    <text x="21" y="33" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="700" font-size="7.5" letter-spacing=".5">TRANSIT</text>
  </svg>`,
  AMT: `<svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
    <rect width="42" height="42" rx="6" fill="#1D6BAE"/>
    <!-- Amtrak-style arrow -->
    <polygon points="21,7 29,21 24,21 24,35 18,35 18,21 13,21" fill="white" opacity=".95"/>
    <text x="21" y="40" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="800" font-size="6.5" letter-spacing=".8">AMTRAK</text>
  </svg>`,
  LIRR: `<svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
    <rect width="42" height="42" rx="6" fill="#00305A"/>
    <text x="21" y="18" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="900" font-size="11" letter-spacing="1">LIRR</text>
    <rect x="6" y="21" width="30" height="2" rx="1" fill="#f7941d"/>
    <text x="21" y="33" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="600" font-size="6" letter-spacing=".3">LONG ISLAND</text>
  </svg>`,
};

function agencyLogo(agency) {
  return AGENCY_SVG[agency] || `<svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
    <rect width="42" height="42" rx="6" fill="rgba(255,255,255,.2)"/>
    <text x="21" y="26" text-anchor="middle" fill="white" font-family="Arial,sans-serif"
          font-weight="900" font-size="11">${escHtml(agency||'?')}</text>
  </svg>`;
}

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

/* ── Fetch departures ── */
async function fetchDepartures(station) {
  setLiveStatus('loading', null);
  try {
    const resp = await fetch(`${API_BASE}&station=${encodeURIComponent(station)}`);
    const data = await resp.json();
    if (!data.ok) throw new Error('API error');
    renderBoard(data.departures || []);
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
  document.getElementById('departure-board').innerHTML = `
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
  const h24 = d.time_24 ? parseInt(d.time_24.split(':')[0]) : 0;
  const ampm = h24 >= 12 ? 'PM' : 'AM';

  const statusClass = {
    ontime:    'status-ontime',
    delayed:   'status-delayed',
    boarding:  'status-boarding',
    cancelled: 'status-cancelled',
  }[d.status_type] || 'status-ontime';

  const track = d.track ? `<span class="train-track">Track ${escHtml(d.track)}</span>` : '';

  return `
<div class="train-card" style="background:${color};animation-delay:${idx*0.04}s">
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
    <div class="agency-logo">${agencyLogo(d.agency)}</div>
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
    document.getElementById('departure-board').innerHTML =
      `<div class="board-loading">
         <span class="material-symbols-outlined">departure_board</span>
         <p>Loading departures…</p>
       </div>`;
    document.getElementById('board-empty').style.display = 'none';
    fetchDepartures(currentStation);
  });
});

/* ── Init ── */
const firstTab = document.querySelector('.station-tab');
if (firstTab) {
  firstTab.classList.add('active');
  currentStation = firstTab.dataset.station;
}
fetchDepartures(currentStation);
</script>
<?php ui_end(); ?>
