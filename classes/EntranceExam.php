<?php
class EntranceExam {
    private $conn;
    private $table_name = "entrance_exam_appointments";

    public $id, $user_id, $preferred_date, $preferred_time, $grade_level_applying;
    public $previous_school, $preferred_program, $qualified_program, $status;
    public $confirmed_by, $confirmed_at, $exam_score, $exam_result, $remarks, $assisted_by;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $query = "INSERT INTO {$this->table_name} 
                  SET user_id=:user_id, preferred_date=:preferred_date, preferred_time=:preferred_time,
                      grade_level_applying=:grade_level_applying, previous_school=:previous_school,
                      preferred_program=:preferred_program, status='confirmed', confirmed_at=NOW()";
        $stmt = $this->conn->prepare($query);
        $this->sanitizeInputs();
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":preferred_date", $this->preferred_date);
        $stmt->bindParam(":preferred_time", $this->preferred_time);
        $stmt->bindParam(":grade_level_applying", $this->grade_level_applying);
        $stmt->bindParam(":previous_school", $this->previous_school);
        $stmt->bindParam(":preferred_program", $this->preferred_program);
        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }

    public function getByUserId($user_id) {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         c.first_name as confirmed_by_name, c.last_name as confirmed_by_lastname,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM {$this->table_name} e JOIN users u ON e.user_id = u.id
                  LEFT JOIN users c ON e.confirmed_by = c.id LEFT JOIN users a ON e.assisted_by = a.id
                  WHERE e.user_id = ? ORDER BY e.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         c.first_name as confirmed_by_name, c.last_name as confirmed_by_lastname,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM {$this->table_name} e JOIN users u ON e.user_id = u.id
                  LEFT JOIN users c ON e.confirmed_by = c.id LEFT JOIN users a ON e.assisted_by = a.id
                  WHERE e.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function getAllAppointments() {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         c.first_name as confirmed_by_name, c.last_name as confirmed_by_lastname,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM {$this->table_name} e JOIN users u ON e.user_id = u.id
                  LEFT JOIN users c ON e.confirmed_by = c.id LEFT JOIN users a ON e.assisted_by = a.id
                  ORDER BY e.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getByStatus($status) {
        $stmt = $this->conn->prepare("SELECT e.*, u.first_name, u.last_name, u.email, u.examinee_type, u.grade_level_applying FROM {$this->table_name} e JOIN users u ON e.user_id = u.id WHERE e.status = ? ORDER BY e.preferred_date ASC, e.preferred_time ASC");
        $stmt->execute([$status]);
        return $stmt;
    }

    public function updateStatus($appointment_id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $appointment_id]);
    }

    public function updateExamResult($appointment_id, $score, $result, $remarks, $assisted_by) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET exam_score = ?, exam_result = ?, remarks = ?, assisted_by = ?, status = 'completed' WHERE id = ?");
        return $stmt->execute([$score, $result, $remarks, $assisted_by, $appointment_id]);
    }

    public function cancelAppointment($appointment_id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET status = 'cancelled' WHERE id = ?");
        return $stmt->execute([$appointment_id]);
    }

    public function hasExistingAppointment($user_id) {
        $stmt = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE user_id = ? AND status IN ('confirmed', 'awaiting_results')");
        $stmt->execute([$user_id]);
        return $stmt->rowCount() > 0;
    }

    public function getAppointmentsByDate($date) {
        $stmt = $this->conn->prepare("SELECT e.*, u.first_name, u.last_name FROM {$this->table_name} e JOIN users u ON e.user_id = u.id WHERE e.preferred_date = ? AND e.status IN ('confirmed', 'completed') ORDER BY e.preferred_time");
        $stmt->execute([$date]);
        return $stmt;
    }

    public function isHoliday($date) {
        require_once __DIR__ . '/Holiday.php';
        $holiday = new Holiday($this->conn);
        return $holiday->isHoliday($date);
    }

    private function sanitizeInputs() {
        $this->grade_level_applying = htmlspecialchars(strip_tags($this->grade_level_applying));
        $this->previous_school = htmlspecialchars(strip_tags($this->previous_school));
        $this->preferred_program = htmlspecialchars(strip_tags($this->preferred_program));
        $this->remarks = htmlspecialchars(strip_tags($this->remarks ?? ''));
    }
}
?>
