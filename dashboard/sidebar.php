<?php
if (!isset($user_info)) { require_once '../includes/session.php'; $user_info = getUserInfo(); }
if (!isset($unread_count)) $unread_count = 0;

$role = $user_info['role'] ?? '';
$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-[#163269] to-[#0f1f42] text-white z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-2xl">
    <!-- Logo -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">SRCB</div>
        <div>
            <div class="font-bold text-sm leading-tight">SRCB Guidance</div>
            <div class="text-[10px] text-white/50">Management System</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-4 px-3 space-y-1">
        <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span>
        </a>

        <?php if (in_array($role, ['super_admin', 'admin', 'guidance_advocate'])): ?>
        <a href="student_records.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-users w-5 text-center"></i><span>Student Records</span>
        </a>
        <a href="manage_counseling.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-comments w-5 text-center"></i><span>Counseling</span>
        </a>
        <a href="manage_entrance_exams.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exams</span>
        </a>
        <a href="schedule_management.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span>Schedules</span>
        </a>
        <?php endif; ?>

        <?php if ($role === 'super_admin'): ?>
        <a href="user_management.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-users-cog w-5 text-center"></i><span>User Management</span>
        </a>
        <a href="system_settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span>
        </a>
        <a href="system_logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>System Logs</span>
        </a>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <a href="../pds/fill_pds.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>Personal Data Sheet</span>
        </a>
        <a href="../counseling/book_appointment.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-calendar-plus w-5 text-center"></i><span>Book Counseling</span>
        </a>
        <?php endif; ?>

        <?php if ($role === 'examinee'): ?>
        <a href="../entrance_exam/book_exam.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exam</span>
        </a>
        <?php endif; ?>

        <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-user w-5 text-center"></i><span>My Profile</span>
        </a>
    </nav>

    <!-- User section at bottom -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-sm font-semibold"><?= $initials ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></div>
                <div class="text-[10px] text-white/50 uppercase"><?= str_replace('_', ' ', $role) ?></div>
            </div>
            <a href="../auth/logout.php" class="text-white/50 hover:text-red-400 transition-colors" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<!-- Mobile topbar -->
<div class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-[#163269] text-white flex items-center justify-between px-4 z-40 shadow-lg">
    <button id="sidebarToggle" class="text-white text-xl"><i class="fas fa-bars"></i></button>
    <span class="font-bold text-sm">SRCB Guidance</span>
    <a href="../auth/logout.php" class="text-white/70 hover:text-white"><i class="fas fa-sign-out-alt"></i></a>
</div>

<!-- Mobile overlay -->
<div id="sidebarOverlay" class="lg:hidden fixed inset-0 bg-black/50 z-40 hidden"></div>

<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
});
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    this.classList.add('hidden');
});
</script>
