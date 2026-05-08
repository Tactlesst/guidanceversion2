<?php
// Sidebar partial — included by layout.php
?>
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-[#163269] to-[#0f1f42] text-white z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-2xl">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">SRCB</div>
        <div><div class="font-bold text-sm leading-tight">SRCB Guidance</div><div class="text-[10px] text-white/50">Management System</div></div>
    </div>
    <nav class="mt-4 px-3 space-y-1">
        <!-- Dashboard - All roles -->
        <a href="layout.php?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='dashboard'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span></a>

        <?php if ($role === 'super_admin'): ?>
        <!-- Super Admin Menu -->
        <a href="layout.php?page=user_management" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='user_management'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-users-cog w-5 text-center"></i><span>User Management</span></a>
        <a href="layout.php?page=academic_settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='academic_settings'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-graduation-cap w-5 text-center"></i><span>Academic Settings</span></a>
        <a href="layout.php?page=backup_restore" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='backup_restore'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-database w-5 text-center"></i><span>Backup & Restore</span></a>
        <a href="layout.php?page=system_logs" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='system_logs'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>System Logs</span></a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin','guidance_advocate'])): ?>
        <!-- Admin/Guidance Advocate Menu -->
        <a href="layout.php?page=student_records" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='student_records'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-users w-5 text-center"></i><span>Student Management</span></a>
        <a href="layout.php?page=manage_counseling" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='manage_counseling'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-comments w-5 text-center"></i><span>Counseling Management</span></a>
        <a href="layout.php?page=manage_exams" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='manage_exams'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exam Appointments</span></a>
        <a href="layout.php?page=manage_announcements" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='manage_announcements'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-bullhorn w-5 text-center"></i><span>Announcements</span></a>
        <a href="layout.php?page=schedules" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='schedules'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span>Schedule</span></a>
        <a href="layout.php?page=daily_limits" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='daily_limits'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-calendar-check w-5 text-center"></i><span>Daily Booking Limits</span></a>
        <?php if ($role === 'admin'): ?>
        <a href="layout.php?page=system_settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='system_settings'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span></a>
        <?php endif; ?>
        <div>
            <button onclick="toggleSubmenu('reports')" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg <?= in_array($page,['counseling_reports','ses_analytics','olsat_reports'])?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-bar w-5 text-center"></i><span>Reports & Analytics</span>
                </div>
                <i id="reports-chevron" class="fas fa-chevron-down text-xs transition-transform <?= in_array($page,['counseling_reports','ses_analytics','olsat_reports'])?'rotate-180':'' ?>"></i>
            </button>
            <div id="reports-submenu" class="<?= in_array($page,['counseling_reports','ses_analytics','olsat_reports'])?'':'hidden' ?> pl-8 mt-1 space-y-1">
                <a href="layout.php?page=counseling_reports" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='counseling_reports'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-calendar-alt w-4 text-center"></i><span>Counseling Reports</span></a>
                <a href="../admin/ses_analytics.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
                    <i class="fas fa-chart-line w-4 text-center"></i><span>SES Analytics</span></a>
                <a href="layout.php?page=olsat_reports" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='olsat_reports'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-clipboard-list w-4 text-center"></i><span>OLSAT Exam Reports</span></a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <?php
        // Check if student is eligible for Multiple Intelligence Survey
        $student_grade_level = '';
        try {
            $sg_stmt = $db->prepare("SELECT grade_level FROM student_profiles WHERE user_id = ?");
            $sg_stmt->execute([$uid]);
            $sg_row = $sg_stmt->fetch(PDO::FETCH_ASSOC);
            $student_grade_level = $sg_row['grade_level'] ?? '';
        } catch (Exception $e) {}
        $mi_eligible_grades = ['Grade 11', 'Grade 12', '1st Year College', '2nd Year College', '3rd Year College', '4th Year College'];
        $show_mi_survey = in_array($student_grade_level, $mi_eligible_grades);
        ?>
        <a href="layout.php?page=fill_pds" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='fill_pds'||$page==='view_pds'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>Personal Data Sheet</span></a>
        <div class="counseling-submenu">
            <button onclick="toggleSubmenu('counseling')" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg <?= $page==='book_appointment'||$page==='view_appointments'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-comments w-5 text-center"></i><span>Counseling Services</span>
                </div>
                <i id="counseling-chevron" class="fas fa-chevron-down text-xs transition-transform <?= $page==='book_appointment'||$page==='view_appointments'?'rotate-180':'' ?>"></i>
            </button>
            <div id="counseling-submenu" class="<?= $page==='book_appointment'||$page==='view_appointments'?'':'hidden' ?> pl-8 mt-1 space-y-1">
                <a href="layout.php?page=book_appointment" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='book_appointment'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-calendar-plus w-4 text-center"></i><span>Book Appointment</span></a>
                <a href="layout.php?page=view_appointments" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='view_appointments'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-list w-4 text-center"></i><span>View Appointments</span></a>
            </div>
        </div>
        <?php if($show_mi_survey): ?>
        <a href="layout.php?page=multiple_intelligence_survey" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='multiple_intelligence_survey'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-brain w-5 text-center"></i><span>Multiple Intelligence Survey</span></a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($role === 'examinee'): ?>
        <?php
        // Check if examinee has completed exam results
        $has_completed_results = false;
        try {
            $completed_check_query = "SELECT id FROM entrance_exam_appointments 
                                     WHERE user_id = ? 
                                     AND status = 'completed' 
                                     AND (total_score IS NOT NULL OR qualified_grade IS NOT NULL)
                                     LIMIT 1";
            $completed_check_stmt = $db->prepare($completed_check_query);
            $completed_check_stmt->execute([$uid]);
            $has_completed_results = $completed_check_stmt->rowCount() > 0;
        } catch (Exception $e) {}
        ?>
        <!-- Examinee Menu -->
        <?php if(!$has_completed_results): ?>
        <a href="layout.php?page=book_exam" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='book_exam'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span>Book Entrance Exam</span></a>
        <?php else: ?>
        <a href="layout.php?page=view_exam_results" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='view_exam_results'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-certificate w-5 text-center"></i><span>View Exam Results</span></a>
        <?php endif; ?>
        <a href="layout.php?page=view_application" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='view_application'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-text w-5 text-center"></i><span>My Application Status</span></a>
        <?php endif; ?>

    </nav>

    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-sm font-semibold"><?= $initials ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
                <div class="text-[10px] text-white/50 uppercase"><?= str_replace('_',' ',$role) ?></div>
            </div>
            <a href="../auth/logout.php" class="text-white/50 hover:text-red-400 transition-colors" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>
