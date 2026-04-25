<?php
require_once '../config/database.php';
require_once '../includes/session.php';
checkLogin();
define('IN_LAYOUT', true);

$user_info = getUserInfo();
$role = $user_info['role'];
$uid = $user_info['id'];

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

// Ensure we have complete user info (e.g., email) from the database
try {
    $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $u_stmt->execute([$uid]);
    $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
    if ($u_row) {
        // Database values should take precedence for completeness
        $user_info = array_merge($user_info, $u_row);
        $role = $user_info['role'] ?? $role;
    }
} catch (Exception $e) {}

// Allowed pages and their titles
$pages = [
    'dashboard' => 'Dashboard',
    'profile' => 'My Profile',
    'fill_pds' => 'Personal Data Sheet',
    'view_pds' => 'View PDS',
    'book_appointment' => 'Book Counseling',
    'view_appointments' => 'My Appointments',
    'complete_profile' => 'Complete Profile',
    'student_records' => 'Student Records',
    'manage_counseling' => 'Counseling',
    'manage_exams' => 'Entrance Exams',
    'schedules' => 'Schedules',
    'user_management' => 'User Management',
    'system_settings' => 'System Settings',
    'system_logs' => 'System Logs',
    'book_exam' => 'Entrance Exam',
];

$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $pages)) $page = 'dashboard';

// Role-based access check
$student_pages = ['dashboard','profile','fill_pds','view_pds','book_appointment','view_appointments','complete_profile'];
$examinee_pages = ['dashboard','profile','fill_pds','view_pds','book_exam','complete_profile'];
$admin_pages = ['dashboard','profile','student_records','manage_counseling','manage_exams','schedules','user_management','system_settings','system_logs'];

if (in_array($role, ['student']) && !in_array($page, $student_pages)) $page = 'dashboard';
if ($role === 'examinee' && !in_array($page, $examinee_pages)) $page = 'dashboard';
if (in_array($role, ['super_admin','admin','guidance_advocate']) && !in_array($page, $admin_pages)) $page = 'dashboard';

$page_title = $pages[$page];

// Map page to file path
$file_map = [
    'dashboard' => __DIR__ . '/index.php',
    'profile' => __DIR__ . '/profile.php',
    'fill_pds' => __DIR__ . '/../pds/fill_pds.php',
    'view_pds' => __DIR__ . '/../pds/view_pds.php',
    'book_appointment' => __DIR__ . '/../counseling/book_appointment.php',
    'view_appointments' => __DIR__ . '/../counseling/view_appointments.php',
    'complete_profile' => __DIR__ . '/../profile/complete_profile.php',
    'student_records' => __DIR__ . '/student_records.php',
    'manage_counseling' => __DIR__ . '/manage_counseling.php',
    'manage_exams' => __DIR__ . '/manage_exams.php',
    'schedules' => __DIR__ . '/schedules.php',
    'user_management' => __DIR__ . '/user_management.php',
    'system_settings' => __DIR__ . '/system_settings.php',
    'system_logs' => __DIR__ . '/system_logs.php',
    'book_exam' => __DIR__ . '/../entrance_exam/book_exam.php',
];
$content_file = $file_map[$page] ?? __DIR__ . '/index.php';

$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
    <?php if (in_array($page, ['fill_pds'])): ?>
    <script src="../pds/wizard.js" defer></script>
    <style>.step-panel{display:none}.step-panel.active{display:block}</style>
    <style>
     .pds-swal{border-radius:18px;padding:28px 22px !important;}
     .pds-swal .swal2-title{margin-top:8px !important;font-size:20px !important;color:#111827 !important;}
     .pds-swal .swal2-html-container{margin:10px 0 0 0 !important;}
     .pds-swal-confirm{background:#0ea5e9 !important;border-radius:10px !important;padding:10px 18px !important;font-weight:700 !important;}
     .pds-swal-cancel{background:#4f46e5 !important;border-radius:10px !important;padding:10px 18px !important;font-weight:700 !important;}
     .pds-swal-actions{gap:10px !important;margin-top:18px !important;}
     .pds-intro-overlay{position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.35);display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none;}
     .pds-intro-card{width:380px;max-width:92vw;background:#fff;border-radius:16px;box-shadow:0 30px 80px rgba(2,6,23,.25);padding:22px;pointer-events:auto;}
     .pds-intro-icon{width:56px;height:56px;border-radius:9999px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;margin:0 auto;}
     .pds-intro-icon-inner{width:40px;height:40px;border-radius:9999px;background:#2563eb;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 25px rgba(37,99,235,.35)}
     .pds-intro-btn{border-radius:10px;padding:10px 14px;font-weight:700;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:8px;}
     @media (min-width: 1024px) {
         .pds-intro-overlay{left:16rem;}
     }
    </style>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-gray-50">

<!-- Sidebar -->
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
        <a href="layout.php?page=fill_pds" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='fill_pds'||$page==='view_pds'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>Personal Data Sheet</span></a>
        <a href="layout.php?page=book_exam" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?= $page==='book_exam'?'bg-white/15 text-white':'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-colors text-sm">
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

<?php include __DIR__ . '/partials/header.php'; ?>

<div id="sidebarOverlay" class="lg:hidden fixed inset-0 bg-black/50 z-30 hidden"></div>

<!-- Main Content Area -->
<div class="lg:ml-64 pt-14">
    <div id="mainContent" class="p-5">
        <?php if (file_exists($content_file)): include $content_file; else: ?>
        <div class="text-center py-20"><i class="fas fa-exclamation-triangle text-4xl text-gray-300 mb-3"></i><p class="text-gray-400">Page not found.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

sidebarToggle?.addEventListener('click', function() {
    sidebar?.classList.toggle('-translate-x-full');
    sidebarOverlay?.classList.toggle('hidden');
});

sidebarOverlay?.addEventListener('click', function() {
    sidebar?.classList.add('-translate-x-full');
    this.classList.add('hidden');
});

const notifBtn = document.getElementById('notifBtn');
const notifMenu = document.getElementById('notifMenu');
const accountBtn = document.getElementById('accountBtn');
const accountMenu = document.getElementById('accountMenu');

function hideMenus() {
    notifMenu?.classList.add('hidden');
    accountMenu?.classList.add('hidden');
}

notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    accountMenu?.classList.add('hidden');
    notifMenu?.classList.toggle('hidden');
});

accountBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notifMenu?.classList.add('hidden');
    accountMenu?.classList.toggle('hidden');
});

document.addEventListener('click', () => hideMenus());

function toggleSubmenu(name) {
    const submenu = document.getElementById(name + '-submenu');
    const chevron = document.getElementById(name + '-chevron');
    if (submenu && chevron) {
        submenu.classList.toggle('hidden');
        chevron.classList.toggle('rotate-180');
    }
}
</script>
</body>
</html>
