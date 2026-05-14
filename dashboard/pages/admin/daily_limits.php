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
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
                <i class="fas fa-calendar-check"></i>Daily Booking Limits
            </h1>
            <p class="text-sm text-gray-500 mt-1">Manage daily capacity for counseling sessions and entrance exams</p>
        </div>
    </div>

    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Stats Cards -->
    <?php
    $default_counseling_limit = 4;
    $default_exam_limit = 5;
    try {
        $sys_stmt = $db->query("SELECT value FROM system_settings WHERE key_name = 'max_daily_appointments' LIMIT 1");
        $val = $sys_stmt->fetchColumn();
        if ($val) $default_counseling_limit = (int)$val;
    } catch (Exception $e) {}
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Counseling Limits Set</div>
                <div class="text-2xl font-bold text-gray-800"><?= $total_counseling ?></div>
                <div class="text-[11px] text-gray-400 mt-1">Default: <?= $default_counseling_limit ?>/day</div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                <i class="fas fa-comments"></i>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Exam Limits Set</div>
                <div class="text-2xl font-bold text-gray-800"><?= $total_exams ?></div>
                <div class="text-[11px] text-gray-400 mt-1">Default: <?= $default_exam_limit ?>/day</div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fas fa-clipboard-list"></i>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Holidays This Month</div>
                <div class="text-2xl font-bold text-gray-800"><?php
                    try { echo $db->query("SELECT COUNT(*) FROM holidays WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetchColumn(); } catch (Exception $e) { echo '0'; }
                ?></div>
                <div class="text-[11px] text-gray-400 mt-1">No bookings allowed</div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center">
                <i class="fas fa-ban"></i>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Next 7 Days</div>
                <div class="text-2xl font-bold text-gray-800"><?php
                    try { echo $db->query("SELECT COUNT(DISTINCT date) FROM daily_booking_limits WHERE date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(); } catch (Exception $e) { echo '0'; }
                ?></div>
                <div class="text-[11px] text-gray-400 mt-1">Days with custom limits</div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="fas fa-calendar-week"></i>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5">
        <div class="flex bg-gray-100 rounded-xl p-1 gap-1">
            <a href="layout.php?page=daily_limits&tab=counseling" class="flex-1 text-center px-4 py-2.5 text-sm font-semibold rounded-lg transition-all <?= $active_tab === 'counseling' ? 'bg-primary text-white shadow-sm' : 'text-gray-600 hover:bg-white' ?>">
                <i class="fas fa-comments mr-2"></i>Counseling
            </a>
            <a href="layout.php?page=daily_limits&tab=entrance-exam" class="flex-1 text-center px-4 py-2.5 text-sm font-semibold rounded-lg transition-all <?= $active_tab === 'entrance-exam' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white' ?>">
                <i class="fas fa-clipboard-list mr-2"></i>Entrance Exam
            </a>
        </div>
    </div>

    <?php if ($active_tab === 'counseling'): ?>
    <!-- Counseling Tab -->
    <div class="grid lg:grid-cols-3 gap-4">
        <!-- Set Limits Forms -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-primary/5 to-transparent">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-day text-primary"></i>Set Single Day Limit
                    </h3>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <input type="hidden" name="set_single_limit" value="1">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Date</label>
                        <input type="date" name="date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Max Appointments</label>
                        <input type="number" name="max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    </div>
                    <button class="w-full px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Limit
                    </button>
                </form>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-emerald-500/5 to-transparent">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-week text-emerald-600"></i>Set Range (Weekdays)
                    </h3>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <input type="hidden" name="set_range_limits" value="1">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Start Date</label>
                        <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">End Date</label>
                        <input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Max Appointments</label>
                        <input type="number" name="range_max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <button class="w-full px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-calendar-plus mr-2"></i>Apply Range
                    </button>
                </form>
            </div>
        </div>

        <!-- Calendar -->
        <?php
        $cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');
        $cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');
        if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
        if ($cal_month > 12) { $cal_month = 1; $cal_year++; }
        $cal_first_day = strtotime("$cal_year-$cal_month-01");
        $cal_days_in_month = (int)date('t', $cal_first_day);
        $cal_start_dow = (int)date('w', $cal_first_day);
        $cal_month_name = date('F Y', $cal_first_day);

        // Get limits for this month
        $cal_limits = [];
        try {
            $cl_stmt = $db->prepare("SELECT date, max_appointments FROM daily_booking_limits WHERE YEAR(date) = ? AND MONTH(date) = ?");
            $cl_stmt->execute([$cal_year, $cal_month]);
            while ($r = $cl_stmt->fetch(PDO::FETCH_ASSOC)) $cal_limits[$r['date']] = (int)$r['max_appointments'];
        } catch (Exception $e) {}

        // Get holidays for this month
        $cal_holidays = [];
        try {
            $ch_stmt = $db->prepare("SELECT date, name FROM holidays WHERE YEAR(date) = ? AND MONTH(date) = ?");
            $ch_stmt->execute([$cal_year, $cal_month]);
            while ($r = $ch_stmt->fetch(PDO::FETCH_ASSOC)) $cal_holidays[$r['date']] = $r['name'];
        } catch (Exception $e) {}
        ?>
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-primary"></i>Monthly Overview
                </h3>
                <div class="flex items-center gap-2">
                    <a href="layout.php?page=daily_limits&tab=counseling&cal_month=<?= $cal_month == 1 ? 12 : $cal_month - 1 ?>&cal_year=<?= $cal_month == 1 ? $cal_year - 1 : $cal_year ?>" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
                    <span class="text-sm font-semibold text-gray-700 min-w-[120px] text-center"><?= $cal_month_name ?></span>
                    <a href="layout.php?page=daily_limits&tab=counseling&cal_month=<?= $cal_month == 12 ? 1 : $cal_month + 1 ?>&cal_year=<?= $cal_month == 12 ? $cal_year + 1 : $cal_year ?>" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
                </div>
            </div>
            <div class="p-4">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 mb-1">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                    <div class="text-center text-[11px] font-semibold text-gray-400 py-1"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-1">
                    <?php for ($i = 0; $i < $cal_start_dow; $i++): ?>
                    <div class="h-16"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $cal_days_in_month; $day++):
                        $date = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
                        $is_today = $date === date('Y-m-d');
                        $is_past = $date < date('Y-m-d');
                        $is_sunday = (int)date('w', strtotime($date)) === 0;
                        $is_holiday = isset($cal_holidays[$date]);
                        $has_limit = isset($cal_limits[$date]);
                        $limit_val = $has_limit ? $cal_limits[$date] : $default_counseling_limit;
                    ?>
                    <div class="h-16 rounded-lg border <?= $is_today ? 'border-primary bg-primary/5' : 'border-gray-100' ?> <?= $is_past ? 'opacity-40' : '' ?> p-1 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs <?= $is_today ? 'font-bold text-primary' : 'text-gray-700' ?>"><?= $day ?></span>
                            <?php if ($has_limit): ?>
                            <span class="text-[9px] bg-cyan-100 text-cyan-700 px-1 py-0.5 rounded font-semibold"><?= $limit_val ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_holiday): ?>
                            <div class="text-[8px] text-red-500 font-medium truncate" title="<?= htmlspecialchars($cal_holidays[$date]) ?>"><?= substr($cal_holidays[$date], 0, 8) ?></div>
                        <?php elseif ($is_sunday): ?>
                            <div class="text-[8px] text-gray-400">Closed</div>
                        <?php elseif (!$is_past): ?>
                            <div class="text-[8px] <?= $has_limit ? 'text-cyan-600 font-medium' : 'text-gray-400' ?>"><?= $has_limit ? 'Custom' : $default_counseling_limit . '/day' ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <!-- Legend -->
                <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-4 text-[11px] text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-primary/10 border border-primary"></span>Today</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-cyan-100"></span>Custom Limit</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-50 border border-red-200"></span>Holiday</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gray-100"></span>Sunday (Closed)</span>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Entrance Exam Tab -->
    <div class="grid lg:grid-cols-3 gap-4">
        <!-- Set Limits Forms -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-indigo-500/5 to-transparent">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-day text-indigo-600"></i>Set Single Day Limit
                    </h3>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <input type="hidden" name="set_exam_single_limit" value="1">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Date</label>
                        <input type="date" name="exam_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Max Appointments</label>
                        <input type="number" name="exam_max_appointments" min="1" max="15" value="5" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors">
                    </div>
                    <button class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Limit
                    </button>
                </form>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-emerald-500/5 to-transparent">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-week text-emerald-600"></i>Set Range (Weekdays)
                    </h3>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <input type="hidden" name="set_exam_range_limits" value="1">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Start Date</label>
                        <input type="date" name="exam_start_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">End Date</label>
                        <input type="date" name="exam_end_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Max Appointments</label>
                        <input type="number" name="exam_range_max_appointments" min="1" max="15" value="5" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                    </div>
                    <button class="w-full px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-calendar-plus mr-2"></i>Apply Range
                    </button>
                </form>
            </div>
        </div>

        <!-- Calendar -->
        <?php
        $cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');
        $cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');
        if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
        if ($cal_month > 12) { $cal_month = 1; $cal_year++; }
        $cal_first_day = strtotime("$cal_year-$cal_month-01");
        $cal_days_in_month = (int)date('t', $cal_first_day);
        $cal_start_dow = (int)date('w', $cal_first_day);
        $cal_month_name = date('F Y', $cal_first_day);

        // Get exam limits for this month
        $cal_limits = [];
        try {
            $cl_stmt = $db->prepare("SELECT date, max_appointments FROM entrance_exam_daily_limits WHERE YEAR(date) = ? AND MONTH(date) = ?");
            $cl_stmt->execute([$cal_year, $cal_month]);
            while ($r = $cl_stmt->fetch(PDO::FETCH_ASSOC)) $cal_limits[$r['date']] = (int)$r['max_appointments'];
        } catch (Exception $e) {}

        // Get holidays for this month
        $cal_holidays = [];
        try {
            $ch_stmt = $db->prepare("SELECT date, name FROM holidays WHERE YEAR(date) = ? AND MONTH(date) = ?");
            $ch_stmt->execute([$cal_year, $cal_month]);
            while ($r = $ch_stmt->fetch(PDO::FETCH_ASSOC)) $cal_holidays[$r['date']] = $r['name'];
        } catch (Exception $e) {}
        ?>
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-indigo-600"></i>Monthly Overview
                </h3>
                <div class="flex items-center gap-2">
                    <a href="layout.php?page=daily_limits&tab=entrance-exam&cal_month=<?= $cal_month == 1 ? 12 : $cal_month - 1 ?>&cal_year=<?= $cal_month == 1 ? $cal_year - 1 : $cal_year ?>" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
                    <span class="text-sm font-semibold text-gray-700 min-w-[120px] text-center"><?= $cal_month_name ?></span>
                    <a href="layout.php?page=daily_limits&tab=entrance-exam&cal_month=<?= $cal_month == 12 ? 1 : $cal_month + 1 ?>&cal_year=<?= $cal_month == 12 ? $cal_year + 1 : $cal_year ?>" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
                </div>
            </div>
            <div class="p-4">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 mb-1">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                    <div class="text-center text-[11px] font-semibold text-gray-400 py-1"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-1">
                    <?php for ($i = 0; $i < $cal_start_dow; $i++): ?>
                    <div class="h-16"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $cal_days_in_month; $day++):
                        $date = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
                        $is_today = $date === date('Y-m-d');
                        $is_past = $date < date('Y-m-d');
                        $is_sunday = (int)date('w', strtotime($date)) === 0;
                        $is_holiday = isset($cal_holidays[$date]);
                        $has_limit = isset($cal_limits[$date]);
                        $limit_val = $has_limit ? $cal_limits[$date] : $default_exam_limit;
                    ?>
                    <div class="h-16 rounded-lg border <?= $is_today ? 'border-indigo-500 bg-indigo-5' : 'border-gray-100' ?> <?= $is_past ? 'opacity-40' : '' ?> p-1 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs <?= $is_today ? 'font-bold text-indigo-600' : 'text-gray-700' ?>"><?= $day ?></span>
                            <?php if ($has_limit): ?>
                            <span class="text-[9px] bg-indigo-100 text-indigo-700 px-1 py-0.5 rounded font-semibold"><?= $limit_val ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_holiday): ?>
                            <div class="text-[8px] text-red-500 font-medium truncate" title="<?= htmlspecialchars($cal_holidays[$date]) ?>"><?= substr($cal_holidays[$date], 0, 8) ?></div>
                        <?php elseif ($is_sunday): ?>
                            <div class="text-[8px] text-gray-400">Closed</div>
                        <?php elseif (!$is_past): ?>
                            <div class="text-[8px] <?= $has_limit ? 'text-indigo-600 font-medium' : 'text-gray-400' ?>"><?= $has_limit ? 'Custom' : $default_exam_limit . '/day' ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <!-- Legend -->
                <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-4 text-[11px] text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-indigo-100 border border-indigo-300"></span>Today</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-indigo-100"></span>Custom Limit</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-50 border border-red-200"></span>Holiday</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gray-100"></span>Sunday (Closed)</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Limits Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list <?= $active_tab === 'counseling' ? 'text-cyan-600' : 'text-indigo-600' ?>"></i>
                <?= $active_tab === 'counseling' ? 'Upcoming Counseling Limits' : 'Upcoming Entrance Exam Limits' ?>
            </h3>
            <div class="text-xs text-gray-400"><?= $records_per_page ?> per page</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">Date</th>
                        <th class="px-5 py-3 text-left font-semibold">Limit</th>
                        <th class="px-5 py-3 text-left font-semibold">Booked</th>
                        <th class="px-5 py-3 text-left font-semibold">Capacity</th>
                        <th class="px-5 py-3 text-left font-semibold">Set By</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php $rows = $active_tab === 'counseling' ? $custom_limits : $exam_custom_limits; ?>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300 mb-3">
                                        <i class="fas fa-calendar-check text-2xl"></i>
                                    </div>
                                    <div class="text-sm text-gray-500">No custom limits set</div>
                                    <div class="text-xs text-gray-400 mt-1">Default limits are applied automatically</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($rows as $limit):
                        $booked = (int)$limit['current_bookings'];
                        $max = (int)$limit['max_appointments'];
                        $pct = $max > 0 ? min(100, round(($booked / $max) * 100)) : 0;
                        $bar_color = $pct >= 90 ? 'bg-red-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-emerald-500');
                        $is_past = $limit['date'] < date('Y-m-d');
                    ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-800"><?= date('M d, Y', strtotime($limit['date'])) ?></div>
                                <div class="text-[11px] text-gray-400"><?= date('l', strtotime($limit['date'])) ?></div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold <?= $active_tab === 'counseling' ? 'bg-cyan-50 text-cyan-700' : 'bg-indigo-50 text-indigo-700' ?>">
                                    <?= $max ?> / day
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-gray-800"><?= $booked ?></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full <?= $bar_color ?> rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500"><?= htmlspecialchars(trim(($limit['first_name'] ?? '') . ' ' . ($limit['last_name'] ?? '')) ?: 'System') ?></td>
                            <td class="px-5 py-3.5 text-right">
                                <form method="POST" class="inline">
                                    <?php if ($active_tab === 'counseling'): ?>
                                        <input type="hidden" name="remove_limit" value="1">
                                        <input type="hidden" name="remove_date" value="<?= htmlspecialchars($limit['date']) ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="remove_exam_limit" value="1">
                                        <input type="hidden" name="remove_exam_date" value="<?= htmlspecialchars($limit['date']) ?>">
                                    <?php endif; ?>
                                    <button class="px-3 py-1.5 text-xs bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium transition-colors" onclick="return confirm('Remove custom limit for <?= date('M d, Y', strtotime($limit['date'])) ?>?')"><i class="fas fa-trash-alt mr-1"></i>Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($rows)): ?>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
            <?php if ($active_tab === 'counseling'): ?>
                Page <?= $counseling_page ?> of <?= $total_counseling_pages ?> (<?= $total_counseling ?> total)
            <?php else: ?>
                Page <?= $exam_page ?> of <?= $total_exam_pages ?> (<?= $total_exams ?> total)
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
