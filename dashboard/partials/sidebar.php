<?php
// Sidebar partial — included by layout.php
?>
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-[#163269] to-[#0f1f42] text-white z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-2xl">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">SRCB</div>
        <div><div class="font-bold text-sm leading-tight">SRCB Guidance</div><div class="text-[10px] text-white/50">Management System</div></div>
    </div>
    <nav class="mt-4 px-3 space-y-1">
        <a href="layout.php?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='dashboard'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span></a>

        <?php if (in_array($role, ['super_admin','admin','guidance_advocate'])): ?>
        <a href="layout.php?page=student_records" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='student_records'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-users w-5 text-center"></i><span>Student Records</span></a>
        <a href="layout.php?page=manage_counseling" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='manage_counseling'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-comments w-5 text-center"></i><span>Counseling</span></a>
        <a href="layout.php?page=manage_exams" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='manage_exams'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exams</span></a>
        <a href="layout.php?page=schedules" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='schedules'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span>Schedules</span></a>
        <?php endif; ?>

        <?php if ($role === 'super_admin'): ?>
        <a href="layout.php?page=user_management" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='user_management'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-users-cog w-5 text-center"></i><span>User Management</span></a>
        <a href="layout.php?page=system_settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='system_settings'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span></a>
        <a href="layout.php?page=system_logs" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='system_logs'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>System Logs</span></a>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <a href="layout.php?page=fill_pds" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='fill_pds'||$page==='view_pds'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>Personal Data Sheet</span></a>
        <div class="counseling-submenu">
            <button onclick="toggleSubmenu('counseling')" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg <?= $page==='book_appointment'||$page==='view_appointments'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-calendar-plus w-5 text-center"></i><span>Counseling</span>
                </div>
                <i id="counseling-chevron" class="fas fa-chevron-down text-xs transition-transform <?= $page==='book_appointment'||$page==='view_appointments'?'rotate-180':'' ?>"></i>
            </button>
            <div id="counseling-submenu" class="<?= $page==='book_appointment'||$page==='view_appointments'?'':'hidden' ?> pl-8 mt-1 space-y-1">
                <a href="layout.php?page=book_appointment" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='book_appointment'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-plus w-5 text-center"></i><span>Book Appointment</span></a>
                <a href="layout.php?page=view_appointments" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $page==='view_appointments'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
                    <i class="fas fa-list w-5 text-center"></i><span>View Appointments</span></a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'examinee'): ?>
        <a href="layout.php?page=entrance_exam/book_exam" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='entrance_exam/book_exam'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exam</span></a>
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
