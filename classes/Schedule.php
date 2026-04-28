<?php
class Schedule {
    private $conn;
    private $table_name = "schedules";

    public $id, $title, $description, $start_datetime, $end_datetime, $event_type, $created_by, $is_active;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $query = "INSERT INTO {$this->table_name} 
                  SET title=:title, description=:description, start_datetime=:start_datetime,
                      end_datetime=:end_datetime, event_type=:event_type, created_by=:created_by, is_active=:is_active";
        $stmt = $this->conn->prepare($query);
        $this->sanitizeInputs();
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":start_datetime", $this->start_datetime);
        $stmt->bindParam(":end_datetime", $this->end_datetime);
        $stmt->bindParam(":event_type", $this->event_type);
        $stmt->bindParam(":created_by", $this->created_by);
        $stmt->bindParam(":is_active", $this->is_active);
        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }

    public function update() {
        $query = "UPDATE {$this->table_name} SET title=:title, description=:description, start_datetime=:start_datetime,
                  end_datetime=:end_datetime, event_type=:event_type, is_active=:is_active WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $this->sanitizeInputs();
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":start_datetime", $this->start_datetime);
        $stmt->bindParam(":end_datetime", $this->end_datetime);
        $stmt->bindParam(":event_type", $this->event_type);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":id", $this->id);
        return $stmt->execute();
    }

    public function delete() {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE id = ?");
        $stmt->bindParam(1, $this->id);
        return $stmt->execute();
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT s.*, u.first_name, u.last_name FROM {$this->table_name} s JOIN users u ON s.created_by = u.id WHERE s.is_active = 1 ORDER BY s.start_datetime ASC");
        $stmt->execute();
        return $stmt;
    }

    public function getByDateRange($start_date, $end_date) {
        $stmt = $this->conn->prepare("SELECT s.*, u.first_name, u.last_name FROM {$this->table_name} s JOIN users u ON s.created_by = u.id WHERE s.is_active = 1 AND DATE(s.start_datetime) BETWEEN ? AND ? ORDER BY s.start_datetime ASC");
        $stmt->execute([$start_date, $end_date]);
        return $stmt;
    }

    public function getByType($event_type) {
        $stmt = $this->conn->prepare("SELECT s.*, u.first_name, u.last_name FROM {$this->table_name} s JOIN users u ON s.created_by = u.id WHERE s.event_type = ? AND s.is_active = 1 ORDER BY s.start_datetime ASC");
        $stmt->execute([$event_type]);
        return $stmt;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT s.*, u.first_name, u.last_name FROM {$this->table_name} s JOIN users u ON s.created_by = u.id WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function isWithinPDSPeriod($date = null) {
        if (!$date) $date = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table_name} WHERE event_type = 'pds_period' AND is_active = 1 AND DATE(?) BETWEEN DATE(start_datetime) AND DATE(end_datetime)");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    public function getEventsByDate($date) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE DATE(start_datetime) = ? AND is_active = 1 ORDER BY start_datetime ASC");
        $stmt->execute([$date]);
        return $stmt;
    }

    public function isHoliday($date) {
        require_once __DIR__ . '/Holiday.php';
        $holiday = new Holiday($this->conn);
        return $holiday->isHoliday($date);
    }

    private function sanitizeInputs() {
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->event_type = htmlspecialchars(strip_tags($this->event_type));
    }
}
?>
