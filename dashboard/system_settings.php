<?php
require_once __DIR__ . '/../classes/SystemSettings.php';

$settings = new SystemSettings($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
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
            $_SESSION['settings_success'] = "Settings saved!"; header("Location: layout.php?page=system_settings"); exit();
        } catch (Exception $e) { $error_message = "Failed to save settings."; }
    }
    if (isset($_POST['save_announcement'])) {
        $title = trim($_POST['announcement_title'] ?? '');
        $message = trim($_POST['announcement_message'] ?? '');
        $audience = $_POST['target_audience'] ?? 'all_users';
        $is_active = isset($_POST['announcement_active']) ? 1 : 0;
        $ann_id = (int)($_POST['announcement_id'] ?? 0);
        if ($title === '' || $message === '') { $error_message = "Title and message required."; }
        else {
            try {
                if ($ann_id > 0) {
                    $db->prepare("UPDATE announcements SET title=?, message=?, target_audience=?, is_active=?, updated_at=NOW() WHERE id=?")->execute([$title, $message, $audience, $is_active, $ann_id]);
                } else {
                    $db->prepare("INSERT INTO announcements (title, message, event_type, target_audience, location, is_active, created_by, created_at) VALUES (?,?,'general',?,'__HOMEPAGE__',?,?,NOW())")->execute([$title, $message, $audience, $is_active, $_SESSION['user_id']]);
                }
                $_SESSION['settings_success'] = "Announcement saved!"; header("Location: layout.php?page=system_settings"); exit();
            } catch (Exception $e) { $error_message = "Failed to save announcement."; }
        }
    }
}

// Get current settings
$all_settings = $settings->getAllSettings();
$settings_map = [];
if ($all_settings) { while ($s = $all_settings->fetch(PDO::FETCH_ASSOC)) $settings_map[$s['setting_key']] = $s['setting_value']; }

// Get homepage announcement
$announcement = null;
try {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE location = '__HOMEPAGE__' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute(); $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-cogs mr-2 text-primary"></i>System Settings</h1>

    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Feature Toggles -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-toggle-on mr-2 text-primary"></i>Feature Toggles</h3>
        <form method="POST" class="space-y-4">
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

    <!-- Homepage Announcement -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-bullhorn mr-2 text-orange-500"></i>Homepage Announcement</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="save_announcement" value="1">
            <input type="hidden" name="announcement_id" value="<?= $announcement['id']??0 ?>">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="announcement_title" value="<?= htmlspecialchars($announcement['title']??'') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Message *</label><textarea name="announcement_message" rows="3" required class="w-full px-3 py-2 border rounded-lg text-sm"><?= htmlspecialchars($announcement['message']??'') ?></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                    <select name="target_audience" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="all_users" <?= ($announcement['target_audience']??'')==='all_users'?'selected':'' ?>>All Users</option>
                        <option value="all_students" <?= ($announcement['target_audience']??'')==='all_students'?'selected':'' ?>>Students Only</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-6"><input type="checkbox" name="announcement_active" value="1" <?= !empty($announcement['is_active'])?'checked':'' ?> class="rounded"><label class="text-sm text-gray-700">Active</label></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm hover:bg-orange-600"><i class="fas fa-save mr-1"></i>Save Announcement</button>
        </form>
    </div>
</div>
