<?php
/**
 * Test Heartbeat Script
 * 
 * Tests the appointment heartbeat system without affecting production data
 * 
 * Usage: php test_heartbeat.php
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

$project_root = dirname(__DIR__);

require_once $project_root . '/config/database.php';
require_once $project_root . '/classes/AppointmentHeartbeat.php';

echo "=== Appointment Heartbeat Test ===\n\n";

try {
    // Connect to database
    echo "1. Connecting to database...\n";
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    echo "   ✓ Database connected\n\n";
    
    // Check for AppointmentHeartbeat class
    echo "2. Checking AppointmentHeartbeat class...\n";
    if (!class_exists('AppointmentHeartbeat')) {
        throw new Exception("AppointmentHeartbeat class not found");
    }
    echo "   ✓ AppointmentHeartbeat class found\n\n";
    
    // Check for NotificationService class
    echo "3. Checking NotificationService class...\n";
    if (!class_exists('NotificationService')) {
        throw new Exception("NotificationService class not found");
    }
    echo "   ✓ NotificationService class found\n\n";
    
    // Check database tables
    echo "4. Checking database tables...\n";
    $tables = ['counseling_appointments', 'entrance_exam_appointments', 'notifications', 'users'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            echo "   ✗ Table '{$table}' not found\n";
        } else {
            echo "   ✓ Table '{$table}' exists\n";
        }
    }
    echo "\n";
    
    // Check for appointments to update
    echo "5. Checking for appointments...\n";
    $current_date = date('Y-m-d');
    
    // Check counseling appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM counseling_appointments WHERE status = 'confirmed' AND appointment_date < ?");
    $stmt->execute([$current_date]);
    $counseling_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Counseling appointments to mark as missed: {$counseling_count}\n";
    
    // Check entrance exam appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM entrance_exam_appointments WHERE status = 'confirmed' AND preferred_date < ?");
    $stmt->execute([$current_date]);
    $exam_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Entrance exam appointments to mark as missed: {$exam_count}\n\n";
    
    // Run heartbeat
    echo "6. Running heartbeat...\n";
    $heartbeat = new AppointmentHeartbeat($db);
    $results = $heartbeat->run();
    
    echo "   ✓ Heartbeat completed\n\n";
    
    // Display results
    echo "7. Results:\n";
    echo "   - Counseling Missed: " . ($results['counseling_missed'] ?? 0) . "\n";
    echo "   - Counseling Completed: " . ($results['counseling_completed'] ?? 0) . "\n";
    echo "   - Exam Missed: " . ($results['exam_missed'] ?? 0) . "\n";
    echo "   - Exam Completed: " . ($results['exam_completed'] ?? 0) . "\n";
    
    $total = ($results['counseling_missed'] ?? 0) + ($results['counseling_completed'] ?? 0) + 
             ($results['exam_missed'] ?? 0) + ($results['exam_completed'] ?? 0);
    echo "   - Total Updated: {$total}\n";
    
    if (!empty($results['errors'])) {
        echo "\n   Errors:\n";
        foreach ($results['errors'] as $error) {
            echo "   - {$error}\n";
        }
    }
    
    echo "\n";
    
    // Check logs
    echo "8. Checking logs...\n";
    $log_file = $project_root . '/logs/appointment_heartbeat.log';
    if (file_exists($log_file)) {
        echo "   ✓ Log file exists: {$log_file}\n";
        $log_size = filesize($log_file);
        echo "   - Log file size: " . number_format($log_size) . " bytes\n";
        
        // Show last 5 lines
        $lines = file($log_file);
        $last_lines = array_slice($lines, -5);
        echo "\n   Last 5 log entries:\n";
        foreach ($last_lines as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "   ✗ Log file not found (will be created on first run)\n";
    }
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
