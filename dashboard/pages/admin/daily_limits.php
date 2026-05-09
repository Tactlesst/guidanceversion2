<?php
require_once __DIR__ . '/../../../classes/DailyBookingLimit.php';

$dailyLimit = new DailyBookingLimit($db);
$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS entrance_exam_daily_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL UNIQUE,
        max_appointments INT NOT NULL DEFAULT 5,
        set_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

if ($_POST) {
    try {
        if (isset($_POST['set_single_limit'])) {
            $dailyLimit->setDailyLimit($_POST['date'], (int)$_POST['max_appointments'], $_SESSION['user_id']);
            $_SESSION['success_message'] = "Counseling limit saved.";
            header("Location: layout.php?page=daily_limits&tab=counseling");
            exit();
        }

        if (isset($_POST['set_range_limits'])) {
            $start = new DateTime($_POST['start_date']);
            $end = new DateTime($_POST['end_date']);
            $max = (int)$_POST['range_max_appointments'];
            $count = 0;
            while ($start <= $end) {
                if ((int)$start->format('N') < 6) {
                    $dailyLimit->setDailyLimit($start->format('Y-m-d'), $max, $_SESSION['user_id']);
                    $count++;
                }
                $start->modify('+1 day');
            }
            $_SESSION['success_message'] = "Counseling limits set for {$count} weekdays.";
            header("Location: layout.php?page=daily_limits&tab=counseling");
            exit();
        }

        if (isset($_POST['remove_limit'])) {
            $dailyLimit->removeDailyLimit($_POST['remove_date']);
            $_SESSION['success_message'] = "Counseling custom limit removed.";
            header("Location: layout.php?page=daily_limits&tab=counseling");
            exit();
        }

        if (isset($_POST['set_exam_single_limit'])) {
            $max = min(15, max(1, (int)$_POST['exam_max_appointments']));
            $stmt = $db->prepare("INSERT INTO entrance_exam_daily_limits (date, max_appointments, set_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE max_appointments = VALUES(max_appointments), set_by = VALUES(set_by), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$_POST['exam_date'], $max, $_SESSION['user_id']]);
            $_SESSION['success_message'] = "Entrance exam limit saved.";
            header("Location: layout.php?page=daily_limits&tab=entrance-exam");
            exit();
        }

        if (isset($_POST['set_exam_range_limits'])) {
            $start = new DateTime($_POST['exam_start_date']);
            $end = new DateTime($_POST['exam_end_date']);
            $max = min(15, max(1, (int)$_POST['exam_range_max_appointments']));
            $count = 0;
            $stmt = $db->prepare("INSERT INTO entrance_exam_daily_limits (date, max_appointments, set_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE max_appointments = VALUES(max_appointments), set_by = VALUES(set_by), updated_at = CURRENT_TIMESTAMP");
            while ($start <= $end) {
                if ((int)$start->format('N') < 6) {
                    $stmt->execute([$start->format('Y-m-d'), $max, $_SESSION['user_id']]);
                    $count++;
                }
                $start->modify('+1 day');
            }
            $_SESSION['success_message'] = "Entrance exam limits set for {$count} weekdays.";
            header("Location: layout.php?page=daily_limits&tab=entrance-exam");
            exit();
        }

        if (isset($_POST['remove_exam_limit'])) {
            $stmt = $db->prepare("DELETE FROM entrance_exam_daily_limits WHERE date = ?");
            $stmt->execute([$_POST['remove_exam_date']]);
            $_SESSION['success_message'] = "Entrance exam custom limit removed.";
            header("Location: layout.php?page=daily_limits&tab=entrance-exam");
            exit();
        }
    } catch (Exception $e) {
        $error_message = "Failed to save limit changes.";
    }
}

$active_tab = $_GET['tab'] ?? 'counseling';
$records_per_page = isset($_GET['per_page']) ? max(5, (int)$_GET['per_page']) : 10;
$counseling_page = isset($_GET['counseling_page']) ? max(1, (int)$_GET['counseling_page']) : 1;
$exam_page = isset($_GET['exam_page']) ? max(1, (int)$_GET['exam_page']) : 1;
$counseling_offset = ($counseling_page - 1) * $records_per_page;
$exam_offset = ($exam_page - 1) * $records_per_page;

$total_counseling = 0;
$total_exams = 0;
$custom_limits = [];
$exam_custom_limits = [];

try {
    $total_counseling = (int)$db->query("SELECT COUNT(*) FROM daily_booking_limits WHERE DATE(date) >= CURDATE()")->fetchColumn();
    $stmt = $db->prepare("SELECT dl.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM counseling_appointments ca WHERE ca.appointment_date = dl.date AND ca.status IN ('confirmed','pending','in_progress','completed')) AS current_bookings FROM daily_booking_limits dl LEFT JOIN users u ON dl.set_by = u.id WHERE DATE(dl.date) >= CURDATE() ORDER BY dl.date ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $counseling_offset, PDO::PARAM_INT);
    $stmt->execute();
    $custom_limits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_exams = (int)$db->query("SELECT COUNT(*) FROM entrance_exam_daily_limits WHERE DATE(date) >= CURDATE()")->fetchColumn();
    $stmt = $db->prepare("SELECT dl.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM entrance_exam_appointments ea WHERE ea.preferred_date = dl.date AND ea.status IN ('confirmed','pending','completed')) AS current_bookings FROM entrance_exam_daily_limits dl LEFT JOIN users u ON dl.set_by = u.id WHERE DATE(dl.date) >= CURDATE() ORDER BY dl.date ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $exam_offset, PDO::PARAM_INT);
    $stmt->execute();
    $exam_custom_limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$total_counseling_pages = max(1, (int)ceil(($total_counseling ?: 0) / $records_per_page));
$total_exam_pages = max(1, (int)ceil(($total_exams ?: 0) / $records_per_page));
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-calendar-check mr-2 text-primary"></i>Daily Booking Limits</h1>
    <?= renderAlerts($success_message, $error_message) ?>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex bg-gray-100 rounded-lg p-1 gap-1">
            <a href="layout.php?page=daily_limits&tab=counseling" class="px-3 py-1.5 text-sm rounded-md <?= $active_tab === 'counseling' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-200' ?>">Counseling</a>
            <a href="layout.php?page=daily_limits&tab=entrance-exam" class="px-3 py-1.5 text-sm rounded-md <?= $active_tab === 'entrance-exam' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-200' ?>">Entrance Exam</a>
        </div>
    </div>

    <?php if ($active_tab === 'counseling'): ?>
    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Set Single Day Limit</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_single_limit" value="1">
                <input type="date" name="date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="number" name="max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Save</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Set Range (Weekdays)</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_range_limits" value="1">
                <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="number" name="range_max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Apply Range</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Set Single Day Limit</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_exam_single_limit" value="1">
                <input type="date" name="exam_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="number" name="exam_max_appointments" min="1" max="15" value="5" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Save</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Set Range (Weekdays)</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_exam_range_limits" value="1">
                <input type="date" name="exam_start_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="date" name="exam_end_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <input type="number" name="exam_range_max_appointments" min="1" max="15" value="5" required class="w-full px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Apply Range</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800"><?= $active_tab === 'counseling' ? 'Upcoming Counseling Limits' : 'Upcoming Entrance Exam Limits' ?></h3>
            <div class="text-xs text-gray-500">Showing <?= $records_per_page ?> per page</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Limit</th><th class="px-4 py-3">Current</th><th class="px-4 py-3">Set By</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $rows = $active_tab === 'counseling' ? $custom_limits : $exam_custom_limits; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No custom limits set.</td></tr>
                    <?php else: foreach ($rows as $limit): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= date('M d, Y', strtotime($limit['date'])) ?></td>
                            <td class="px-4 py-3"><?= (int)$limit['max_appointments'] ?></td>
                            <td class="px-4 py-3"><?= (int)$limit['current_bookings'] ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars(trim(($limit['first_name'] ?? '') . ' ' . ($limit['last_name'] ?? '')) ?: 'System') ?></td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" class="inline">
                                    <?php if ($active_tab === 'counseling'): ?>
                                        <input type="hidden" name="remove_limit" value="1">
                                        <input type="hidden" name="remove_date" value="<?= htmlspecialchars($limit['date']) ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="remove_exam_limit" value="1">
                                        <input type="hidden" name="remove_exam_date" value="<?= htmlspecialchars($limit['date']) ?>">
                                    <?php endif; ?>
                                    <button class="px-3 py-1.5 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200" onclick="return confirm('Remove custom limit?')"><i class="fas fa-trash mr-1"></i>Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t text-xs text-gray-500">
            <?php if ($active_tab === 'counseling'): ?>
                Page <?= $counseling_page ?> of <?= $total_counseling_pages ?> (<?= $total_counseling ?> total)
            <?php else: ?>
                Page <?= $exam_page ?> of <?= $total_exam_pages ?> (<?= $total_exams ?> total)
            <?php endif; ?>
        </div>
    </div>
</div>
