<?php
$in_layout = defined('IN_LAYOUT');

if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    require_once '../classes/CounselingAppointment.php';
    require_once '../classes/CounselingRemarks.php';
    require_once '../classes/SystemSettings.php';
    require_once '../classes/Notification.php';
    checkLogin();
    checkRole(['student']);
    
    $database = new Database();
    $db = $database->getConnection();
} else {
    require_once __DIR__ . '/../classes/CounselingAppointment.php';
    require_once __DIR__ . '/../classes/CounselingRemarks.php';
    require_once __DIR__ . '/../classes/SystemSettings.php';
    require_once __DIR__ . '/../classes/Notification.php';
    // $db is already available from layout.php
}

$user_info = getUserInfo();
$counseling = new CounselingAppointment($db);
$counseling_remarks = new CounselingRemarks($db);
$settings = new SystemSettings($db);
$notification = new Notification($db);

// Get unread notification count
$unread_count = $notification->getUnreadCount($user_info['id']);

// Get user's appointments using the class method (already includes reschedule details via LEFT JOIN)
$appointments_result = $counseling->getUserAppointments($user_info['id']);
$appointments = [];
if ($appointments_result) {
    while ($app = $appointments_result->fetch(PDO::FETCH_ASSOC)) {
        $appointments[] = $app;
    }
}

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Status badge helper
function statusBadge($status) {
    $map = [
        'pending' => 'bg-amber-100 text-amber-700',
        'confirmed' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-green-100 text-green-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'rescheduled' => 'bg-purple-100 text-purple-700',
        'missed' => 'bg-red-100 text-red-700',
        'in_progress' => 'bg-indigo-100 text-indigo-700',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-600';
}

function concernLabel($type) {
    $map = ['academic' => 'Academic', 'personal' => 'Personal', 'behavioral' => 'Behavioral', 'career' => 'Career Guidance', 'family' => 'Family Issues', 'other' => 'Other'];
    return $map[$type] ?? ucfirst($type);
}

function urgencyLabel($level) {
    $map = ['low' => ' Low', 'medium' => ' Medium', 'high' => 'High', 'urgent' => 'Urgent'];
    return $map[$level] ?? ucfirst($level);
}

$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
$book_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';

// --- Standalone HTML wrapper (only when NOT in layout) ---
if (!$in_layout) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#163269', 'primary-dark': '#3a56c4' } } } }</script>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Topbar -->
    <div class="bg-primary text-white py-3 px-4 flex items-center justify-between shadow-lg">
        <a href="../dashboard/index.php" class="flex items-center gap-2 text-white hover:text-white/80"><i class="fas fa-graduation-cap"></i><span class="font-bold text-sm">SRCB Guidance</span></a>
        <div class="flex items-center gap-3">
            <span class="text-sm text-white/70">Welcome, <?= htmlspecialchars($user_info['first_name']) ?></span>
            <a href="../auth/logout.php" class="text-white/70 hover:text-white text-sm"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
<?php } ?>

<div class="max-w-6xl mx-auto p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-calendar-check mr-2 text-primary"></i>My Counseling Appointments</h1>
            <p class="text-sm text-gray-500 mt-1">View and track your counseling sessions</p>
        </div>
        <a href="<?= $book_url ?>" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30">
            <i class="fas fa-plus"></i>Book New Appointment
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php
        $total = count($appointments);
        $pending = count(array_filter($appointments, fn($a) => $a['status'] === 'pending'));
        $upcoming = count(array_filter($appointments, fn($a) => in_array($a['status'], ['pending', 'confirmed', 'in_progress'])));
        $completed = count(array_filter($appointments, fn($a) => $a['status'] === 'completed'));
        ?>
        <div class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <i class="fas fa-calendar-alt text-lg"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?= $total ?></div>
                    <div class="text-xs text-gray-500">Total</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?= $pending ?></div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                    <i class="fas fa-calendar-check text-lg"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?= $upcoming ?></div>
                    <div class="text-xs text-gray-500">Upcoming</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?= $completed ?></div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-2xl px-5 py-4 mb-6 text-sm flex items-center gap-3">
        <i class="fas fa-check-circle text-lg"></i><?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 mb-6 text-sm flex items-center gap-3">
        <i class="fas fa-exclamation-triangle text-lg"></i><?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <?php if (!empty($appointments)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-2 mb-6 border border-gray-100">
        <div class="flex gap-2 overflow-x-auto">
            <button onclick="filterAppointments('all')" class="filter-btn active px-4 py-2 rounded-xl text-sm font-medium transition-all" data-filter="all">
                <i class="fas fa-list mr-2"></i>All
            </button>
            <button onclick="filterAppointments('upcoming')" class="filter-btn px-4 py-2 rounded-xl text-sm font-medium transition-all" data-filter="upcoming">
                <i class="fas fa-calendar-check mr-2"></i>Upcoming
            </button>
            <button onclick="filterAppointments('completed')" class="filter-btn px-4 py-2 rounded-xl text-sm font-medium transition-all" data-filter="completed">
                <i class="fas fa-check-circle mr-2"></i>Completed
            </button>
            <button onclick="filterAppointments('cancelled')" class="filter-btn px-4 py-2 rounded-xl text-sm font-medium transition-all" data-filter="cancelled">
                <i class="fas fa-times-circle mr-2"></i>Cancelled
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
    <div class="bg-white rounded-3xl shadow-sm p-12 text-center border border-gray-100">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center mx-auto mb-6 text-gray-300 text-3xl">
            <i class="fas fa-calendar-times"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Appointments Yet</h3>
        <p class="text-gray-400 mb-6 max-w-md mx-auto">You haven't booked any counseling appointments. Start your journey by booking your first session.</p>
        <?php if($settings->isCounselingEnabled()): ?>
        <a href="<?= $book_url ?>" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30">
            <i class="fas fa-calendar-plus"></i>Book Your First Appointment
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid gap-4" id="appointmentsList">
        <?php foreach ($appointments as $appt): ?>
        <?php
        // Status border color
        $border_color = 'border-gray-300';
        $icon_bg = 'bg-gray-50 text-gray-400';
        $status_icon = 'fa-clock';
        if ($appt['status'] === 'pending') { $border_color = 'border-amber-400'; $icon_bg = 'bg-amber-50 text-amber-500'; }
        elseif ($appt['status'] === 'confirmed') { $border_color = 'border-blue-400'; $icon_bg = 'bg-blue-50 text-blue-500'; $status_icon = 'fa-check-circle'; }
        elseif ($appt['status'] === 'in_progress') { $border_color = 'border-indigo-400'; $icon_bg = 'bg-indigo-50 text-indigo-500'; $status_icon = 'fa-spinner'; }
        elseif ($appt['status'] === 'completed') { $border_color = 'border-green-400'; $icon_bg = 'bg-green-50 text-green-500'; $status_icon = 'fa-check-circle'; }
        elseif ($appt['status'] === 'cancelled') { $border_color = 'border-red-400'; $icon_bg = 'bg-red-50 text-red-500'; $status_icon = 'fa-times-circle'; }
        elseif ($appt['status'] === 'missed') { $border_color = 'border-red-400'; $icon_bg = 'bg-red-50 text-red-500'; $status_icon = 'fa-exclamation-triangle'; }
        elseif ($appt['status'] === 'rescheduled') { $border_color = 'border-purple-400'; $icon_bg = 'bg-purple-50 text-purple-500'; $status_icon = 'fa-calendar-alt'; }
        ?>
        <div class="appointment-card bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all border border-gray-100 overflow-hidden" data-status="<?= $appt['status'] ?>" data-date="<?= $appt['appointment_date'] ?>" data-time="<?= $appt['appointment_time'] ?>">
            <!-- Card Header -->
            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Calendar Date Card -->
                        <div class="bg-primary rounded-xl shadow-md p-2 text-center min-w-[60px]">
                            <div class="text-xs font-semibold text-white/80 uppercase tracking-wide"><?= date('M', strtotime($appt['appointment_date'])) ?></div>
                            <div class="text-2xl font-bold text-white leading-none"><?= date('j', strtotime($appt['appointment_date'])) ?></div>
                            <div class="text-xs text-white/70"><?= date('Y', strtotime($appt['appointment_date'])) ?></div>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 text-lg"><?= date('l', strtotime($appt['appointment_date'])) ?></p>
                            <p class="text-sm text-gray-500"><i class="far fa-clock mr-1"></i><?= date('g:i A', strtotime($appt['appointment_time'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (!empty($appt['is_follow_up']) && $appt['is_follow_up'] == 1): ?>
                        <span class="px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-xl"><i class="fas fa-redo mr-1"></i>Follow-up</span>
                        <?php endif; ?>
                        <span class="px-4 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wide <?= statusBadge($appt['status']) ?>"><?= str_replace('_', ' ', $appt['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-6">
                <!-- Quick Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-gray-50 rounded-xl p-3">
                        <span class="text-xs text-gray-400 uppercase tracking-wide block mb-1">Concern Type</span>
                        <p class="font-semibold text-gray-700 text-sm"><i class="fas fa-lightbulb mr-2 text-primary"></i><?= concernLabel($appt['concern_type'] ?? '') ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                        <span class="text-xs text-gray-400 uppercase tracking-wide block mb-1">Urgency Level</span>
                        <p class="font-semibold text-gray-700 text-sm"><?= urgencyLabel($appt['urgency_level'] ?? '') ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                        <span class="text-xs text-gray-400 uppercase tracking-wide block mb-1">Counselor</span>
                        <?php if (!empty($appt['assigned_advocate_name'])): ?>
                        <p class="font-semibold text-green-600 text-sm"><i class="fas fa-user-tie mr-2"></i><?= htmlspecialchars($appt['assigned_advocate_name']) ?></p>
                        <?php elseif ($appt['status'] === 'confirmed'): ?>
                        <p class="font-semibold text-amber-500 text-sm"><i class="fas fa-hourglass-half mr-2"></i>Awaiting Assignment</p>
                        <?php else: ?>
                        <p class="font-semibold text-gray-400 text-sm"><i class="fas fa-minus mr-2"></i>Not Assigned</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($appt['concern_description']) && trim($appt['concern_description']) !== 'individual'): ?>
                <div class="bg-gradient-to-r from-primary/5 to-blue-50 rounded-xl p-4 mb-4 border-l-4 border-primary">
                    <span class="text-xs text-gray-500 uppercase tracking-wide block mb-2 font-semibold">Concern Description</span>
                    <p class="text-gray-700 text-sm leading-relaxed"><?= nl2br(htmlspecialchars(trim($appt['concern_description']))) ?></p>
                </div>
                <?php endif; ?>

                <!-- Reschedule Info -->
                <?php if (!empty($appt['original_appointment_id']) && !empty($appt['original_date'])): ?>
                <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-xl p-4 mb-4 border-l-4 border-red-400">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-calendar-alt text-red-500"></i>
                        <strong class="text-red-600 text-sm font-semibold">Rescheduled From:</strong>
                    </div>
                    <p class="text-sm text-gray-700 ml-6 font-medium"><?= date('F j, Y', strtotime($appt['original_date'])) ?> at <?= date('g:i A', strtotime($appt['original_time'])) ?></p>
                    <p class="text-xs text-gray-500 ml-6 mt-1"><i class="fas fa-user-tie mr-1"></i>By: Guidance Office</p>
                </div>
                <?php endif; ?>

                <!-- Status-specific messages -->
                <?php if ($appt['status'] === 'confirmed'): ?>
                <div class="bg-blue-50 rounded-xl p-4 border-l-4 border-blue-400 text-sm text-blue-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle text-lg"></i>
                        <span class="font-medium">Your appointment has been confirmed. Please arrive 10 minutes before your scheduled time.</span>
                    </div>
                </div>
                <?php elseif ($appt['status'] === 'pending'): ?>
                <div class="bg-amber-50 rounded-xl p-4 border-l-4 border-amber-400 text-sm text-amber-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-lg"></i>
                        <span class="font-medium">Your appointment is pending confirmation. You will be notified once it's confirmed.</span>
                    </div>
                </div>
                <?php elseif ($appt['status'] === 'completed'): ?>
                <div class="bg-green-50 rounded-xl p-4 border-l-4 border-green-400 text-sm text-green-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="font-medium">Your counseling session has been completed.</span>
                    </div>
                </div>
                <?php elseif ($appt['status'] === 'missed'): ?>
                <div class="bg-red-50 rounded-xl p-4 border-l-4 border-red-500 text-sm text-red-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                        <span class="font-bold">This appointment was marked as missed.</span>
                    </div>
                </div>
                <?php elseif ($appt['status'] === 'cancelled'): ?>
                <div class="bg-red-50 rounded-xl p-4 border-l-4 border-red-400 text-sm text-red-600">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-times-circle text-lg"></i>
                        <span class="font-medium">This appointment has been cancelled.</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer Info -->
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                    <span><i class="fas fa-calendar-plus mr-1"></i>Submitted: <?= date('M j, Y g:i A', strtotime($appt['created_at'])) ?></span>
                    <?php if (!empty($appt['confirmed_at'])): ?>
                    <span class="text-green-600"><i class="fas fa-check mr-1"></i>Confirmed: <?= date('M j, Y g:i A', strtotime($appt['confirmed_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$in_layout): ?>
    <div class="mt-8 text-center">
        <a href="<?= $dashboard_url ?>" class="inline-flex items-center gap-2 text-primary text-sm font-semibold hover:underline">
            <i class="fas fa-arrow-left"></i>Back to Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for filtering -->
<script>
function confirmCancel(appointmentId) {
    return Swal.fire({
        title: 'Cancel Appointment?',
        text: 'Are you sure you want to cancel this appointment? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Cancel It',
        cancelButtonText: 'No, Keep It'
    }).then((result) => {
        return result.isConfirmed;
    });
}

function filterAppointments(filter) {
    const cards = document.querySelectorAll('.appointment-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update button styles
    buttons.forEach(btn => {
        btn.classList.remove('active', 'bg-primary', 'text-white');
        btn.classList.add('text-gray-600', 'hover:bg-gray-100');
    });
    const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
    activeBtn.classList.add('active', 'bg-primary', 'text-white');
    activeBtn.classList.remove('text-gray-600', 'hover:bg-gray-100');
    
    // Filter cards
    cards.forEach(card => {
        const status = card.dataset.status;
        const dateStr = card.dataset.date;
        const timeStr = card.dataset.time;
        let show = false;
        
        if (filter === 'all') {
            show = true;
        } else if (filter === 'upcoming') {
            // Check if status is upcoming AND date is today or in the future
            const isUpcomingStatus = ['pending', 'confirmed', 'in_progress'].includes(status);
            const appointmentDateTime = new Date(dateStr + 'T' + timeStr);
            const now = new Date();
            const isFutureOrToday = appointmentDateTime >= new Date(now.getFullYear(), now.getMonth(), now.getDate());
            show = isUpcomingStatus && isFutureOrToday;
        } else if (filter === 'completed') {
            show = status === 'completed';
        } else if (filter === 'cancelled') {
            show = ['cancelled', 'missed'].includes(status);
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

// Initialize filter buttons styling
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        if (!btn.classList.contains('active')) {
            btn.classList.add('text-gray-600', 'hover:bg-gray-100');
        } else {
            btn.classList.add('bg-primary', 'text-white');
        }
    });
});
</script>

<style>
.filter-btn {
    transition: all 0.2s ease;
}
.filter-btn:hover {
    transform: translateY(-1px);
}
.appointment-card {
    transition: all 0.3s ease;
}
.appointment-card:hover {
    transform: translateY(-2px);
}
</style>

<?php if (!$in_layout): ?>
</body>
</html>
<?php endif; ?>
