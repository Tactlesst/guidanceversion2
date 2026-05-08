<?php
/**
 * Admin: Mark Missed Appointments
 * 
 * Manual interface for administrators to mark missed appointments
 * Provides statistics and frequent misser tracking
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

require_once '../config/database.php';
require_once '../classes/AppointmentHeartbeat.php';
require_once '../includes/session.php';

// Check authentication and authorization
checkLogin();
checkRole(['admin', 'guidance_counselor', 'super_admin']);

$database = new Database();
$db = $database->getConnection();
$heartbeat = new AppointmentHeartbeat($db);

$result = null;
$stats = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_missed'])) {
    try {
        $result = $heartbeat->markMissedAppointments();
        
        if ($result['success']) {
            // Log the manual trigger
            error_log(sprintf(
                "Manual missed appointment marking by user %s: %d appointments marked",
                $_SESSION['user_id'] ?? 'unknown',
                $result['affected_count']
            ));
        }
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'affected_count' => 0
        ];
        error_log("Error in manual missed appointment marking: " . $e->getMessage());
    }
}

// Get current missed appointment statistics
try {
    // Get missed appointments from last 30 days
    $stats_query = "SELECT 
                        DATE(appointment_date) as missed_date,
                        COUNT(*) as daily_count,
                        COUNT(DISTINCT user_id) as unique_students
                    FROM counseling_appointments
                    WHERE status = 'missed'
                    AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(appointment_date)
                    ORDER BY appointment_date DESC
                    LIMIT 10";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $current_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get frequent missers (students with 2+ missed appointments)
    $missers_query = "SELECT 
                        u.first_name, u.last_name,
                        sp.student_id, sp.grade_level,
                        COUNT(*) as missed_count,
                        MAX(ca.appointment_date) as last_missed_date
                      FROM counseling_appointments ca
                      JOIN users u ON ca.user_id = u.id
                      LEFT JOIN student_profiles sp ON u.id = sp.user_id
                      WHERE ca.status = 'missed'
                      AND ca.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      GROUP BY ca.user_id
                      HAVING missed_count >= 2
                      ORDER BY missed_count DESC, last_missed_date DESC
                      LIMIT 20";
    
    $missers_stmt = $db->prepare($missers_query);
    $missers_stmt->execute();
    $frequent_missers = $missers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching missed appointment stats: " . $e->getMessage());
    $current_stats = [];
    $frequent_missers = [];
}

$user_info = getUserInfo();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Missed Appointments - SRCB Guidance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .btn-warning {
            background-color: #facc15;
            border-color: #facc15;
            color: #1e293b;
        }
        
        .btn-warning:hover {
            background-color: #eab308;
            border-color: #eab308;
            color: #1e293b;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
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

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-times-circle text-danger me-2"></i>Mark Missed Appointments</h2>
                        <p class="text-muted mb-0">Manually trigger missed appointment marking</p>
                    </div>
                    <a href="manage_appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Management
                    </a>
                </div>

                <?php if ($result): ?>
                    <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $result['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($result['message']); ?>
                        <?php if ($result['success'] && $result['affected_count'] > 0): ?>
                            <br><strong><?php echo $result['affected_count']; ?></strong> appointment(s) marked as missed.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Manual Trigger Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-play me-2"></i>Manual Trigger</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This system automatically marks missed appointments via the heartbeat cron job. 
                            Use this manual trigger only if you need to force an immediate check.
                        </div>
                        <p class="text-muted mb-3">
                            Click the button below to manually check and mark appointments as missed. 
                            This will mark any confirmed appointments where the scheduled date and time have passed as "missed".
                        </p>
                        <form method="POST">
                            <button type="submit" name="mark_missed" class="btn btn-warning" onclick="return confirm('Are you sure you want to mark missed appointments now?');">
                                <i class="fas fa-clock me-2"></i>Mark Missed Appointments Now
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Missed Appointments Statistics (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($current_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Missed Count</th>
                                            <th>Unique Students</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($current_stats as $stat): ?>
                                            <tr>
                                                <td><?php echo date('F j, Y', strtotime($stat['missed_date'])); ?></td>
                                                <td><span class="badge bg-danger"><?php echo $stat['daily_count']; ?></span></td>
                                                <td><?php echo $stat['unique_students']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem; opacity: 0.3;"></i>
                                <h5 class="text-muted mt-3">No Missed Appointments</h5>
                                <p class="text-muted">All confirmed appointments have been attended in the last 30 days.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Frequent Missers -->
                <?php if (count($frequent_missers) > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Students with Multiple Missed Appointments (Last 90 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                These students may need follow-up or intervention.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Student ID</th>
                                            <th>Grade Level</th>
                                            <th>Missed Count</th>
                                            <th>Last Missed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($frequent_missers as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                <td><?php echo htmlspecialchars($student['grade_level']); ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo $student['missed_count']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($student['last_missed_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
