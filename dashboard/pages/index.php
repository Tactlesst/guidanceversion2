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

        $grade_stats = $db->query("SELECT sp.grade_level, COUNT(*) as cnt FROM student_profiles sp JOIN users u ON sp.user_id=u.id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) GROUP BY sp.grade_level ORDER BY sp.grade_level")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $grade_stats = []; }
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

    <div>
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <a href="layout.php?page=system_settings" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3"><i class="fas fa-cogs"></i></div>
                <div class="font-semibold text-gray-800">System Settings</div>
                <div class="text-xs text-gray-500 mt-1">Configure system features</div>
            </a>
        </div>
    </div>

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
