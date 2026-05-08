<?php
ob_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
checkLogin();
checkRole(['examinee']);

$user_info = getUserInfo();
$uid = $user_info['id'];

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

$grade_level = $user_info['grade_level_applying'] ?? '';
$examinee_type = $user_info['examinee_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examinee Dashboard - SRCB Guidance</title>
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
        <a href="examinee_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/15 text-white transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span></a>
        <a href="../entrance_exam/book_exam.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-clipboard-list w-5 text-center"></i><span>Entrance Exam</span></a>
    </nav>
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-sm font-semibold"><?= $initials ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
                <div class="text-[10px] text-white/50 uppercase">Examinee</div>
            </div>
            <a href="../auth/logout.php" class="text-white/50 hover:text-red-400 transition-colors" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<!-- Header -->
<header class="fixed top-0 left-0 lg:left-64 right-0 h-14 bg-white border-b border-gray-200 z-40 flex items-center justify-between px-5">
    <button id="sidebarToggle" class="lg:hidden text-primary"><i class="fas fa-bars text-xl"></i></button>
    <h1 class="text-lg font-bold text-primary hidden lg:block">Examinee Portal</h1>
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
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-primary mb-1">Examinee Portal</h1>
            <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= htmlspecialchars($first_name) ?></span>! Here's your examinee dashboard.</p>
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
                    <a href="../entrance_exam/book_exam.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book Exam</span>
                    </a>
                    <a href="../entrance_exam/book_exam.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                        <i class="fas fa-eye"></i>
                        <span>My Application Status</span>
                    </a>
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
                                <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
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
                                <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($examinee_type ?: '—') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
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
