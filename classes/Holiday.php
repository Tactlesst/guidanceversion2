<?php
class Holiday {
    private $conn;
    private $table_name = "holidays";

    public $id, $name, $date, $type, $is_recurring, $year;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} SET name=:name, date=:date, type=:type, is_recurring=:is_recurring, year=:year");
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":date", $this->date);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":is_recurring", $this->is_recurring);
        $stmt->bindParam(":year", $this->year);
        return $stmt->execute();
    }

    public function getByYear($year) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE year = ? ORDER BY date ASC");
        $stmt->execute([$year]);
        return $stmt;
    }

    public function getByMonth($month, $year) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE MONTH(date) = ? AND year = ? ORDER BY date ASC");
        $stmt->execute([$month, $year]);
        return $stmt;
    }

    public function isHoliday($date) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table_name} WHERE date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    public function getHolidayInfo($date) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE date = ? LIMIT 1");
        $stmt->execute([$date]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function getUpcomingHolidays($limit = 5) {
        $stmt = $this->conn->prepare("SELECT DISTINCT name, date, type, year, MIN(id) as id FROM {$this->table_name} WHERE date >= ? GROUP BY name, date, year ORDER BY date ASC LIMIT ?");
        $today = date('Y-m-d');
        $stmt->bindParam(1, $today);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function getHolidaysByMonth($month) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE MONTH(date) = ? AND (year = ? OR is_recurring = 1) ORDER BY date ASC");
        $stmt->execute([$month, date('Y')]);
        return $stmt;
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function needsSync($year = null) {
        if (!$year) $year = date('Y');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table_name} WHERE year = ?");
        $stmt->execute([$year]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] < 10;
    }

    public function getSyncStatus($startYear = null, $endYear = null) {
        if (!$startYear) $startYear = 2024;
        if (!$endYear) $endYear = date('Y') + 2;
        $status = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table_name} WHERE year = ?");
            $stmt->execute([$year]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $status[$year] = ['year' => $year, 'holiday_count' => $count, 'is_synced' => $count >= 10];
        }
        return $status;
    }
}
?>
