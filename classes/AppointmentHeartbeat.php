<?php
/**
 * AppointmentHeartbeat Class
 * 
 * Handles automatic status updates for appointments based on dates.
 * Can be called from cron jobs, web requests, or directly from application code.
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

require_once __DIR__ . '/NotificationService.php';

class AppointmentHeartbeat {
    private $db;
    private $log_enabled = true;
    private $log_file = null;
    private $notificationService = null;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
        
        // Set log file path
        $this->log_file = dirname(__DIR__) . '/logs/appointment_heartbeat.log';
 
        // Ensure logs directory exists
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
 
        // Initialize notification service
        $this->notificationService = new NotificationService($database_connection);
    }
    
    /**
     * Enable or disable logging
     */
    public function setLogging($enabled = true) {
        $this->log_enabled = $enabled;
    }
    
    /**
     * Log a message
     */
    private function log($message) {
        if (!$this->log_enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        
        // Try to write to file
        if ($this->log_file && is_writable(dirname($this->log_file))) {
            file_put_contents($this->log_file, $log_message, FILE_APPEND);
        }
        
        // Also log to PHP error log
        error_log($log_message);
    }
    
    /**
     * Run the complete heartbeat
     * 
     * @return array Results of the heartbeat execution
     */
    public function run() {
        $this->log("=== Appointment Status Heartbeat Started ===");
        
        $results = [
            'counseling_missed' => 0,
            'counseling_completed' => 0,
            'exam_missed' => 0,
            'exam_completed' => 0,
            'errors' => []
        ];
        
        try {
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            
            // Update counseling appointments
            $results['counseling_missed'] = $this->updateCounselingMissed($current_date);
            $results['counseling_completed'] = $this->updateCounselingCompleted($current_date);
            
            // Update entrance exam appointments
            $results['exam_missed'] = $this->updateExamMissed($current_date, $current_time);
            $results['exam_completed'] = 0;
            
            $total = array_sum($results) - count($results['errors']);
            $this->log("=== Heartbeat Completed Successfully - Total Updated: {$total} ===");
            
        } catch (Exception $e) {
            $error = "Error during heartbeat: " . $e->getMessage();
            $this->log($error);
            $results['errors'][] = $error;
        }
        
        return $results;
    }
    
    /**
     * Update counseling appointments to "missed" status
     */
    private function updateCounselingMissed($current_date) {
        try {
            // First, get all appointments that will be marked as missed
            $select_query = "SELECT ca.id, ca.user_id, ca.appointment_date, ca.appointment_time, u.first_name, u.last_name
                             FROM counseling_appointments ca
                             JOIN users u ON ca.user_id = u.id
                             WHERE ca.status = 'confirmed' 
                             AND ca.appointment_date < ?
                             AND ca.appointment_date IS NOT NULL";
            
            $select_stmt = $this->db->prepare($select_query);
            $select_stmt->execute([$current_date]);
            $missed_appointments = $select_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update the status
            $update_query = "UPDATE counseling_appointments 
                             SET status = 'missed', 
                                 updated_at = NOW()
                             WHERE status = 'confirmed' 
                             AND appointment_date < ?
                             AND appointment_date IS NOT NULL";
            
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->execute([$current_date]);
            $count = $update_stmt->rowCount();
            
            // Create notifications for each missed appointment
            foreach ($missed_appointments as $appointment) {
                $this->createMissedAppointmentNotification(
                    $appointment['user_id'],
                    $appointment['appointment_date'],
                    $appointment['appointment_time'],
                    $appointment['id']
                );
            }
            
            if ($count > 0) {
                $this->log("Marked {$count} counseling appointments as MISSED and created notifications");
            }
            
            return $count;
        } catch (Exception $e) {
            $this->log("Error updating counseling missed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update counseling appointments to "completed" status
     */
    private function updateCounselingCompleted($current_date) {
        try {
            // Appointments with an assigned counselor should move to in_progress;
            // once the date has passed, auto-complete them.
            $query = "UPDATE counseling_appointments 
                      SET status = 'completed', 
                          updated_at = NOW()
                      WHERE status = 'in_progress' 
                      AND appointment_date <= ?
                      AND appointment_date IS NOT NULL
                      AND DATE_ADD(appointment_date, INTERVAL 1 DAY) <= ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$current_date, $current_date]);
            $count = $stmt->rowCount();
            
            if ($count > 0) {
                $this->log("Marked {$count} counseling appointments as COMPLETED");
            }
            
            return $count;
        } catch (Exception $e) {
            $this->log("Error updating counseling completed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update entrance exam appointments to "missed" status
     */
    private function updateExamMissed($current_date, $current_time) {
        try {
            // First, get all exam appointments that will be marked as missed
            $select_query = "SELECT ea.id, ea.user_id, ea.preferred_date, ea.preferred_time
                             FROM entrance_exam_appointments ea
                             WHERE ea.status = 'confirmed' 
                             AND (
                                 ea.preferred_date < ?
                                 OR (ea.preferred_date = ? AND ea.preferred_time < ?)
                             )
                             AND ea.preferred_date IS NOT NULL";
            
            $select_stmt = $this->db->prepare($select_query);
            $select_stmt->execute([$current_date, $current_date, $current_time]);
            $missed_exams = $select_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update the status
            $update_query = "UPDATE entrance_exam_appointments 
                             SET status = 'missed', 
                                 updated_at = NOW()
                             WHERE status = 'confirmed' 
                             AND (
                                 preferred_date < ?
                                 OR (preferred_date = ? AND preferred_time < ?)
                             )
                             AND preferred_date IS NOT NULL";
            
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->execute([$current_date, $current_date, $current_time]);
            $count = $update_stmt->rowCount();
            
            // Create notifications for each missed exam
            foreach ($missed_exams as $exam) {
                $this->createExamMissedNotification(
                    $exam['user_id'],
                    $exam['preferred_date'],
                    $exam['preferred_time'],
                    $exam['id']
                );
            }
            
            if ($count > 0) {
                $this->log("Marked {$count} entrance exam appointments as MISSED and created notifications");
            }
            
            return $count;
        } catch (Exception $e) {
            $this->log("Error updating exam missed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get the status of a specific appointment
     * 
     * @param string $type 'counseling' or 'exam'
     * @param int $appointment_id
     * @return array|null Appointment data or null if not found
     */
    public function getAppointmentStatus($type, $appointment_id) {
        try {
            if ($type === 'counseling') {
                $query = "SELECT id, status, appointment_date, appointment_time 
                          FROM counseling_appointments 
                          WHERE id = ?";
            } else if ($type === 'exam') {
                $query = "SELECT id, status, preferred_date, preferred_time 
                          FROM entrance_exam_appointments 
                          WHERE id = ?";
            } else {
                return null;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$appointment_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->log("Error getting appointment status: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if an appointment should be marked as missed
     * 
     * @param string $appointment_date Date in Y-m-d format
     * @return bool True if appointment date has passed
     */
    public static function shouldBeMissed($appointment_date) {
        $current_date = date('Y-m-d');
        return strtotime($appointment_date) < strtotime($current_date);
    }
    
    /**
     * Check if an appointment should be marked as completed
     * 
     * @param string $appointment_date Date in Y-m-d format
     * @return bool True if appointment date is today or past
     */
    public static function shouldBeCompleted($appointment_date) {
        $current_date = date('Y-m-d');
        $appointment_ts = strtotime($appointment_date);
        $current_ts = strtotime($current_date);
        $next_day_ts = strtotime($current_date . ' +1 day');
        
        return $appointment_ts <= $current_ts && $next_day_ts > $current_ts;
    }
    
    /**
     * Create a missed appointment notification
     * 
     * @param int $user_id User ID
     * @param string $appointment_date Appointment date (Y-m-d)
     * @param string $appointment_time Appointment time (H:i:s)
     * @param int $appointment_id Appointment ID
     */
    private function createMissedAppointmentNotification($user_id, $appointment_date, $appointment_time, $appointment_id) {
        try {
            $formatted_date = date('F j, Y', strtotime($appointment_date));
            $formatted_time = date('g:i A', strtotime($appointment_time));
            
            $title = "Appointment Missed";
            $message = "Your counseling appointment scheduled for {$formatted_date} at {$formatted_time} was marked as missed.";
            
            $result = $this->notificationService->notify(
                $user_id,
                $title,
                $message,
                'appointment_missed',
                'counseling_appointments',
                $appointment_id,
                true,
                $title,
                null,
                'appointment_missed:' . $appointment_id
            );
            
            if ($result) {
                $this->log("Created missed appointment notification for user {$user_id} (Appointment ID: {$appointment_id})");
            } else {
                $this->log("Failed to create missed appointment notification for user {$user_id} (Appointment ID: {$appointment_id})");
            }
        } catch (Exception $e) {
            $this->log("Error creating missed appointment notification: " . $e->getMessage());
        }
    }
    
    /**
     * Create an exam missed notification
     * 
     * @param int $user_id User ID
     * @param string $exam_date Exam date (Y-m-d)
     * @param string $exam_time Exam time (H:i:s)
     * @param int $exam_id Exam ID
     */
    private function createExamMissedNotification($user_id, $exam_date, $exam_time, $exam_id) {
        try {
            $formatted_date = date('F j, Y', strtotime($exam_date));
            $formatted_time = date('g:i A', strtotime($exam_time));
            
            $title = "Entrance Exam Missed";
            $message = "Your entrance exam scheduled for {$formatted_date} at {$formatted_time} was marked as missed.";
            
            $result = $this->notificationService->notify(
                $user_id,
                $title,
                $message,
                'exam_missed',
                'entrance_exam_appointments',
                $exam_id,
                true,
                $title,
                null,
                'exam_missed:' . $exam_id
            );
            
            if ($result) {
                $this->log("Created missed exam notification for user {$user_id} (Exam ID: {$exam_id})");
            } else {
                $this->log("Failed to create missed exam notification for user {$user_id} (Exam ID: {$exam_id})");
            }
        } catch (Exception $e) {
            $this->log("Error creating missed exam notification: " . $e->getMessage());
        }
    }
}
