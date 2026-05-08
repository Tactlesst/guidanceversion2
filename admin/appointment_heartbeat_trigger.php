<?php
/**
 * Admin: Appointment Heartbeat Trigger
 * 
 * Web interface for manually triggering the appointment status heartbeat
 * Can be called via AJAX for periodic updates or accessed directly
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

require_once '../config/database.php';
require_once '../classes/AppointmentHeartbeat.php';
require_once '../includes/session.php';

// Check authentication and authorization
try {
    checkLogin();
    checkRole(['admin', 'super_admin']);
} catch (Exception $e) {
    http_response_code(403);
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required'
        ]);
    } else {
        $_SESSION['error_message'] = 'Insufficient permissions';
        header('Location: ../dashboard/index.php');
    }
    exit();
}

// Determine if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

try {
    // Initialize database and heartbeat
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    $heartbeat = new AppointmentHeartbeat($db);
    
    // Execute heartbeat
    $results = $heartbeat->run();
    
    // Calculate total updates
    $total_updated = 0;
    if (is_array($results)) {
        $total_updated = (int)($results['counseling_missed'] ?? 0)
            + (int)($results['counseling_completed'] ?? 0)
            + (int)($results['exam_missed'] ?? 0)
            + (int)($results['exam_completed'] ?? 0);
    }
    
    // Log the manual trigger
    $log_message = sprintf(
        "Manual heartbeat trigger by user %s: %d appointments updated",
        $_SESSION['user_id'] ?? 'unknown',
        $total_updated
    );
    error_log($log_message);
    
    // Return response based on request type
    if (isAjaxRequest()) {
        // JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Heartbeat executed successfully. Total appointments updated: {$total_updated}",
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results,
            'total' => $total_updated,
            'details' => [
                'counseling_missed' => $results['counseling_missed'] ?? 0,
                'counseling_completed' => $results['counseling_completed'] ?? 0,
                'exam_missed' => $results['exam_missed'] ?? 0,
                'exam_completed' => $results['exam_completed'] ?? 0
            ]
        ]);
    } else {
        // Redirect with success message for regular requests
        $_SESSION['success_message'] = "Appointment heartbeat executed successfully! {$total_updated} appointments were updated.";
        header("Location: mark_missed_appointments.php");
        exit();
    }
    
} catch (PDOException $e) {
    // Database error
    $error_message = "Database error: " . $e->getMessage();
    error_log("Heartbeat trigger error: " . $error_message);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        $_SESSION['error_message'] = "Error executing heartbeat: Database error occurred";
        header("Location: mark_missed_appointments.php");
        exit();
    }
    
} catch (Exception $e) {
    // General error
    $error_message = $e->getMessage();
    error_log("Heartbeat trigger error: " . $error_message);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error executing heartbeat: ' . $error_message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        $_SESSION['error_message'] = "Error executing heartbeat: " . $error_message;
        header("Location: mark_missed_appointments.php");
        exit();
    }
}
