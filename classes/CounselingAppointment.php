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
                  WHERE user_id = ? AND status IN ('pending', 'confirmed') AND appointment_date >= CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function getByUserId($user_id) {
        $query = "SELECT c.*, u.first_name, u.last_name 
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  WHERE c.user_id = ? 
                  ORDER BY c.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
    }
}
