<?php
class CounselingRemarks {
    private $conn;
    private $table_name = "counseling_remarks";

    public $id, $appointment_id, $counselor_id, $session_date, $remarks;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} SET appointment_id=:appointment_id, counselor_id=:counselor_id, session_date=:session_date, remarks=:remarks");
        $this->remarks = htmlspecialchars(strip_tags($this->remarks));
        $stmt->bindParam(":appointment_id", $this->appointment_id);
        $stmt->bindParam(":counselor_id", $this->counselor_id);
        $stmt->bindParam(":session_date", $this->session_date);
        $stmt->bindParam(":remarks", $this->remarks);
        return $stmt->execute();
    }

    public function getByAppointmentId($appointment_id) {
        $stmt = $this->conn->prepare("SELECT r.*, u.first_name as counselor_first_name, u.last_name as counselor_last_name FROM {$this->table_name} r JOIN users u ON r.counselor_id = u.id WHERE r.appointment_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$appointment_id]);
        return $stmt;
    }

    public function getByStudentId($student_id) {
        $stmt = $this->conn->prepare("SELECT r.*, u.first_name as counselor_first_name, u.last_name as counselor_last_name, c.appointment_date FROM {$this->table_name} r JOIN users u ON r.counselor_id = u.id JOIN counseling_appointments c ON r.appointment_id = c.id WHERE c.user_id = ? ORDER BY r.session_date DESC");
        $stmt->execute([$student_id]);
        return $stmt;
    }

    public function update() {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET session_date=:session_date, remarks=:remarks WHERE id=:id");
        $this->remarks = htmlspecialchars(strip_tags($this->remarks));
        $stmt->bindParam(":session_date", $this->session_date);
        $stmt->bindParam(":remarks", $this->remarks);
        $stmt->bindParam(":id", $this->id);
        return $stmt->execute();
    }
}
?>
