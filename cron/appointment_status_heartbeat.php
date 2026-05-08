<?php
/**
 * Appointment Status Heartbeat
 * 
 * This script runs as a cron job to automatically update appointment statuses:
 * - Marks "confirmed" appointments as "missed" if the appointment date has passed
 * - Marks "confirmed" appointments as "completed" if they're ongoing (today or past)
 * - Creates notifications for missed appointments
 * 
 * Usage: Add to Windows Task Scheduler to run daily (e.g., at 12:00 AM)
 * Command: php "C:\xampp\htdocs\guidanceversion2\cron\appointment_status_heartbeat.php"
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

// Prevent direct web access - only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

$project_root = dirname(__DIR__);

require_once $project_root . '/config/database.php';
require_once $project_root . '/classes/AppointmentHeartbeat.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Log the heartbeat execution
    error_log("[" . date('Y-m-d H:i:s') . "] Appointment Status Heartbeat Started");

    // Run the heartbeat
    $heartbeat = new AppointmentHeartbeat($db);
    $results = $heartbeat->run();

    // Calculate total updates
    $total_updated = 0;
    if (is_array($results)) {
        $total_updated = (int)($results['counseling_missed'] ?? 0)
            + (int)($results['counseling_completed'] ?? 0)
            + (int)($results['exam_missed'] ?? 0)
            + (int)($results['exam_completed'] ?? 0);
    }

    // Log results
    error_log("[" . date('Y-m-d H:i:s') . "] Appointment Status Heartbeat Completed - Total Updated: {$total_updated}");
    
    // Log details
    if ($total_updated > 0) {
        error_log("  - Counseling Missed: " . ($results['counseling_missed'] ?? 0));
        error_log("  - Counseling Completed: " . ($results['counseling_completed'] ?? 0));
        error_log("  - Exam Missed: " . ($results['exam_missed'] ?? 0));
        error_log("  - Exam Completed: " . ($results['exam_completed'] ?? 0));
    }
    
    // Log errors if any
    if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
            error_log("  - ERROR: " . $error);
        }
    }

    exit(0);
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR in Appointment Status Heartbeat: " . $e->getMessage());
    exit(1);
}
