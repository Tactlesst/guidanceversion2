<?php
/**
 * Admin: Manage Appointments
 * 
 * Comprehensive appointment management interface for admins
 * Supports viewing, confirming, cancelling, completing, and rescheduling
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

require_once '../config/database.php';
require_once '../classes/CounselingAppointment.php';
require_once '../classes/Notification.php';
require_once '../includes/session.php';

checkLogin();
checkRole(['admin', 'guidance_advocate', 'super_admin']);

$database = new Database();
$db = $database->getConnection();
$counseling = new CounselingAppointment($db);
$notification = new Notification($db);

$user_info = getUserInfo();
$success_message = '';
$error_message = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($appointment_id && $action) {
        try {
            switch ($action) {
                case 'confirm':
                    if ($counseling->confirmAppointment($appointment_id, $user_info['id'])) {
                        $success_message = "Appointment confirmed successfully!";
                    } else {
                        $error_message = "Failed to confirm appointment.";
                    }
                    break;
                    
                case 'cancel':
                    if ($counseling->cancelAppointment($appointment_id)) {
                        $success_message = "Appointment cancelled successfully!";
                    } else {
                        $error_message = "Failed to cancel appointment.";
                    }
                    break;
                    
                case 'complete':
                    if ($counseling->completeAppointment($appointment_id)) {
                        $success_message = "Appointment marked as completed!";
                    } else {
                        $error_message = "Failed to complete appointment.";
                    }
                    break;
                    
                case 'reschedule':
                    $new_date = $_POST['new_date'] ?? null;
                    $new_time = $_POST['new_time'] ?? null;
                    
                    if ($new_date && $new_time) {
                        if ($counseling->rescheduleAppointment($appointment_id, $new_date, $new_time)) {
                            $success_message = "Appointment rescheduled successfully!";
                        } else {
                            $error_message = "Failed to reschedule appointment.";
                        }
                    } else {
                        $error_message = "Please provide new date and time.";
                    }
                    break;
                    
                default:
                    $error_message = "Invalid action specified.";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("Appointment action error: " . $e->getMessage());
        }
    }
}

// Get appointments by status
$pending_appointments = $counseling->getPendingAppointments();
$confirmed_appointments = $counseling->getConfirmedAppointments();
$all_appointments = $counseling->getAllAppointments();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - SRCB Guidance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #4ade80;
            --warning-color: #facc15;
            --danger-color: #f87171;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --light-text: #64748b;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            min-height: 100vh;
        }
        
        .appointment-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            margin-bottom: 20px;
        }
        
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .status-pending { border-left: 4px solid var(--warning-color); }
        .status-confirmed { border-left: 4px solid var(--success-color); }
        .status-completed { border-left: 4px solid var(--primary-color); }
        .status-cancelled { border-left: 4px solid var(--danger-color); }
        .status-missed { border-left: 4px solid #6b7280; }
        
        .urgency-urgent { border-top: 3px solid var(--danger-color); }
        .urgency-high { border-top: 3px solid var(--warning-color); }
        .urgency-medium { border-top: 3px solid var(--accent-color); }
        .urgency-low { border-top: 3px solid var(--success-color); }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .appointment-actions {
            gap: 0.5rem;
        }
        
        .appointment-details {
            font-size: 0.9rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
        }
    </style>
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
                            <i class="fas fa-calendar-check me-2"></i>Manage Appointments
                        </h2>
                        <p class="text-muted mb-0">View and manage counseling appointments</p>
                    </div>
                    <div>
                        <a href="../dashboard/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-pills mb-4" id="appointmentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button" role="tab">
                            <i class="fas fa-clock me-1"></i>Pending (<?php echo $pending_appointments->rowCount(); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="confirmed-tab" data-bs-toggle="pill" data-bs-target="#confirmed" type="button" role="tab">
                            <i class="fas fa-check me-1"></i>Confirmed (<?php echo $confirmed_appointments->rowCount(); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button" role="tab">
                            <i class="fas fa-list me-1"></i>All Appointments
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="appointmentTabsContent">
                    <!-- Pending Appointments -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <?php if ($pending_appointments->rowCount() > 0): ?>
                            <?php while ($app = $pending_appointments->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php include 'appointment_card.php'; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="appointment-card p-5 text-center">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No pending appointments</h4>
                                <p class="text-muted">All appointments have been reviewed.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Confirmed Appointments -->
                    <div class="tab-pane fade" id="confirmed" role="tabpanel">
                        <?php if ($confirmed_appointments->rowCount() > 0): ?>
                            <?php while ($app = $confirmed_appointments->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php include 'appointment_card.php'; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="appointment-card p-5 text-center">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No confirmed appointments</h4>
                                <p class="text-muted">No appointments are currently confirmed.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- All Appointments -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        <?php if ($all_appointments->rowCount() > 0): ?>
                            <?php while ($app = $all_appointments->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php include 'appointment_card.php'; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="appointment-card p-5 text-center">
                                <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No appointments found</h4>
                                <p class="text-muted">No appointments have been scheduled yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                        <input type="hidden" name="action" value="reschedule">
                        
                        <div class="mb-3">
                            <label for="new_date" class="form-label">New Date</label>
                            <input type="date" class="form-control" name="new_date" id="new_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_time" class="form-label">New Time</label>
                            <input type="time" class="form-control" name="new_time" id="new_time" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function rescheduleAppointment(appointmentId) {
            document.getElementById('reschedule_appointment_id').value = appointmentId;
            new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
        }
        
        function confirmAction(action, appointmentId, studentName) {
            const actions = {
                'confirm': 'confirm this appointment',
                'cancel': 'cancel this appointment', 
                'complete': 'mark this appointment as completed'
            };
            
            if (confirm(`Are you sure you want to ${actions[action]} for ${studentName}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                    <input type="hidden" name="action" value="${action}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
