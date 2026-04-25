<?php
$in_layout = defined('IN_LAYOUT');
if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    checkLogin();
    checkRole(['student']);
}
$user_info = getUserInfo();

try {
    $db = (new Database())->getConnection();
} catch (Exception $e) { die("Database connection failed."); }

// Get user's appointments
$appointments = [];
try {
    $stmt = $db->prepare("SELECT * FROM counseling_appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC");
    $stmt->execute([$user_info['id']]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
        'no_show' => 'bg-gray-100 text-gray-600',
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

    <div class="max-w-3xl mx-auto p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-xl font-bold text-primary"><i class="fas fa-calendar-alt mr-2"></i>My Counseling Appointments</h1>
                <p class="text-sm text-gray-400">View and track your counseling sessions</p>
            </div>
            <a href="book_appointment.php" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-plus mr-1"></i>Book New</a>
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
            <a href="book_appointment.php" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-calendar-plus"></i>Book Your First Appointment</a>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($appointments as $appt): ?>
            <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4 <?= $appt['status'] === 'pending' ? 'border-amber-400' : ($appt['status'] === 'confirmed' ? 'border-blue-400' : ($appt['status'] === 'completed' ? 'border-green-400' : 'border-gray-300')) ?>">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm <?= $appt['status'] === 'pending' ? 'bg-amber-50 text-amber-500' : ($appt['status'] === 'confirmed' ? 'bg-blue-50 text-blue-500' : ($appt['status'] === 'completed' ? 'bg-green-50 text-green-500' : 'bg-gray-50 text-gray-400')) ?>">
                            <i class="fas <?= $appt['status'] === 'completed' ? 'fa-check-circle' : ($appt['status'] === 'cancelled' ? 'fa-times-circle' : 'fa-clock') ?>"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm"><?= date('F j, Y', strtotime($appt['appointment_date'])) ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?></p>
                            <p class="text-xs text-gray-400">Submitted <?= date('M j, Y g:i A', strtotime($appt['created_at'])) ?></p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= statusBadge($appt['status']) ?>"><?= ucfirst($appt['status']) ?></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                    <div><span class="text-gray-400 text-xs">Concern</span><p class="font-medium"><?= concernLabel($appt['concern_type'] ?? '') ?></p></div>
                    <div><span class="text-gray-400 text-xs">Urgency</span><p class="font-medium"><?= urgencyLabel($appt['urgency_level'] ?? '') ?></p></div>
                    <?php if (!empty($appt['concern_description'])): ?>
                    <div class="col-span-2 md:col-span-1"><span class="text-gray-400 text-xs">Description</span><p class="font-medium text-xs truncate" title="<?= htmlspecialchars($appt['concern_description']) ?>"><?= htmlspecialchars(substr($appt['concern_description'], 0, 60)) ?><?= strlen($appt['concern_description']) > 60 ? '...' : '' ?></p></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-5 text-center">
            <a href="<?= $dashboard_url ?>" class="text-primary text-sm font-semibold hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
        </div>
    </div>
<?php } ?>
<?php if (!$in_layout): ?>
</body>
</html>
<?php endif; ?>
