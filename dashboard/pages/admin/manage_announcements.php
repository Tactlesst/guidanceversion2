<?php
$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_announcement') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $event_type = $_POST['event_type'] ?? 'general';
            $target = $_POST['target_audience'] ?? 'all_students';
            $event_date = $_POST['event_date'] ?: null;
            $event_time = $_POST['event_time'] ?: null;
            $location = trim($_POST['location'] ?? '');

            if ($title === '' || $message === '') throw new Exception("Title and message are required.");

            $stmt = $db->prepare("INSERT INTO announcements (title, message, event_type, target_audience, event_date, event_time, location, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())");
            $stmt->execute([$title, $message, $event_type, $target, $event_date, $event_time, $location, $_SESSION['user_id']]);
            $announcement_id = (int)$db->lastInsertId();

            $userQuery = "SELECT id FROM users WHERE role IN ('student','examinee') AND (archived = 0 OR archived IS NULL)";
            if ($target === 'all_students') $userQuery .= " AND role = 'student'";
            if ($target === 'all_examinees') $userQuery .= " AND role = 'examinee'";
            $users = $db->query($userQuery)->fetchAll(PDO::FETCH_ASSOC);

            $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, related_table, related_id, created_at) VALUES (?, ?, ?, 'info', 'announcements', ?, NOW())");
            foreach ($users as $u) {
                $notif_stmt->execute([$u['id'], $title, $message, $announcement_id]);
            }
            $_SESSION['success_message'] = "Announcement created and sent to " . count($users) . " users.";
            header("Location: layout.php?page=manage_announcements");
            exit();
        }

        if ($action === 'delete_announcement') {
            $id = (int)($_POST['announcement_id'] ?? 0);
            $db->prepare("DELETE FROM notifications WHERE related_table='announcements' AND related_id=?")->execute([$id]);
            $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
            $_SESSION['success_message'] = "Announcement deleted.";
            header("Location: layout.php?page=manage_announcements");
            exit();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$page_no = max(1, (int)($_GET['p'] ?? 1));
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 10)));
$offset = ($page_no - 1) * $per_page;

$total_records = (int)$db->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));

$stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM notifications n WHERE n.related_table='announcements' AND n.related_id=a.id) AS notification_count FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$audience_count = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','examinee') AND (archived=0 OR archived IS NULL)")->fetchColumn();
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-bullhorn mr-2 text-primary"></i>Announcements</h1>
        <button onclick="openModal('announcementModal')" class="px-3 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Create</button>
    </div>

    <?= renderAlerts($success_message, $error_message) ?>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><div class="text-xs text-gray-500">Total Announcements</div><div class="text-2xl font-bold text-gray-800"><?= $total_records ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500"><div class="text-xs text-gray-500">Target Reach</div><div class="text-2xl font-bold text-gray-800"><?= $audience_count ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500"><div class="text-xs text-gray-500">This Page</div><div class="text-2xl font-bold text-gray-800"><?= count($announcements) ?></div></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Audience</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Sent</th><th class="px-4 py-3">By</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($announcements)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No announcements yet.</td></tr>
                    <?php else: foreach ($announcements as $a): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><div class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars(substr($a['message'], 0, 70)) ?><?= strlen($a['message']) > 70 ? '...' : '' ?></div></td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a['event_type'] ?? 'general'))) ?></span></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(str_replace('_', ' ', $a['target_audience'] ?? 'all_students')) ?></td>
                        <td class="px-4 py-3 text-xs text-gray-600"><?= !empty($a['event_date']) ? date('M d, Y', strtotime($a['event_date'])) : 'N/A' ?></td>
                        <td class="px-4 py-3"><?= (int)$a['notification_count'] ?></td>
                        <td class="px-4 py-3 text-xs text-gray-600"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'System') ?></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="announcement_id" value="<?= (int)$a['id'] ?>">
                                <button class="px-3 py-1.5 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200" onclick="return confirm('Delete this announcement?')"><i class="fas fa-trash mr-1"></i>Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t text-xs text-gray-500">Page <?= $page_no ?> of <?= $total_pages ?></div>
    </div>
</div>

<div id="announcementModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-bullhorn mr-2"></i>Create Announcement</h3>
            <button onclick="closeModal('announcementModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create_announcement">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Message *</label><textarea name="message" rows="4" required class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label><select name="event_type" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="general">General</option><option value="academic">Academic</option><option value="guidance">Guidance</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Audience</label><select name="target_audience" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="all_students">Students</option><option value="all_examinees">Examinees</option><option value="all_users">All Users</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Event Date</label><input type="date" name="event_date" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Event Time</label><input type="time" name="event_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Location</label><input type="text" name="location" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('announcementModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Create</button></div>
        </form>
    </div>
</div>
