<?php
ob_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
checkLogin();
checkRole(['admin']);

$user_info = getUserInfo();
$uid = $user_info['id'];

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get statistics
$stats = [
    'total_students' => $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','examinee') AND (archived=0 OR archived IS NULL)")->fetchColumn(),
    'pending_counseling' => $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status='pending'")->fetchColumn(),
    'upcoming_exams' => $db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='confirmed' AND preferred_date>=CURDATE()")->fetchColumn(),
    'today_counseling' => $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE DATE(appointment_date)=CURDATE() AND status='confirmed'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
</head>
<body class="min-h-screen bg-gray-50">

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-[#163269] to-[#0f1f42] text-white z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-2xl">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">SRCB</div>
        <div><div class="font-bold text-sm leading-tight">SRCB Guidance</div><div class="text-[10px] text-white/50">Management System</div></div>
    </div>
    <nav class="mt-4 px-3 space-y-1">
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/15 text-white transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span></a>
        <a href="student_records.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-users w-5 text-center"></i><span>Student Records</span></a>
        <a href="manage_counseling.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-comments w-5 text-center"></i><span>Counseling</span></a>
        <a href="manage_exams.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exams</span></a>
        <a href="schedules.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span>Schedules</span></a>
        <a href="system_settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span></a>
    </nav>
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-sm font-semibold"><?= $initials ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
                <div class="text-[10px] text-white/50 uppercase">Admin</div>
            </div>
            <a href="../auth/logout.php" class="text-white/50 hover:text-red-400 transition-colors" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<!-- Header -->
<header class="fixed top-0 left-0 lg:left-64 right-0 h-14 bg-white border-b border-gray-200 z-40 flex items-center justify-between px-5">
    <button id="sidebarToggle" class="lg:hidden text-primary"><i class="fas fa-bars text-xl"></i></button>
    <h1 class="text-lg font-bold text-primary hidden lg:block">Admin Dashboard</h1>
    <div class="flex items-center gap-3">
        <div class="relative">
            <button id="accountBtn" class="flex items-center gap-2 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-xs font-semibold text-white"><?= $initials ?></div>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div id="accountMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-2">
                <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<div id="sidebarOverlay" class="lg:hidden fixed inset-0 bg-black/50 z-30 hidden"></div>

<!-- Main Content -->
<div class="lg:ml-64 pt-14">
    <div class="p-5">
        <h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-home mr-2"></i>Admin Dashboard</h1>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Total Students</p>
                        <p class="text-lg font-bold text-gray-800"><?= $stats['total_students'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Pending Counseling</p>
                        <p class="text-lg font-bold text-gray-800"><?= $stats['pending_counseling'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Upcoming Exams</p>
                        <p class="text-lg font-bold text-gray-800"><?= $stats['upcoming_exams'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Today's Counseling</p>
                        <p class="text-lg font-bold text-gray-800"><?= $stats['today_counseling'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <h3 class="font-bold text-primary text-sm mb-3"><i class="fas fa-bolt mr-1"></i>Quick Actions</h3>
            <div class="grid md:grid-cols-3 gap-3">
                <a href="manage_counseling.php" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Manage Counseling</p>
                        <p class="text-xs text-gray-400">View appointments</p>
                    </div>
                </a>
                <a href="manage_exams.php" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all">
                    <div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Entrance Exams</p>
                        <p class="text-xs text-gray-400">Manage applications</p>
                    </div>
                </a>
                <a href="schedules.php" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Schedules</p>
                        <p class="text-xs text-gray-400">Manage schedules</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const accountBtn = document.getElementById('accountBtn');
const accountMenu = document.getElementById('accountMenu');

sidebarToggle?.addEventListener('click', function() {
    sidebar?.classList.toggle('-translate-x-full');
    sidebarOverlay?.classList.toggle('hidden');
});

sidebarOverlay?.addEventListener('click', function() {
    sidebar?.classList.add('-translate-x-full');
    this.classList.add('hidden');
});

accountBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    accountMenu?.classList.toggle('hidden');
});

document.addEventListener('click', () => accountMenu?.classList.add('hidden'));
</script>
</body>
</html>
<?php ob_end_flush(); ?>
