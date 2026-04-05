<?php
/**
 * wmata/mods.php – Minecraft mod tracker with Modrinth integration
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';
require_once __DIR__ . '/inc/helpers.php';

$user = require_auth('wmata');

/* ── Fetch icon from Modrinth API ────────────────── */
function modrinth_fetch_project(string $slug): ?array {
    $url = 'https://api.modrinth.com/v2/project/' . rawurlencode($slug);
    $ctx = stream_context_create(['http' => [
        'timeout'       => 5,
        'ignore_errors' => true,
        'header'        => "User-Agent: CG-Internal/1.0 (internal.calebgruber.me)\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/* ── POST handlers ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'add_mod') {
        $slug        = trim($_POST['modrinth_slug'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $mod_version = trim($_POST['mod_version'] ?? '');
        $mc_version  = trim($_POST['mc_version'] ?? '');
        $dl_url      = trim($_POST['download_url'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');

        if ($slug === '' || $name === '' || $mod_version === '' || $mc_version === '' || $dl_url === '') {
            flash('danger', 'All required fields must be filled.');
        } else {
            // Attempt to fetch icon from Modrinth
            $icon_url    = null;
            $description = null;
            $project     = modrinth_fetch_project($slug);
            if ($project) {
                $icon_url    = $project['icon_url']    ?? null;
                $description = $project['description'] ?? null;
                // Use the title from Modrinth if name was left as the slug
                if (isset($project['title']) && $name === $slug) {
                    $name = $project['title'];
                }
            }

            try {
                $max_sort = db()->query('SELECT COALESCE(MAX(sort_order),0) FROM wmata_mods')->fetchColumn();
                db()->prepare(
                    'INSERT INTO wmata_mods (modrinth_slug, name, mod_version, mc_version, download_url, icon_url, description, notes, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$slug, $name, $mod_version, $mc_version, $dl_url, $icon_url, $description ?: null, $notes ?: null, (int)$max_sort + 1]);
                flash('success', "Mod \"{$name}\" added.");
            } catch (PDOException $e) {
                flash('danger', 'Could not add mod: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/wmata/mods'); exit;
    }

    if ($pa === 'refresh_icon') {
        $mid  = (int)($_POST['mod_id'] ?? 0);
        $row  = db()->prepare('SELECT modrinth_slug FROM wmata_mods WHERE id=?');
        $row->execute([$mid]); $row = $row->fetch();
        if ($row) {
            $project = modrinth_fetch_project($row['modrinth_slug']);
            if ($project) {
                $icon_url    = $project['icon_url']    ?? null;
                $description = $project['description'] ?? null;
                db()->prepare(
                    'UPDATE wmata_mods SET icon_url=?, description=? WHERE id=?'
                )->execute([$icon_url, $description, $mid]);
                flash('success', 'Icon refreshed from Modrinth.');
            } else {
                flash('warning', 'Could not reach Modrinth API.');
            }
        }
        header('Location: ' . APP_URL . '/wmata/mods'); exit;
    }

    if ($pa === 'delete_mod') {
        $mid = (int)($_POST['mod_id'] ?? 0);
        $row = db()->prepare('SELECT name FROM wmata_mods WHERE id=?');
        $row->execute([$mid]); $row = $row->fetch();
        if ($row) {
            db()->prepare('DELETE FROM wmata_mods WHERE id=?')->execute([$mid]);
            flash('success', "Mod \"{$row['name']}\" deleted.");
        }
        header('Location: ' . APP_URL . '/wmata/mods'); exit;
    }

    if ($pa === 'update_mod') {
        $mid         = (int)($_POST['mod_id'] ?? 0);
        $mod_version = trim($_POST['mod_version'] ?? '');
        $mc_version  = trim($_POST['mc_version']  ?? '');
        $dl_url      = trim($_POST['download_url'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        db()->prepare(
            'UPDATE wmata_mods SET mod_version=?, mc_version=?, download_url=?, notes=? WHERE id=?'
        )->execute([$mod_version, $mc_version, $dl_url, $notes ?: null, $mid]);
        flash('success', 'Mod updated.');
        header('Location: ' . APP_URL . '/wmata/mods'); exit;
    }
}

/* ── Fetch mods ─────────────────────────────────── */
try {
    $mods = db()->query('SELECT * FROM wmata_mods ORDER BY sort_order, name')->fetchAll();
} catch (PDOException $e) {
    $mods = [];
    flash('warning', 'Mods table not found. Run migrations first.');
}

$nav_items = [
    ['icon' => 'dashboard',         'label' => 'Dashboard',     'href' => APP_URL . '/wmata/'],
    ['icon' => 'train',             'label' => 'Stations',      'href' => APP_URL . '/wmata/stations'],
    ['icon' => 'directions_transit','label' => 'Rolling Stock', 'href' => APP_URL . '/wmata/rolling-stock'],
    ['icon' => 'calculate',         'label' => 'Calculator',    'href' => APP_URL . '/wmata/calculator'],
    ['icon' => 'folder',            'label' => 'Files',         'href' => APP_URL . '/wmata/files'],
    ['icon' => 'extension',         'label' => 'Mods',          'href' => APP_URL . '/wmata/mods', 'active' => true],
];

ui_head('Mods – WMATA Tracker', 'wmata', 'WMATA Tracker', 'train');
ui_sidebar('WMATA Tracker', 'train', $nav_items);
ui_page_header('Minecraft Mods', 'WMATA Tracker › Mods');
?>
<div class="page-body">
<?php ui_flash(); ?>

<div class="card-grid card-grid-2">

<!-- ── Add Mod form ── -->
<?php ui_card_open('add_circle', 'Add Mod', '', '#6366f1'); ?>
<p style="margin-bottom:1rem;font-size:.875rem;color:var(--text-muted)">
  Enter the Modrinth project slug (from the URL, e.g. <code>sodium</code>) and the icon will be fetched automatically.
</p>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add_mod">
  <div class="form-row">
    <div class="form-group">
      <label>Modrinth Slug / ID <span style="color:var(--danger)">*</span></label>
      <input type="text" name="modrinth_slug" class="form-control" placeholder="e.g. sodium" required>
      <div class="form-hint">Found in the Modrinth project URL</div>
    </div>
    <div class="form-group">
      <label>Mod Name <span style="color:var(--danger)">*</span></label>
      <input type="text" name="name" class="form-control" placeholder="e.g. Sodium" required>
      <div class="form-hint">Overridden automatically from Modrinth if slug matches</div>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Mod Version <span style="color:var(--danger)">*</span></label>
      <input type="text" name="mod_version" class="form-control" placeholder="e.g. 0.5.8" required>
    </div>
    <div class="form-group">
      <label>Minecraft Version <span style="color:var(--danger)">*</span></label>
      <input type="text" name="mc_version" class="form-control" placeholder="e.g. 1.21.4" required>
    </div>
  </div>
  <div class="form-group">
    <label>Modrinth Download URL <span style="color:var(--danger)">*</span></label>
    <input type="url" name="download_url" class="form-control" placeholder="https://modrinth.com/mod/…" required>
  </div>
  <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <span class="material-symbols-outlined">add</span> Add Mod
    </button>
  </div>
</form>
<?php ui_card_close(); ?>

<!-- ── Modrinth info card ── -->
<?php ui_card_open('info', 'About Modrinth', '', '#3b82f6'); ?>
<div style="font-size:.875rem;display:flex;flex-direction:column;gap:.75rem">
  <p>Icons and descriptions are fetched live from the <a href="https://modrinth.com" target="_blank" rel="noopener">Modrinth API</a> when a mod is added. Use <strong>Refresh Icon</strong> to re-fetch if the icon is missing.</p>
  <div style="padding:.6rem .75rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>Finding the slug:</strong>
    <span class="text-muted"> The slug is the last part of the Modrinth project URL —
    e.g. for <code>modrinth.com/mod/sodium</code> the slug is <code>sodium</code>.</span>
  </div>
  <div style="padding:.6rem .75rem;background:var(--surface-raised);border-radius:var(--radius)">
    <strong>Download URLs:</strong>
    <span class="text-muted"> Paste the direct Modrinth version download link so others can grab the exact file.</span>
  </div>
</div>
<?php ui_card_close(); ?>

</div><!-- .card-grid-2 -->

<!-- ── Mod list ── -->
<div style="margin-top:1.5rem">
<?php ui_card_open('extension', 'Installed Mods (' . count($mods) . ')', '', '#6366f1'); ?>

<?php if ($mods): ?>
<div class="mod-list" style="display:flex;flex-direction:column;gap:.75rem">
  <?php foreach ($mods as $mod): ?>
  <div class="mod-card" style="display:flex;align-items:flex-start;gap:1rem;padding:.875rem 1rem;
       background:var(--surface-raised);border-radius:var(--radius);border:1px solid var(--border)">

    <!-- Icon -->
    <div style="flex-shrink:0;width:3rem;height:3rem;border-radius:6px;overflow:hidden;
         background:var(--bg);display:flex;align-items:center;justify-content:center;border:1px solid var(--border)">
      <?php if ($mod['icon_url']): ?>
      <img src="<?= htmlspecialchars($mod['icon_url']) ?>" alt="<?= htmlspecialchars($mod['name']) ?>"
           style="width:100%;height:100%;object-fit:cover" loading="lazy"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <span class="material-symbols-outlined" style="display:none;font-size:1.5rem;color:var(--text-muted)">extension</span>
      <?php else: ?>
      <span class="material-symbols-outlined" style="font-size:1.5rem;color:var(--text-muted)">extension</span>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.25rem">
        <span style="font-weight:700;font-size:.9375rem"><?= htmlspecialchars($mod['name']) ?></span>
        <span class="badge badge-info"><?= htmlspecialchars($mod['mod_version']) ?></span>
        <span class="badge badge-neutral">MC <?= htmlspecialchars($mod['mc_version']) ?></span>
        <a href="<?= htmlspecialchars($mod['download_url']) ?>" target="_blank" rel="noopener"
           class="badge badge-success" style="text-decoration:none">
          <span class="material-symbols-outlined" style="font-size:.75rem">download</span>
          Download
        </a>
        <a href="https://modrinth.com/mod/<?= rawurlencode($mod['modrinth_slug']) ?>" target="_blank" rel="noopener"
           class="badge badge-neutral" style="text-decoration:none">
          <span class="material-symbols-outlined" style="font-size:.75rem">open_in_new</span>
          Modrinth
        </a>
      </div>
      <?php if ($mod['description']): ?>
      <p style="font-size:.8125rem;color:var(--text-muted);margin:.25rem 0;line-height:1.5;max-width:60ch">
        <?= htmlspecialchars(mb_strimwidth($mod['description'], 0, 200, '…')) ?>
      </p>
      <?php endif; ?>
      <?php if ($mod['notes']): ?>
      <p style="font-size:.8125rem;color:var(--text-muted);font-style:italic"><?= htmlspecialchars($mod['notes']) ?></p>
      <?php endif; ?>
      <div class="text-xs text-muted" style="margin-top:.375rem">slug: <?= htmlspecialchars($mod['modrinth_slug']) ?></div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:.25rem;flex-shrink:0;flex-wrap:wrap">
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="refresh_icon">
        <input type="hidden" name="mod_id" value="<?= (int)$mod['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" title="Refresh icon from Modrinth">
          <span class="material-symbols-outlined">refresh</span>
        </button>
      </form>
      <button type="button" class="btn btn-ghost btn-sm" title="Edit"
              onclick="openModEdit(<?= (int)$mod['id'] ?>, <?= htmlspecialchars(json_encode($mod['mod_version'])) ?>, <?= htmlspecialchars(json_encode($mod['mc_version'])) ?>, <?= htmlspecialchars(json_encode($mod['download_url'])) ?>, <?= htmlspecialchars(json_encode($mod['notes'] ?? '')) ?>)">
        <span class="material-symbols-outlined">edit</span>
      </button>
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_mod">
        <input type="hidden" name="mod_id" value="<?= (int)$mod['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"
                data-confirm="Delete mod &quot;<?= htmlspecialchars(addslashes($mod['name'])) ?>&quot;?">
          <span class="material-symbols-outlined">delete</span>
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <span class="material-symbols-outlined">extension</span>
  <h3>No mods yet</h3>
  <p>Add your first mod using the form above.</p>
</div>
<?php endif; ?>

<?php ui_card_close(); ?>
</div>

<!-- ── Edit Modal (inline hidden form) ── -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:8px;padding:1.5rem;width:min(480px,95vw);
       box-shadow:var(--shadow-md);max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:1rem;font-weight:700">Edit Mod</h3>
    <form method="POST" id="edit-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_mod">
      <input type="hidden" name="mod_id" id="edit-mod-id">
      <div class="form-group">
        <label>Mod Version</label>
        <input type="text" name="mod_version" id="edit-mod-version" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Minecraft Version</label>
        <input type="text" name="mc_version" id="edit-mc-version" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Download URL</label>
        <input type="url" name="download_url" id="edit-dl-url" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="edit-notes" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn" onclick="closeModEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModEdit(id, modVer, mcVer, dlUrl, notes) {
    document.getElementById('edit-mod-id').value = id;
    document.getElementById('edit-mod-version').value = modVer;
    document.getElementById('edit-mc-version').value = mcVer;
    document.getElementById('edit-dl-url').value = dlUrl;
    document.getElementById('edit-notes').value = notes;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeModEdit() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModEdit();
});
</script>

</div>
<?php ui_end(); ?>
