<?php

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

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, appointment_date, appointment_time, concern_type, concern_description, urgency_level, status, created_at) 
                  VALUES 
                  (:user_id, :appointment_date, :appointment_time, :concern_type, :concern_description, :urgency_level, 'pending', NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":appointment_date", $this->appointment_date);
        $stmt->bindParam(":appointment_time", $this->appointment_time);
        $stmt->bindParam(":concern_type", $this->concern_type);
        $stmt->bindParam(":concern_description", $this->concern_description);
        $stmt->bindParam(":urgency_level", $this->urgency_level);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
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
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.user_id = ? 
                  ORDER BY c.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
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
        return $this->updateStatus($id, 'cancelled');
    }

    public function getAppointmentStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
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
}
