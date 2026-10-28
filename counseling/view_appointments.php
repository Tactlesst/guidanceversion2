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

$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
$book_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';
require __DIR__.'/../includes/student_appointments_view.php';
