<?php
require_once __DIR__ . '/../../../classes/Schedule.php';
require_once __DIR__ . '/../../../classes/Holiday.php';
require_once __DIR__ . '/../../../classes/DailyBookingLimit.php';

$schedule = new Schedule($db);
$holiday = new Holiday($db);
$dailyLimit = new DailyBookingLimit($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

if ($_POST) {
    if (isset($_POST['create_schedule'])) {
        $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->created_by=$_SESSION['user_id']; $schedule->is_active=1;
        if($schedule->create()){$_SESSION['success_message']="Schedule created!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['update_schedule'])) {
        $schedule->id=$_POST['schedule_id']; $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->is_active=1;
        if($schedule->update()){$_SESSION['success_message']="Schedule updated!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['delete_schedule'])) {
        $schedule->id=$_POST['schedule_id'];
        if($schedule->delete()){$_SESSION['success_message']="Schedule deleted!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['set_daily_limit'])) {
        $dailyLimit->setDailyLimit($_POST['limit_date'],(int)$_POST['max_appointments'],$_SESSION['user_id']);
        $_SESSION['success_message']="Daily limit set!";header("Location:layout.php?page=schedules");exit();
    }
    if (isset($_POST['remove_daily_limit'])) {
        $dailyLimit->removeDailyLimit($_POST['limit_date']);
        $_SESSION['success_message']="Daily limit removed!";header("Location:layout.php?page=schedules");exit();
    }
    if (isset($_POST['create_holiday'])) {
        $holiday->name=$_POST['holiday_name']; $holiday->date=$_POST['holiday_date'];
        $holiday->type=$_POST['holiday_type']??'regular'; $holiday->is_recurring=isset($_POST['is_recurring'])?1:0;
        $holiday->year=date('Y',strtotime($_POST['holiday_date']));
        if($holiday->create()){$_SESSION['success_message']="Holiday added!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['delete_holiday'])) {
        $holiday->delete($_POST['holiday_id']);
        $_SESSION['success_message']="Holiday removed!";header("Location:layout.php?page=schedules");exit();
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

// Build events map for the month with role-based filtering
$events_map = [];
$current_user_id = $_SESSION['user_id'] ?? null;
$current_role = $_SESSION['role'] ?? '';

$sched_list = $schedule->getAll();
while($s = $sched_list->fetch(PDO::FETCH_ASSOC)) {
    // Role-based filtering
    $show_schedule = false;
    
    if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'guidance_advocate') {
        // Admins and guidance advocates see all schedules
        $show_schedule = true;
    } elseif ($current_role === 'examinee') {
        // Examinees see only entrance exam schedules
        $show_schedule = ($s['event_type'] === 'entrance_exam');
    } elseif ($current_role === 'student') {
        // Students see PDS periods and general events
        $show_schedule = ($s['event_type'] === 'pds_period' || $s['event_type'] === 'event');
    } elseif ($current_role === 'faculty') {
        // Faculty see all schedules (similar to admins)
        $show_schedule = true;
    }
    
    // Counseling events: only show if created by current user (for all roles except admin)
    if ($s['event_type'] === 'counseling' && $current_role !== 'super_admin' && $current_role !== 'admin' && $current_role !== 'guidance_advocate') {
        $show_schedule = ($s['created_by'] == $current_user_id);
    }
    
    if ($show_schedule) {
        // Get creator name
        $creator_stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $creator_stmt->execute([$s['created_by']]);
        $creator = $creator_stmt->fetch(PDO::FETCH_ASSOC);
        $s['created_by_name'] = $creator['name'] ?? 'Unknown';
        
        $d = date('Y-m-d', strtotime($s['start_datetime']));
        $events_map[$d][] = $s;
    }
}

// Build holiday lookup
$holiday_dates = [];
$start_date = "$year-$month-01"; $end_date = date('Y-m-t', strtotime($start_date));
try { $h_stmt = $db->prepare("SELECT id, date, name FROM holidays WHERE date BETWEEN ? AND ?"); $h_stmt->execute([$start_date, $end_date]); while ($hr = $h_stmt->fetch(PDO::FETCH_ASSOC)) { $holiday_dates[$hr['date']] = ['id'=>$hr['id'], 'name'=>$hr['name']]; } } catch (Exception $e) {}

// Build daily limits map
$limits_map = [];
try { $l_stmt = $db->prepare("SELECT limit_date, max_appointments FROM daily_booking_limits WHERE limit_date BETWEEN ? AND ?"); $l_stmt->execute([$start_date, $end_date]); while ($lr = $l_stmt->fetch(PDO::FETCH_ASSOC)) { $limits_map[$lr['limit_date']] = $lr['max_appointments']; } } catch (Exception $e) {}

// Stats
$schedules_this_month = 0; $holidays_this_month = count($holiday_dates); $limits_this_month = count($limits_map);
foreach ($events_map as $d => $evts) { if (strpos($d, "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT)) === 0) $schedules_this_month += count($evts); }

$upcoming_holidays = $holiday->getUpcomingHolidays(10);
$event_colors = ['pds_period'=>'bg-purple-500','entrance_exam'=>'bg-cyan-500','counseling'=>'bg-blue-500','event'=>'bg-green-500','holiday'=>'bg-red-500'];
$event_labels = ['pds_period'=>'PDS Period','entrance_exam'=>'Entrance Exam','counseling'=>'Counseling','event'=>'Event','holiday'=>'Holiday'];
$today_str = date('Y-m-d');

// Handle view_schedule parameter for auto-opening view modal
$auto_view_schedule = null;
if (isset($_GET['view_schedule'])) {
    try {
        $auto_stmt = $db->prepare("SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name FROM schedules s LEFT JOIN users u ON s.created_by = u.id WHERE s.id = ?");
        $auto_stmt->execute([$_GET['view_schedule']]);
        $auto_view_schedule = $auto_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $auto_view_schedule = null; }
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-calendar-alt mr-2 text-primary"></i>Schedule Management</h1>
        <div class="flex gap-2">
            <button onclick="openModal('createScheduleModal')" class="px-3 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Schedule</button>
            <button onclick="openModal('dailyLimitModal')" class="px-3 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-clock mr-1"></i>Booking Limits</button>
            <button onclick="openModal('holidayModal')" class="px-3 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600"><i class="fas fa-umbrella-beach mr-1"></i>Holidays</button>
        </div>
    </div>

    <!-- Alerts -->
    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $schedules_this_month ?></div>
            <div class="text-sm text-white/80">Schedules This Month</div>
        </div>
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $holidays_this_month ?></div>
            <div class="text-sm text-white/80">Holidays This Month</div>
        </div>
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl p-4 text-white">
            <div class="text-3xl font-bold"><?= $limits_this_month ?></div>
            <div class="text-sm text-white/80">Daily Limits Set</div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-5">
        <!-- Calendar Section -->
        <div class="flex-1">
            <!-- Legend -->
            <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
                <div class="flex flex-wrap gap-4 items-center">
                    <span class="text-sm font-medium text-gray-600">Event Types:</span>
                    <?php foreach ($event_labels as $type=>$label): $c=$event_colors[$type]??'bg-gray-500'; ?>
                    <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded <?= $c ?>"></span><?= $label ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Calendar -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between p-4 border-b">
                    <div class="flex items-center gap-2">
                        <a href="?page=schedules&month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition-colors">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </a>
                        <a href="?page=schedules&month=<?= $next_month ?>&year=<?= $next_year ?>" class="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition-colors">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </a>
                        <a href="?page=schedules" class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm hover:bg-primary-dark transition-colors">today</a>
                        <button onclick="openModal('allSchedulesModal')" class="px-3 py-1.5 rounded-lg bg-green-500 text-white text-sm hover:bg-green-600 transition-colors">
                            <i class="fas fa-list mr-1"></i>View All
                        </button>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800"><?= $month_name ?></h2>
                    <span class="text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i>Click any date to create a schedule</span>
                </div>

                <!-- Calendar Grid -->
                <div class="border border-gray-200 rounded-lg overflow-hidden m-4">
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
                            $holiday_data = $holiday_dates[$ds] ?? null;
                            $holiday_name = $holiday_data['name'] ?? '';
                            $holiday_id = $holiday_data['id'] ?? '';
                            $is_past = $ds < $today_str;
                            $is_sunday = date('w', strtotime($ds)) === '0';
                            $day_events = $events_map[$ds] ?? [];
                            $has_limit = isset($limits_map[$ds]);
                        ?>
                            <div class="h-24 border-r border-b border-gray-100 p-1 relative transition-all duration-200
                                <?= $is_today ? 'bg-blue-50/50' : '' ?>
                                <?= $is_holiday ? 'bg-red-50/30' : '' ?>
                                <?= $is_past ? 'bg-gray-50/50' : '' ?>
                                <?= !$is_past ? 'cursor-pointer hover:bg-blue-50 hover:shadow-md hover:scale-105' : '' ?>
                            " onclick="<?= !$is_past ? "openEventTypeSelection('{$ds}')" : '' ?>">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium
                                        <?= $is_today ? 'bg-primary text-white w-6 h-6 rounded-full flex items-center justify-center' : '' ?>
                                        <?= !$is_today && $is_holiday ? 'text-red-600' : '' ?>
                                        <?= !$is_today && $is_past ? 'text-gray-300' : '' ?>
                                        <?= !$is_today && !$is_holiday && !$is_past ? 'text-gray-700' : '' ?>
                                    "><?= $day ?></span>
                                    <?php if($has_limit): ?>
                                    <span class="text-[9px] text-yellow-600 bg-yellow-50 px-1 rounded">Limit:<?= $limits_map[$ds] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    <?php if($is_holiday): ?>
                                    <div class="text-[10px] px-1.5 py-0.5 rounded truncate bg-red-100 text-red-700 cursor-pointer hover:bg-red-200" onclick="event.stopPropagation(); viewHoliday(<?= htmlspecialchars(json_encode(['id'=>$holiday_id,'name'=>$holiday_name,'date'=>$ds])) ?>)">
                                        <i class="fas fa-umbrella-beach mr-1"></i><?= htmlspecialchars(substr($holiday_name, 0, 15)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php foreach(array_slice($day_events, 0, 2) as $ev): $ec=$event_colors[$ev['event_type']]??'bg-gray-500'; ?>
                                    <div class="<?= $ec ?> text-white text-[10px] px-1.5 py-0.5 rounded truncate" onclick="event.stopPropagation(); viewSchedule(<?= htmlspecialchars(json_encode($ev)) ?>)"><?= htmlspecialchars($ev['title']) ?></div>
                                    <?php endforeach; ?>
                                    <?php if(count($day_events)>2): ?>
                                    <div class="text-[10px] text-gray-400 px-1 cursor-pointer hover:text-primary hover:underline" onclick="event.stopPropagation(); showDayEvents('<?= $ds ?>')">+<?= count($day_events)-2 ?> more</div>
                                    <?php endif; ?>
                                    <?php if($is_sunday && !$is_holiday && !$is_past): ?>
                                    <div class="text-[9px] text-gray-400">Sun</div>
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
            <!-- Upcoming Holidays -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2"><i class="fas fa-umbrella-beach text-red-500"></i>Upcoming Holidays</h3>
                <div class="space-y-2">
                <?php if($upcoming_holidays->rowCount()>0): while($h=$upcoming_holidays->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="flex items-center justify-between py-2 border-b last:border-0">
                        <div><span class="text-sm font-medium"><?= htmlspecialchars($h['name']) ?></span><span class="text-xs text-gray-500 ml-2"><?= date('M d',strtotime($h['date'])) ?></span></div>
                        <form method="POST" class="inline"><input type="hidden" name="holiday_id" value="<?= $h['id'] ?>"><button type="submit" name="delete_holiday" value="1" class="text-red-400 hover:text-red-600 text-xs" onclick="return confirm('Remove this holiday?')"><i class="fas fa-trash"></i></button></form>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-sm text-gray-400">No upcoming holidays</p>
                <?php endif; ?>
                </div>
            </div>

            <!-- Calendar Legend -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold text-primary mb-3 flex items-center gap-2"><i class="fas fa-info-circle"></i>Calendar Legend</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500"></span>Today</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span>Holidays - No bookings</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-green-500"></span>Events</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-purple-500"></span>PDS Period</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-cyan-500"></span>Entrance Exam</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-yellow-500"></span>Daily Limits</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-gray-300"></span>Past Dates</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Schedules Modal -->
<div id="allSchedulesModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-list mr-2"></i>All Schedules</h3>
            <button onclick="closeModal('allSchedulesModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Start</th><th class="px-4 py-3">End</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100" id="allSchedulesTableBody">
                    <!-- Schedules will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-4 pt-4 border-t" id="paginationControls">
                <div class="text-sm text-gray-600" id="paginationInfo">
                    <!-- Pagination info will be populated by JavaScript -->
                </div>
                <div class="flex items-center gap-2" id="paginationButtons">
                    <!-- Pagination buttons will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Day Events Modal -->
<div id="dayEventsModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-calendar-day mr-2"></i>Events for <span id="dayEventsDate"></span></h3>
            <button onclick="closeModal('dayEventsModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="dayEventsList" class="p-4 space-y-2">
            <!-- Events will be populated here -->
        </div>
    </div>
</div>

<!-- Event Type Selection Modal -->
<div id="eventTypeSelectionModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-calendar-plus mr-2"></i>Select Event Type</h3>
            <button onclick="closeModal('eventTypeSelectionModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <input type="hidden" id="selected_date">
            <div class="text-sm text-gray-600 mb-4">Date: <span id="selected_date_display" class="font-semibold"></span></div>
            <div class="grid grid-cols-2 gap-4">
                <div onclick="selectEventType('event')" class="p-6 rounded-xl border-2 border-green-200 bg-green-50 hover:bg-green-100 cursor-pointer transition-colors">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-xl bg-green-500 text-white flex items-center justify-center">
                            <i class="fas fa-calendar text-xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800">EVENTS</div>
                            <div class="text-xs text-gray-600">General events</div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 font-medium">8:00 AM - 5:00 PM</div>
                </div>
                <div onclick="selectEventType('pds_period')" class="p-6 rounded-xl border-2 border-purple-200 bg-purple-50 hover:bg-purple-100 cursor-pointer transition-colors">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-xl bg-purple-500 text-white flex items-center justify-center">
                            <i class="fas fa-file-alt text-xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800">PDS PERIOD</div>
                            <div class="text-xs text-gray-600">Personal Data Sheet</div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 font-medium">8:00 AM - 5:00 PM</div>
                </div>
                <div onclick="selectEventType('entrance_exam')" class="p-6 rounded-xl border-2 border-cyan-200 bg-cyan-50 hover:bg-cyan-100 cursor-pointer transition-colors">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500 text-white flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800">ENTRANCE EXAM</div>
                            <div class="text-xs text-gray-600">Exam schedules</div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 font-medium">8:00 AM - 5:00 PM</div>
                </div>
                <div onclick="selectEventType('counseling')" class="p-6 rounded-xl border-2 border-blue-200 bg-blue-50 hover:bg-blue-100 cursor-pointer transition-colors">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-xl bg-blue-500 text-white flex items-center justify-center">
                            <i class="fas fa-comments text-xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800">COUNSELING</div>
                            <div class="text-xs text-gray-600">Counseling sessions</div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 font-medium">8:00 AM - 5:00 PM</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Schedule Modal -->
<div id="createScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div id="createScheduleModalHeader" class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-plus mr-2"></i>Create Schedule</h3>
            <button onclick="closeModal('createScheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_schedule" value="1">
            <input type="hidden" name="event_type" id="create_event_type">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label><input type="date" name="start_date" id="create_start_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label><input type="time" name="start_time" value="08:00" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label><input type="date" name="end_date" id="create_end_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Time</label><input type="time" name="end_time" value="17:00" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('createScheduleModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Create</button></div>
        </form>
    </div>
</div>

<!-- View Schedule Modal -->
<div id="viewScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div id="viewScheduleModalHeader" class="bg-blue-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-eye mr-2"></i>View Schedule</h3>
            <button onclick="closeModal('viewScheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="view_schedule_id">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Title</label>
                    <div id="view_title" class="text-lg font-semibold text-gray-800"></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Event Type</label>
                    <div id="view_event_type" class="text-sm font-medium px-3 py-1 rounded-full bg-blue-100 text-blue-700 inline-block"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Start</label>
                        <div id="view_start_datetime" class="text-sm text-gray-700"></div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">End</label>
                        <div id="view_end_datetime" class="text-sm text-gray-700"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Description</label>
                    <div id="view_description" class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg"></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Created By</label>
                    <div id="view_created_by" class="text-sm text-gray-700"></div>
                </div>
            </div>
            <form id="deleteScheduleForm" method="POST" class="hidden">
                <input type="hidden" name="delete_schedule" value="1">
                <input type="hidden" id="delete_schedule_id" name="schedule_id">
            </form>
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('viewScheduleModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Close</button>
                <button type="button" onclick="deleteScheduleFromView(document.getElementById('view_schedule_id').value)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Delete</button>
                <button id="viewEditBtn" type="button" onclick="editSchedule(currentScheduleData)" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- View Holiday Modal -->
<div id="viewHolidayModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="bg-red-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-umbrella-beach mr-2"></i>View Holiday</h3>
            <button onclick="closeModal('viewHolidayModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="view_holiday_id">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Holiday Name</label>
                    <div id="view_holiday_name" class="text-lg font-semibold text-gray-800"></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Date</label>
                    <div id="view_holiday_date" class="text-sm text-gray-700"></div>
                </div>
            </div>
            <form id="deleteHolidayForm" method="POST" class="hidden">
                <input type="hidden" name="delete_holiday" value="1">
                <input type="hidden" id="delete_holiday_id" name="holiday_id">
            </form>
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('viewHolidayModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Close</button>
                <button type="button" onclick="deleteHolidayFromView(document.getElementById('view_holiday_id').value)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Schedule</h3>
            <button onclick="closeModal('editScheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="update_schedule" value="1">
            <input type="hidden" name="schedule_id" id="edit_schedule_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" id="edit_title" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="description" id="edit_description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                <select name="event_type" id="edit_event_type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="event">Event</option><option value="pds_period">PDS Period</option><option value="entrance_exam">Entrance Exam</option><option value="counseling">Counseling</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label><input type="date" name="start_date" id="edit_start_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label><input type="time" name="start_time" id="edit_start_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label><input type="date" name="end_date" id="edit_end_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Time</label><input type="time" name="end_time" id="edit_end_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('editScheduleModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Update</button></div>
        </form>
    </div>
</div>

<!-- Daily Booking Limit Modal -->
<div id="dailyLimitModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-clock mr-2"></i>Daily Booking Limits</h3>
            <button onclick="closeModal('dailyLimitModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_daily_limit" value="1">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="limit_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Appointments *</label><input type="number" name="max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <button type="submit" class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Set Limit</button>
            </form>
            <hr>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="remove_daily_limit" value="1">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Remove Limit for Date</label><input type="date" name="limit_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <button type="submit" class="w-full px-4 py-2 border text-red-600 rounded-lg text-sm hover:bg-red-50">Remove Limit</button>
            </form>
        </div>
    </div>
</div>

<!-- Holiday Modal -->
<div id="holidayModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-red-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-umbrella-beach mr-2"></i>Add Holiday</h3>
            <button onclick="closeModal('holidayModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_holiday" value="1">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Holiday Name *</label><input type="text" name="holiday_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="holiday_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="holiday_type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="regular">Regular Holiday</option><option value="special">Special Non-Working</option>
                </select>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_recurring" value="1" class="rounded"><label class="text-sm text-gray-700">Recurring (every year)</label></div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('holidayModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm">Add Holiday</button></div>
        </form>
    </div>
</div>

<script>
let currentScheduleData = null;
let currentHolidayData = null;
let eventsByDate = {};
let allSchedulesData = [];
let currentSchedulePage = 1;
const itemsPerPage = 5;

// Store events by date for the "+more" functionality
<?php foreach($events_map as $date => $events): ?>
eventsByDate['<?= $date ?>'] = <?= json_encode($events) ?>;
<?php endforeach; ?>

// Store all schedules data for pagination
<?php 
$sched_list3=$schedule->getAll(); 
while($s=$sched_list3->fetch(PDO::FETCH_ASSOC)) {
    $show_schedule = false;
    if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'guidance_advocate') {
        $show_schedule = true;
    } elseif ($current_role === 'examinee') {
        $show_schedule = ($s['event_type'] === 'entrance_exam');
    } elseif ($current_role === 'student') {
        $show_schedule = ($s['event_type'] === 'pds_period' || $s['event_type'] === 'event');
    } elseif ($current_role === 'faculty') {
        $show_schedule = true;
    }
    if ($s['event_type'] === 'counseling' && $current_role !== 'super_admin' && $current_role !== 'admin' && $current_role !== 'guidance_advocate') {
        $show_schedule = ($s['created_by'] == $current_user_id);
    }
    if ($show_schedule) {
        $s['created_by_name'] = 'Unknown'; // Will be populated if needed
        echo "allSchedulesData.push(" . json_encode($s) . ");\n";
    }
}
?>

// Sort by newest first
allSchedulesData.sort(function(a, b) { return new Date(b.start_datetime) - new Date(a.start_datetime); });

function populateAllSchedulesModal(){
    const tableBody = document.getElementById('allSchedulesTableBody');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationButtons = document.getElementById('paginationButtons');
    
    tableBody.innerHTML = '';
    
    const totalItems = allSchedulesData.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const offset = (currentSchedulePage - 1) * itemsPerPage;
    const paginatedData = allSchedulesData.slice(offset, offset + itemsPerPage);
    
    const eventColors = {
        'event': 'bg-green-500',
        'pds_period': 'bg-purple-500',
        'entrance_exam': 'bg-cyan-500',
        'counseling': 'bg-blue-500'
    };
    
    paginatedData.forEach(s => {
        const ec = eventColors[s.event_type] || 'bg-gray-500';
        const startTime = new Date(s.start_datetime).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
        const endTime = new Date(s.end_datetime).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
        
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-4 py-3 font-medium">${s.title}</td>
            <td class="px-4 py-3"><span class="${ec} text-white text-xs px-2 py-1 rounded capitalize">${s.event_type.replace('_', ' ')}</span></td>
            <td class="px-4 py-3 text-gray-500 text-xs">${startTime}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">${endTime}</td>
            <td class="px-4 py-3 text-right">
                <button onclick='viewSchedule(${JSON.stringify(s)})' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="View"><i class="fas fa-eye"></i></button>
                <button onclick='editSchedule(${JSON.stringify(s)})' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="fas fa-edit"></i></button>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this schedule?')"><input type="hidden" name="schedule_id" value="${s.id}"><button type="submit" name="delete_schedule" value="1" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Delete"><i class="fas fa-trash"></i></button></form>
            </td>
        `;
        tableBody.appendChild(row);
    });
    
    // Update pagination info
    paginationInfo.textContent = `Showing ${offset + 1} to ${Math.min(offset + itemsPerPage, totalItems)} of ${totalItems} schedules`;
    
    // Update pagination buttons
    paginationButtons.innerHTML = '';
    
    if (currentSchedulePage > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'px-3 py-1 border rounded text-sm hover:bg-gray-50';
        prevBtn.textContent = 'Previous';
        prevBtn.onclick = () => { currentSchedulePage--; populateAllSchedulesModal(); };
        paginationButtons.appendChild(prevBtn);
    }
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentSchedulePage) {
            const span = document.createElement('span');
            span.className = 'px-3 py-1 bg-primary text-white rounded text-sm';
            span.textContent = i;
            paginationButtons.appendChild(span);
        } else {
            const btn = document.createElement('button');
            btn.className = 'px-3 py-1 border rounded text-sm hover:bg-gray-50';
            btn.textContent = i;
            btn.onclick = () => { currentSchedulePage = i; populateAllSchedulesModal(); };
            paginationButtons.appendChild(btn);
        }
    }
    
    if (currentSchedulePage < totalPages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'px-3 py-1 border rounded text-sm hover:bg-gray-50';
        nextBtn.textContent = 'Next';
        nextBtn.onclick = () => { currentSchedulePage++; populateAllSchedulesModal(); };
        paginationButtons.appendChild(nextBtn);
    }
}

// Override openModal to populate schedules when opening allSchedulesModal
document.addEventListener('DOMContentLoaded', function() {
    const originalOpenModal = window.openModal;
    if (originalOpenModal) {
        window.openModal = function(modalId) {
            originalOpenModal(modalId);
            if (modalId === 'allSchedulesModal') {
                currentSchedulePage = 1;
                populateAllSchedulesModal();
            }
        };
    }
});

function changeSchedulePage(page){
    const url = new URL(window.location.href);
    url.searchParams.set('schedule_page', page);
    window.location.href = url.toString();
}

function showDayEvents(date){
    const events = eventsByDate[date] || [];
    const dateObj = new Date(date);
    document.getElementById('dayEventsDate').textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const listContainer = document.getElementById('dayEventsList');
    listContainer.innerHTML = '';
    
    if(events.length === 0){
        listContainer.innerHTML = '<div class="text-gray-500 text-center py-4">No events for this day</div>';
    } else {
        const eventColors = {
            'event': 'bg-green-500',
            'pds_period': 'bg-purple-500',
            'entrance_exam': 'bg-cyan-500',
            'counseling': 'bg-blue-500'
        };
        
        events.forEach(ev => {
            const color = eventColors[ev.event_type] || 'bg-gray-500';
            const startTime = new Date(ev.start_datetime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const endTime = new Date(ev.end_datetime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            
            const eventDiv = document.createElement('div');
            eventDiv.className = 'p-3 rounded-lg border hover:shadow-md cursor-pointer transition-shadow';
            eventDiv.style.borderLeft = '4px solid';
            eventDiv.style.borderLeftColor = color.replace('bg-', '').replace('-500', '');
            eventDiv.onclick = () => {
                closeModal('dayEventsModal');
                viewSchedule(ev);
            };
            
            eventDiv.innerHTML = `
                <div class="font-semibold text-gray-800">${ev.title}</div>
                <div class="text-xs text-gray-600">${startTime} - ${endTime}</div>
                <div class="text-xs text-gray-500 mt-1">${ev.event_type.replace('_', ' ').toUpperCase()}</div>
            `;
            
            listContainer.appendChild(eventDiv);
        });
    }
    
    openModal('dayEventsModal');
}

function openEventTypeSelection(date){
    document.getElementById('selected_date').value = date;
    const dateObj = new Date(date);
    document.getElementById('selected_date_display').textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    openModal('eventTypeSelectionModal');
}

function selectEventType(eventType){
    closeModal('eventTypeSelectionModal');
    const date = document.getElementById('selected_date').value;
    document.getElementById('create_event_type').value = eventType;
    const startDateInput = document.getElementById('create_start_date');
    const endDateInput = document.getElementById('create_end_date');
    if(startDateInput) startDateInput.value = date;
    if(endDateInput) endDateInput.value = date;
    updateCreateModalColor(eventType);
    openModal('createScheduleModal');
}

function updateCreateModalColor(eventType){
    const header = document.getElementById('createScheduleModalHeader');
    const submitBtn = document.querySelector('#createScheduleModal button[type="submit"]');
    
    const colorMap = {
        'event': 'bg-green-500',
        'pds_period': 'bg-purple-500',
        'entrance_exam': 'bg-cyan-500',
        'counseling': 'bg-blue-500'
    };
    
    // Remove old color classes
    header.classList.remove('bg-primary', 'bg-green-500', 'bg-purple-500', 'bg-cyan-500', 'bg-blue-500');
    submitBtn.classList.remove('bg-primary', 'bg-green-500', 'bg-purple-500', 'bg-cyan-500', 'bg-blue-500');
    
    // Add new color class
    const newColor = colorMap[eventType] || 'bg-primary';
    header.classList.add(newColor);
    submitBtn.classList.add(newColor);
}

function viewSchedule(data){
    currentScheduleData = data;
    document.getElementById('view_schedule_id').value=data.id;
    document.getElementById('view_title').textContent=data.title||'';
    document.getElementById('view_description').textContent=data.description||'No description';
    document.getElementById('view_event_type').textContent=(data.event_type||'event').replace('_',' ').toUpperCase();
    document.getElementById('view_start_datetime').textContent=new Date(data.start_datetime).toLocaleString();
    document.getElementById('view_end_datetime').textContent=new Date(data.end_datetime).toLocaleString();
    document.getElementById('view_created_by').textContent=data.created_by_name||'Unknown';
    updateViewModalColor(data.event_type||'event');
    openModal('viewScheduleModal');
}

function updateViewModalColor(eventType){
    const header = document.getElementById('viewScheduleModalHeader');
    const eventTypeBadge = document.getElementById('view_event_type');
    const editBtn = document.getElementById('viewEditBtn');
    
    const colorMap = {
        'event': { bg: 'bg-green-500', badgeBg: 'bg-green-100', badgeText: 'text-green-700' },
        'pds_period': { bg: 'bg-purple-500', badgeBg: 'bg-purple-100', badgeText: 'text-purple-700' },
        'entrance_exam': { bg: 'bg-cyan-500', badgeBg: 'bg-cyan-100', badgeText: 'text-cyan-700' },
        'counseling': { bg: 'bg-blue-500', badgeBg: 'bg-blue-100', badgeText: 'text-blue-700' }
    };
    
    const colors = colorMap[eventType] || colorMap['event'];
    
    // Remove old color classes from header
    header.classList.remove('bg-primary', 'bg-green-500', 'bg-purple-500', 'bg-cyan-500', 'bg-blue-500');
    header.classList.add(colors.bg);
    
    // Update badge colors
    eventTypeBadge.classList.remove('bg-blue-100', 'text-blue-700', 'bg-green-100', 'text-green-700', 'bg-purple-100', 'text-purple-700', 'bg-cyan-100', 'text-cyan-700');
    eventTypeBadge.classList.add(colors.badgeBg, colors.badgeText);
}

function viewHoliday(data){
    currentHolidayData = data;
    document.getElementById('view_holiday_id').value=data.id;
    document.getElementById('view_holiday_name').textContent=data.name||'';
    document.getElementById('view_holiday_date').textContent=new Date(data.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    openModal('viewHolidayModal');
}

function editSchedule(data){
    document.getElementById('edit_schedule_id').value=data.id;
    document.getElementById('edit_title').value=data.title||'';
    document.getElementById('edit_description').value=data.description||'';
    document.getElementById('edit_event_type').value=data.event_type||'event';
    const sd=new Date(data.start_datetime);
    const ed=new Date(data.end_datetime);
    document.getElementById('edit_start_date').value=sd.toISOString().split('T')[0];
    document.getElementById('edit_start_time').value=sd.toTimeString().slice(0,5);
    document.getElementById('edit_end_date').value=ed.toISOString().split('T')[0];
    document.getElementById('edit_end_time').value=ed.toTimeString().slice(0,5);
    closeModal('viewScheduleModal');
    openModal('editScheduleModal');
}

function deleteScheduleFromView(scheduleId){
    if(confirm('Are you sure you want to delete this schedule?')){
        document.getElementById('delete_schedule_id').value=scheduleId;
        document.getElementById('deleteScheduleForm').submit();
    }
}

function deleteHolidayFromView(holidayId){
    if(confirm('Are you sure you want to delete this holiday?')){
        document.getElementById('delete_holiday_id').value=holidayId;
        document.getElementById('deleteHolidayForm').submit();
    }
}

function quickCreateSchedule(date){
    const startDateInput = document.getElementById('create_start_date');
    const endDateInput = document.getElementById('create_end_date');
    if(startDateInput) startDateInput.value = date;
    if(endDateInput) endDateInput.value = date;
    openModal('createScheduleModal');
}

// Auto-open view modal if view_schedule parameter is present
<?php if($auto_view_schedule): ?>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleData = <?= json_encode($auto_view_schedule) ?>;
    if(scheduleData && scheduleData.id){
        viewSchedule(scheduleData);
        // Remove the view_schedule parameter from URL to prevent re-opening on refresh
        const url = new URL(window.location.href);
        url.searchParams.delete('view_schedule');
        window.history.replaceState({}, '', url);
    }
});
<?php endif; ?>
</script>
