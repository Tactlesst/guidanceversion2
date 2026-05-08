<?php
/**
 * Appointment Actions AJAX Handler
 * 
 * Handles AJAX requests for appointment management
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../classes/CounselingAppointment.php';
require_once '../classes/Notification.php';
require_once '../includes/session.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check role
$allowed_roles = ['admin', 'guidance_advocate', 'super_admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$counseling = new CounselingAppointment($db);
$notification = new Notification($db);

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$appointment_id = $_POST['appointment_id'] ?? $_GET['appointment_id'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action not specified']);
    exit();
}

try {
    switch ($action) {
        case 'confirm':
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $result = $counseling->confirmAppointment($appointment_id, $_SESSION['user_id']);
            
            if ($result) {
                // Get appointment details for notification
                $app = $counseling->getAppointmentById($appointment_id);
                
                if ($app) {
                    // Create notification
                    $notification->createNotification(
                        $app['user_id'],
                        'Appointment Confirmed',
                        'Your counseling appointment has been confirmed for ' . date('F j, Y', strtotime($app['appointment_date'])) . ' at ' . date('g:i A', strtotime($app['appointment_time'])),
                        'success',
                        'counseling_appointments',
                        $appointment_id
                    );
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment confirmed successfully'
                ]);
            } else {
                throw new Exception('Failed to confirm appointment');
            }
            break;
            
        case 'cancel':
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $result = $counseling->cancelAppointment($appointment_id);
            
            if ($result) {
                // Get appointment details for notification
                $app = $counseling->getAppointmentById($appointment_id);
                
                if ($app) {
                    // Create notification
                    $notification->createNotification(
                        $app['user_id'],
                        'Appointment Cancelled',
                        'Your counseling appointment scheduled for ' . date('F j, Y', strtotime($app['appointment_date'])) . ' has been cancelled.',
                        'warning',
                        'counseling_appointments',
                        $appointment_id
                    );
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment cancelled successfully'
                ]);
            } else {
                throw new Exception('Failed to cancel appointment');
            }
            break;
            
        case 'complete':
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $result = $counseling->completeAppointment($appointment_id);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment marked as completed'
                ]);
            } else {
                throw new Exception('Failed to complete appointment');
            }
            break;
            
        case 'reschedule':
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $new_date = $_POST['new_date'] ?? null;
            $new_time = $_POST['new_time'] ?? null;
            
            if (!$new_date || !$new_time) {
                throw new Exception('New date and time required');
            }
            
            $result = $counseling->rescheduleAppointment($appointment_id, $new_date, $new_time);
            
            if ($result) {
                // Get appointment details for notification
                $app = $counseling->getAppointmentById($appointment_id);
                
                if ($app) {
                    // Create notification
                    $notification->createNotification(
                        $app['user_id'],
                        'Appointment Rescheduled',
                        'Your counseling appointment has been rescheduled to ' . date('F j, Y', strtotime($new_date)) . ' at ' . date('g:i A', strtotime($new_time)),
                        'info',
                        'counseling_appointments',
                        $appointment_id
                    );
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment rescheduled successfully'
                ]);
            } else {
                throw new Exception('Failed to reschedule appointment');
            }
            break;
            
        case 'get_details':
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $app = $counseling->getAppointmentById($appointment_id);
            
            if ($app) {
                echo json_encode([
                    'success' => true,
                    'appointment' => $app
                ]);
            } else {
                throw new Exception('Appointment not found');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
