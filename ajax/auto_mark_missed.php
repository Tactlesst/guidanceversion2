<?php
/**
 * AJAX Endpoint: Auto Mark Missed Appointments
 * 
 * Automatically marks appointments as missed when admin visits dashboard
 * Uses AppointmentHeartbeat class for consistent logic
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

require_once '../config/database.php';
require_once '../classes/AppointmentHeartbeat.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Check authentication and authorization
try {
    checkLogin();
    checkRole(['admin', 'guidance_counselor', 'super_admin']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access',
        'error' => $e->getMessage()
    ]);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Only POST requests allowed'
    ]);
    exit();
}

try {
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['auto_check']) || $input['auto_check'] !== true) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid request parameters'
        ]);
        exit();
    }
    
    // Initialize database and heartbeat
    $database = new Database();
    $db = $database->getConnection();
    
    $heartbeat = new AppointmentHeartbeat($db);
    
    // Mark missed appointments
    $result = $heartbeat->markMissedAppointments();
    
    // Log the automatic check
    $log_message = sprintf(
        "Auto missed appointment check by user %s: %d appointments marked as missed",
        $_SESSION['user_id'] ?? 'unknown',
        $result['affected_count']
    );
    error_log($log_message);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'affected_count' => $result['affected_count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    // Database error
    error_log("Database error in auto_mark_missed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'affected_count' => 0
    ]);
    
} catch (Exception $e) {
    // General error
    error_log("Error in auto_mark_missed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'affected_count' => 0
    ]);
}
