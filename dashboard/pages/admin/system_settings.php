<?php
if(!defined('IN_LAYOUT')||!in_array($role??'',['admin','super_admin'],true)){http_response_code(403);return;}
$_SESSION['office_settings_csrf']=$_SESSION['office_settings_csrf']??bin2hex(random_bytes(32));
if(($_GET['section']??'')==='delivery'||isset($_POST['save_mail_settings'])||isset($_POST['save_website_settings'])){require __DIR__.'/../../../includes/mail_settings_view.php';return;}
$settingsSection=($_GET['section']??'')==='activity'?'activity':'settings';

require_once __DIR__ . '/../../../classes/SystemSettings.php';

$settings = new SystemSettings($db);
if($settingsSection==='activity'){
 echo '<link rel="stylesheet" href="../assets/css/staff-activity.css?v=20261028-1"><nav class="st-tabs" aria-label="System settings sections"><a href="layout.php?page=system_settings">Office settings</a><a href="layout.php?page=system_settings&amp;section=activity" aria-current="page">Staff activity</a><a href="layout.php?page=system_settings&amp;section=delivery">Email &amp; notifications</a></nav>';
 require __DIR__.'/../../../includes/staff_activity_panel.php';return;
}


$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
    if(isset($_POST['save_announcement'])){http_response_code(410);echo '<p role="alert">Announcements are now managed on the <a href="layout.php?page=manage_announcements">Announcements page</a>.</p>';return;}
    if(!is_string($_POST['office_settings_csrf']??null)||!hash_equals($_SESSION['office_settings_csrf'],$_POST['office_settings_csrf'])){http_response_code(403);echo '<p role="alert">Refresh System Settings and try again.</p>';return;}
    if (isset($_POST['save_settings'])) {
        try {
            $toggle_fields = ['counseling_enabled','pds_enabled','entrance_exam_enabled'];
            foreach ($toggle_fields as $field) {
                $val = isset($_POST[$field]) ? '1' : '0';
                $settings->setSettingValue($field, $val);
            }
            $text_fields = ['default_password','max_daily_appointments','session_duration'];
            foreach ($text_fields as $field) {
                if (isset($_POST[$field])) $settings->setSettingValue($field, trim($_POST[$field]));
            }
            if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['settings_success'] = "Settings saved!"; header("Location: " . portalSaveRedirect("layout.php?page=system_settings")); exit();
        } catch (Exception $e) { $error_message = "Failed to save settings."; }
    }

}

// Get current settings
$all_settings = $settings->getAllSettings();
$settings_map = [];
if ($all_settings) { while ($s = $all_settings->fetch(PDO::FETCH_ASSOC)) $settings_map[$s['setting_key']] = $s['setting_value']; }

?>

<link rel="stylesheet" href="../assets/css/staff-activity.css?v=20261028-1">
<nav class="st-tabs" aria-label="System settings sections"><a href="layout.php?page=system_settings" aria-current="page">Office settings</a><a href="layout.php?page=system_settings&amp;section=activity"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Staff activity</a><a href="layout.php?page=system_settings&amp;section=delivery">Email &amp; notifications</a></nav>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-cogs mr-2 text-primary"></i>System Settings</h1>

    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>

    <!-- Feature Toggles -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-toggle-on mr-2 text-primary"></i>Feature Toggles</h3>
        <form data-portal-save method="POST" class="space-y-4"><input type="hidden" name="office_settings_csrf" value="<?=htmlspecialchars($_SESSION['office_settings_csrf'],ENT_QUOTES)?>">
            <input type="hidden" name="save_settings" value="1">
            <div class="space-y-3">
                <?php foreach (['counseling_enabled'=>'Counseling Appointments','pds_enabled'=>'Personal Data Sheet','entrance_exam_enabled'=>'Entrance Exam'] as $key=>$label): ?>
                <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <span class="text-sm font-medium text-gray-700"><?= $label ?></span>
                    <input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($settings_map[$key]) && $settings_map[$key]==='1' ? 'checked' : '' ?> class="w-5 h-5 rounded text-primary focus:ring-primary">
                </label>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Default Password</label><input type="text" name="default_password" value="<?= htmlspecialchars($settings_map['default_password']??'password123') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Daily Appointments</label><input type="number" name="max_daily_appointments" value="<?= htmlspecialchars($settings_map['max_daily_appointments']??'4') ?>" min="1" max="20" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Session Duration (min)</label><input type="number" name="session_duration" value="<?= htmlspecialchars($settings_map['session_duration']??'60') ?>" min="15" max="120" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-save mr-1"></i>Save Settings</button>
        </form>
    </div>

    <p><a class="st-link" href="layout.php?page=manage_announcements"><i class="fas fa-bullhorn" aria-hidden="true"></i> Manage homepage announcements</a></p>
</div>
