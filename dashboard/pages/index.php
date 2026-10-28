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
            WHERE DATE(s.start_datetime) <= CURDATE() AND DATE(s.end_datetime) >= CURDATE() 
            AND s.is_active = 1
            ORDER BY s.start_datetime ASC
        ");
        $schedule_stmt->execute();
        $today_schedules = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
<?php include __DIR__ . '/../partials/overview.php'; ?>

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
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($user_info['email'] ?? 'N/A') ?></div>
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
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($grade_level ?: 'N/A') ?></div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-5 flex justify-center text-primary mt-0.5">
                        <i class="fas fa-user-tag text-sm"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-medium">Examinee Type</div>
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($user_info['examinee_type'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- No Update Profile button for examinees -->
    </div>
</div>

<?php elseif ($role === 'student'): ?>
<?php include __DIR__.'/../partials/student_overview.php'; ?>
<?php endif; ?>

<?php if (in_array($role, ['super_admin','admin','guidance_advocate']) && $students_without_id > 0): ?>
<!-- Missing Student IDs Modal -->
<div id="missingIdModal" class="pd-overlay hidden" data-portal-dialog data-managed-dialog data-dialog-tone="security" role="dialog" aria-modal="true" aria-labelledby="missingIdTitle" aria-describedby="missingIdHint">
    <div class="pd-card pd-card-wide">
        <div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-id-card" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="missingIdTitle">Missing student IDs</h2><p id="missingIdHint">Find student accounts that need an official school ID.</p></div><button type="button" class="pd-close" data-dialog-close="missingIdModal" aria-label="Close missing student IDs"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        <div class="pd-body">
            <div class="mb-4">
                <label for="missingIdSearch" class="pd-hint" style="margin-bottom:7px">Search students</label><input type="search" id="missingIdSearch" placeholder="Search by name or email..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-yellow-500 focus:outline-none" oninput="filterMissingIds()">
            </div>
            <div class="pd-table-wrap">
                <table class="pd-table">
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
                            <td class="px-4 py-3 text-gray-500 break-all"><?= htmlspecialchars($student['email'] ?? 'N/A') ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></td>
                            <td class="px-4 py-3 text-gray-400 text-xs"><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
            <div class="pd-footer">
                <button type="button" data-dialog-close="missingIdModal" class="pd-button">Close</button>
                <?php if($role === 'super_admin'): ?>
                <a href="layout.php?page=user_management&filter=missing_id" class="pd-button pd-button-primary">Go to User Management</a>
                <?php else: ?>
                <button onclick="sendMissingIdNotification()" class="pd-button pd-button-primary">Send Notification</button>
                <?php endif; ?>
            </div>
    </div>
</div>

<script>
function openMissingIdModal() {
    if(window.PortalDialogs){const dialog=document.getElementById('missingIdModal');window.PortalDialogs.open(dialog);document.getElementById('missingIdSearch').focus({preventScroll:true});}else openModal('missingIdModal');
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
