<?php
/**
 * Send Notifications
 * 
 * Admin interface for sending notifications to students
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

require_once '../config/database.php';
require_once '../classes/Notification.php';
require_once '../includes/session.php';

checkLogin();
checkRole(['admin', 'guidance_advocate', 'super_admin']);

$database = new Database();
$db = $database->getConnection();
$notification = new Notification($db);

$user_info = getUserInfo();
$success_message = '';
$error_message = '';

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_type = $_POST['recipient_type'] ?? null;
    $recipient_ids = $_POST['recipient_ids'] ?? [];
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'info';
    
    if ($title && $message) {
        $sent_count = 0;
        $recipients = [];
        
        // Determine recipients based on type
        switch ($recipient_type) {
            case 'all_students':
                $query = "SELECT id FROM users WHERE role = 'student' AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'pending_appointments':
                $query = "SELECT DISTINCT user_id FROM counseling_appointments WHERE status = 'pending'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'confirmed_appointments':
                $query = "SELECT DISTINCT user_id FROM counseling_appointments WHERE status = 'confirmed'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'specific':
                $recipients = $recipient_ids;
                break;
        }
        
        // Send notifications
        foreach ($recipients as $user_id) {
            if ($notification->createNotification($user_id, $title, $message, $type)) {
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            $success_message = "Notification sent to {$sent_count} recipient(s) successfully!";
        } else {
            $error_message = "Failed to send notifications.";
        }
    } else {
        $error_message = "Please provide both title and message.";
    }
}

// Get students for selection
$students_query = "SELECT id, first_name, last_name, email FROM users WHERE role = 'student' AND is_active = 1 ORDER BY first_name, last_name";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts
$pending_count_query = "SELECT COUNT(DISTINCT user_id) as count FROM counseling_appointments WHERE status = 'pending'";
$pending_count_stmt = $db->prepare($pending_count_query);
$pending_count_stmt->execute();
$pending_count = $pending_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$confirmed_count_query = "SELECT COUNT(DISTINCT user_id) as count FROM counseling_appointments WHERE status = 'confirmed'";
$confirmed_count_stmt = $db->prepare($confirmed_count_query);
$confirmed_count_stmt->execute();
$confirmed_count = $confirmed_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - SRCB Guidance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/index.php">
                <i class="fas fa-graduation-cap me-2"></i>SRCB Guidance
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="text-primary mb-0">
                            <i class="fas fa-bell me-2"></i>Send Notifications
                        </h2>
                        <p class="text-muted mb-0">Send notifications to students</p>
                    </div>
                    <div>
                        <a href="../dashboard/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="recipient_type" class="form-label">Recipients</label>
                                        <select class="form-select" name="recipient_type" id="recipient_type" required onchange="toggleRecipientOptions()">
                                            <option value="">Select recipient type</option>
                                            <option value="all_students">All Students (<?php echo count($students); ?>)</option>
                                            <option value="pending_appointments">Students with Pending Appointments (<?php echo $pending_count; ?>)</option>
                                            <option value="confirmed_appointments">Students with Confirmed Appointments (<?php echo $confirmed_count; ?>)</option>
                                            <option value="specific">Specific Students</option>
                                        </select>
                                    </div>

                                    <div class="mb-3" id="specific_recipients" style="display: none;">
                                        <label class="form-label">Select Students</label>
                                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem;">
                                            <?php foreach($students as $student): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="recipient_ids[]" value="<?php echo $student['id']; ?>" id="student_<?php echo $student['id']; ?>">
                                                <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($student['email']); ?>)</small>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="type" class="form-label">Notification Type</label>
                                        <select class="form-select" name="type" id="type" required>
                                            <option value="info">Information</option>
                                            <option value="success">Success</option>
                                            <option value="warning">Warning</option>
                                            <option value="error">Error</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title</label>
                                        <input type="text" class="form-control" name="title" id="title" required maxlength="100">
                                    </div>

                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" name="message" id="message" rows="8" required></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Send Notification
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRecipientOptions() {
            const recipientType = document.getElementById('recipient_type').value;
            const specificDiv = document.getElementById('specific_recipients');
            specificDiv.style.display = (recipientType === 'specific') ? 'block' : 'none';
        }
    </script>
</body>
</html>
