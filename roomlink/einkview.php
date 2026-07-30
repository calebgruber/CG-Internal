<?php
/**
 * roomlink/einkview.php – E-Ink Display Preview (800×480, red/black/white)
 *
 * ?bare=1 → renders display content only, no sidebar/nav (for Pi/iframe use)
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

$eink      = rl_eink_state();
$bare      = isset($_GET['bare']);
$tab       = $eink['current_tab'] ?? 'transit';

/* ── Handle tab switch POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'set_tab') {
        rl_set_eink_tab($_POST['tab'] ?? 'transit');
        flash('success', 'Display tab updated.');
        $redirect = APP_URL . '/roomlink/einkview' . ($bare ? '?bare=1' : '');
        header('Location: ' . $redirect);
        exit;
    }
}

/* ── Fetch transit data for transit tab ── */
$departures = [];
if ($tab === 'transit') {
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $raw = @file_get_contents(APP_URL . '/roomlink/api/transit?json=1&limit=5', false, $ctx);
        if ($raw) {
            $api = json_decode($raw, true);
            $departures = $api['departures'] ?? [];
        }
    } catch (Throwable $e) {
        $departures = [];
    }
}

/* ───────────────────────────────────────────
   BARE MODE: just the display frame content
   ─────────────────────────────────────────── */
if ($bare): ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=800, initial-scale=1">
  <title>RoomLink E-Ink</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #ffffff; width: 800px; height: 480px; overflow: hidden;
           font-family: 'Courier New', monospace; }
  </style>
</head>
<body>
<?php echo render_eink_display($tab, $eink, $departures); ?>
</body>
</html>
<?php exit; endif;

/* ───────────────────────────────────────────
   FULL PAGE MODE: with sidebar
   ─────────────────────────────────────────── */

$nav_items = rl_nav_items('/roomlink/einkview');

ui_head('E-Ink Preview – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('E-Ink Display Preview', '7.3" 800×480 red/black/white display simulation');
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Tab switcher ── -->
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
  <form method="POST" style="display:contents">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="set_tab">
    <?php foreach (['transit','clock','weather','custom'] as $t): ?>
    <button type="submit" name="tab" value="<?= $t ?>"
            class="btn btn-sm"
            style="<?= $tab === $t ? 'background:#1e3a5f;color:#e2e8f0;border:1px solid #3b82f6;font-weight:700' : '' ?>">
      <span class="material-symbols-outlined" style="font-size:.875rem">
        <?= ['transit'=>'departure_board','clock'=>'schedule','weather'=>'wb_sunny','custom'=>'edit_note'][$t] ?>
      </span>
      <?= ucfirst($t) ?>
    </button>
    <?php endforeach; ?>
  </form>
  <a href="<?= APP_URL ?>/roomlink/einkview?bare=1" target="_blank" class="btn btn-sm" style="margin-left:auto">
    <span class="material-symbols-outlined">open_in_new</span> Bare View
  </a>
  <a href="<?= APP_URL ?>/roomlink/api/state" target="_blank" class="btn btn-sm">
    <span class="material-symbols-outlined">api</span> Pi API
  </a>
</div>

<!-- ── Display frame ── -->
<div style="overflow-x:auto;margin-bottom:1.5rem">
  <div style="display:inline-block;border:8px solid #1e293b;border-radius:4px;
              box-shadow:0 0 0 2px #0f172a, 0 8px 32px rgba(0,0,0,.5)">
    <!-- Screen bezel label -->
    <div style="background:#0f172a;padding:.3rem .75rem;display:flex;align-items:center;gap:.5rem">
      <span style="width:8px;height:8px;border-radius:50%;background:#334155;display:inline-block"></span>
      <span style="font-size:.65rem;color:#475569;font-family:monospace">7.3" E-INK · 800×480 · R/B/W</span>
      <span style="margin-left:auto;font-size:.65rem;color:#334155">
        Tab: <strong style="color:#64748b"><?= htmlspecialchars($tab) ?></strong>
      </span>
    </div>
    <!-- The actual simulated display -->
    <div id="eink-display" style="width:800px;height:480px;background:#ffffff;overflow:hidden;position:relative;
                                   font-family:'Courier New',monospace">
      <?php echo render_eink_display($tab, $eink, $departures); ?>
    </div>
  </div>
</div>

<!-- ── Status info ── -->
<div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8rem;color:var(--text-muted)">
  <span>Last updated: <strong><?= $eink['last_updated'] ? htmlspecialchars(date('M j, g:i:s a', strtotime($eink['last_updated']))) : 'Never' ?></strong></span>
  <span>Pi last poll: <strong><?= $eink['last_pi_poll'] ? htmlspecialchars(date('M j, g:i:s a', strtotime($eink['last_pi_poll']))) : 'Never' ?></strong></span>
  <span>Mode: <strong><?= htmlspecialchars($eink['display_mode']) ?></strong></span>
</div>

</div>
<?php ui_end(); ?>

<?php
/* ═══════════════════════════════════════════════════
   E-INK DISPLAY RENDERER
   Outputs HTML using ONLY black, white, and red.
   ═══════════════════════════════════════════════════ */
function render_eink_display(string $tab, array $eink, array $departures): string {
    ob_start();
    switch ($tab) {
        case 'transit':  eink_transit($departures); break;
        case 'clock':    eink_clock();              break;
        case 'weather':  eink_weather();            break;
        case 'custom':   eink_custom($eink['custom_text'] ?? ''); break;
        default:         eink_transit($departures);
    }
    return ob_get_clean();
}

function eink_transit(array $departures): void {
    $now = date('g:i A');
    ?>
    <div style="width:800px;height:480px;background:#fff;color:#000;position:relative;overflow:hidden">
      <!-- Header bar -->
      <div style="background:#cc0000;color:#fff;padding:8px 20px;display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:18px;font-weight:bold;letter-spacing:2px">DEPARTURE BOARD</div>
        <div style="font-size:16px;font-weight:bold"><?= htmlspecialchars($now) ?></div>
      </div>

      <!-- Column headers -->
      <div style="display:flex;background:#000;color:#fff;padding:6px 20px;font-size:12px;font-weight:bold;letter-spacing:1px">
        <div style="width:60px">TRACK</div>
        <div style="width:80px">TIME</div>
        <div style="flex:1">DESTINATION</div>
        <div style="width:60px">LINE</div>
        <div style="width:100px">STATUS</div>
      </div>

      <!-- Departure rows -->
      <?php
      $sample = $departures ?: eink_sample_departures();
      foreach (array_slice($sample, 0, 7) as $i => $d):
        $bg = $i % 2 === 0 ? '#ffffff' : '#f5f5f5';
        $statusColor = ($d['status_type'] ?? 'ontime') !== 'ontime' ? '#cc0000' : '#000000';
      ?>
      <div style="display:flex;align-items:center;padding:6px 20px;background:<?= $bg ?>;border-bottom:1px solid #e0e0e0;min-height:44px">
        <div style="width:60px;font-size:20px;font-weight:bold"><?= htmlspecialchars($d['track'] ?? '—') ?></div>
        <div style="width:80px;font-size:14px;font-weight:bold"><?= htmlspecialchars($d['time'] ?? '') ?></div>
        <div style="flex:1;font-size:14px;font-weight:bold"><?= htmlspecialchars($d['destination'] ?? '') ?></div>
        <div style="width:60px;font-size:11px;font-weight:bold"><?= htmlspecialchars($d['agency'] ?? '') ?></div>
        <div style="width:100px;font-size:12px;font-weight:bold;color:<?= $statusColor ?>"><?= htmlspecialchars($d['status'] ?? 'On Time') ?></div>
      </div>
      <?php endforeach; ?>

      <!-- Footer -->
      <div style="position:absolute;bottom:0;left:0;right:0;background:#000;color:#fff;
                  padding:5px 20px;font-size:10px;display:flex;justify-content:space-between">
        <span>RoomLink Transit Display</span>
        <span><?= htmlspecialchars(date('l, F j, Y')) ?></span>
      </div>
    </div>
    <?php
}

function eink_clock(): void {
    ?>
    <div style="width:800px;height:480px;background:#fff;color:#000;display:flex;flex-direction:column;
                align-items:center;justify-content:center;text-align:center">
      <!-- Red accent bar top -->
      <div style="position:absolute;top:0;left:0;right:0;height:8px;background:#cc0000"></div>

      <div style="font-size:96px;font-weight:bold;letter-spacing:-2px;line-height:1"><?= date('g:i') ?></div>
      <div style="font-size:36px;color:#cc0000;font-weight:bold;margin-top:.25rem"><?= date('A') ?></div>
      <div style="font-size:24px;margin-top:1rem;font-weight:bold"><?= date('l') ?></div>
      <div style="font-size:20px;margin-top:.5rem;color:#444"><?= date('F j, Y') ?></div>

      <!-- Bottom bar -->
      <div style="position:absolute;bottom:0;left:0;right:0;height:8px;background:#000"></div>
    </div>
    <?php
}

function eink_weather(): void {
    ?>
    <div style="width:800px;height:480px;background:#fff;color:#000;overflow:hidden">
      <div style="background:#cc0000;color:#fff;padding:10px 24px;display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:18px;font-weight:bold;letter-spacing:1px">WEATHER</div>
        <div style="font-size:14px"><?= date('D, M j') ?></div>
      </div>
      <div style="display:flex;height:calc(480px - 44px)">
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:2px solid #000;padding:20px">
          <div style="font-size:16px;font-weight:bold;margin-bottom:16px">NOW</div>
          <div style="font-size:80px;font-weight:bold;line-height:1">--°</div>
          <div style="font-size:18px;margin-top:12px;color:#444">No API key configured</div>
          <div style="font-size:14px;margin-top:8px;color:#666">Add weather API in settings</div>
        </div>
        <div style="width:240px;display:flex;flex-direction:column;justify-content:center;padding:20px;gap:16px">
          <?php foreach (['Mon','Tue','Wed','Thu','Fri'] as $day): ?>
          <div style="display:flex;justify-content:space-between;border-bottom:1px solid #ccc;padding-bottom:8px">
            <span style="font-weight:bold"><?= $day ?></span>
            <span>--° / --°</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
}

function eink_custom(string $text): void {
    ?>
    <div style="width:800px;height:480px;background:#fff;color:#000;overflow:hidden">
      <div style="background:#cc0000;color:#fff;padding:10px 24px">
        <div style="font-size:18px;font-weight:bold;letter-spacing:1px">CUSTOM MESSAGE</div>
      </div>
      <div style="padding:32px 40px;font-size:22px;line-height:1.6;word-break:break-word">
        <?= $text ? nl2br(htmlspecialchars($text)) : '<span style="color:#aaa;font-style:italic">No custom text set. Configure in settings.</span>' ?>
      </div>
      <div style="position:absolute;bottom:0;left:0;right:0;height:6px;background:#000"></div>
    </div>
    <?php
}

function eink_sample_departures(): array {
    return [
        ['track'=>'3',  'time'=>date('g:i', time()+7*60),  'destination'=>'New York Penn Station', 'agency'=>'NJT', 'status'=>'On Time',  'status_type'=>'ontime'],
        ['track'=>'1',  'time'=>date('g:i', time()+14*60), 'destination'=>'Secaucus Junction',     'agency'=>'NJT', 'status'=>'On Time',  'status_type'=>'ontime'],
        ['track'=>'7',  'time'=>date('g:i', time()+22*60), 'destination'=>'Grand Central Terminal','agency'=>'MNR', 'status'=>'On Time',  'status_type'=>'ontime'],
        ['track'=>'5',  'time'=>date('g:i', time()+31*60), 'destination'=>'New York Penn Station', 'agency'=>'AMT', 'status'=>'On Time',  'status_type'=>'ontime'],
        ['track'=>'2',  'time'=>date('g:i', time()+38*60), 'destination'=>'Trenton',               'agency'=>'NJT', 'status'=>'DELAYED', 'status_type'=>'delayed'],
    ];
}
?>
