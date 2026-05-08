<?php
ob_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
checkLogin();
checkRole(['student']);

$user_info = getUserInfo();
$uid = $user_info['id'];

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get student profile
$student_profile = null;
try {
    $sp_stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $sp_stmt->execute([$uid]);
    $student_profile = $sp_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $student_profile = null; }

$grade_level = $student_profile['grade_level'] ?? '';
$department = $student_profile['department'] ?? '';
$program = $student_profile['program'] ?? '';
$strand = $student_profile['strand'] ?? '';
$student_id = $student_profile['student_id'] ?? '';

// Check if student is eligible for Multiple Intelligence Survey
$mi_eligible_grades = ['Grade 11', 'Grade 12', '1st Year College', '2nd Year College', '3rd Year College', '4th Year College'];
$show_mi_survey = in_array($grade_level, $mi_eligible_grades);

// Check PDS status
$pds_status = 'pending';
try {
    $ps = $db->prepare("SELECT id FROM pds WHERE user_id = ? LIMIT 1");
    $ps->execute([$uid]);
    $pds_status = $ps->fetchColumn() ? 'completed' : 'pending';
} catch (Exception $e) { $pds_status = 'pending'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SRCB Guidance</title>
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
        <a href="student_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/15 text-white transition-colors text-sm">
            <i class="fas fa-home w-5 text-center"></i><span>Dashboard</span></a>
        <a href="../pds/fill_pds.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-file-alt w-5 text-center"></i><span>Personal Data Sheet</span></a>
        <div>
            <button onclick="toggleCounselingSubmenu()" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-comments w-5 text-center"></i><span>Counseling Services</span>
                </div>
                <i id="counseling-chevron" class="fas fa-chevron-down text-xs transition-transform"></i>
            </button>
            <div id="counseling-submenu" class="hidden pl-8 mt-1 space-y-1">
                <a href="../counseling/book_appointment.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
                    <i class="fas fa-calendar-plus w-4 text-center"></i><span>Book Appointment</span></a>
                <a href="../counseling/view_appointments.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
                    <i class="fas fa-list w-4 text-center"></i><span>View Appointments</span></a>
            </div>
        </div>
        <?php if($show_mi_survey): ?>
        <a href="../surveys/multiple_intelligence_survey.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors text-sm">
            <i class="fas fa-brain w-5 text-center"></i><span>Multiple Intelligence Survey</span></a>
        <?php endif; ?>
    </nav>
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-sm font-semibold"><?= $initials ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
                <div class="text-[10px] text-white/50 uppercase">Student</div>
            </div>
            <a href="../auth/logout.php" class="text-white/50 hover:text-red-400 transition-colors" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<!-- Header -->
<header class="fixed top-0 left-0 lg:left-64 right-0 h-14 bg-white border-b border-gray-200 z-40 flex items-center justify-between px-5">
    <button id="sidebarToggle" class="lg:hidden text-primary"><i class="fas fa-bars text-xl"></i></button>
    <h1 class="text-lg font-bold text-primary hidden lg:block">Student Portal</h1>
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
            <h1 class="text-2xl font-bold text-primary mb-1">Student Portal</h1>
            <p class="text-sm text-gray-500">Welcome back, <span class="font-semibold text-gray-700"><?= htmlspecialchars($first_name) ?></span>! Here's your student dashboard.</p>
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
                            <a href="../pds/view_pds.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                                <i class="fas fa-eye"></i>
                                <span>View PDS</span>
                            </a>
                        <?php else: ?>
                            <a href="../pds/fill_pds.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
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
                        <a href="../counseling/book_appointment.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors shadow-sm">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Book Appointment</span>
                        </a>
                        <a href="../counseling/view_appointments.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
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
                        <div class="flex items-start gap-3">
                            <div class="w-5 flex justify-center text-primary mt-0.5">
                                <i class="fas fa-id-badge text-sm"></i>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 font-medium">Student ID</div>
                                <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($student_id ?: '—') ?></div>
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
                    <a href="../pds/view_pds.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">
                        <i class="fas fa-user-pen"></i>
                        <span>Update Profile</span>
                    </a>
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

function toggleCounselingSubmenu() {
    const submenu = document.getElementById('counseling-submenu');
    const chevron = document.getElementById('counseling-chevron');
    submenu?.classList.toggle('hidden');
    chevron?.classList.toggle('rotate-180');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
