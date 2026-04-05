<?php
/**
 * admin/settings.php
 * Manage all global settings: DB, SMTP, Twilio, etc.
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/ui.php';

$user = require_auth('admin', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $keys = [
        'site_name','login_banner','login_banner_text','login_banner_subtext',
        'mail_from','mail_from_name',
        'smtp_host','smtp_port','smtp_user','smtp_secure',
        'twilio_sid','twilio_from',
        'notify_email','notify_phone','remind_days_before',
    ];
    // Password-like fields only update if non-empty
    $secret_keys = ['smtp_pass','twilio_token'];

    foreach ($keys as $k) {
        set_setting($k, trim($_POST[$k] ?? ''));
    }
    foreach ($secret_keys as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($v !== '') set_setting($k, $v);
    }

    flash('success', 'Settings saved.');
    header('Location: ' . APP_URL . '/admin/settings');
    exit;
}

// Load current settings
$s = [];
$all_settings = db()->query('SELECT `key`,`value` FROM settings')->fetchAll();
foreach ($all_settings as $row) $s[$row['key']] = $row['value'];

$nav_items = [
    ['icon'=>'dashboard',       'label'=>'Overview',    'href'=>APP_URL.'/admin/'],
    ['icon'=>'settings',        'label'=>'Settings',    'href'=>APP_URL.'/admin/settings', 'active'=>true],
    ['icon'=>'storage',         'label'=>'Migrations',  'href'=>APP_URL.'/admin/migrations'],
    ['icon'=>'engineering',     'label'=>'Maintenance', 'href'=>APP_URL.'/admin/maintenance'],
    ['icon'=>'add_alert',       'label'=>'Alerts',      'href'=>APP_URL.'/admin/alerts'],
    ['section'=>'Sub-systems'],
    ['icon'=>'manage_accounts', 'label'=>'ID Admin',    'href'=>APP_URL.'/id/admin/'],
    ['icon'=>'school',          'label'=>'EDU Hub',     'href'=>APP_URL.'/edu/'],
];

ui_head('Settings – System Admin','admin','System Admin','admin_panel_settings');
ui_sidebar('System Admin','admin_panel_settings',$nav_items,APP_URL.'/id/auth/logout');
ui_page_header('Settings','System Admin → Settings');

function sv(string $key, array $settings, string $default = ''): string {
    return htmlspecialchars($settings[$key] ?? $default);
}
?>
<div class="page-body">
<?php ui_flash(); ?>

<form method="POST">
<?= csrf_field() ?>

<div class="card-grid card-grid-2">

  <!-- General -->
  <?php ui_card_open('settings','General'); ?>
    <div class="form-group">
      <label>Site Name</label>
      <input type="text" name="site_name" class="form-control" value="<?=sv('site_name',$s,'CG Internal')?>">
    </div>
    <div class="form-group">
      <label>Login Page Banner <span class="text-muted text-xs">(image URL <em>or</em> CSS gradient)</span></label>
      <input type="text" name="login_banner" class="form-control"
             placeholder="https://…/image.jpg  or  linear-gradient(135deg,#0f172a,#1e40af)"
             value="<?=sv('login_banner',$s)?>">
      <div class="form-hint">Image URL fills the left panel. CSS gradient/color is used as the background.</div>
    </div>
    <div class="form-group">
      <label>Login Banner Heading</label>
      <input type="text" name="login_banner_text" class="form-control"
             placeholder="<?= htmlspecialchars(APP_NAME) ?>"
             value="<?=sv('login_banner_text',$s)?>">
    </div>
    <div class="form-group">
      <label>Login Banner Subtext</label>
      <input type="text" name="login_banner_subtext" class="form-control"
             placeholder="Internal management platform"
             value="<?=sv('login_banner_subtext',$s)?>">
    </div>
    <div class="form-group">
      <label>Notification Email</label>
      <input type="email" name="notify_email" class="form-control"
             placeholder="you@purchase.edu" value="<?=sv('notify_email',$s)?>">
      <div class="form-hint">Email for reminders (can be your purchase.edu address)</div>
    </div>
    <div class="form-group">
      <label>Notification Phone (E.164)</label>
      <input type="tel" name="notify_phone" class="form-control"
             placeholder="+12025551234" value="<?=sv('notify_phone',$s)?>">
      <div class="form-hint">Phone for SMS reminders via Twilio</div>
    </div>
    <div class="form-group">
      <label>Remind N Days Before Due</label>
      <input type="number" name="remind_days_before" class="form-control"
             min="1" max="30" value="<?=sv('remind_days_before',$s,'3')?>">
    </div>
  <?php ui_card_close(); ?>

  <!-- Email / SMTP -->
  <?php ui_card_open('mail','Email / SMTP'); ?>
    <div class="form-row">
      <div class="form-group">
        <label>From Address</label>
        <input type="email" name="mail_from" class="form-control" value="<?=sv('mail_from',$s)?>">
      </div>
      <div class="form-group">
        <label>From Name</label>
        <input type="text" name="mail_from_name" class="form-control" value="<?=sv('mail_from_name',$s)?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>SMTP Host</label>
        <input type="text" name="smtp_host" class="form-control"
               placeholder="smtp.example.com" value="<?=sv('smtp_host',$s)?>">
      </div>
      <div class="form-group">
        <label>SMTP Port</label>
        <input type="number" name="smtp_port" class="form-control" value="<?=sv('smtp_port',$s,'587')?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>SMTP User</label>
        <input type="text" name="smtp_user" class="form-control" value="<?=sv('smtp_user',$s)?>">
      </div>
      <div class="form-group">
        <label>SMTP Password</label>
        <input type="password" name="smtp_pass" class="form-control"
               placeholder="Leave blank to keep current" autocomplete="new-password">
      </div>
    </div>
    <div class="form-group">
      <label>Encryption</label>
      <select name="smtp_secure" class="form-control">
        <option value="tls" <?=($s['smtp_secure']??'tls')==='tls'?'selected':''?>>TLS (port 587)</option>
        <option value="ssl" <?=($s['smtp_secure']??'')==='ssl'?'selected':''?>>SSL (port 465)</option>
        <option value="" <?=($s['smtp_secure']??'')==''?'selected':''?>>None</option>
      </select>
    </div>
  <?php ui_card_close(); ?>

  <!-- Twilio / SMS -->
  <?php ui_card_open('sms','Twilio / SMS'); ?>
    <div class="form-group">
      <label>Twilio Account SID</label>
      <input type="text" name="twilio_sid" class="form-control" value="<?=sv('twilio_sid',$s)?>">
    </div>
    <div class="form-group">
      <label>Twilio Auth Token</label>
      <input type="password" name="twilio_token" class="form-control"
             placeholder="Leave blank to keep current" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label>Twilio From Number (E.164)</label>
      <input type="tel" name="twilio_from" class="form-control"
             placeholder="+15005550006" value="<?=sv('twilio_from',$s)?>">
    </div>
  <?php ui_card_close(); ?>

</div><!-- .card-grid -->

<div class="form-actions" style="margin-top:1.5rem">
  <button type="submit" class="btn btn-primary">
    <span class="material-symbols-outlined">save</span> Save Settings
  </button>
</div>

</form>
</div>
<?php ui_end(); ?>
