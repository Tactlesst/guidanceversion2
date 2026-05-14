<?php
// Book Entrance Exam - Examinee page
// Loaded by layout.php - session/db already set up

if (!defined('IN_LAYOUT')) die('Direct access not allowed');

// Check if entrance exam is enabled
try {
    require_once __DIR__ . '/../../../classes/SystemSettings.php';
    require_once __DIR__ . '/../../../classes/Holiday.php';
    $settings = new SystemSettings($db);
    $holiday = new Holiday($db);
    if(!$settings->isEntranceExamEnabled()) {
        echo '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-5"><i class="fas fa-exclamation-triangle mr-2"></i>Entrance exam booking is currently disabled.</div>';
        echo '<a href="layout.php?page=dashboard" class="text-primary font-semibold hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>';
        return;
    }
} catch (Exception $e) { $holiday = null; }

$success_message = '';
$error_message = '';

// Handle success message from redirect
if(isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Your entrance exam appointment has been booked successfully! Your selected slot is already confirmed.";
}

// Check if user already has completed entrance exam results
$completed_exam_query = "SELECT ea.id, ea.status, ea.preferred_date, ea.preferred_time,
                                ea.total_score, ea.qualified_grade, ea.updated_at
                         FROM entrance_exam_appointments ea
                         WHERE ea.user_id = ? 
                         AND ea.status = 'completed' 
                         AND (ea.total_score IS NOT NULL OR ea.qualified_grade IS NOT NULL)
                         ORDER BY ea.updated_at DESC
                         LIMIT 1";
$completed_exam_stmt = $db->prepare($completed_exam_query);
$completed_exam_stmt->execute([$uid]);
$completed_exam = $completed_exam_stmt->fetch(PDO::FETCH_ASSOC);

// If user has completed exam with results, redirect to results page
if($completed_exam) {
    echo '<script>window.location.href = "layout.php?page=view_exam_results&message=exam_already_completed";</script>';
    return;
}

// Check if user has an active appointment (confirmed or awaiting_results)
$active_exam_query = "SELECT ea.id, ea.status, ea.preferred_date, ea.preferred_time
                      FROM entrance_exam_appointments ea
                      WHERE ea.user_id = ? 
                      AND ea.status IN ('confirmed', 'awaiting_results')
                      ORDER BY ea.preferred_date ASC
                      LIMIT 1";
$active_exam_stmt = $db->prepare($active_exam_query);
$active_exam_stmt->execute([$uid]);
$active_exam = $active_exam_stmt->fetch(PDO::FETCH_ASSOC);
$has_active_exam = !empty($active_exam);

// Handle form submission
if($_POST && isset($_POST['book_appointment'])) {
    if($has_active_exam) {
        $error_message = "You already have an active entrance exam appointment. You cannot book a new appointment until your current appointment is completed or marked as missed.";
    } else {
        require_once __DIR__ . '/../../../classes/EntranceExam.php';
        $entrance_exam = new EntranceExam($db);
        
        $entrance_exam->user_id = $uid;
        $entrance_exam->preferred_date = $_POST['preferred_date'];
        $entrance_exam->preferred_time = $_POST['preferred_time'] ?? '';
        $entrance_exam->grade_level_applying = $_POST['grade_level_applying'];
        $entrance_exam->previous_school = $_POST['previous_school'];
        $entrance_exam->preferred_program = $_POST['preferred_program'] ?? null;

        if(strtotime($entrance_exam->preferred_date) < strtotime(date('Y-m-d'))) {
            $error_message = "Please select a future date for your appointment.";
        }
        if (empty($error_message) && empty($entrance_exam->preferred_time)) {
            $error_message = "Please select a time for your entrance exam appointment.";
        }
        if (empty($error_message)) {
            $existing_exam_check = "SELECT COUNT(*) as count FROM entrance_exam_appointments 
                                   WHERE user_id = ? AND preferred_date = ? 
                                   AND status IN ('confirmed', 'rescheduled', 'awaiting_results')";
            $existing_exam_stmt = $db->prepare($existing_exam_check);
            $existing_exam_stmt->execute([$uid, $entrance_exam->preferred_date]);
            $existing_exam_count = $existing_exam_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($existing_exam_count > 0) {
                $error_message = "You already have an entrance exam scheduled for " . date('F j, Y', strtotime($entrance_exam->preferred_date)) . ".";
            }
        }
        
        if(empty($error_message)) {
            $appointment_id = $entrance_exam->create();
            if($appointment_id) {
                require_once __DIR__ . '/../../../classes/Notification.php';
                require_once __DIR__ . '/../../../classes/NotificationService.php';
                $notification = new Notification($db);
                $notificationService = new NotificationService($db);
                $staff_query = "SELECT id FROM users WHERE role IN ('guidance_advocate', 'admin') AND is_active = 1";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->execute();
                while($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $applicant_name = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
                    $notificationService->notify(
                        (int)$staff['id'], 'New Entrance Exam Application',
                        "New entrance exam application from {$applicant_name}",
                        'info', 'entrance_exam_appointments', (int)$appointment_id,
                        false, null, null,
                        'entrance_exam_application:' . (int)$appointment_id . ':staff:' . (int)$staff['id']
                    );
                }
                echo '<script>window.location.href = "layout.php?page=book_exam&success=1";</script>';
                return;
            } else {
                $error_message = "Failed to submit your application. Please try again.";
            }
        }
    }
}

// Calendar navigation
$current_month = date('n'); $current_year = date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$first_day_ts = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day_ts);
$start_weekday = date('w', $first_day_ts);
$month_name = date('F Y', $first_day_ts);
$prev_month = $month - 1; $prev_year = $year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year; if ($next_month > 12) { $next_month = 1; $next_year++; }

// Build holiday lookup
$holiday_dates = [];
$start_date = "$year-$month-01"; $end_date = date('Y-m-t', strtotime($start_date));
try { $h_stmt = $db->prepare("SELECT date, name FROM holidays WHERE date BETWEEN ? AND ?"); $h_stmt->execute([$start_date, $end_date]); while ($hr = $h_stmt->fetch(PDO::FETCH_ASSOC)) { $holiday_dates[$hr['date']] = $hr['name']; } } catch (Exception $e) {}

// Get existing exam appointments for calendar display
$my_exams = [];
try {
    $my_stmt = $db->prepare("SELECT id, preferred_date, preferred_time, status FROM entrance_exam_appointments WHERE user_id = ? AND status IN ('confirmed','awaiting_results') ORDER BY preferred_date");
    $my_stmt->execute([$uid]);
    while ($row = $my_stmt->fetch(PDO::FETCH_ASSOC)) { $my_exams[$row['preferred_date']][] = $row; }
} catch (Exception $e) {}

// Get booked exam slots for the month
$booked_slots = [];
try {
    $bk_stmt = $db->prepare("SELECT preferred_date, preferred_time, COUNT(*) as count FROM entrance_exam_appointments WHERE preferred_date BETWEEN ? AND ? AND status IN ('confirmed','awaiting_results') GROUP BY preferred_date, preferred_time");
    $bk_stmt->execute([$start_date, $end_date]);
    while ($row = $bk_stmt->fetch(PDO::FETCH_ASSOC)) { $booked_slots[$row['preferred_date']][$row['preferred_time']] = $row['count']; }
} catch (Exception $e) {}

// Get scheduled entrance exam events
$exam_events = [];
try {
    $ev_stmt = $db->prepare("SELECT DATE(start_datetime) as event_date, title, event_type FROM schedules WHERE event_type = 'entrance_exam' AND DATE(start_datetime) BETWEEN ? AND ? AND is_active = 1");
    $ev_stmt->execute([$start_date, $end_date]);
    while ($row = $ev_stmt->fetch(PDO::FETCH_ASSOC)) { $exam_events[$row['event_date']][] = $row; }
} catch (Exception $e) {}

// Stats
$exams_this_month = 0;
foreach ($booked_slots as $d => $slots) { foreach ($slots as $cnt) { $exams_this_month += $cnt; } }
$holidays_this_month = count($holiday_dates);

$today_str = date('Y-m-d');
$min_date = $today_str;
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-primary"><i class="fas fa-clipboard-list mr-2"></i>Book Entrance Exam</h1>
            <p class="text-sm text-gray-400">Schedule your entrance examination</p>
        </div>
        <?php if(!$has_active_exam): ?>
        <button onclick="openBookingModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors inline-flex items-center gap-2">
            <i class="fas fa-plus"></i>Book Exam
        </button>
        <?php endif; ?>
    </div>

    <!-- Alerts -->
    <?php if($success_message): ?>
    <div class="bg-green-50 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm flex items-center gap-2"><i class="fas fa-check-circle"></i><?= $success_message ?></div>
    <?php endif; ?>
    <?php if($error_message): ?>
    <div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i><?= $error_message ?></div>
    <?php endif; ?>

    <?php if($has_active_exam): ?>
    <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 px-4 py-3 rounded-lg mb-5">
        <i class="fas fa-info-circle mr-1"></i>You have an active entrance exam scheduled for <strong><?= date('F j, Y', strtotime($active_exam['preferred_date'])) ?> at <?= date('g:i A', strtotime($active_exam['preferred_time'])) ?></strong>.
        <a href="layout.php?page=view_application" class="text-blue-600 underline ml-1">View Status</a>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-cyan-500 to-cyan-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $exams_this_month ?></div>
            <div class="text-sm text-white/80">Exam Slots Booked This Month</div>
        </div>
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= count($exam_events) ?></div>
            <div class="text-sm text-white/80">Exam Schedule Events</div>
        </div>
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $holidays_this_month ?></div>
            <div class="text-sm text-white/80">Holidays This Month</div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-5">
        <!-- Calendar Section -->
        <div class="flex-1">
            <!-- Calendar -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <a href="?page=book_exam&month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition-colors">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </a>
                        <a href="?page=book_exam&month=<?= $next_month ?>&year=<?= $next_year ?>" class="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition-colors">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </a>
                        <a href="?page=book_exam" class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm hover:bg-primary-dark transition-colors">today</a>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800"><?= $month_name ?></h2>
                    <div></div>
                </div>

                <!-- Calendar Grid -->
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd): ?>
                            <div class="py-2 text-center text-xs font-semibold text-gray-600"><?= $wd ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="grid grid-cols-7">
                        <?php for($i=0; $i<$start_weekday; $i++) echo '<div class="h-24 border-r border-b border-gray-100 bg-gray-50/50"></div>'; ?>
                        <?php for($day=1; $day<=$days_in_month; $day++):
                            $ds = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $is_today = $ds === $today_str;
                            $is_holiday = isset($holiday_dates[$ds]);
                            $holiday_name = $holiday_dates[$ds] ?? '';
                            $is_past = $ds < $today_str;
                            $is_sunday = date('w', strtotime($ds)) === '0';
                            $is_bookable = !$is_past && !$is_holiday && !$is_sunday && !$has_active_exam;
                            $has_my_exam = isset($my_exams[$ds]);
                            $day_exam_events = $exam_events[$ds] ?? [];
                        ?>
                            <div class="h-24 border-r border-b border-gray-100 p-1 relative transition-colors
                                <?= $is_today ? 'bg-blue-50/50' : '' ?>
                                <?= $is_holiday ? 'bg-red-50/30' : '' ?>
                                <?= $is_past ? 'bg-gray-50/50' : '' ?>
                                <?= $is_bookable ? 'cursor-pointer hover:bg-cyan-50 hover:border-cyan-300 group' : '' ?>
                                <?= $has_my_exam ? 'ring-2 ring-inset ring-cyan-400' : '' ?>
                            " <?php if ($is_bookable): ?>onclick="selectDate('<?= $ds ?>')"<?php endif; ?>>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium
                                        <?= $is_today ? 'bg-primary text-white w-6 h-6 rounded-full flex items-center justify-center' : '' ?>
                                        <?= !$is_today && $is_holiday ? 'text-red-600' : '' ?>
                                        <?= !$is_today && $is_past ? 'text-gray-300' : '' ?>
                                        <?= !$is_today && !$is_holiday && !$is_past && $is_bookable ? 'text-gray-700' : '' ?>
                                        <?= !$is_today && !$is_bookable && !$is_holiday && !$is_past ? 'text-gray-400' : '' ?>
                                    "><?= $day ?></span>
                                    <?php if ($is_bookable): ?>
                                    <span class="text-[9px] text-cyan-400 opacity-0 group-hover:opacity-100 transition-opacity">+Book</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    <?php if ($has_my_exam): ?>
                                    <div class="text-[10px] px-1.5 py-0.5 rounded truncate bg-cyan-100 text-cyan-700">
                                        <i class="fas fa-clipboard-check mr-1"></i>My Exam
                                    </div>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($day_exam_events, 0, 2) as $evt): ?>
                                    <div class="text-[10px] px-1.5 py-0.5 rounded truncate bg-cyan-100 text-cyan-700">
                                        <i class="fas fa-file-alt mr-1"></i><?= htmlspecialchars(substr($evt['title'], 0, 15)) ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if($is_holiday): ?>
                                    <div class="text-[9px] text-red-500 font-medium">Holiday</div>
                                    <?php elseif($is_sunday && !$is_past): ?>
                                    <div class="text-[9px] text-gray-400">Closed</div>
                                    <?php elseif($is_past): ?>
                                    <div class="text-[9px] text-gray-300">Past</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="w-full lg:w-72 space-y-4">
            <!-- Calendar Legend -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2"><i class="fas fa-info-circle"></i>Calendar Legend</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-cyan-500"></span>Your Exam</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-cyan-400"></span>Exam Schedule Events</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span>Holidays - No exams</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-gray-300"></span>Past / Unavailable</div>
                </div>
            </div>

            <!-- How to Book -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2"><i class="fas fa-question-circle"></i>How to Book</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0">1</span>
                        <div><span class="font-medium">Select Date</span><p class="text-xs text-gray-500">Click an available date on the calendar</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0">2</span>
                        <div><span class="font-medium">Pick Time Slot</span><p class="text-xs text-gray-500">Choose your preferred exam time</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0">3</span>
                        <div><span class="font-medium">Fill Details</span><p class="text-xs text-gray-500">Enter your grade level and previous school</p></div>
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
                <h2 class="text-xl font-bold text-primary"><i class="fas fa-clipboard-list mr-2"></i>Book Entrance Exam</h2>
                <button onclick="closeBookingModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500"><i class="fas fa-times"></i></button>
            </div>

            <?php if ($error_message): ?>
            <div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" id="bookingForm">
                <!-- Schedule -->
                <div class="mb-4">
                    <h3 class="font-semibold text-primary text-sm mb-3"><i class="fas fa-calendar mr-1"></i>Selected Schedule</h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Date <span class="text-red-500">*</span></label>
                            <input type="date" id="examDate" name="preferred_date" min="<?= $min_date ?>" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Time <span class="text-red-500">*</span></label>
                            <select name="preferred_time" id="examTime" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                                <option value="">Select Date First</option>
                            </select>
                        </div>
                    </div>
                    <div id="timeSlotsContainer" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                        <div id="timeSlots" class="grid grid-cols-4 gap-2 mb-2"></div>
                    </div>
                    <div id="dateInfo" class="hidden bg-cyan-50 rounded-lg p-3 text-sm text-cyan-700 flex items-start gap-2">
                        <i class="fas fa-info-circle mt-0.5"></i>
                        <span id="dateInfoText">Select a date and time for your entrance exam.</span>
                    </div>
                </div>

                <!-- Applicant Details -->
                <div class="mb-4">
                    <h3 class="font-semibold text-primary text-sm mb-3"><i class="fas fa-user mr-1"></i>Applicant Details</h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grade Level Applying <span class="text-red-500">*</span></label>
                            <select name="grade_level_applying" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                                <option value="">Select Grade Level</option>
                                <option value="Grade 7">Grade 7</option><option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option><option value="Grade 10">Grade 10</option>
                                <option value="Grade 11">Grade 11</option><option value="Grade 12">Grade 12</option>
                                <option value="1st Year College">1st Year College</option><option value="2nd Year College">2nd Year College</option>
                                <option value="3rd Year College">3rd Year College</option><option value="4th Year College">4th Year College</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Previous School <span class="text-red-500">*</span></label>
                            <input type="text" name="previous_school" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Program (Optional)</label>
                        <input type="text" name="preferred_program" class="w-full rounded-lg border-2 border-gray-200 px-4 py-2.5 text-sm focus:border-primary outline-none transition-all" placeholder="e.g., BSIT, BSED, ABM">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeBookingModal()" class="flex-1 py-3 rounded-lg border-2 border-gray-200 text-gray-500 font-semibold text-sm hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" name="book_appointment" class="flex-1 py-3 rounded-lg bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-colors">
                        <i class="fas fa-paper-plane mr-1"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const allTimeSlots = [
    { value: '08:00:00', label: '8:00 AM' },
    { value: '09:00:00', label: '9:00 AM' },
    { value: '10:00:00', label: '10:00 AM' },
    { value: '11:00:00', label: '11:00 AM' },
    { value: '13:00:00', label: '1:00 PM' },
    { value: '14:00:00', label: '2:00 PM' },
    { value: '15:00:00', label: '3:00 PM' }
];
const bookedSlots = <?= json_encode($booked_slots) ?>;
const myExams = <?= json_encode($my_exams) ?>;
const holidayDates = <?= json_encode(array_keys($holiday_dates)) ?>;

function openBookingModal() {
    document.getElementById('bookingModal').classList.remove('hidden');
    document.getElementById('bookingModal').classList.add('flex');
}
function closeBookingModal() {
    document.getElementById('bookingModal').classList.add('hidden');
    document.getElementById('bookingModal').classList.remove('flex');
}

function selectDate(dateStr) {
    const today = new Date(); today.setHours(0,0,0,0);
    const selected = new Date(dateStr + 'T00:00:00');
    if (selected < today) { Swal.fire({icon:'warning',title:'Invalid Date',text:'Cannot book exams for past dates.',confirmButtonColor:'#163269'}); return; }
    if (selected.getDay() === 0) { Swal.fire({icon:'warning',title:'Sunday',text:'Exams are not available on Sundays.',confirmButtonColor:'#163269'}); return; }
    if (holidayDates.includes(dateStr)) { Swal.fire({icon:'warning',title:'Holiday',text:'Cannot book exams on holidays.',confirmButtonColor:'#163269'}); return; }
    
    openBookingModal();
    document.getElementById('examDate').value = dateStr;
    loadTimeSlots(dateStr);
    const dateInfo = document.getElementById('dateInfo');
    const dateInfoText = document.getElementById('dateInfoText');
    const formattedDate = selected.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    dateInfoText.textContent = 'Selected: ' + formattedDate + '. Choose an available time slot.';
    dateInfo.classList.remove('hidden');
}

function loadTimeSlots(dateStr) {
    const timeSelect = document.getElementById('examTime');
    const slotsContainer = document.getElementById('timeSlotsContainer');
    const slotsDiv = document.getElementById('timeSlots');
    const dateBookings = bookedSlots[dateStr] || {};
    const dateMyExams = myExams[dateStr] || [];
    
    timeSelect.innerHTML = '<option value="">Select a time slot</option>';
    slotsDiv.innerHTML = '';
    let availableCount = 0;
    
    allTimeSlots.forEach(slot => {
        const bookedCount = dateBookings[slot.value] || 0;
        const isMySlot = dateMyExams.some(a => a.preferred_time === slot.value);
        const isFull = bookedCount >= 3;
        
        if (!isFull) {
            const opt = document.createElement('option');
            opt.value = slot.value;
            opt.textContent = slot.label + (bookedCount > 0 ? ' (' + bookedCount + ' booked)' : '');
            timeSelect.appendChild(opt);
            availableCount++;
        }
        
        const slotBtn = document.createElement('button');
        slotBtn.type = 'button';
        slotBtn.className = 'px-3 py-2 rounded-lg text-xs font-medium border-2 transition-all text-center ' +
            (isMySlot ? 'bg-cyan-100 border-cyan-400 text-cyan-700 cursor-default' :
            isFull ? 'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through' :
            'bg-white border-gray-200 text-gray-700 hover:border-cyan-400 hover:bg-cyan-50 hover:text-cyan-700 cursor-pointer');
        slotBtn.innerHTML = slot.label + (isMySlot ? '<br><span class="text-[9px]">Your Exam</span>' : '') + (isFull ? '<br><span class="text-[9px]">Full</span>' : '') + (!isFull && !isMySlot && bookedCount > 0 ? '<br><span class="text-[9px]">' + bookedCount + ' booked</span>' : '');
        
        if (!isFull && !isMySlot) {
            slotBtn.onclick = function() {
                document.querySelectorAll('#timeSlots button').forEach(b => {
                    if (!b.classList.contains('bg-cyan-100') && !b.classList.contains('bg-red-50')) {
                        b.classList.remove('border-cyan-500','bg-cyan-50','text-cyan-700');
                        b.classList.add('border-gray-200','bg-white','text-gray-700');
                    }
                });
                this.classList.remove('border-gray-200','bg-white','text-gray-700');
                this.classList.add('border-cyan-500','bg-cyan-50','text-cyan-700');
                timeSelect.value = slot.value;
            };
        }
        slotsDiv.appendChild(slotBtn);
    });
    
    slotsContainer.classList.remove('hidden');
    if (availableCount === 0) {
        const noSlots = document.createElement('div');
        noSlots.className = 'col-span-4 text-center text-sm text-red-500 py-2';
        noSlots.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>No available time slots. Please choose another date.';
        slotsDiv.appendChild(noSlots);
    }
}

// Date input change handler
const dateInput = document.getElementById('examDate');
if (dateInput) {
    dateInput.addEventListener('change', function() {
        if (this.value) { loadTimeSlots(this.value); }
    });
}

// Close modal on backdrop click
document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingModal();
});

// Form validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const dateVal = document.getElementById('examDate').value;
    const timeVal = document.getElementById('examTime').value;
    if (!dateVal || !timeVal) {
        e.preventDefault();
        Swal.fire({icon:'warning',title:'Missing Fields',text:'Please select both a date and time slot.',confirmButtonColor:'#163269'});
        return;
    }
    // Confirmation
    e.preventDefault();
    const selected = new Date(dateVal + 'T00:00:00');
    const formattedDate = selected.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeLabel = allTimeSlots.find(s => s.value === timeVal)?.label || timeVal;
    const grade = this.querySelector('[name="grade_level_applying"]').value;
    Swal.fire({
        title: 'Confirm Exam Booking?',
        html: '<div class="text-left text-sm"><p><strong>Date:</strong> ' + formattedDate + '</p><p><strong>Time:</strong> ' + timeLabel + '</p><p><strong>Grade Level:</strong> ' + grade + '</p></div>',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#163269', cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Book It!'
    }).then(function(result) { if (result.isConfirmed) document.getElementById('bookingForm').submit(); });
});

<?php if ($error_message): ?>openBookingModal();<?php endif; ?>

<?php if ($success_message): ?>
Swal.fire({ icon: 'success', title: 'Exam Booked!', text: '<?= addslashes($success_message) ?>', confirmButtonColor: '#163269' });
<?php endif; ?>
</script>
