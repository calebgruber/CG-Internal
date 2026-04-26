<?php
/**
 * roomlink/settings.php – RoomLink Settings
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('roomlink');

/* ── POST handlers ─────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ── Save general settings ── */
    if ($action === 'save_general') {
        $keys = ['transit_refresh_sec', 'eink_auto_refresh', 'default_origin', 'wled_global_ip'];
        foreach ($keys as $k) {
            set_setting('rl_' . $k, trim($_POST[$k] ?? ''));
        }
        // API keys only update if non-empty
        foreach (['mta_api_key', 'njt_api_key'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if ($v !== '') set_setting('rl_' . $k, $v);
        }
        flash('success', 'Settings saved.');
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }

    /* ── Add WLED controller ── */
    if ($action === 'add_controller') {
        $name = trim($_POST['ctrl_name'] ?? '');
        $ip   = trim($_POST['ctrl_ip'] ?? '');
        $port = (int)($_POST['ctrl_port'] ?? 80);
        $notes = trim($_POST['ctrl_notes'] ?? '');
        if ($name && $ip) {
            try {
                db()->prepare(
                    'INSERT INTO roomlink_wled_controllers (name, ip_address, port, notes, sort_order)
                     VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM roomlink_wled_controllers t))'
                )->execute([$name, $ip, $port, $notes]);
                flash('success', 'Controller added.');
            } catch (PDOException $e) {
                flash('danger', 'Failed to add controller: ' . $e->getMessage());
            }
        } else {
            flash('warning', 'Name and IP address are required.');
        }
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }

    /* ── Delete WLED controller ── */
    if ($action === 'delete_controller') {
        $id = (int)($_POST['ctrl_id'] ?? 0);
        if ($id > 0) {
            try {
                db()->prepare('DELETE FROM roomlink_wled_controllers WHERE id = ?')->execute([$id]);
                flash('success', 'Controller removed.');
            } catch (PDOException $e) {
                flash('danger', 'Failed to delete controller.');
            }
        }
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }

    /* ── Add transit destination ── */
    if ($action === 'add_destination') {
        $label   = trim($_POST['dest_label']   ?? '');
        $from    = trim($_POST['dest_from']    ?? '');
        $to      = trim($_POST['dest_to']      ?? '');
        $ag_id   = (int)($_POST['dest_agency'] ?? 0) ?: null;
        if ($label && $from && $to) {
            try {
                db()->prepare(
                    'INSERT INTO roomlink_transit_destinations (label, from_station, to_station, agency_id, sort_order)
                     VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM roomlink_transit_destinations t))'
                )->execute([$label, $from, $to, $ag_id]);
                flash('success', 'Destination added.');
            } catch (PDOException $e) {
                flash('danger', 'Failed to add destination: ' . $e->getMessage());
            }
        } else {
            flash('warning', 'Label, From, and To are required.');
        }
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }

    /* ── Delete transit destination ── */
    if ($action === 'delete_destination') {
        $id = (int)($_POST['dest_id'] ?? 0);
        if ($id > 0) {
            try {
                db()->prepare('DELETE FROM roomlink_transit_destinations WHERE id = ?')->execute([$id]);
                flash('success', 'Destination removed.');
            } catch (PDOException $e) {
                flash('danger', 'Failed to delete destination.');
            }
        }
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }

    /* ── Reset all settings ── */
    if ($action === 'reset_settings') {
        $rl_keys = [
            'rl_transit_refresh_sec', 'rl_eink_auto_refresh', 'rl_mta_api_key',
            'rl_njt_api_key', 'rl_default_origin', 'rl_wled_global_ip',
        ];
        $defaults = [
            'rl_transit_refresh_sec' => '30',
            'rl_eink_auto_refresh'   => '1',
            'rl_mta_api_key'         => '',
            'rl_njt_api_key'         => '',
            'rl_default_origin'      => '',
            'rl_wled_global_ip'      => '',
        ];
        foreach ($defaults as $k => $v) set_setting($k, $v);
        flash('warning', 'Settings reset to defaults.');
        header('Location: ' . APP_URL . '/roomlink/settings');
        exit;
    }
}

/* ── Load data ─────────────────────────────────── */
try {
    $controllers = db()->query(
        'SELECT * FROM roomlink_wled_controllers ORDER BY sort_order, name'
    )->fetchAll();
} catch (PDOException $e) {
    $controllers = [];
}

try {
    $destinations = db()->query(
        'SELECT d.*, a.name AS agency_name, a.short_name, a.color AS agency_color
         FROM roomlink_transit_destinations d
         LEFT JOIN roomlink_transit_agencies a ON a.id = d.agency_id
         ORDER BY d.sort_order, d.label'
    )->fetchAll();
} catch (PDOException $e) {
    $destinations = [];
}

try {
    $agencies = db()->query(
        'SELECT * FROM roomlink_transit_agencies WHERE is_active = 1 ORDER BY sort_order'
    )->fetchAll();
} catch (PDOException $e) {
    $agencies = [];
}

$nav_items = rl_nav_items('/roomlink/settings');

ui_head('Settings – RoomLink', 'roomlink', 'RoomLink', 'home_iot_device');
ui_sidebar('RoomLink', 'home_iot_device', $nav_items);
ui_page_header('Settings', 'Configure RoomLink — lights, transit, display');
?>
<div class="page-body">
<?php ui_flash(); ?>

<div class="card-grid card-grid-2">

  <!-- ── WLED Controllers ── -->
  <?php ui_card_open('lightbulb', 'WLED Controllers', '', '#f59e0b'); ?>
  <?php if ($controllers): ?>
  <table style="width:100%;border-collapse:collapse;margin-bottom:1rem">
    <thead>
      <tr style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)">
        <th style="text-align:left;padding:.5rem .25rem">Name</th>
        <th style="text-align:left;padding:.5rem .25rem">IP Address</th>
        <th style="text-align:left;padding:.5rem .25rem">Port</th>
        <th style="text-align:left;padding:.5rem .25rem">Notes</th>
        <th style="padding:.5rem .25rem"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($controllers as $ctrl): ?>
    <tr style="border-bottom:1px solid var(--border)">
      <td style="padding:.6rem .25rem;font-weight:600"><?= htmlspecialchars($ctrl['name']) ?></td>
      <td style="padding:.6rem .25rem;font-family:monospace;font-size:.85rem"><?= htmlspecialchars($ctrl['ip_address']) ?></td>
      <td style="padding:.6rem .25rem;font-size:.85rem"><?= (int)$ctrl['port'] ?></td>
      <td style="padding:.6rem .25rem;color:var(--text-muted);font-size:.8rem"><?= htmlspecialchars($ctrl['notes'] ?? '') ?></td>
      <td style="padding:.6rem .25rem;text-align:right">
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this controller?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_controller">
          <input type="hidden" name="ctrl_id" value="<?= (int)$ctrl['id'] ?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)">
            Delete
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Add controller form -->
  <form method="POST" style="display:flex;flex-direction:column;gap:.75rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_controller">
    <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);margin-bottom:.25rem">Add Controller</div>
    <div class="form-row">
      <div class="form-group">
        <label>Name</label>
        <input type="text" name="ctrl_name" class="form-control" placeholder="Room Light" required>
      </div>
      <div class="form-group">
        <label>IP Address</label>
        <input type="text" name="ctrl_ip" class="form-control" placeholder="192.168.1.100" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Port</label>
        <input type="number" name="ctrl_port" class="form-control" value="80" min="1" max="65535">
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <input type="text" name="ctrl_notes" class="form-control" placeholder="Desk lamp">
      </div>
    </div>
    <div>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined">add</span> Add Controller
      </button>
    </div>
  </form>
  <?php ui_card_close(); ?>

  <!-- ── Transit API Keys ── -->
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_general">
    <?php ui_card_open('vpn_key', 'Transit API Keys', '', '#8b5cf6'); ?>
    <div class="form-group">
      <label>MTA API Key</label>
      <input type="password" name="mta_api_key" class="form-control"
             placeholder="Leave blank to keep current"
             autocomplete="new-password">
      <div class="form-hint">Used for MTA Metro-North GTFS-RT feed. Leave blank to use demo data.</div>
    </div>
    <div class="form-group">
      <label>NJ Transit API Key</label>
      <input type="password" name="njt_api_key" class="form-control"
             placeholder="Leave blank to keep current"
             autocomplete="new-password">
      <div class="form-hint">NJ Transit developer API key. Leave blank to use demo data.</div>
    </div>
    <div class="form-group">
      <label>Default Origin Station</label>
      <input type="text" name="default_origin" class="form-control"
             value="<?= htmlspecialchars(rl_setting('default_origin')) ?>"
             placeholder="e.g. Newark Penn Station">
    </div>
    <div>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined">save</span> Save API Keys
      </button>
    </div>
    <?php ui_card_close(); ?>
  </form>

  <!-- ── Transit Destinations ── -->
  <?php ui_card_open('departure_board', 'Transit Destinations', '', '#0E71B3'); ?>
  <?php if ($destinations): ?>
  <table style="width:100%;border-collapse:collapse;margin-bottom:1rem">
    <thead>
      <tr style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)">
        <th style="text-align:left;padding:.5rem .25rem">Label</th>
        <th style="text-align:left;padding:.5rem .25rem">From</th>
        <th style="text-align:left;padding:.5rem .25rem">To</th>
        <th style="text-align:left;padding:.5rem .25rem">Agency</th>
        <th style="padding:.5rem .25rem"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($destinations as $dest): ?>
    <tr style="border-bottom:1px solid var(--border)">
      <td style="padding:.6rem .25rem;font-weight:600"><?= htmlspecialchars($dest['label']) ?></td>
      <td style="padding:.6rem .25rem;font-size:.85rem"><?= htmlspecialchars($dest['from_station']) ?></td>
      <td style="padding:.6rem .25rem;font-size:.85rem"><?= htmlspecialchars($dest['to_station']) ?></td>
      <td style="padding:.6rem .25rem">
        <?php if ($dest['agency_name']): ?>
        <span style="font-size:.75rem;padding:.15rem .5rem;border-radius:3px;
                     background:<?= htmlspecialchars($dest['agency_color']) ?>22;
                     color:<?= htmlspecialchars($dest['agency_color']) ?>;font-weight:700">
          <?= htmlspecialchars($dest['short_name']) ?>
        </span>
        <?php endif; ?>
      </td>
      <td style="padding:.6rem .25rem;text-align:right">
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this destination?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_destination">
          <input type="hidden" name="dest_id" value="<?= (int)$dest['id'] ?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)">
            Delete
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Add destination form -->
  <form method="POST" style="display:flex;flex-direction:column;gap:.75rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_destination">
    <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);margin-bottom:.25rem">Add Destination</div>
    <div class="form-group">
      <label>Label / Display Name</label>
      <input type="text" name="dest_label" class="form-control" placeholder="NYC Penn" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>From Station</label>
        <input type="text" name="dest_from" class="form-control" placeholder="Newark Penn Station" required>
      </div>
      <div class="form-group">
        <label>To Station</label>
        <input type="text" name="dest_to" class="form-control" placeholder="New York Penn Station" required>
      </div>
    </div>
    <div class="form-group">
      <label>Agency</label>
      <select name="dest_agency" class="form-control">
        <option value="">— Select agency —</option>
        <?php foreach ($agencies as $ag): ?>
        <option value="<?= (int)$ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined">add</span> Add Destination
      </button>
    </div>
  </form>
  <?php ui_card_close(); ?>

  <!-- ── Display Settings ── -->
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_general">
    <?php ui_card_open('settings', 'Display Settings', '', '#6366f1'); ?>
    <div class="form-group">
      <label>Transit Refresh Interval (seconds)</label>
      <input type="number" name="transit_refresh_sec" class="form-control"
             value="<?= htmlspecialchars(rl_setting('transit_refresh_sec', '30')) ?>"
             min="10" max="300">
      <div class="form-hint">How often the transit board auto-refreshes. Min 10s.</div>
    </div>
    <div class="form-group">
      <label>E-Ink Auto Refresh</label>
      <select name="eink_auto_refresh" class="form-control">
        <option value="1" <?= rl_setting('eink_auto_refresh', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
        <option value="0" <?= rl_setting('eink_auto_refresh', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
      </select>
      <div class="form-hint">Automatically refresh e-ink display on tab change.</div>
    </div>
    <div class="form-group">
      <label>Global WLED IP (optional)</label>
      <input type="text" name="wled_global_ip" class="form-control"
             value="<?= htmlspecialchars(rl_setting('wled_global_ip')) ?>"
             placeholder="192.168.1.50">
      <div class="form-hint">If all controllers share a broadcast IP, set it here.</div>
    </div>
    <div>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined">save</span> Save Display Settings
      </button>
    </div>
    <?php ui_card_close(); ?>
  </form>

  <!-- ── Danger Zone ── -->
  <?php ui_card_open('warning', 'Danger Zone', '', '#ef4444'); ?>
  <p style="color:var(--text-muted);font-size:.875rem;margin-bottom:1rem">
    These actions cannot be undone. Use with caution.
  </p>
  <form method="POST" onsubmit="return confirm('Reset all RoomLink settings to defaults? This cannot be undone.')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_settings">
    <button type="submit" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)">
      <span class="material-symbols-outlined">restart_alt</span>
      Reset All Settings to Defaults
    </button>
  </form>
  <?php ui_card_close(); ?>

</div>
</div>
<?php ui_end(); ?>
