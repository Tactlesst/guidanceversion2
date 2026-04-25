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

$stats = ['total_users'=>0,'active_users'=>0,'total_students'=>0,'total_examinees'=>0,'upcoming_exams'=>0,'pending_counseling'=>0,'submitted_pds'=>0,'today_counseling'=>0,'awaiting_results'=>0];

if (in_array($role, ['super_admin','admin','guidance_advocate'])) {
    try {
        $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['active_users'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['total_students'] = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','examinee') AND (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['total_examinees'] = $db->query("SELECT COUNT(*) FROM users WHERE role='examinee' AND (archived=0 OR archived IS NULL)")->fetchColumn();
        $stats['upcoming_exams'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='confirmed' AND preferred_date>=CURDATE()")->fetchColumn();
        $stats['pending_counseling'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status='pending'")->fetchColumn();
        $stats['today_counseling'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE DATE(appointment_date)=CURDATE() AND status='confirmed'")->fetchColumn();
        $stats['awaiting_results'] = $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='confirmed'")->fetchColumn();
        $grade_stats = $db->query("SELECT sp.grade_level, COUNT(*) as cnt FROM student_profiles sp JOIN users u ON sp.user_id=u.id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) GROUP BY sp.grade_level ORDER BY sp.grade_level")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $grade_stats = []; }
    $recent = [];
    try {
        $act_stmt = $db->query("(SELECT u.first_name, u.last_name, u.created_at, 'registration' as type, u.role as extra FROM users u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.role IN ('student','examinee') ORDER BY u.created_at DESC LIMIT 3) UNION ALL (SELECT u.first_name, u.last_name, ca.created_at, 'counseling' as type, ca.status as extra FROM counseling_appointments ca JOIN users u ON ca.user_id=u.id WHERE ca.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY ca.created_at DESC LIMIT 3) ORDER BY created_at DESC LIMIT 5");
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

<h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-home mr-2"></i>Dashboard</h1>

<?php if (in_array($role, ['super_admin','admin','guidance_advocate'])): ?>
<!-- Admin Dashboard -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center"><i class="fas fa-users"></i></div><div><p class="text-xs text-gray-400">Total Users</p><p class="text-lg font-bold text-gray-800"><?= $stats['total_users'] ?></p></div></div></div>
    <div class="bg-white rounded-xl p-4 shadow-sm"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center"><i class="fas fa-user-check"></i></div><div><p class="text-xs text-gray-400">Active</p><p class="text-lg font-bold text-gray-800"><?= $stats['active_users'] ?></p></div></div></div>
    <div class="bg-white rounded-xl p-4 shadow-sm"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center"><i class="fas fa-graduation-cap"></i></div><div><p class="text-xs text-gray-400">Students</p><p class="text-lg font-bold text-gray-800"><?= $stats['total_students'] ?></p></div></div></div>
    <div class="bg-white rounded-xl p-4 shadow-sm"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center"><i class="fas fa-clipboard-list"></i></div><div><p class="text-xs text-gray-400">Pending Counseling</p><p class="text-lg font-bold text-gray-800"><?= $stats['pending_counseling'] ?></p></div></div></div>
</div>
<div class="grid md:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl p-5 shadow-sm"><h3 class="font-bold text-primary text-sm mb-3"><i class="fas fa-chart-bar mr-1"></i>Students by Grade</h3>
        <?php if(!empty($grade_stats)): foreach($grade_stats as $g): ?>
        <div class="flex items-center justify-between py-1.5 text-sm"><span class="text-gray-600"><?= htmlspecialchars($g['grade_level']) ?></span><span class="font-semibold"><?= $g['cnt'] ?></span></div>
        <?php endforeach; else: ?><p class="text-gray-400 text-sm">No data</p><?php endif; ?>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm"><h3 class="font-bold text-primary text-sm mb-3"><i class="fas fa-clock mr-1"></i>Recent Activity</h3>
        <?php if(!empty($recent)): foreach($recent as $r): ?>
        <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0"><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs text-gray-500"><?= strtoupper(substr($r['first_name'],0,1)) ?></div><div><p class="text-sm"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?> <span class="text-xs text-gray-400"><?= $r['type']==='registration'?'registered':'booked counseling' ?></span></p><p class="text-[10px] text-gray-400"><?= timeAgo($r['created_at']) ?></p></div></div>
        <?php endforeach; else: ?><p class="text-gray-400 text-sm">No recent activity</p><?php endif; ?>
    </div>
</div>

<?php elseif (in_array($role, ['student','examinee'])): ?>
<div class="mb-5">
    <h1 class="text-2xl font-bold text-primary">Student Portal</h1>
    <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold"><?= htmlspecialchars($user_info['first_name'] ?? 'Student') ?></span>! Here's your student dashboard.</p>
</div>

<div class="grid md:grid-cols-2 gap-5 mb-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5">
            <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                <i class="fas fa-file-alt"></i>
                <span>Personal Data Sheet</span>
            </div>
            <p class="text-sm text-gray-500 mt-2">Fill out or update your personal information.</p>
            <div class="mt-4">
                <?php if($pds_status === 'completed'): ?>
                    <a href="layout.php?page=view_pds" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                        <i class="fas fa-eye"></i>
                        <span>View PDS</span>
                    </a>
                <?php else: ?>
                    <a href="layout.php?page=fill_pds" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                        <i class="fas fa-pen-to-square"></i>
                        <span>Fill out PDS</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5">
            <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                <i class="fas fa-comments"></i>
                <span>Counseling Services</span>
            </div>
            <p class="text-sm text-gray-500 mt-2">Schedule counseling sessions with our guidance counselors.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="layout.php?page=book_appointment" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
                <a href="layout.php?page=view_appointments" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                    <i class="fas fa-calendar"></i>
                    <span>View Appointments</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2 text-primary font-semibold text-sm">
        <i class="fas fa-id-card"></i>
        <span>My Information</span>
    </div>
    <div class="p-5">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div class="flex items-start gap-3">
                    <i class="fas fa-user text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Name</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars(($user_info['first_name'] ?? '').' '.($user_info['last_name'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-envelope text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Email</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($user_info['email'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-id-badge text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Student ID</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($student_profile['student_id'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex items-start gap-3">
                    <i class="fas fa-layer-group text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Grade Level</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($grade_level ?: '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-building-columns text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Department</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($department ?: '—') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-graduation-cap text-primary mt-0.5"></i>
                    <div>
                        <div class="text-xs text-gray-500">Program</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars(($program ?: $strand) ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5">
            <a href="layout.php?page=profile" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                <i class="fas fa-user-pen"></i>
                <span>Update Profile</span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
