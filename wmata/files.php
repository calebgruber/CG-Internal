<?php
/**
 * wmata/files.php – Global file storage
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');

$upload_dir  = __DIR__ . '/uploads/global/';
$allowed_ext = ['jpg','jpeg','png','gif','webp','obj','mtl','glb','gltf','fbx','blend','txt','json','yaml','yml','xml','zip','jar','mrpack'];
$max_size    = 50 * 1024 * 1024;

/* ── POST handlers ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'upload') {
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
                    $ftype = in_array($_POST['file_type'] ?? '', ['texture','model','diagram','screenshot','reference','mod','other'])
                             ? $_POST['file_type'] : 'other';
                    $category = trim($_POST['category'] ?? '');
                    $notes    = trim($_POST['notes']    ?? '');
                    db()->prepare(
                        'INSERT INTO wmata_global_files (filename, original_name, mime_type, file_size, file_type, category, notes)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([$fname, $orig, $file['type'], $file['size'], $ftype, $category ?: null, $notes ?: null]);
                    flash('success', "File '{$orig}' uploaded.");
                } else {
                    flash('danger', 'Failed to move uploaded file.');
                }
            }
        }
        header('Location: ' . APP_URL . '/wmata/files'); exit;
    }

    if ($pa === 'delete') {
        $fid = (int)($_POST['file_id'] ?? 0);
        $frow = db()->prepare('SELECT filename FROM wmata_global_files WHERE id=?');
        $frow->execute([$fid]); $frow = $frow->fetch();
        if ($frow) {
            @unlink($upload_dir . $frow['filename']);
            db()->prepare('DELETE FROM wmata_global_files WHERE id=?')->execute([$fid]);
            flash('success', 'File deleted.');
        }
        header('Location: ' . APP_URL . '/wmata/files'); exit;
    }
}

/* ── Active tab (file_type filter) ─────────────── */
$valid_tabs = ['all','texture','model','diagram','screenshot','reference','mod','other'];
$active_tab = in_array($_GET['tab'] ?? '', $valid_tabs) ? $_GET['tab'] : 'all';

/* ── Fetch counts per type ──────────────────────── */
$type_counts = db()->query(
    'SELECT file_type, COUNT(*) as cnt FROM wmata_global_files GROUP BY file_type'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$total_files = array_sum($type_counts);

/* ── Fetch files ────────────────────────────────── */
$where  = '1=1';
$params = [];
if ($active_tab !== 'all') {
    $where    = 'file_type=?';
    $params[] = $active_tab;
}
$files_stmt = db()->prepare("SELECT * FROM wmata_global_files WHERE {$where} ORDER BY uploaded_at DESC");
$files_stmt->execute($params);
$files = $files_stmt->fetchAll();

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations'],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files', 'active' => true],
    ['icon' => 'extension',         'label' => 'Mods',          'href' => APP_URL . '/wmata/mods'],
];

ui_head('Files – WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Global Files', 'WMATA Tracker › Files');
?>
<div class="page-body">
<?php ui_flash(); ?>

<!-- ── Stat badges ── -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
  <span class="badge badge-neutral">All (<?= $total_files ?>)</span>
  <?php
  $type_labels = ['texture'=>'Textures','model'=>'Models','diagram'=>'Diagrams',
                  'screenshot'=>'Screenshots','reference'=>'Reference','mod'=>'Mods','other'=>'Other'];
  foreach ($type_labels as $type => $label):
    $cnt = (int)($type_counts[$type] ?? 0);
    if ($cnt === 0) continue;
  ?>
  <a href="?tab=<?= $type ?>" class="badge <?= $active_tab===$type?'badge-info':'badge-neutral' ?>"
     style="text-decoration:none;cursor:pointer">
    <?= htmlspecialchars($label) ?> (<?= $cnt ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="card-grid card-grid-2">

<!-- ── Upload form ── -->
<?php ui_card_open('upload_file', 'Upload File', '', '#f59e0b'); ?>
<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="upload">
  <div class="form-group">
    <label>File *</label>
    <input type="file" name="file" class="form-control" required>
    <small class="text-muted">Max 50 MB. Allowed: images, 3D files (obj, glb, fbx), text/config, zip, mod files (jar, mrpack).</small>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:140px">
      <label>File Type</label>
      <select name="file_type" class="form-control">
        <option value="texture">Texture</option>
        <option value="model">Model</option>
        <option value="diagram">Diagram</option>
        <option value="screenshot">Screenshot</option>
        <option value="reference">Reference</option>
        <option value="mod">Mod</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:140px">
      <label>Category</label>
      <input type="text" name="category" class="form-control" placeholder="e.g. Red Line, Interior">
    </div>
  </div>
  <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="2" placeholder="Optional description…"></textarea>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <span class="material-symbols-outlined">upload</span> Upload
    </button>
  </div>
</form>
<?php ui_card_close(); ?>

<!-- ── File type info ── -->
<?php ui_card_open('info', 'File Types', '', '#6366f1'); ?>
<div style="font-size:.875rem;display:flex;flex-direction:column;gap:.4rem">
  <?php
  $type_descs = [
    'texture'    => 'PNG/JPG images used as Minecraft textures',
    'model'      => '3D model files (OBJ, GLB, FBX, Blend)',
    'diagram'    => 'Blueprint/diagram images for reference',
    'screenshot' => 'In-game or real-world screenshots',
    'reference'  => 'Reference documents, PDFs, photos',
    'mod'        => 'Minecraft mod files (.jar, .mrpack)',
    'other'      => 'Other miscellaneous files',
  ];
  foreach ($type_descs as $t => $d): ?>
  <div style="padding:.4rem .6rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong><?= htmlspecialchars(ucfirst($t)) ?>:</strong>
    <span class="text-muted"> <?= htmlspecialchars($d) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php ui_card_close(); ?>

</div>

<!-- ── File listing ── -->
<!-- Tab bar -->
<div class="tabs" style="margin:1.25rem 0 1rem">
  <?php
  $all_tabs = ['all' => 'All'] + array_map('ucfirst', array_combine(
      array_keys($type_labels), array_keys($type_labels)
  ));
  $tab_labels = ['all' => 'All', 'texture' => 'Textures', 'model' => 'Models',
                 'diagram' => 'Diagrams', 'screenshot' => 'Screenshots',
                 'reference' => 'Reference', 'mod' => 'Mods', 'other' => 'Other'];
  foreach ($tab_labels as $t => $l):
    $cnt = $t === 'all' ? $total_files : (int)($type_counts[$t] ?? 0);
  ?>
  <a href="?tab=<?= $t ?>" class="tab <?= $active_tab===$t?'active':'' ?>">
    <?= htmlspecialchars($l) ?>
    <?php if ($cnt > 0): ?><span class="badge badge-neutral" style="font-size:.7rem"><?= $cnt ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php ui_card_open('folder', ($tab_labels[$active_tab] ?? 'Files') . ' (' . count($files) . ')', '', '#f59e0b'); ?>

<?php if ($files): ?>
<div class="table-wrap">
<table>
  <thead>
    <tr><th>File</th><th>Type</th><th>Category</th><th>Size</th><th>Notes</th><th>Uploaded</th><th>Actions</th></tr>
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
        'reference'  => 'neutral',
        'mod'        => 'info',
        default      => 'neutral',
    };
    $dl_url = APP_URL . '/wmata/uploads/global/' . rawurlencode($f['filename']);
    $is_img = in_array(strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
  ?>
  <tr>
    <td>
      <?php if ($is_img): ?>
      <a href="<?= htmlspecialchars($dl_url) ?>" target="_blank" rel="noopener" style="font-weight:500">
        <?= htmlspecialchars($f['original_name']) ?>
      </a>
      <?php else: ?>
      <a href="<?= htmlspecialchars($dl_url) ?>" download="<?= htmlspecialchars($f['original_name']) ?>" style="font-weight:500">
        <?= htmlspecialchars($f['original_name']) ?>
      </a>
      <?php endif; ?>
      <?php if ($f['category']): ?>
      <div class="text-xs text-muted"><?= htmlspecialchars($f['category']) ?></div>
      <?php endif; ?>
    </td>
    <td><?= ui_badge(ucfirst($f['file_type']), $ftype_badge) ?></td>
    <td class="text-sm text-muted"><?= htmlspecialchars($f['category'] ?? '—') ?></td>
    <td class="text-sm text-muted"><?= htmlspecialchars($fsize) ?></td>
    <td class="text-sm"><?= htmlspecialchars($f['notes'] ?? '—') ?></td>
    <td class="text-sm text-muted"><?= date('M j, Y', strtotime($f['uploaded_at'])) ?></td>
    <td>
      <div style="display:flex;gap:.25rem">
        <a href="<?= htmlspecialchars($dl_url) ?>" class="btn btn-ghost btn-sm"
           download="<?= htmlspecialchars($f['original_name']) ?>" title="Download">
          <span class="material-symbols-outlined">download</span>
        </a>
        <?php if ($is_img): ?>
        <a href="<?= htmlspecialchars($dl_url) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Preview">
          <span class="material-symbols-outlined">open_in_new</span>
        </a>
        <?php endif; ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                  data-confirm="Delete '<?= htmlspecialchars(addslashes($f['original_name'])) ?>'?">
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
<div class="empty-state">
  <span class="material-symbols-outlined">folder_open</span>
  <h3>No files <?= $active_tab !== 'all' ? 'in this category' : 'uploaded yet' ?></h3>
  <p>Upload files using the form above.</p>
</div>
<?php endif; ?>
<?php ui_card_close(); ?>

</div>
<?php ui_end(); ?>
