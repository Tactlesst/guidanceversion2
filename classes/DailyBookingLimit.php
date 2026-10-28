<?php
class DailyBookingLimit {
    private $conn;
    private $table_name = "daily_booking_limits";

    public $id, $date, $max_appointments, $set_by;

    public function __construct($db) { $this->conn = $db; }

    public function setDailyLimit($date, $max_appointments, $set_by) {
        $parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid booking-limit date.');
        if(!is_numeric($max_appointments)||(int)$max_appointments!=$max_appointments||$max_appointments<1||$max_appointments>20)throw new InvalidArgumentException('Daily booking limits must be between 1 and 20 appointments.');
        $query = "INSERT INTO {$this->table_name} (date, max_appointments, set_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE max_appointments = VALUES(max_appointments), set_by = VALUES(set_by), updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$date, $max_appointments, $set_by]);
    }

    public function getDailyLimit($date) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE date = ?");
        $stmt->execute([$date]);
        if ($stmt->rowCount() > 0) return $stmt->fetch(PDO::FETCH_ASSOC);
        return ['date' => $date, 'max_appointments' => 4, 'set_by' => null];
    }

    public function isDailyLimitReached($date) {
        $limit_info = $this->getDailyLimit($date);
        $max = $limit_info['max_appointments'];
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM counseling_appointments WHERE appointment_date = ? AND status IN ('confirmed', 'pending', 'in_progress', 'completed', 'missed')");
        $stmt->execute([$date]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        return ['is_reached' => $current >= $max, 'current_count' => $current, 'max_limit' => $max, 'remaining_slots' => max(0, $max - $current)];
    }

    public function getCapacityForRange($start, $end) {
        $limits=$this->conn->prepare('SELECT date,max_appointments FROM daily_booking_limits WHERE date BETWEEN ? AND ?');
        $limits->execute([$start,$end]);$limits=$limits->fetchAll(PDO::FETCH_KEY_PAIR);
        $counts=$this->conn->prepare("SELECT appointment_date,COUNT(*) FROM counseling_appointments WHERE appointment_date BETWEEN ? AND ? AND status IN ('confirmed','pending','in_progress','completed','missed') GROUP BY appointment_date");
        $counts->execute([$start,$end]);$counts=$counts->fetchAll(PDO::FETCH_KEY_PAIR);$days=[];
        for($date=$start;$date<=$end;$date=date('Y-m-d',strtotime($date.' +1 day'))){
            $maximum=(int)($limits[$date]??4);$current=(int)($counts[$date]??0);
            $days[$date]=['max_limit'=>$maximum,'current_count'=>$current,'remaining_slots'=>max(0,$maximum-$current),'is_reached'=>$current>=$maximum];
        }
        return $days;
    }

    public function getDailyLimitsForRange($start_date, $end_date) {
        $stmt = $this->conn->prepare("SELECT dl.*, u.first_name, u.last_name FROM {$this->table_name} dl LEFT JOIN users u ON dl.set_by = u.id WHERE dl.date BETWEEN ? AND ? ORDER BY dl.date ASC");
        $stmt->execute([$start_date, $end_date]);
        return $stmt;
    }

    public function removeDailyLimit($date) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE date = ?");
        return $stmt->execute([$date]);
    }

    public function getCustomLimitsForMonth($year, $month) {
        $stmt = $this->conn->prepare("SELECT dl.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM counseling_appointments ca WHERE ca.appointment_date = dl.date AND ca.status IN ('confirmed', 'pending')) as current_bookings FROM {$this->table_name} dl LEFT JOIN users u ON dl.set_by = u.id WHERE YEAR(dl.date) = ? AND MONTH(dl.date) = ? ORDER BY dl.date ASC");
        $stmt->execute([$year, $month]);
        return $stmt;
    }
}
?>
