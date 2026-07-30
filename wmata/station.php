<?php
/**
 * wmata/station.php – Individual station detail/edit page
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid station.');
    header('Location: ' . APP_URL . '/wmata/stations');
    exit;
}

/* ── Helpers ─────────────────────────────────────── */
$allowed_ext  = ['jpg','jpeg','png','gif','webp','obj','mtl','glb','gltf','fbx','blend','txt','json','yaml','yml','xml','zip','jar','mrpack'];
$allowed_mime = ['image/jpeg','image/png','image/gif','image/webp',
                 'application/octet-stream','model/gltf+json','model/gltf-binary',
                 'text/plain','application/json','application/xml','text/xml',
                 'application/zip','application/x-zip-compressed',
                 'application/java-archive','application/x-java-archive'];
$upload_dir   = __DIR__ . '/uploads/stations/';
$max_size     = 50 * 1024 * 1024;

/* ── Fetch station ──────────────────────────────── */
$station_stmt = db()->prepare('SELECT * FROM wmata_stations WHERE id=?');
$station_stmt->execute([$id]);
$station = $station_stmt->fetch();
if (!$station) {
    flash('danger', 'Station not found.');
    header('Location: ' . APP_URL . '/wmata/stations');
    exit;
}

/* ── POST handlers ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'update_station') {
        $status    = in_array($_POST['status'] ?? '', ['incomplete','in_progress','complete'])
                     ? $_POST['status'] : 'incomplete';
        $pb        = $_POST['platform_blocks'] !== '' ? (int)$_POST['platform_blocks'] : null;
        $maps_url  = trim($_POST['google_maps_url']  ?? '');
        $earth_url = trim($_POST['google_earth_url'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        db()->prepare(
            'UPDATE wmata_stations SET status=?, platform_blocks=?, google_maps_url=?, google_earth_url=?, notes=? WHERE id=?'
        )->execute([$status, $pb, $maps_url ?: null, $earth_url ?: null, $notes ?: null, $id]);
        flash('success', 'Station updated.');
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id); exit;
    }

    if ($pa === 'add_check') {
        $name = trim($_POST['check_name'] ?? '');
        if ($name !== '') {
            db()->prepare('INSERT INTO wmata_station_checks (station_id, check_name) VALUES (?,?)')
                ->execute([$id, $name]);
            flash('success', 'Check item added.');
        }
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id . '#checks'); exit;
    }

    if ($pa === 'toggle_check') {
        $cid  = (int)($_POST['check_id'] ?? 0);
        $chk  = (int)(bool)($_POST['is_checked'] ?? 0);
        $cnotes = trim($_POST['check_notes'] ?? '');
        db()->prepare('UPDATE wmata_station_checks SET is_checked=?, notes=? WHERE id=? AND station_id=?')
            ->execute([$chk, $cnotes ?: null, $cid, $id]);
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id . '#checks'); exit;
    }

    if ($pa === 'delete_check') {
        $cid = (int)($_POST['check_id'] ?? 0);
        db()->prepare('DELETE FROM wmata_station_checks WHERE id=? AND station_id=?')->execute([$cid, $id]);
        flash('success', 'Check item removed.');
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id . '#checks'); exit;
    }

    if ($pa === 'upload_file') {
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Upload error: ' . ($file['error'] ?? 'no file'));
        } else {
            $orig = basename($file['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                flash('danger', 'File type not allowed.');
            } elseif ($file['size'] > $max_size) {
                flash('danger', 'File too large (max 50 MB).');
            } else {
                $fname = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
                    $ftype = in_array($_POST['file_type'] ?? '', ['texture','model','diagram','screenshot','mod','other'])
                             ? $_POST['file_type'] : 'other';
                    $fnotes = trim($_POST['file_notes'] ?? '');
                    db()->prepare(
                        'INSERT INTO wmata_station_files (station_id, filename, original_name, mime_type, file_size, file_type, notes)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([$id, $fname, $orig, $file['type'], $file['size'], $ftype, $fnotes ?: null]);
                    flash('success', 'File uploaded.');
                } else {
                    flash('danger', 'Failed to move uploaded file.');
                }
            }
        }
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id . '#files'); exit;
    }

    if ($pa === 'delete_file') {
        $fid = (int)($_POST['file_id'] ?? 0);
        $frow = db()->prepare('SELECT filename FROM wmata_station_files WHERE id=? AND station_id=?');
        $frow->execute([$fid, $id]); $frow = $frow->fetch();
        if ($frow) {
            @unlink($upload_dir . $frow['filename']);
            db()->prepare('DELETE FROM wmata_station_files WHERE id=?')->execute([$fid]);
            flash('success', 'File deleted.');
        }
        header('Location: ' . APP_URL . '/wmata/station?id=' . $id . '#files'); exit;
    }
}

/* ── Fetch related data ─────────────────────────── */
$station_lines = db()->prepare(
    'SELECT l.* FROM wmata_station_lines sl JOIN wmata_lines l ON l.id=sl.line_id WHERE sl.station_id=? ORDER BY l.sort_order'
);
$station_lines->execute([$id]); $station_lines = $station_lines->fetchAll();

$checks = db()->prepare('SELECT * FROM wmata_station_checks WHERE station_id=? ORDER BY id');
$checks->execute([$id]); $checks = $checks->fetchAll();

$files = db()->prepare('SELECT * FROM wmata_station_files WHERE station_id=? ORDER BY uploaded_at DESC');
$files->execute([$id]); $files = $files->fetchAll();

$diagram = null;
foreach ($files as $f) {
    if ($f['file_type'] === 'diagram') { $diagram = $f; break; }
}

$checks_total = count($checks);
$checks_done  = count(array_filter($checks, fn($c) => $c['is_checked']));

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations', 'active' => true],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files'],
    ['icon' => 'extension',         'label' => 'Mods',          'href' => APP_URL . '/wmata/mods'],
];

$status_badge = match($station['status']) {
    'complete'    => ['success', 'Complete'],
    'in_progress' => ['warning', 'In Progress'],
    default       => ['danger',  'Incomplete'],
};

$actions = '<a href="' . APP_URL . '/wmata/stations" class="btn btn-sm">
  <span class="material-symbols-outlined">arrow_back</span> Back to Stations
</a>';

ui_head(htmlspecialchars($station['name']) . ' – WMATA', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header(htmlspecialchars($station['name']), 'WMATA Tracker › Stations › ' . htmlspecialchars($station['name']), $actions);
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Station header info ── -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <?= ui_badge($status_badge[1], $status_badge[0]) ?>
  <span class="badge badge-neutral" style="font-size:.75rem"><?= htmlspecialchars($station['abbreviation']) ?></span>
  <?php foreach ($station_lines as $l): ?>
  <?= wmata_line_badge($l['abbreviation'], $l['color'], 24) ?>
  <?php endforeach; ?>
  <?php
    // Generate auto links from lat/lng if stored URLs are empty
    $lat = isset($station['lat']) && is_numeric($station['lat']) ? (float)$station['lat'] : null;
    $lng = isset($station['lng']) && is_numeric($station['lng']) ? (float)$station['lng'] : null;
    $maps_link  = $station['google_maps_url']  ?: ($lat !== null && $lng !== null ? "https://www.google.com/maps?q={$lat},{$lng}" : '');
    $earth_link = $station['google_earth_url'] ?: ($lat !== null && $lng !== null ? "https://earth.google.com/web/@{$lat},{$lng},0a,500d" : '');
    $wmata_link = "https://www.wmata.com/rail/station/?station=" . rawurlencode($station['name']);
  ?>
  <?php if ($maps_link): ?>
  <a href="<?= htmlspecialchars($maps_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
    <span class="material-symbols-outlined">map</span> Google Maps
  </a>
  <?php endif; ?>
  <?php if ($earth_link): ?>
  <a href="<?= htmlspecialchars($earth_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
    <span class="material-symbols-outlined">public</span> Google Earth
  </a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars($wmata_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
    <span class="material-symbols-outlined">train</span> WMATA Site
  </a>
</div>

<div class="card-grid card-grid-2">

<!-- ── Google Maps embed ── -->
<?php
$embed_lat = isset($station['lat']) && is_numeric($station['lat']) ? (float)$station['lat'] : null;
$embed_lng = isset($station['lng']) && is_numeric($station['lng']) ? (float)$station['lng'] : null;
if ($embed_lat !== null && $embed_lng !== null):
    $embed_url = 'https://maps.google.com/maps?q=' . urlencode($station['name'] . ' Metro Station') . '&output=embed&ll=' . $embed_lat . ',' . $embed_lng . '&z=16';
?>
<div class="card card-full" style="border-left:3px solid #003DA5;--card-accent:#003DA5;--card-accent-rgb:0,61,165;--card-text-on-solid:#ffffff">
  <div class="card-top">
    <div class="card-tab" style="background:#003DA5;border-top-right-radius:100px;border-bottom-right-radius:100px">
      <span class="material-symbols-outlined" style="color:#fff">map</span>
      <h3 style="color:#fff">Map – <?= htmlspecialchars($station['name']) ?></h3>
    </div>
    <div class="card-header-actions">
      <?php if ($maps_link): ?>
      <a href="<?= htmlspecialchars($maps_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
        <span class="material-symbols-outlined">open_in_new</span> Open in Google Maps
      </a>
      <?php endif; ?>
      <?php if ($earth_link): ?>
      <a href="<?= htmlspecialchars($earth_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
        <span class="material-symbols-outlined">public</span> Google Earth
      </a>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($wmata_link) ?>" target="_blank" rel="noopener" class="btn btn-sm">
        <span class="material-symbols-outlined">train</span> WMATA
      </a>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <iframe
      src="<?= htmlspecialchars($embed_url) ?>"
      width="100%" height="400" style="border:0;display:block" allowfullscreen
      loading="lazy" referrerpolicy="no-referrer-when-downgrade"
      title="Map of <?= htmlspecialchars($station['name']) ?> Metro Station">
    </iframe>
  </div>
</div>
<?php endif; ?>

<!-- ── Edit form + Diagram ── -->
<div class="card-grid card-grid-2" style="display:contents">
<?php ui_card_open('edit', 'Station Details', '', '#003DA5'); ?>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update_station">
  <div class="form-group">
    <label>Status</label>
    <select name="status" class="form-control">
      <?php foreach (['incomplete'=>'Incomplete','in_progress'=>'In Progress','complete'=>'Complete'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $station['status']===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Platform Blocks</label>
    <input type="number" name="platform_blocks" class="form-control" min="0"
           value="<?= $station['platform_blocks'] !== null ? (int)$station['platform_blocks'] : '' ?>"
           placeholder="e.g. 40">
  </div>
  <div class="form-group">
    <label>Google Maps URL</label>
    <input type="url" name="google_maps_url" class="form-control"
           value="<?= htmlspecialchars($station['google_maps_url'] ?? '') ?>" placeholder="https://maps.google.com/…">
  </div>
  <div class="form-group">
    <label>Google Earth URL</label>
    <input type="url" name="google_earth_url" class="form-control"
           value="<?= htmlspecialchars($station['google_earth_url'] ?? '') ?>" placeholder="https://earth.google.com/…">
  </div>
  <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($station['notes'] ?? '') ?></textarea>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <span class="material-symbols-outlined">save</span> Save
    </button>
  </div>
</form>
<?php ui_card_close(); ?>

<!-- ── Diagram ── -->
<?php ui_card_open('image', 'Platform Diagram', '', '#6366f1'); ?>
<?php if ($diagram): ?>
  <?php
    $img_url = APP_URL . '/wmata/uploads/stations/' . rawurlencode($diagram['filename']);
    $is_img  = in_array(strtolower(pathinfo($diagram['original_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
  ?>
  <?php if ($is_img): ?>
  <img src="<?= htmlspecialchars($img_url) ?>" alt="Platform diagram"
       style="max-width:100%;border-radius:var(--radius);border:1px solid var(--border)">
  <?php else: ?>
  <div class="empty-state" style="padding:1rem">
    <span class="material-symbols-outlined">description</span>
    <p><?= htmlspecialchars($diagram['original_name']) ?></p>
    <a href="<?= htmlspecialchars($img_url) ?>" class="btn btn-sm" download="<?= htmlspecialchars($diagram['original_name']) ?>">Download</a>
  </div>
  <?php endif; ?>
  <?php if ($station['platform_blocks'] !== null): ?>
  <p style="margin-top:.5rem;font-size:.875rem;color:var(--text-muted)">
    <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle">view_column</span>
    Platform length: <strong><?= (int)$station['platform_blocks'] ?> blocks</strong>
  </p>
  <?php endif; ?>
<?php else: ?>
<div class="empty-state" style="padding:1.5rem">
  <span class="material-symbols-outlined">image_not_supported</span>
  <p>No diagram uploaded yet. Upload a file with type "Diagram" below.</p>
</div>
<?php endif; ?>
<?php ui_card_close(); ?>

</div><!-- inner card-grid-2 contents -->
</div><!-- outer card-grid wrapping map + details -->

<!-- ── Checklist ── -->
<div id="checks" style="margin-top:1.5rem">
<?php
$check_extra = '<span class="badge badge-info" style="margin-left:auto">' . $checks_done . '/' . $checks_total . '</span>';
ui_card_open('checklist', 'Checklist', $check_extra, '#10b981');
?>
<!-- Add check -->
<form method="POST" style="display:flex;gap:.5rem;margin-bottom:1rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add_check">
  <input type="text" name="check_name" class="form-control" placeholder="Add check item…" required style="flex:1">
  <button type="submit" class="btn btn-primary btn-sm">
    <span class="material-symbols-outlined">add</span> Add
  </button>
</form>

<?php if ($checks): ?>
<div style="display:flex;flex-direction:column;gap:.5rem">
  <?php foreach ($checks as $c): ?>
  <div style="padding:.6rem .75rem;background:var(--surface-raised);border-radius:var(--radius);
       border-left:3px solid <?= $c['is_checked'] ? 'var(--success)' : 'var(--border)' ?>">
    <form method="POST" style="display:flex;align-items:flex-start;gap:.6rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_check">
      <input type="hidden" name="check_id" value="<?= (int)$c['id'] ?>">
      <input type="checkbox" name="is_checked" value="1" <?= $c['is_checked']?'checked':'' ?>
             onchange="this.form.submit()" style="margin-top:.15rem;cursor:pointer">
      <div style="flex:1">
        <span style="font-weight:500;<?= $c['is_checked'] ? 'text-decoration:line-through;color:var(--text-muted)' : '' ?>">
          <?= htmlspecialchars($c['check_name']) ?>
        </span>
        <?php if ($c['notes']): ?>
        <div class="text-xs text-muted"><?= htmlspecialchars($c['notes']) ?></div>
        <?php endif; ?>
      </div>
    </form>
    <div style="display:flex;justify-content:flex-end;margin-top:.25rem">
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_check">
        <input type="hidden" name="check_id" value="<?= (int)$c['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                data-confirm="Remove this check item?">
          <span class="material-symbols-outlined">delete</span>
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state" style="padding:1rem">
  <span class="material-symbols-outlined">checklist</span>
  <p>No check items yet. Add one above.</p>
</div>
<?php endif; ?>
<?php ui_card_close(); ?>
</div>

<!-- ── Files ── -->
<div id="files" style="margin-top:1.5rem">
<?php ui_card_open('upload_file', 'Files', '<span class="badge badge-neutral" style="margin-left:auto">' . count($files) . ' file(s)</span>', '#f59e0b'); ?>

<!-- Upload form -->
<form method="POST" enctype="multipart/form-data" style="margin-bottom:1.5rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="upload_file">
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
      <label>File</label>
      <input type="file" name="file" class="form-control" required>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label>Type</label>
      <select name="file_type" class="form-control">
        <option value="texture">Texture</option>
        <option value="model">Model</option>
        <option value="diagram">Diagram</option>
        <option value="screenshot">Screenshot</option>
        <option value="mod">Mod</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:150px;margin-bottom:0">
      <label>Notes</label>
      <input type="text" name="file_notes" class="form-control" placeholder="Optional notes">
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
      <span class="material-symbols-outlined">upload</span> Upload
    </button>
  </div>
</form>

<?php if ($files): ?>
<div class="table-wrap">
<table>
  <thead>
    <tr><th>File</th><th>Type</th><th>Size</th><th>Notes</th><th>Uploaded</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($files as $f):
    $fsize = $f['file_size'] !== null
             ? ($f['file_size'] >= 1048576 ? round($f['file_size']/1048576,1).' MB' : round($f['file_size']/1024,1).' KB')
             : '—';
    $ftype_badge = match($f['file_type']) {
        'texture'    => 'info',
        'model'      => 'warning',
        'diagram'    => 'success',
        'screenshot' => 'neutral',
        'mod'        => 'info',
        default      => 'neutral',
    };
    $dl_url = APP_URL . '/wmata/uploads/stations/' . rawurlencode($f['filename']);
  ?>
  <tr>
    <td>
      <a href="<?= htmlspecialchars($dl_url) ?>" download="<?= htmlspecialchars($f['original_name']) ?>"
         style="font-weight:500">
        <?= htmlspecialchars($f['original_name']) ?>
      </a>
    </td>
    <td><?= ui_badge(ucfirst($f['file_type']), $ftype_badge) ?></td>
    <td class="text-sm text-muted"><?= htmlspecialchars($fsize) ?></td>
    <td class="text-sm"><?= htmlspecialchars($f['notes'] ?? '—') ?></td>
    <td class="text-sm text-muted"><?= date('M j, Y', strtotime($f['uploaded_at'])) ?></td>
    <td>
      <div style="display:flex;gap:.25rem">
        <a href="<?= htmlspecialchars($dl_url) ?>" class="btn btn-ghost btn-sm" download="<?= htmlspecialchars($f['original_name']) ?>" title="Download">
          <span class="material-symbols-outlined">download</span>
        </a>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_file">
          <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                  data-confirm="Delete this file?">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state" style="padding:1rem">
  <span class="material-symbols-outlined">folder_open</span>
  <p>No files uploaded yet.</p>
</div>
<?php endif; ?>
<?php ui_card_close(); ?>
</div>

</div>
<?php ui_end(); ?>
