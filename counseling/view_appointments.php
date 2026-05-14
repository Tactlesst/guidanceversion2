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
    $map = ['low' => '🟢 Low', 'medium' => '🟡 Medium', 'high' => '🟠 High', 'urgent' => '🔴 Urgent'];
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

<!-- === PAGE CONTENT (always rendered) === -->
<div class="max-w-3xl mx-auto p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-primary"><i class="fas fa-calendar-alt mr-2"></i>My Counseling Appointments</h1>
            <p class="text-sm text-gray-400">View and track your counseling sessions</p>
        </div>
        <a href="<?= $book_url ?>" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-plus mr-1"></i>Book New</a>
    </div>

    <?php if ($success_message): ?>
    <div class="bg-green-50 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm flex items-center gap-2"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4 text-gray-300 text-2xl"><i class="fas fa-calendar-times"></i></div>
        <h3 class="font-semibold text-gray-500 mb-1">No Appointments Yet</h3>
        <p class="text-sm text-gray-400 mb-4">You haven't booked any counseling appointments.</p>
        <?php if($settings->isCounselingEnabled()): ?>
        <a href="<?= $book_url ?>" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-calendar-plus"></i>Book Your First Appointment</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="space-y-4">
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
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow border-l-4 <?= $border_color ?> overflow-hidden">
            <!-- Appointment Header -->
            <div class="p-5 pb-3">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm <?= $icon_bg ?>">
                            <i class="fas <?= $status_icon ?>"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm"><?= date('F j, Y', strtotime($appt['appointment_date'])) ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?></p>
                            <p class="text-xs text-gray-400">Submitted <?= date('M j, Y g:i A', strtotime($appt['created_at'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (!empty($appt['is_follow_up']) && $appt['is_follow_up'] == 1): ?>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-calendar-plus mr-1"></i>Follow-up</span>
                        <?php endif; ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= statusBadge($appt['status']) ?>"><?= ucfirst(str_replace('_', ' ', $appt['status'])) ?></span>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-3">
                    <div><span class="text-gray-400 text-xs">Concern</span><p class="font-medium"><?= concernLabel($appt['concern_type'] ?? '') ?></p></div>
                    <div><span class="text-gray-400 text-xs">Urgency</span><p class="font-medium"><?= urgencyLabel($appt['urgency_level'] ?? '') ?></p></div>
                    <?php if (!empty($appt['assigned_advocate_name'])): ?>
                    <div><span class="text-gray-400 text-xs">Counselor</span><p class="font-medium text-green-600"><i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($appt['assigned_advocate_name']) ?></p></div>
                    <?php elseif ($appt['status'] === 'confirmed'): ?>
                    <div><span class="text-gray-400 text-xs">Counselor</span><p class="font-medium text-amber-500"><i class="fas fa-clock mr-1"></i>Awaiting Assignment</p></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($appt['concern_description']) && trim($appt['concern_description']) !== 'individual'): ?>
                <div class="bg-gray-50 rounded-lg p-3 text-sm border-l-4 border-primary">
                    <span class="text-gray-400 text-xs block mb-1">Concern Description</span>
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars(trim($appt['concern_description']))) ?></p>
                </div>
                <?php endif; ?>

                <!-- Reschedule Info -->
                <?php if (!empty($appt['original_appointment_id']) && !empty($appt['original_date'])): ?>
                <div class="mt-3 p-3 rounded-lg bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-400">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-calendar-alt text-red-500"></i>
                        <strong class="text-red-600 text-sm">Rescheduled From:</strong>
                    </div>
                    <p class="text-sm ml-6"><?= date('M j, Y', strtotime($appt['original_date'])) ?> at <?= date('g:i A', strtotime($appt['original_time'])) ?></p>
                    <p class="text-xs text-gray-500 ml-6 mt-1"><i class="fas fa-user-tie mr-1"></i>By: Guidance Office</p>
                </div>
                <?php endif; ?>

                <!-- Status-specific messages -->
                <?php if ($appt['status'] === 'confirmed'): ?>
                <div class="mt-3 p-3 rounded-lg bg-blue-50 border-l-4 border-blue-400 text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Your appointment has been confirmed. Please arrive 10 minutes before your scheduled time.
                </div>
                <?php elseif ($appt['status'] === 'pending'): ?>
                <div class="mt-3 p-3 rounded-lg bg-amber-50 border-l-4 border-amber-400 text-sm text-amber-700">
                    <i class="fas fa-clock mr-1"></i>
                    Your appointment is pending confirmation. You will be notified once it's confirmed.
                </div>
                <?php elseif ($appt['status'] === 'completed'): ?>
                <div class="mt-3 p-3 rounded-lg bg-green-50 border-l-4 border-green-400 text-sm text-green-700">
                    <i class="fas fa-check-circle mr-1"></i>
                    Your counseling session has been completed.
                </div>
                <?php elseif ($appt['status'] === 'missed'): ?>
                <div class="mt-3 p-3 rounded-lg bg-red-50 border-l-4 border-red-500 text-sm text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>This appointment was marked as missed.</strong>
                </div>
                <?php elseif ($appt['status'] === 'cancelled'): ?>
                <div class="mt-3 p-3 rounded-lg bg-red-50 border-l-4 border-red-400 text-sm text-red-600">
                    <i class="fas fa-times-circle mr-1"></i>
                    This appointment has been cancelled.
                </div>
                <?php endif; ?>

                <!-- Confirmed At -->
                <?php if (!empty($appt['confirmed_at'])): ?>
                <div class="mt-2 text-xs text-gray-400">
                    <i class="fas fa-check mr-1"></i>Confirmed: <?= date('M j, Y g:i A', strtotime($appt['confirmed_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$in_layout): ?>
    <div class="mt-5 text-center">
        <a href="<?= $dashboard_url ?>" class="text-primary text-sm font-semibold hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$in_layout): ?>
</body>
</html>
<?php endif; ?>
