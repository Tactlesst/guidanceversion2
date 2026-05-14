<?php

require_once __DIR__ . '/NotificationService.php';

class CounselingAppointment {
    private $conn;
    private $table_name = "counseling_appointments";

    public $id;
    public $user_id;
    public $appointment_date;
    public $appointment_time;
    public $concern_type;
    public $concern_description;
    public $urgency_level;
    public $status;
    public $confirmed_at;
    public $assigned_advocate_id;
    public $nature_of_contact;
    public $session_duration;
    public $is_follow_up;
    public $parent_appointment_id;
    public $booking_type;
    public $original_appointment_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $is_followup = isset($this->is_follow_up) && $this->is_follow_up == 1;

        // Auto-confirm all appointments (matches old guidance behavior)
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, appointment_date=:appointment_date, appointment_time=:appointment_time,
                      concern_type=:concern_type, concern_description=:concern_description, urgency_level=:urgency_level,
                      nature_of_contact=:nature_of_contact, session_duration=:session_duration,
                      is_follow_up=:is_follow_up, parent_appointment_id=:parent_appointment_id,
                      booking_type=:booking_type, original_appointment_id=:original_appointment_id,
                      status='confirmed', confirmed_at=NOW()";

        $stmt = $this->conn->prepare($query);
        $this->sanitizeInputs();

        // Set defaults for new fields if not provided
        $is_follow_up = isset($this->is_follow_up) ? $this->is_follow_up : 0;
        $parent_appointment_id = isset($this->parent_appointment_id) ? $this->parent_appointment_id : null;
        $booking_type = isset($this->booking_type) ? $this->booking_type : 'regular';
        $nature_of_contact = isset($this->nature_of_contact) ? $this->nature_of_contact : 'walk-in';
        $session_duration = isset($this->session_duration) ? $this->session_duration : 60;
        $original_appointment_id = isset($this->original_appointment_id) ? $this->original_appointment_id : null;

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":appointment_date", $this->appointment_date);
        $stmt->bindParam(":appointment_time", $this->appointment_time);
        $stmt->bindParam(":concern_type", $this->concern_type);
        $stmt->bindParam(":concern_description", $this->concern_description);
        $stmt->bindParam(":urgency_level", $this->urgency_level);
        $stmt->bindParam(":nature_of_contact", $nature_of_contact);
        $stmt->bindParam(":session_duration", $session_duration);
        $stmt->bindParam(":is_follow_up", $is_follow_up);
        $stmt->bindParam(":parent_appointment_id", $parent_appointment_id);
        $stmt->bindParam(":booking_type", $booking_type);
        $stmt->bindParam(":original_appointment_id", $original_appointment_id);

        if($stmt->execute()) {
            $appointment_id = $this->conn->lastInsertId();

            // Send automatic confirmation notification
            $this->createAutoConfirmationNotification($appointment_id);

            return $appointment_id;
        }
        return false;
    }

    public function hasActiveAppointment($user_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE user_id = ? AND status IN ('pending', 'confirmed', 'in_progress') AND appointment_date >= CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function getByUserId($user_id) {
        $query = "SELECT c.*, u.first_name, u.last_name, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name,
                         CONCAT(adv.first_name, ' ', adv.last_name) as assigned_advocate_name,
                         orig.appointment_date as original_date,
                         orig.appointment_time as original_time
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  LEFT JOIN " . $this->table_name . " orig ON c.original_appointment_id = orig.id
                  WHERE c.user_id = ? 
                  ORDER BY c.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
    }

    public function getUserAppointments($user_id) {
        return $this->getByUserId($user_id);
    }

    public function getById($id) {
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function getAllAppointments() {
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  ORDER BY c.appointment_date DESC, c.appointment_time DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getByStatus($status) {
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.status = ? ORDER BY c.appointment_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $status);
        $stmt->execute();
        return $stmt;
    }

    public function getByDate($date) {
        $query = "SELECT c.*, u.first_name, u.last_name, sp.student_id,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.appointment_date = ? ORDER BY c.appointment_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $date);
        $stmt->execute();
        return $stmt;
    }

    public function getByDateRange($start_date, $end_date) {
        $query = "SELECT c.*, u.first_name, u.last_name, sp.student_id,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.appointment_date BETWEEN ? AND ? ORDER BY c.appointment_date ASC, c.appointment_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        return $stmt;
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = ?";
        $params = [$status];
        if ($status === 'confirmed') { $query .= ", confirmed_at = NOW()"; }
        if ($status === 'completed') { $query .= ", completed_at = NOW()"; }
        $query .= " WHERE id = ?";
        $params[] = $id;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function assignAdvocate($id, $advocate_id) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET assigned_advocate_id = ? WHERE id = ?");
        return $stmt->execute([$advocate_id, $id]);
    }

    public function cancelAppointment($id) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $this->createAppointmentNotification($id, 'appointment_cancelled');
            return true;
        }
        return false;
    }

    public function completeAppointment($id, $remarks = null) {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET status = 'completed', updated_at = NOW() WHERE id = ?");
            if (!$stmt->execute([$id])) {
                throw new Exception("Failed to update appointment status");
            }

            if (!empty($remarks)) {
                $app_stmt = $this->conn->prepare("SELECT appointment_date, assigned_advocate_id FROM " . $this->table_name . " WHERE id = ?");
                $app_stmt->execute([$id]);
                $app_data = $app_stmt->fetch(PDO::FETCH_ASSOC);
                $counselor_id = !empty($app_data['assigned_advocate_id']) ? $app_data['assigned_advocate_id'] : ($_SESSION['user_id'] ?? null);
                if ($counselor_id) {
                    $remarks_stmt = $this->conn->prepare("INSERT INTO counseling_remarks (appointment_id, counselor_id, session_date, remarks, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $remarks_stmt->execute([$id, $counselor_id, $app_data['appointment_date'], $remarks]);
                }
            }

            $this->conn->commit();
            $this->createAppointmentNotification($id, 'appointment_completed');
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function assignCounselor($id, $advocate_id) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET assigned_advocate_id = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$advocate_id, $id])) {
            // Notify assigned advocate
            require_once __DIR__ . '/Notification.php';
            $appointment = $this->getById($id);
            if ($appointment) {
                $notification = new Notification($this->conn);
                $notification->user_id = $advocate_id;
                $notification->title = 'New Counseling Assignment';
                $date = date('F j, Y', strtotime($appointment['appointment_date']));
                $time = date('g:i A', strtotime($appointment['appointment_time']));
                $notification->message = "You have been assigned to a counseling session with {$appointment['first_name']} {$appointment['last_name']} on {$date} at {$time}.";
                $notification->type = 'advocate_assignment';
                $notification->related_table = 'counseling_appointments';
                $notification->related_id = $id;
                $notification->create();
            }
            return true;
        }
        return false;
    }

    public function getAppointmentStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByAdvocateId($advocate_id) {
        $query = "SELECT c.*, u.first_name, u.last_name, sp.student_id, sp.grade_level
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  WHERE c.assigned_advocate_id = ? AND c.status IN ('confirmed', 'in_progress')
                  ORDER BY c.appointment_date ASC, c.appointment_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $advocate_id);
        $stmt->execute();
        return $stmt;
    }

    private function sanitizeInputs() {
        $this->concern_type = htmlspecialchars(strip_tags($this->concern_type ?? ''));
        $this->concern_description = htmlspecialchars(strip_tags($this->concern_description ?? ''));
    }

    private function createAutoConfirmationNotification($appointment_id) {
        $appointment = $this->getById($appointment_id);
        if (!$appointment) return false;

        // Skip for rescheduled appointments
        if (!empty($appointment['original_appointment_id'])) return true;

        // Skip for follow-up appointments
        if (!empty($appointment['is_follow_up'])) return true;

        try {
            $notificationService = new NotificationService($this->conn);
            $title = 'Appointment Confirmed';
            $date = date('F j, Y', strtotime($appointment['appointment_date']));
            $time = date('g:i A', strtotime($appointment['appointment_time']));
            $message = "Your counseling appointment has been confirmed for {$date} at {$time}.";

            return $notificationService->notify(
                $appointment['user_id'],
                $title,
                $message,
                'appointment_confirmed',
                'counseling_appointments',
                $appointment_id,
                true,
                $title,
                null,
                'appointment_confirmed:' . $appointment_id
            );
        } catch (Exception $e) {
            error_log('Auto confirmation notification error: ' . $e->getMessage());
            return false;
        }
    }

    private function createAppointmentNotification($appointment_id, $type) {
        $appointment = $this->getById($appointment_id);
        if (!$appointment) return false;

        try {
            $notificationService = new NotificationService($this->conn);

            $titles = [
                'appointment_confirmed' => 'Appointment Confirmed',
                'appointment_cancelled' => 'Appointment Cancelled',
                'appointment_rescheduled' => 'Appointment Rescheduled',
                'appointment_completed' => 'Appointment Completed',
                'appointment_missed' => 'Appointment Missed'
            ];

            $base_messages = [
                'appointment_confirmed' => 'Your counseling appointment has been confirmed',
                'appointment_cancelled' => 'Your counseling appointment has been cancelled',
                'appointment_rescheduled' => 'Your counseling appointment has been rescheduled',
                'appointment_completed' => 'Your counseling appointment has been completed.',
                'appointment_missed' => 'You missed your counseling appointment'
            ];

            $title = $titles[$type] ?? 'Appointment Update';
            $base_message = $base_messages[$type] ?? 'Your appointment status has been updated.';

            if (isset($appointment['appointment_date']) && isset($appointment['appointment_time'])) {
                $date = date('F j, Y', strtotime($appointment['appointment_date']));
                $time = date('g:i A', strtotime($appointment['appointment_time']));
                $message = $base_message . " for {$date} at {$time}.";
            } else {
                $message = $base_message;
            }

            $shouldEmail = in_array($type, ['appointment_confirmed', 'appointment_cancelled', 'appointment_rescheduled'], true);
            $dedupe_key = $type . ':' . $appointment_id;

            return $notificationService->notify(
                $appointment['user_id'],
                $title,
                $message,
                $type,
                'counseling_appointments',
                $appointment_id,
                $shouldEmail,
                $title,
                null,
                $dedupe_key
            );
        } catch (Exception $e) {
            error_log('Appointment notification error: ' . $e->getMessage());
            return false;
        }
    }
}
