<?php
// Dashboard content partial — loaded by layout.php
// All session/db setup is done in layout.php

$student_profile = null;
if (in_array($role, ['student', 'examinee'])) {
    try {
        $sp_stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $sp_stmt->execute([$uid]);
        $student_profile = $sp_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $student_profile = null; }
}

$grade_level = $student_profile['grade_level'] ?? $user_info['grade_level_applying'] ?? '';
$department = $student_profile['department'] ?? '';
$program = $student_profile['program'] ?? '';
$strand = $student_profile['strand'] ?? '';

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

$stats = ['total_users'=>0,'active_users'=>0,'total_students'=>0,'total_examinees'=>0,'upcoming_exams'=>0,'pending_counseling'=>0,'submitted_pds'=>0,'total_pds'=>0,'today_counseling'=>0,'awaiting_results'=>0,'total_exams'=>0,'active_counseling'=>0,'exams_today'=>0,'counseling_this_week'=>0,'pds_this_week'=>0,'new_users_today'=>0];

if (in_array($role, ['super_admin','admin','guidance_advocate'])) {
    try {
        $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['active_users'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['total_students'] = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','examinee') AND (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['total_examinees'] = $db->query("SELECT COUNT(*) FROM users WHERE role='examinee' AND (archived=0 OR archived IS NULL)")->fetchColumn();

        // Upcoming entrance exams (confirmed or pending with future date)
        $stats['upcoming_exams'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status IN ('confirmed','pending') AND preferred_date >= CURDATE()")->fetchColumn();
        // Total exam appointments
        $stats['total_exams'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments")->fetchColumn();
        // Exams today
        $stats['exams_today'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE preferred_date = CURDATE() AND status IN ('confirmed','pending')")->fetchColumn();

        // Active counseling (pending + confirmed + in_progress)
        $stats['pending_counseling'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status IN ('pending','confirmed','in_progress')")->fetchColumn();
        // Completed counseling this week
        $stats['counseling_this_week'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status = 'completed' AND YEARWEEK(appointment_date) = YEARWEEK(CURDATE())")->fetchColumn();
        // Today's counseling
        $stats['today_counseling'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE DATE(appointment_date)=CURDATE() AND status IN ('confirmed','in_progress')")->fetchColumn();

        // PDS stats
        $stats['submitted_pds'] = $db->query("SELECT COUNT(*) FROM pds")->fetchColumn();
        $stats['total_pds'] = $stats['submitted_pds'];
        $stats['pds_this_week'] = $db->query("SELECT COUNT(*) FROM pds WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

        // Awaiting exam results
        $stats['awaiting_results'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status = 'awaiting_results'")->fetchColumn();

        // New users today
        $stats['new_users_today'] = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND role IN ('student','examinee')")->fetchColumn();

        // Students without student IDs
        $students_without_id = $db->query("SELECT COUNT(*) FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) AND (sp.student_id IS NULL OR sp.student_id='')")->fetchColumn();
        
        // Get students without student IDs for modal
        $students_missing_id = [];
        if ($students_without_id > 0) {
            $stmt = $db->prepare("SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.created_at, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) AND (sp.student_id IS NULL OR sp.student_id='') ORDER BY u.created_at DESC LIMIT 20");
            $stmt->execute();
            $students_missing_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $grade_stats = $db->query("SELECT sp.grade_level, COUNT(*) as cnt FROM student_profiles sp JOIN users u ON sp.user_id=u.id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) GROUP BY sp.grade_level ORDER BY sp.grade_level")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $grade_stats = []; }
    
    // Fetch today's schedules for schedule summary
    $today_schedules = [];
    try {
        $schedule_stmt = $db->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email 
            FROM schedules s 
            LEFT JOIN users u ON s.created_by = u.id 
            WHERE DATE(s.start_datetime) = CURDATE() 
            AND s.is_active = 1
            ORDER BY s.start_datetime ASC
        ");
        $schedule_stmt->execute();
        $today_schedules = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: log if no results found
        if (empty($today_schedules)) {
            error_log("No schedules found for today. Checking database...");
            $debug_stmt = $db->query("SELECT COUNT(*) as total FROM schedules WHERE DATE(start_datetime) = CURDATE() AND is_active = 1");
            $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Total schedules for today: " . $debug_result['total']);
            
            $debug_all = $db->query("SELECT id, title, event_type, start_datetime, end_datetime, is_active FROM schedules ORDER BY start_datetime DESC LIMIT 5");
            $debug_all_result = $debug_all->fetchAll(PDO::FETCH_ASSOC);
            error_log("Recent schedules: " . json_encode($debug_all_result));
        }
    } catch (Exception $e) { 
        error_log("Error fetching schedules: " . $e->getMessage());
        $today_schedules = []; 
    }
    
    $recent = [];
    try {
        $act_stmt = $db->query("(SELECT u.first_name, u.last_name, u.created_at, 'registration' as type, u.role as extra FROM users u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND u.role IN ('student','examinee') ORDER BY u.created_at DESC LIMIT 5) UNION ALL (SELECT u.first_name, u.last_name, ca.created_at, 'counseling' as type, ca.status as extra FROM counseling_appointments ca JOIN users u ON ca.user_id=u.id WHERE ca.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY ca.created_at DESC LIMIT 5) UNION ALL (SELECT u.first_name, u.last_name, ea.created_at, 'exam' as type, ea.status as extra FROM entrance_exam_appointments ea JOIN users u ON ea.user_id=u.id WHERE ea.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY ea.created_at DESC LIMIT 5) ORDER BY created_at DESC LIMIT 8");
        $recent = $act_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $recent = []; }
}

$exam_result = null; $exam_application = null;
if (in_array($role, ['student','examinee'])) {
    try {
        $er = $db->prepare("SELECT * FROM entrance_exam_appointments WHERE user_id=? AND status='completed' ORDER BY created_at DESC LIMIT 1");
        $er->execute([$uid]); $exam_result = $er->fetch(PDO::FETCH_ASSOC) ?: null;
        $ea = $db->prepare("SELECT * FROM entrance_exam_appointments WHERE user_id=? AND status IN ('pending','confirmed') ORDER BY created_at DESC LIMIT 1");
        $ea->execute([$uid]); $exam_application = $ea->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {}
}

// PDS status
$pds_status = null;
if (in_array($role, ['student','examinee'])) {
    try {
        $ps = $db->prepare("SELECT id FROM pds WHERE user_id = ? LIMIT 1");
        $ps->execute([$uid]);
        $pds_status = $ps->fetchColumn() ? 'completed' : 'pending';
    } catch (Exception $e) { $pds_status = 'pending'; }
}

// Check if user has active counseling appointment
$has_active_appointment = false;
if ($role === 'student') {
    try {
        $appt_stmt = $db->prepare("SELECT id FROM counseling_appointments WHERE user_id = ? AND status IN ('pending', 'confirmed', 'in_progress') AND appointment_date >= CURDATE() LIMIT 1");
        $appt_stmt->execute([$uid]);
        $has_active_appointment = $appt_stmt->fetchColumn() !== false;
    } catch (Exception $e) { $has_active_appointment = false; }
}
?>

<?php if (in_array($role, ['super_admin','admin','guidance_advocate'])): ?>
<?php
    $welcome_name = htmlspecialchars($user_info['first_name'] ?? 'User');
    $upcoming_exams = (int)($stats['upcoming_exams'] ?? 0);
    $total_exams = (int)($stats['total_exams'] ?? 0);
    $exams_today = (int)($stats['exams_today'] ?? 0);
    $pending_counseling = (int)($stats['pending_counseling'] ?? 0);
    $counseling_this_week = (int)($stats['counseling_this_week'] ?? 0);
    $submitted_pds = (int)($stats['submitted_pds'] ?? 0);
    $pds_this_week = (int)($stats['pds_this_week'] ?? 0);
    $today_counseling = (int)($stats['today_counseling'] ?? 0);
    $total_pds = (int)($stats['total_pds'] ?? 0);
    $total_students = (int)($stats['total_students'] ?? 0);
    $new_users_today = (int)($stats['new_users_today'] ?? 0);

    // Dynamic subtitle logic
    $exam_subtitle = $upcoming_exams > 0 ? "$upcoming_exams upcoming" . ($exams_today > 0 ? " · $exams_today today" : '') : ($total_exams > 0 ? "$total_exams total exam records" : 'No upcoming exams');
    $counseling_subtitle = $pending_counseling > 0 ? "$pending_counseling active session" . ($pending_counseling > 1 ? 's' : '') : ($counseling_this_week > 0 ? "$counseling_this_week completed this week" : 'No active sessions');
    $pds_subtitle = $submitted_pds > 0 ? ($pds_this_week > 0 ? "+$pds_this_week this week" : "$submitted_pds total submitted") : 'No submissions yet';
    $total_pds_subtitle = $total_students > 0 ? "$total_students enrolled students" : 'No student records';
?>

<div class="space-y-6">
    <?php if($role === 'super_admin'): ?>
    <div>
        <h1 class="text-2xl font-bold text-primary">Super Admin Dashboard</h1>
        <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= $welcome_name ?></span>! You have full system control and access.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between border-l-4 border-l-primary">
            <div>
                <div class="text-xs text-gray-500">Total Users</div>
                <div class="text-2xl font-bold text-gray-800"><?= $stats['total_users'] ?></div>
                <div class="text-xs text-gray-400 mt-1">All system users</div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fas fa-users"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between border-l-4 border-l-green-500">
            <div>
                <div class="text-xs text-gray-500">Active Users</div>
                <div class="text-2xl font-bold text-gray-800"><?= $stats['active_users'] ?></div>
                <div class="text-xs text-gray-400 mt-1">Currently active</div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                <i class="fas fa-user-check"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between border-l-4 border-l-blue-500">
            <div>
                <div class="text-xs text-gray-500">Total Students</div>
                <div class="text-2xl font-bold text-gray-800"><?= $stats['total_students'] ?></div>
                <div class="text-xs text-gray-400 mt-1">Registered students</div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between border-l-4 border-l-cyan-500">
            <div>
                <div class="text-xs text-gray-500">All Examinees</div>
                <div class="text-2xl font-bold text-gray-800"><?= $stats['total_examinees'] ?></div>
                <div class="text-xs text-gray-400 mt-1">Examinees</div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div>
        <h1 class="text-2xl font-bold text-primary">Dashboard Overview</h1>
        <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= $welcome_name ?></span>! Here's what's happening today.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Upcoming Entrance Exams</div>
                <div class="text-2xl font-bold text-gray-800"><?= $upcoming_exams ?></div>
                <div class="text-[11px] <?= $upcoming_exams > 0 ? 'text-indigo-600 font-medium' : 'text-gray-400' ?> mt-1"><?= $exam_subtitle ?></div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fas fa-clipboard-list"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Pending Counseling</div>
                <div class="text-2xl font-bold text-gray-800"><?= $pending_counseling ?></div>
                <div class="text-[11px] <?= $pending_counseling > 0 ? 'text-cyan-600 font-medium' : 'text-gray-400' ?> mt-1"><?= $counseling_subtitle ?></div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                <i class="fas fa-comments"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Submitted PDS</div>
                <div class="text-2xl font-bold text-gray-800"><?= $submitted_pds ?></div>
                <div class="text-[11px] <?= $submitted_pds > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400' ?> mt-1"><?= $pds_subtitle ?></div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500">Total PDS Records</div>
                <div class="text-2xl font-bold text-gray-800"><?= $total_pds ?></div>
                <div class="text-[11px] <?= $total_pds > 0 ? 'text-amber-600 font-medium' : 'text-gray-400' ?> mt-1"><?= $total_pds_subtitle ?></div>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="fas fa-folder-open"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div>
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php if($role === 'super_admin'): ?>
            <?php if ($students_without_id > 0): ?>
            <a href="#" onclick="openMissingIdModal(); return false;" class="bg-yellow-50 rounded-2xl shadow-sm border border-yellow-200 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-yellow-100 text-yellow-700 flex items-center justify-center mb-3"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="font-semibold text-gray-800">Missing Student IDs</div>
                <div class="text-xs text-yellow-700 mt-1"><?= $students_without_id ?> student(s) without ID</div>
            </a>
            <?php endif; ?>
            <a href="layout.php?page=user_management" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><i class="fas fa-users-cog"></i></div>
                <div class="font-semibold text-gray-800">User Management</div>
                <div class="text-xs text-gray-500 mt-1">Create, edit, and manage all users</div>
            </a>
            <a href="layout.php?page=academic_settings" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3"><i class="fas fa-graduation-cap"></i></div>
                <div class="font-semibold text-gray-800">Academic Settings</div>
                <div class="text-xs text-gray-500 mt-1">Configure academic year and terms</div>
            </a>
            <a href="layout.php?page=system_logs" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-gray-50 text-gray-600 flex items-center justify-center mb-3"><i class="fas fa-file-alt"></i></div>
                <div class="font-semibold text-gray-800">System Logs</div>
                <div class="text-xs text-gray-500 mt-1">View system activity logs</div>
            </a>
            <?php else: ?>
            <?php if ($students_without_id > 0): ?>
            <a href="#" onclick="openMissingIdModal(); return false;" class="bg-yellow-50 rounded-2xl shadow-sm border border-yellow-200 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-yellow-100 text-yellow-700 flex items-center justify-center mb-3"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="font-semibold text-gray-800">Missing Student IDs</div>
                <div class="text-xs text-yellow-700 mt-1"><?= $students_without_id ?> student(s) without ID</div>
            </a>
            <?php endif; ?>
            <a href="layout.php?page=manage_exams" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><i class="fas fa-clipboard-list"></i></div>
                <div class="font-semibold text-gray-800">Manage Entrance Exams Appointments</div>
                <div class="text-xs text-gray-500 mt-1">Review and confirm entrance exam appointments</div>
            </a>
            <a href="layout.php?page=manage_counseling" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center mb-3"><i class="fas fa-user-friends"></i></div>
                <div class="font-semibold text-gray-800">Assigned Counseling</div>
                <div class="text-xs text-gray-500 mt-1">View your assigned counseling appointments</div>
            </a>
            <a href="layout.php?page=schedules" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3"><i class="fas fa-calendar-alt"></i></div>
                <div class="font-semibold text-gray-800">Schedule Management</div>
                <div class="text-xs text-gray-500 mt-1">Manage events and calendar</div>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php if($role !== 'super_admin'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2 text-gray-700">
                <i class="fas fa-calendar-day text-primary"></i>
                <div class="font-semibold">Today's Schedule</div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-xs text-gray-500"><?= date('l, F j, Y') ?></div>
                <a href="layout.php?page=schedules" class="text-xs text-primary hover:text-primary-dark font-medium">View All</a>
            </div>
        </div>
        <div class="p-5">
            <?php if(empty($today_schedules)): ?>
                <div class="text-center py-8">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 mx-auto flex items-center justify-center text-gray-300 mb-3">
                        <i class="fas fa-calendar-check text-2xl"></i>
                    </div>
                    <div class="text-sm text-gray-500">No scheduled activities for today</div>
                    <div class="text-xs text-gray-400 mt-1">Events will appear here when scheduled</div>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php 
                    $event_colors = [
                        'pds_period' => 'bg-purple-50 border-purple-200 text-purple-700',
                        'entrance_exam' => 'bg-cyan-50 border-cyan-200 text-cyan-700',
                        'counseling' => 'bg-blue-50 border-blue-200 text-blue-700',
                        'event' => 'bg-green-50 border-green-200 text-green-700',
                        'holiday' => 'bg-red-50 border-red-200 text-red-700'
                    ];
                    $event_icons = [
                        'pds_period' => 'fa-file-alt',
                        'entrance_exam' => 'fa-clipboard-list',
                        'counseling' => 'fa-comments',
                        'event' => 'fa-calendar',
                        'holiday' => 'fa-umbrella-beach'
                    ];
                    $event_labels = [
                        'pds_period' => 'PDS Period',
                        'entrance_exam' => 'Entrance Exam',
                        'counseling' => 'Counseling',
                        'event' => 'Event',
                        'holiday' => 'Holiday'
                    ];
                    ?>
                    <?php foreach($today_schedules as $schedule): 
                        $event_type = $schedule['event_type'] ?? 'event';
                        $color_class = $event_colors[$event_type] ?? $event_colors['event'];
                        $icon = $event_icons[$event_type] ?? $event_icons['event'];
                        $label = $event_labels[$event_type] ?? 'Event';
                        $start_time = date('g:i A', strtotime($schedule['start_datetime']));
                        $end_time = date('g:i A', strtotime($schedule['end_datetime']));
                    ?>
                        <div class="flex items-start gap-3 p-4 rounded-xl <?= $color_class ?> border hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='layout.php?page=schedules&view_schedule=<?= $schedule['id'] ?>'">
                            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center flex-shrink-0">
                                <i class="fas <?= $icon ?> text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($schedule['title']) ?></div>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-white/60 flex-shrink-0">
                                        <?= $label ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600 mb-1">
                                    <i class="fas fa-clock"></i>
                                    <span><?= $start_time ?> - <?= $end_time ?></span>
                                </div>
                                <?php if($schedule['description']): ?>
                                    <div class="text-xs text-gray-600 line-clamp-2 leading-relaxed">
                                        <?= htmlspecialchars($schedule['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if($role === 'super_admin'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:col-span-2">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-gray-700">
                <i class="fas fa-history text-primary"></i>
                <div class="font-semibold">Recent System Logs</div>
            </div>
            <div class="p-5">
                <?php
                $recent_logs = [];
                try {
                    $logs_query = "SELECT sl.*, u.first_name, u.last_name 
                                  FROM system_logs sl 
                                  LEFT JOIN users u ON sl.user_id = u.id 
                                  ORDER BY sl.created_at DESC 
                                  LIMIT 3";
                    $logs_stmt = $db->prepare($logs_query);
                    $logs_stmt->execute();
                    $recent_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) { $recent_logs = []; }
                
                if (!empty($recent_logs)):
                    foreach ($recent_logs as $log):
                        $icon_map = [
                            'error' => ['icon' => 'fa-exclamation-circle', 'color' => 'red'],
                            'warning' => ['icon' => 'fa-exclamation-triangle', 'color' => 'yellow'],
                            'info' => ['icon' => 'fa-info-circle', 'color' => 'blue'],
                            'success' => ['icon' => 'fa-check-circle', 'color' => 'green'],
                            'login' => ['icon' => 'fa-sign-in-alt', 'color' => 'indigo'],
                            'logout' => ['icon' => 'fa-sign-out-alt', 'color' => 'gray'],
                            'admin_action' => ['icon' => 'fa-user-shield', 'color' => 'purple'],
                            'system' => ['icon' => 'fa-cog', 'color' => 'blue']
                        ];
                        $log_style = $icon_map[$log['log_type']] ?? ['icon' => 'fa-circle', 'color' => 'gray'];
                        
                        $time_diff = time() - strtotime($log['created_at']);
                        if ($time_diff < 60) {
                            $time_ago = 'Just now';
                        } elseif ($time_diff < 3600) {
                            $time_ago = floor($time_diff / 60) . ' min ago';
                        } elseif ($time_diff < 86400) {
                            $time_ago = floor($time_diff / 3600) . ' hr ago';
                        } else {
                            $time_ago = floor($time_diff / 86400) . ' day(s) ago';
                        }
                        
                        $user_name = $log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'System';
                        $color_map = [
                            'red' => 'bg-red-50 text-red-600',
                            'yellow' => 'bg-yellow-50 text-yellow-600',
                            'blue' => 'bg-blue-50 text-blue-600',
                            'green' => 'bg-green-50 text-green-600',
                            'indigo' => 'bg-indigo-50 text-indigo-600',
                            'gray' => 'bg-gray-50 text-gray-600',
                            'purple' => 'bg-purple-50 text-purple-600'
                        ];
                        $bg_class = $color_map[$log_style['color']] ?? 'bg-gray-50 text-gray-600';
                ?>
                    <div class="flex items-start gap-3 py-3 border-b border-gray-50 last:border-0">
                        <div class="w-9 h-9 rounded-xl <?= $bg_class ?> flex items-center justify-center flex-shrink-0">
                            <i class="fas <?= $log_style['icon'] ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($log['message']) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($user_name) ?> • <?= $time_ago ?></div>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <div class="text-center py-10">
                        <div class="w-14 h-14 rounded-2xl bg-gray-50 mx-auto flex items-center justify-center text-gray-300 mb-3">
                            <i class="fas fa-inbox text-2xl"></i>
                        </div>
                        <div class="text-sm text-gray-500">No system logs found</div>
                        <div class="text-xs text-gray-400 mt-1">System activity logs will appear here</div>
                    </div>
                <?php endif; ?>
                <div class="mt-3 text-center">
                    <a href="layout.php?page=system_logs" class="inline-flex items-center gap-2 px-4 py-2 border border-primary text-primary text-sm font-semibold rounded-lg hover:bg-primary hover:text-white transition-colors">
                        <i class="fas fa-list"></i>
                        <span>View All Logs</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-gray-700">
                <i class="fas fa-chart-pie text-primary"></i>
                <div class="font-semibold">User Distribution</div>
            </div>
            <div class="p-5 space-y-3">
                <?php
                $user_stats = [];
                try {
                    $stats_query = "SELECT role, COUNT(*) as count FROM users WHERE (archived=0 OR archived IS NULL) GROUP BY role";
                    $stats_stmt = $db->query($stats_query);
                    $user_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $total_users = array_sum(array_column($user_stats, 'count'));
                } catch (Exception $e) { $user_stats = []; $total_users = 0; }
                
                $role_colors = [
                    'super_admin' => 'bg-gray-800',
                    'admin' => 'bg-red-500',
                    'guidance_advocate' => 'bg-green-500',
                    'student' => 'bg-indigo-500',
                    'examinee' => 'bg-yellow-500'
                ];
                
                foreach($user_stats as $stat):
                    $percentage = $total_users > 0 ? ($stat['count'] / $total_users) * 100 : 0;
                    $bg_color = $role_colors[$stat['role']] ?? 'bg-gray-400';
                ?>
                <div class="mb-3">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700"><?= ucfirst(str_replace('_', ' ', $stat['role'])) ?></span>
                        <span class="text-sm text-gray-500"><?= $stat['count'] ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="<?= $bg_color ?> h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:col-span-2">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-gray-700">
                <i class="fas fa-history text-primary"></i>
                <div class="font-semibold">Recent Activities</div>
            </div>
            <div class="p-5">
                <?php if(!empty($recent)): foreach($recent as $r): ?>
                    <div class="flex items-start gap-3 py-3 border-b border-gray-50 last:border-0">
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600">
                            <?= strtoupper(substr($r['first_name'],0,1)) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm text-gray-800">
                                <span class="font-semibold"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></span>
                                <?php if($r['type']==='registration'): ?>
                                    <span class="text-gray-400 text-xs ml-1">registered as <?= $r['extra'] ?></span>
                                <?php elseif($r['type']==='counseling'): ?>
                                    <span class="text-gray-400 text-xs ml-1">booked counseling (<?= $r['extra'] ?>)</span>
                                <?php elseif($r['type']==='exam'): ?>
                                    <span class="text-gray-400 text-xs ml-1">booked exam (<?= $r['extra'] ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-gray-400"><?= timeAgo($r['created_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="text-center py-10">
                        <div class="w-14 h-14 rounded-2xl bg-gray-50 mx-auto flex items-center justify-center text-gray-300 mb-3">
                            <i class="fas fa-inbox text-2xl"></i>
                        </div>
                        <div class="text-sm text-gray-500">No recent activities</div>
                        <div class="text-xs text-gray-400 mt-1">Activities from the last 30 days will appear here</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-gray-700">
                <i class="fas fa-list-check text-primary"></i>
                <div class="font-semibold">Tasks Overview</div>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center"><i class="fas fa-comments"></i></div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Today's Counseling</div>
                            <div class="text-xs text-gray-500">Scheduled sessions for today</div>
                        </div>
                    </div>
                    <div class="text-xs font-bold <?= $today_counseling > 0 ? 'text-cyan-700' : 'text-gray-700' ?> bg-white border rounded-full px-2 py-0.5"><?= $today_counseling ?></div>
                </div>

                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center"><i class="fas fa-calendar-day"></i></div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Active Counseling</div>
                            <div class="text-xs text-gray-500">Pending & confirmed sessions</div>
                        </div>
                    </div>
                    <div class="text-xs font-bold <?= $pending_counseling > 0 ? 'text-emerald-700' : 'text-gray-700' ?> bg-white border rounded-full px-2 py-0.5"><?= $pending_counseling ?></div>
                </div>

                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center"><i class="fas fa-clipboard-list"></i></div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Upcoming Exams</div>
                            <div class="text-xs text-gray-500">Confirmed & pending</div>
                        </div>
                    </div>
                    <div class="text-xs font-bold <?= $upcoming_exams > 0 ? 'text-indigo-700' : 'text-gray-700' ?> bg-white border rounded-full px-2 py-0.5"><?= $upcoming_exams ?></div>
                </div>

                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">New Users Today</div>
                            <div class="text-xs text-gray-500">Students & examinees</div>
                        </div>
                    </div>
                    <div class="text-xs font-bold <?= $new_users_today > 0 ? 'text-amber-700' : 'text-gray-700' ?> bg-white border rounded-full px-2 py-0.5"><?= $new_users_today ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($role === 'examinee'): ?>
<!-- Examinee Portal Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-primary mb-1">Examinee Portal</h1>
    <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= htmlspecialchars($user_info['first_name'] ?? 'Examinee') ?></span>! Here's your examinee dashboard.</p>
</div>

<!-- Entrance Examination Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
    <div class="p-5">
        <div class="flex items-center gap-2 text-primary font-bold text-base mb-2">
            <i class="fas fa-clipboard-check text-lg"></i>
            <span>Entrance Examination</span>
        </div>
        <p class="text-sm text-gray-500">Apply for entrance examination to SRCB.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="layout.php?page=book_exam" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                <i class="fas fa-calendar-plus"></i>
                <span>Book Exam</span>
            </a>
            <a href="layout.php?page=view_application" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                <i class="fas fa-eye"></i>
                <span>My Application Status</span>
            </a>
        </div>
    </div>
</div>

<!-- My Information Card (Read-only for examinees) -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-primary font-bold text-base">
        <i class="fas fa-id-card"></i>
        <span>My Information</span>
    </div>
    <div class="p-5">
        <div class="grid md:grid-cols-2 gap-x-8 gap-y-4">
            <!-- Left Column -->
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Name</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars(($user_info['first_name'] ?? '').' '.($user_info['last_name'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-envelope text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Email</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($user_info['email'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
            <!-- Right Column -->
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-graduation-cap text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Grade Level Applying</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($grade_level ?: '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-user-tag text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Examinee Type</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($user_info['examinee_type'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- No Update Profile button for examinees -->
    </div>
</div>

<?php elseif ($role === 'student'): ?>
<!-- Student Portal Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-primary mb-1">Student Portal</h1>
    <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= htmlspecialchars($user_info['first_name'] ?? 'Student') ?></span>! Here's your student dashboard.</p>
</div>

<!-- Cards Grid -->
<div class="grid md:grid-cols-2 gap-5 mb-5">
    <!-- Personal Data Sheet Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5">
            <div class="flex items-center gap-2 text-primary font-bold text-base mb-2">
                <i class="fas fa-file-alt text-lg"></i>
                <span>Personal Data Sheet</span>
            </div>
            <p class="text-sm text-gray-500">Fill out or update your personal information.</p>
            <div class="mt-4">
                <?php if($pds_status === 'completed'): ?>
                    <a href="layout.php?page=view_pds" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                        <i class="fas fa-eye"></i>
                        <span>View PDS</span>
                    </a>
                <?php else: ?>
                    <a href="layout.php?page=fill_pds" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                        <i class="fas fa-pen-to-square"></i>
                        <span>Fill out PDS</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Counseling Services Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5">
            <div class="flex items-center gap-2 text-primary font-bold text-base mb-2">
                <i class="fas fa-comments text-lg"></i>
                <span>Counseling Services</span>
            </div>
            <p class="text-sm text-gray-500">Schedule counseling sessions with our guidance counselors.</p>
            
            <?php if($has_active_appointment): ?>
            <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-amber-800 text-sm">You have an active appointment</p>
                    <p class="text-xs text-amber-700">Please complete or cancel before booking a new one.</p>
                </div>
                <a href="layout.php?page=view_appointments" class="text-xs text-amber-700 font-semibold hover:underline">
                    View
                </a>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="layout.php?page=book_appointment" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
                <a href="layout.php?page=view_appointments" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                    <i class="fas fa-eye"></i>
                    <span>View Appointments</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- My Information Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-primary font-bold text-base">
        <i class="fas fa-id-card"></i>
        <span>My Information</span>
    </div>
    <div class="p-5">
        <div class="grid md:grid-cols-2 gap-x-8 gap-y-4">
            <!-- Left Column -->
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Name</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars(($user_info['first_name'] ?? '').' '.($user_info['last_name'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-envelope text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Email</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($user_info['email'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-id-badge text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Student ID</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($student_profile['student_id'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
            <!-- Right Column -->
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-graduation-cap text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Grade Level</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($grade_level ?: '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-building-columns text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Department</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($department ?: '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-book text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Program</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars(($program ?: $strand) ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100">
            <a href="layout.php?page=view_pds" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                <i class="fas fa-user-pen"></i>
                <span>Update Profile</span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($role, ['super_admin','admin','guidance_advocate']) && $students_without_id > 0): ?>
<!-- Missing Student IDs Modal -->
<div id="missingIdModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Students Without Student IDs</h3>
            <button onclick="closeModal('missingIdModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <input type="text" id="missingIdSearch" placeholder="Search by name or email..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-yellow-500 focus:outline-none" oninput="filterMissingIds()">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-left">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Student ID</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody id="missingIdTableBody" class="divide-y divide-gray-100">
                        <?php foreach ($students_missing_id as $student): ?>
                        <tr class="hover:bg-gray-50 <?= $role === 'super_admin' ? 'cursor-pointer' : '' ?>" <?= $role === 'super_admin' ? "onclick=\"window.location.href='layout.php?page=missing_student_ids&q=" . urlencode($student['first_name'] . ' ' . $student['last_name']) . "'" : '' ?>>
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars(($student['last_name'] ?? '') . ', ' . ($student['first_name'] ?? '')) ?></td>
                            <td class="px-4 py-3 text-gray-500 break-all"><?= htmlspecialchars($student['email'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($student['student_id'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-gray-400 text-xs"><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <button onclick="closeModal('missingIdModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <?php if($role === 'super_admin'): ?>
                <a href="layout.php?page=user_management&filter=missing_id" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600">Go to User Management</a>
                <?php else: ?>
                <button onclick="sendMissingIdNotification()" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600">Send Notification</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function openMissingIdModal() {
    openModal('missingIdModal');
}

function sendMissingIdNotification() {
    Swal.fire({
        title: 'Send Notification',
        text: 'This will send a notification to all students without student IDs reminding them to complete their profile.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Send Notification',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Notification Sent!',
                text: 'Students have been notified about their missing student IDs.',
                icon: 'success',
                confirmButtonColor: '#3b82f6'
            }).then(() => {
                closeModal('missingIdModal');
            });
        }
    });
}

function filterMissingIds() {
    const search = document.getElementById('missingIdSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#missingIdTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}
</script>
<?php endif; ?>
