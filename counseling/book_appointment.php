<?php
$in_layout = defined('IN_LAYOUT');

if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    require_once '../classes/CounselingAppointment.php';
    require_once '../classes/SystemSettings.php';
    require_once '../classes/Notification.php';
    require_once '../classes/User.php';
    checkLogin();
    checkRole(['student']);
    
    $database = new Database();
    $db = $database->getConnection();
} else {
    require_once __DIR__ . '/../classes/CounselingAppointment.php';
    require_once __DIR__ . '/../classes/SystemSettings.php';
    require_once __DIR__ . '/../classes/Notification.php';
    require_once __DIR__ . '/../classes/User.php';
    // $db is already available from layout.php
}

// Check system settings
$settings = new SystemSettings($db);
if(!$settings->isCounselingEnabled()) {
    $redirect = $in_layout ? '../dashboard/layout.php?page=dashboard&error=counseling_disabled' : '../dashboard/index.php?error=counseling_disabled';
    header("Location: $redirect");
    exit();
}

$user_info = getUserInfo();
$success_message = '';
$error_message = '';

// Check if user already has an active appointment
$counseling = new CounselingAppointment($db);
if($counseling->hasActiveAppointment($user_info['id'])) {
    $redirect = $in_layout ? 'layout.php?page=view_appointments' : 'view_appointments.php';
    header("Location: $redirect");
    exit();
}

// Handle POST for booking appointment
if($_POST) {
    $counseling->user_id = $user_info['id'];
    $counseling->appointment_date = $_POST['appointment_date'] ?? '';
    $counseling->appointment_time = $_POST['appointment_time'] ?? '';
    $counseling->concern_type = $_POST['concern_type'] ?? '';
    $counseling->concern_description = $_POST['concern_description'] ?? '';
    $counseling->urgency_level = $_POST['urgency_level'] ?? '';
    $counseling->nature_of_contact = 'walk-in';
    $counseling->session_duration = 60;
    $counseling->original_appointment_id = null;

    // Validate date is not in the past
    if(strtotime($counseling->appointment_date) < strtotime(date('Y-m-d'))) {
        $error_message = "Please select a future date for your appointment.";
    } elseif (empty($counseling->concern_type) || empty($counseling->concern_description) || empty($counseling->urgency_level)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $appointment_id = $counseling->create();
        if($appointment_id) {
            $success_message = "Your counseling appointment has been booked successfully! You will be notified once a counselor is assigned.";
            
            // Create notification for guidance advocates
            $notification = new Notification($db);
            $user = new User($db);
            $advocates_result = $user->getUsersByRole('guidance_advocate');
            
            while($advocate = $advocates_result->fetch(PDO::FETCH_ASSOC)) {
                $notification->user_id = $advocate['id'];
                $notification->title = "New Counseling Appointment";
                $notification->message = "New counseling appointment from " . $user_info['first_name'] . " " . $user_info['last_name'];
                $notification->type = "info";
                $notification->related_table = "counseling_appointments";
                $notification->related_id = $appointment_id;
                $notification->create();
            }
        } else {
            $error_message = "Failed to submit your appointment request. Please try again.";
        }
    }
}

// Get calendar data
$current_month = date('n');
$current_year = date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// Get user's existing appointments for calendar display
$my_appointments = [];
try {
    $my_appts_stmt = $db->prepare("SELECT id, appointment_date, appointment_time, status FROM counseling_appointments WHERE user_id = ? AND status IN ('confirmed','in_progress','pending') ORDER BY appointment_date");
    $my_appts_stmt->execute([$user_info['id']]);
    while ($row = $my_appts_stmt->fetch(PDO::FETCH_ASSOC)) {
        $my_appointments[$row['appointment_date']][] = $row;
    }
} catch (Exception $e) {}

// Get booked appointments (other students) for availability display
$booked_slots = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $booked_stmt = $db->prepare("SELECT appointment_date, appointment_time, COUNT(*) as count FROM counseling_appointments WHERE appointment_date BETWEEN ? AND ? AND status IN ('confirmed','in_progress','pending') GROUP BY appointment_date, appointment_time");
    $booked_stmt->execute([$start_date, $end_date]);
    while ($row = $booked_stmt->fetch(PDO::FETCH_ASSOC)) {
        $booked_slots[$row['appointment_date']][$row['appointment_time']] = $row['count'];
    }
} catch (Exception $e) {}

// Build lookup: dates that have holiday
$holiday_dates = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    try {
        $stmt = $db->prepare("SELECT date FROM holidays WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $holiday_dates[] = $row['date']; }
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $holiday_dates[] = $row['holiday_date']; }
        } catch (Exception $e2) {}
    }
} catch (Exception $e) {}

// Stats
$events_this_month = 0;
$todays_events = 0;
$holidays_this_month = count($holiday_dates);
try {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?");
        $stmt->execute([$month, $year]);
        $events_this_month = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE MONTH(start_datetime) = ? AND YEAR(start_datetime) = ? AND is_active = 1");
            $stmt->execute([$month, $year]);
            $events_this_month = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e2) { $events_this_month = 0; }
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()");
        $stmt->execute();
        $todays_events = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE DATE(start_datetime) = CURDATE() AND is_active = 1");
            $stmt->execute();
            $todays_events = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e2) { $todays_events = 0; }
    }
} catch (Exception $e) {}

// Get events and holidays for calendar
$calendar_events = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Try events table first
    try {
        $stmt = $db->prepare("SELECT event_date, event_name, event_type FROM events WHERE event_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calendar_events[$row['event_date']][] = $row;
        }
    } catch (Exception $e) {
        // Fallback to schedules table
        try {
            $stmt = $db->prepare("SELECT DATE(start_datetime) as event_date, title as event_name, event_type FROM schedules WHERE DATE(start_datetime) BETWEEN ? AND ? AND is_active = 1");
            $stmt->execute([$start_date, $end_date]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $calendar_events[$row['event_date']][] = $row;
            }
        } catch (Exception $e2) {}
    }
    
    // Holidays - try both column names
    try {
        $stmt = $db->prepare("SELECT date as holiday_date, name as holiday_name FROM holidays WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calendar_events[$row['holiday_date']][] = ['event_name' => $row['holiday_name'], 'event_type' => 'holiday'];
        }
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $calendar_events[$row['holiday_date']][] = ['event_name' => $row['holiday_name'], 'event_type' => 'holiday'];
            }
        } catch (Exception $e2) {}
    }
} catch (Exception $e) {}

// Calendar generation
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_weekday = date('w', $first_day);
$month_name = date('F Y', $first_day);

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
$book_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';

if (!$in_layout) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Counseling - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#2563eb', 'primary-dark': '#1d4ed8' } } } }</script>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Topbar -->
    <div class="bg-primary text-white py-3 px-4 flex items-center justify-between shadow-lg">
        <a href="../dashboard/index.php" class="flex items-center gap-2 text-white hover:text-white/80"><i class="fas fa-graduation-cap"></i><span class="font-bold text-sm">SRCB Guidance</span></a>
        <div class="flex items-center gap-3">
            <span class="text-sm text-white/70">Welcome, <?= htmlspecialchars($user_info['first_name']) ?></span>
            <a href="../auth/logout.php" class="text-white/70 hover:text-white text-sm"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>

    <div class="p-5">
<?php } ?>
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-primary"><i class="fas fa-calendar-plus mr-2"></i>Book Counseling Appointment</h1>
            <p class="text-sm text-gray-400">Schedule a session with our guidance counselors</p>
        </div>
        <button onclick="openBookingModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors inline-flex items-center gap-2">
            <i class="fas fa-plus"></i>Book Appointment
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $events_this_month ?></div>
            <div class="text-sm text-white/80">Events This Month</div>
        </div>
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $todays_events ?></div>
            <div class="text-sm text-white/80">Today's Events</div>
        </div>
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $holidays_this_month ?></div>
            <div class="text-sm text-white/80">Holidays This Month</div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-5">
        <!-- Calendar Section -->
        <div class="flex-1">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-gray-500 mb-1 block">Search Events</label>
                        <input type="text" placeholder="Type to filter events..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                    </div>
                    <div class="w-full md:w-48">
                        <label class="text-xs text-gray-500 mb-1 block">Event Type</label>
                        <select class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                            <option>All Types</option>
                            <option>Guidance Events</option>
                            <option>Entrance Exams</option>
                            <option>Holidays</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <i class="fas fa-broom mr-1"></i>Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <a href="?<?= $in_layout ? 'page=book_appointment&' : '' ?>month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center hover:bg-blue-600 transition-colors">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </a>
                        <a href="?<?= $in_layout ? 'page=book_appointment&' : '' ?>month=<?= $next_month ?>&year=<?= $next_year ?>" class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center hover:bg-blue-600 transition-colors">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </a>
                        <a href="?<?= $in_layout ? 'page=book_appointment' : '' ?>" class="px-3 py-1.5 rounded-lg bg-blue-500 text-white text-sm hover:bg-blue-600 transition-colors">today</a>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800"><?= $month_name ?></h2>
                    <div class="flex items-center gap-1">
                        <button class="px-3 py-1.5 rounded-lg bg-blue-500 text-white text-sm">month</button>
                        <button class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition-colors">week</button>
                        <button class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition-colors">day</button>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <!-- Weekday Headers -->
                    <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                        <?php $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; ?>
                        <?php foreach ($weekdays as $day): ?>
                            <div class="py-2 text-center text-xs font-semibold text-gray-600"><?= $day ?></div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Days -->
                    <div class="grid grid-cols-7">
                        <?php
                        $today_str = date('Y-m-d');
                        // Empty cells before start
                        for ($i = 0; $i < $start_weekday; $i++) {
                            echo '<div class="h-24 border-r border-b border-gray-100 bg-gray-50/50"></div>';
                        }
                        
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $is_today = $date === $today_str;
                            $day_events = $calendar_events[$date] ?? [];
                            $is_holiday = in_array($date, $holiday_dates);
                            foreach ($day_events as $evt) {
                                if (($evt['event_type'] ?? '') === 'holiday') $is_holiday = true;
                            }
                            $is_past = $date < $today_str;
                            $is_sunday = date('w', strtotime($date)) === '0'; // Sunday = 0
                            $is_bookable = !$is_past && !$is_holiday && !$is_sunday;
                            $has_my_appt = isset($my_appointments[$date]);
                            $has_bookings = isset($booked_slots[$date]);
                            $booked_count = $has_bookings ? count($booked_slots[$date]) : 0;
                            ?>
                            <div 
                                class="h-24 border-r border-b border-gray-100 p-1 relative transition-colors
                                    <?= $is_today ? 'bg-blue-50/50' : '' ?>
                                    <?= $is_holiday ? 'bg-red-50/30' : '' ?>
                                    <?= $is_past ? 'bg-gray-50/50' : '' ?>
                                    <?= $is_bookable ? 'cursor-pointer hover:bg-blue-50 hover:border-blue-300 group' : '' ?>
                                    <?= $has_my_appt ? 'ring-2 ring-inset ring-green-400' : '' ?>
                                "
                                <?php if ($is_bookable): ?>
                                onclick="selectDate('<?= $date ?>')"
                                <?php endif; ?>
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium 
                                        <?= $is_today ? 'bg-blue-500 text-white w-6 h-6 rounded-full flex items-center justify-center' : '' ?>
                                        <?= !$is_today && $is_holiday ? 'text-red-600' : '' ?>
                                        <?= !$is_today && $is_past ? 'text-gray-300' : '' ?>
                                        <?= !$is_today && !$is_holiday && !$is_past && $is_bookable ? 'text-gray-700' : '' ?>
                                        <?= !$is_today && !$is_bookable && !$is_holiday && !$is_past ? 'text-gray-400' : '' ?>
                                    "><?= $day ?></span>
                                    <?php if ($is_bookable): ?>
                                    <span class="text-[9px] text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity">+Book</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    <?php if ($has_my_appt): ?>
                                        <div class="text-[10px] px-1.5 py-0.5 rounded truncate bg-green-100 text-green-700">
                                            <i class="fas fa-calendar-check mr-1"></i>My Appt
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($day_events, 0, 2) as $evt): ?>
                                        <div class="text-[10px] px-1.5 py-0.5 rounded truncate <?= 
                                            ($evt['event_type'] ?? '') === 'holiday' ? 'bg-red-100 text-red-700' : (
                                            ($evt['event_type'] ?? '') === 'exam' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700')
                                        ?>">
                                            <i class="fas fa-<?= ($evt['event_type'] ?? '') === 'holiday' ? 'calendar' : (($evt['event_type'] ?? '') === 'exam' ? 'file-alt' : 'circle') ?> mr-1"></i>
                                            <?= htmlspecialchars(substr($evt['event_name'], 0, 15)) ?><?= strlen($evt['event_name']) > 15 ? '...' : '' ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($day_events) > 2): ?>
                                        <div class="text-[10px] text-gray-400 px-1">+<?= count($day_events) - 2 ?> more</div>
                                    <?php endif; ?>
                                    <?php if ($is_holiday): ?>
                                        <div class="text-[9px] text-red-500 font-medium">Holiday</div>
                                    <?php elseif ($is_sunday && !$is_past): ?>
                                        <div class="text-[9px] text-gray-400">Closed</div>
                                    <?php elseif ($is_past): ?>
                                        <div class="text-[9px] text-gray-300">Past</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="w-full lg:w-72 space-y-4">
            <!-- Calendar Legend -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>Calendar Legend
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                        <span>Your Appointments</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                        <span>My Counseling Sessions</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-pink-400"></span>
                        <span>Unavailable Dates</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                        <span>Holidays - No bookings</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                        <span>Booked Slots - Other students</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-green-600"></span>
                        <span>Entrance Exams - Reserved</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-blue-400"></span>
                        <span>Guidance Events</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                        <span>PDS Period</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-violet-400"></span>
                        <span>Intake Interview</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-400"></span>
                        <span>Exit Interview</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-cyan-400"></span>
                        <span>Staff Meetings</span>
                    </div>
                </div>
            </div>

            <!-- How to Book -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2">
                    <i class="fas fa-question-circle"></i>How to Book
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-user-check text-blue-500 mt-0.5"></i>
                        <div>
                            <span class="font-medium">Before Booking:</span>
                            <p class="text-xs text-gray-500">Ensure your Academic Information is complete in your profile</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center flex-shrink-0">1</span>
                        <div>
                            <span class="font-medium">Step 1:</span>
                            <p class="text-xs text-gray-500">Select an available date from the calendar</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center flex-shrink-0">2</span>
                        <div>
                            <span class="font-medium">Step 2:</span>
                            <p class="text-xs text-gray-500">Pick your preferred time slot</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-primary"><i class="fas fa-calendar-plus mr-2"></i>Book Counseling Appointment</h2>
                <button onclick="closeBookingModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" id="bookingForm">
                <!-- Student Info -->
                <div class="bg-blue-50/50 rounded-xl p-4 mb-4 border-l-4 border-primary">
                    <h3 class="font-semibold text-primary text-sm mb-3"><i class="fas fa-user mr-1"></i>Student Information</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-400 text-xs">Name</span><p class="font-medium"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></p></div>
                        <div><span class="text-gray-400 text-xs">Email</span><p class="font-medium"><?= htmlspecialchars($user_info['email'] ?? 'N/A') ?></p></div>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="mb-4">
                    <h3 class="font-semibold text-primary text-sm mb-3"><i class="fas fa-calendar mr-1"></i>Selected Schedule</h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Date <span class="text-red-500">*</span></label>
                            <input type="date" id="appointmentDate" name="appointment_date" min="<?= date('Y-m-d') ?>" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Time <span class="text-red-500">*</span></label>
                            <select name="appointment_time" id="appointmentTime" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                                <option value="">Select Date First</option>
                            </select>
                        </div>
                    </div>
                    <!-- Time Slots Availability -->
                    <div id="timeSlotsContainer" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                        <div id="timeSlots" class="grid grid-cols-4 gap-2 mb-2"></div>
                    </div>
                    <div id="dateInfo" class="hidden bg-blue-50 rounded-lg p-3 text-sm text-blue-700 flex items-start gap-2">
                        <i class="fas fa-info-circle mt-0.5"></i>
                        <span id="dateInfoText">Your preferred schedule is subject to counselor availability.</span>
                    </div>
                </div>

                <!-- Concern Details -->
                <div class="mb-4">
                    <h3 class="font-semibold text-primary text-sm mb-3"><i class="fas fa-heart mr-1"></i>Counseling Details</h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type of Concern <span class="text-red-500">*</span></label>
                            <select name="concern_type" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                                <option value="">Select Concern Type</option>
                                <option value="academic">Academic</option>
                                <option value="personal">Personal</option>
                                <option value="behavioral">Behavioral</option>
                                <option value="career">Career Guidance</option>
                                <option value="family">Family Issues</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Urgency Level <span class="text-red-500">*</span></label>
                            <select name="urgency_level" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                                <option value="">Select Urgency</option>
                                <option value="low">Low - Can wait a week</option>
                                <option value="medium">Medium - Within few days</option>
                                <option value="high">High - As soon as possible</option>
                                <option value="urgent">Urgent - Immediate attention</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description of Concern <span class="text-red-500">*</span></label>
                        <textarea name="concern_description" rows="3" required placeholder="Please describe your concern in detail. This information will be kept confidential." class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all resize-none"></textarea>
                        <p class="text-xs text-gray-400 mt-1"><i class="fas fa-lock mr-1"></i>Your information will be kept confidential.</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeBookingModal()" class="flex-1 py-3 rounded-lg border-2 border-gray-200 text-gray-500 font-semibold text-sm hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="flex-1 py-3 rounded-lg bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-colors">
                        <i class="fas fa-paper-plane mr-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// All time slots available for booking
const allTimeSlots = [
    { value: '08:00:00', label: '8:00 AM' },
    { value: '09:00:00', label: '9:00 AM' },
    { value: '10:00:00', label: '10:00 AM' },
    { value: '11:00:00', label: '11:00 AM' },
    { value: '13:00:00', label: '1:00 PM' },
    { value: '14:00:00', label: '2:00 PM' },
    { value: '15:00:00', label: '3:00 PM' },
    { value: '16:00:00', label: '4:00 PM' }
];

// Booked slots from PHP (keyed by date then time)
const bookedSlots = <?= json_encode($booked_slots) ?>;
const myAppointments = <?= json_encode($my_appointments) ?>;
const holidayDates = <?= json_encode($holiday_dates) ?>;

function openBookingModal() {
    document.getElementById('bookingModal').classList.remove('hidden');
    document.getElementById('bookingModal').classList.add('flex');
}

function closeBookingModal() {
    document.getElementById('bookingModal').classList.add('hidden');
    document.getElementById('bookingModal').classList.remove('flex');
}

// Click a calendar date to book
function selectDate(dateStr) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selected = new Date(dateStr + 'T00:00:00');
    
    // Validate: no past dates
    if (selected < today) {
        Swal.fire({ icon: 'warning', title: 'Invalid Date', text: 'Cannot book appointments for past dates.', confirmButtonColor: '#2563eb' });
        return;
    }
    
    // Validate: no Sundays
    if (selected.getDay() === 0) {
        Swal.fire({ icon: 'warning', title: 'Sunday', text: 'Counseling is not available on Sundays. Please select a different date.', confirmButtonColor: '#2563eb' });
        return;
    }
    
    // Validate: no holidays
    if (holidayDates.includes(dateStr)) {
        Swal.fire({ icon: 'warning', title: 'Holiday', text: 'Cannot book appointments on holidays. Please select a different date.', confirmButtonColor: '#2563eb' });
        return;
    }
    
    // Open modal and set date
    openBookingModal();
    const dateInput = document.getElementById('appointmentDate');
    dateInput.value = dateStr;
    
    // Load time slots for this date
    loadTimeSlots(dateStr);
    
    // Show date info
    const dateInfo = document.getElementById('dateInfo');
    const dateInfoText = document.getElementById('dateInfoText');
    const formattedDate = selected.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    dateInfoText.textContent = `Selected: ${formattedDate}. Choose an available time slot below.`;
    dateInfo.classList.remove('hidden');
}

// Load available time slots for a given date
function loadTimeSlots(dateStr) {
    const timeSelect = document.getElementById('appointmentTime');
    const slotsContainer = document.getElementById('timeSlotsContainer');
    const slotsDiv = document.getElementById('timeSlots');
    
    const dateBookings = bookedSlots[dateStr] || {};
    const dateMyAppts = myAppointments[dateStr] || [];
    
    // Clear existing options
    timeSelect.innerHTML = '<option value="">Select a time slot</option>';
    slotsDiv.innerHTML = '';
    
    let availableCount = 0;
    
    allTimeSlots.forEach(slot => {
        const bookedCount = dateBookings[slot.value] || 0;
        const isMySlot = dateMyAppts.some(a => a.appointment_time === slot.value);
        // Consider a slot "full" if 3+ bookings (adjust threshold as needed)
        const isFull = bookedCount >= 3;
        
        // Add to select dropdown
        if (!isFull) {
            const opt = document.createElement('option');
            opt.value = slot.value;
            opt.textContent = slot.label + (bookedCount > 0 ? ` (${bookedCount} booked)` : '');
            timeSelect.appendChild(opt);
            availableCount++;
        }
        
        // Add visual time slot button
        const slotBtn = document.createElement('button');
        slotBtn.type = 'button';
        slotBtn.className = `px-3 py-2 rounded-lg text-xs font-medium border-2 transition-all text-center ${
            isMySlot ? 'bg-green-100 border-green-400 text-green-700 cursor-default' :
            isFull ? 'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through' :
            'bg-white border-gray-200 text-gray-700 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700 cursor-pointer'
        }`;
        slotBtn.innerHTML = `${slot.label}${isMySlot ? '<br><span class=\'text-[9px]\'>Your Appt</span>' : ''}${isFull ? '<br><span class=\'text-[9px]\'>Full</span>' : ''}${!isFull && !isMySlot && bookedCount > 0 ? `<br><span class=\'text-[9px]\'>${bookedCount} booked</span>` : ''}`;
        
        if (!isFull && !isMySlot) {
            slotBtn.onclick = function() {
                // Highlight selected
                document.querySelectorAll('#timeSlots button').forEach(b => {
                    if (!b.classList.contains('bg-green-100') && !b.classList.contains('bg-red-50')) {
                        b.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-700');
                        b.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                    }
                });
                this.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
                this.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-700');
                // Set the select value
                timeSelect.value = slot.value;
            };
        }
        
        slotsDiv.appendChild(slotBtn);
    });
    
    slotsContainer.classList.remove('hidden');
    
    if (availableCount === 0) {
        const noSlots = document.createElement('div');
        noSlots.className = 'col-span-4 text-center text-sm text-red-500 py-2';
        noSlots.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>No available time slots for this date. Please choose another date.';
        slotsDiv.appendChild(noSlots);
    }
}

// When date input changes manually
const dateInput = document.getElementById('appointmentDate');
if (dateInput) {
    dateInput.addEventListener('change', function() {
        if (this.value) {
            loadTimeSlots(this.value);
            const dateInfo = document.getElementById('dateInfo');
            const dateInfoText = document.getElementById('dateInfoText');
            const selected = new Date(this.value + 'T00:00:00');
            const formattedDate = selected.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            dateInfoText.textContent = `Selected: ${formattedDate}. Choose an available time slot below.`;
            dateInfo.classList.remove('hidden');
        }
    });
}

// Close modal when clicking outside
function closeModalOnClickOutside(event) {
    const modal = document.getElementById('bookingModal');
    if (event.target === modal) {
        closeBookingModal();
    }
}

// Add click listener to modal background
document.getElementById('bookingModal').addEventListener('click', closeModalOnClickOutside);

// Validate form before submit
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const dateVal = document.getElementById('appointmentDate').value;
    const timeVal = document.getElementById('appointmentTime').value;
    
    if (!dateVal) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select a date for your appointment.', confirmButtonColor: '#2563eb' });
        return;
    }
    if (!timeVal) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Missing Time', text: 'Please select a time slot for your appointment.', confirmButtonColor: '#2563eb' });
        return;
    }
    
    // Check Sunday
    const selected = new Date(dateVal + 'T00:00:00');
    if (selected.getDay() === 0) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Sunday', text: 'Counseling is not available on Sundays.', confirmButtonColor: '#2563eb' });
        return;
    }
    // Check holiday
    if (holidayDates.includes(dateVal)) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Holiday', text: 'Cannot book appointments on holidays.', confirmButtonColor: '#2563eb' });
        return;
    }
    
    // Show confirmation
    e.preventDefault();
    const concernType = this.querySelector('[name="concern_type"]').value;
    const urgency = this.querySelector('[name="urgency_level"]').value;
    const formattedDate = selected.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeLabel = allTimeSlots.find(s => s.value === timeVal)?.label || timeVal;
    
    Swal.fire({
        title: 'Confirm Appointment?',
        html: `<div class="text-left text-sm">
            <p><strong>Date:</strong> ${formattedDate}</p>
            <p><strong>Time:</strong> ${timeLabel}</p>
            <p><strong>Concern:</strong> ${concernType.charAt(0).toUpperCase() + concernType.slice(1)}</p>
            <p><strong>Urgency:</strong> ${urgency.charAt(0).toUpperCase() + urgency.slice(1)}</p>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Book It!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('bookingForm').submit();
        }
    });
});

<?php if ($error_message): ?>
openBookingModal();
<?php endif; ?>

<?php if ($success_message): ?>
Swal.fire({ 
    icon: 'success', 
    title: 'Appointment Booked!', 
    text: '<?= addslashes($success_message) ?>', 
    confirmButtonColor: '#2563eb' 
}).then(() => { 
    location.href = '<?= $in_layout ? "layout.php?page=view_appointments" : "view_appointments.php" ?>'; 
});
<?php endif; ?>
</script>

<?php if (!$in_layout): ?>
</body>
</html>
<?php endif; ?>
