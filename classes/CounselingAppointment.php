<?php

require_once __DIR__ . '/NotificationService.php';require_once __DIR__.'/SubmissionNotifications.php';
require_once __DIR__ . '/DailyBookingLimit.php';
require_once __DIR__.'/SchoolClosure.php';

class CounselingAppointment {
    // Student counseling uses regular office slots; an availability event is not required.
    public const BOOKING_TIMES = ['08:00:00','09:00:00','10:00:00','11:00:00','13:00:00','14:00:00','15:00:00','16:00:00'];
    public const MAX_PER_TIME = 1;
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
        $owned=!$this->conn->inTransaction();if($owned)$this->conn->beginTransaction();else $this->conn->exec('SAVEPOINT counseling_create');
        try{
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
            SubmissionNotifications::appointment($this->conn,'counseling',(int)$appointment_id);
            if($owned)$this->conn->commit();else $this->conn->exec('RELEASE SAVEPOINT counseling_create');

            return $appointment_id;
        }
        if($owned)$this->conn->rollBack();else $this->conn->exec('ROLLBACK TO SAVEPOINT counseling_create');return false;
        }catch(Throwable $e){if($owned&&$this->conn->inTransaction())$this->conn->rollBack();elseif(!$owned)$this->conn->exec('ROLLBACK TO SAVEPOINT counseling_create');throw $e;}
    }

    /** Serialize capacity checks and student booking inserts so the last slot cannot be overbooked. */
    public function createWithDailyLimit() {
        $date=$this->appointment_date;
        $parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Please select a valid appointment date.');
        if($date<=date('Y-m-d'))throw new InvalidArgumentException('Appointments must be planned in advance. Please choose tomorrow or a later date.');
        if($parsed->format('w')==='0')throw new RuntimeException('The guidance office is closed on Sundays. Please choose another date.');
        if(!in_array($this->appointment_time,self::BOOKING_TIMES,true))throw new InvalidArgumentException('Please select a regular counseling time slot.');
        $locked=$this->conn->query("SELECT GET_LOCK('guidanceversion2_counseling_booking',5)")->fetchColumn();
        if(!$locked)throw new RuntimeException('Booking is busy. Please try again.');
        $ownsTransaction=!$this->conn->inTransaction();
        try{
            if($ownsTransaction)$this->conn->beginTransaction();
            if($closure=(new SchoolClosure($this->conn))->forDate($date))throw new RuntimeException('School closed: '.$closure['reason'].'. Please choose another date.');
            // Recheck inside the booking lock so simultaneous requests cannot bypass limits.
            if($this->hasActiveAppointment($this->user_id))throw new RuntimeException('You already have an active appointment. Please complete or cancel it before booking another.');
            $holidayColumns=$this->conn->query('SHOW COLUMNS FROM holidays')->fetchAll(PDO::FETCH_COLUMN);
            $holidayColumn=in_array('date',$holidayColumns,true)?'date':'holiday_date';
            $holiday=$this->conn->prepare("SELECT 1 FROM holidays WHERE `$holidayColumn`=? LIMIT 1");
            $holiday->execute([$date]);
            if($holiday->fetchColumn())throw new RuntimeException('The guidance office is closed for a holiday. Please choose another date.');
            $holiday=$this->conn->prepare("SELECT 1 FROM schedules WHERE event_type='holiday' AND is_active=1 AND DATE(start_datetime)<=? AND DATE(end_datetime)>=? LIMIT 1");
            $holiday->execute([$date,$date]);
            if($holiday->fetchColumn())throw new RuntimeException('The guidance office is closed for a holiday. Please choose another date.');
            $capacity=(new DailyBookingLimit($this->conn))->isDailyLimitReached($date);
            if($capacity['is_reached'])throw new RuntimeException('The daily counseling booking limit has been reached for this date. Please choose another date.');
            if($this->slotConflictCount($date,$this->appointment_time,(int)($this->session_duration??60))>=self::MAX_PER_TIME)throw new RuntimeException('This time is already booked. Please choose another time.');
            $id=$this->create();
            if(!$id)throw new RuntimeException('Your appointment could not be saved. Please try again.');
            if($ownsTransaction)$this->conn->commit();
            return $id;
        }catch(Throwable $e){if($ownsTransaction&&$this->conn->inTransaction())$this->conn->rollBack();throw $e;}
        finally{$this->conn->query("SELECT RELEASE_LOCK('guidanceversion2_counseling_booking')");}
    }

    /** Count occupied sessions overlapping the requested interval; end boundaries may touch. */
    public function slotConflictCount(string $date,string $time,int $duration=60,int $excludeId=0):int {
        $q=$this->conn->prepare("SELECT COUNT(*) FROM counseling_appointments WHERE appointment_date=? AND status IN ('pending','confirmed','in_progress') AND id<>? AND TIME_TO_SEC(appointment_time)<TIME_TO_SEC(?)+? AND TIME_TO_SEC(appointment_time)+CASE WHEN session_duration>0 THEN session_duration ELSE 60 END*60>TIME_TO_SEC(?)");
        $q->execute([$date,$excludeId,$time,max(1,$duration)*60,$time]);return (int)$q->fetchColumn();
    }
    /** Only aggregate occupied-slot counts are passed to the student calendar. */
    public function slotCountsForRange(string $start,string $end):array {
        $q=$this->conn->prepare("SELECT appointment_date,appointment_time,session_duration FROM counseling_appointments WHERE appointment_date BETWEEN ? AND ? AND status IN ('pending','confirmed','in_progress')");$q->execute([$start,$end]);$out=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$parts=explode(':',$row['appointment_time']);$from=(int)$parts[0]*3600+(int)$parts[1]*60+(int)($parts[2]??0);$until=$from+((int)$row['session_duration']>0?(int)$row['session_duration']:60)*60;
            foreach(self::BOOKING_TIMES as $slot){$parts=explode(':',$slot);$slotFrom=(int)$parts[0]*3600+(int)$parts[1]*60;if($from<$slotFrom+3600&&$until>$slotFrom)$out[$row['appointment_date']][$slot]=($out[$row['appointment_date']][$slot]??0)+1;}
        }return $out;
    }
    /** Staff schedule changes share the student booking lock and interval checks. */
    public function saveStaffSchedule(int $appointmentId,string $date,string $time,bool $followup,int $actor):array {
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid appointment date.');
        if(!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/',$time))throw new InvalidArgumentException('Choose a valid appointment time.');
        $locked=$this->conn->query("SELECT GET_LOCK('guidanceversion2_counseling_booking',5)")->fetchColumn();if(!$locked)throw new RuntimeException('Booking is busy. Please try again.');$owns=!$this->conn->inTransaction();
        try{
            if($owns)$this->conn->beginTransaction();
            $q=$this->conn->prepare('SELECT * FROM counseling_appointments WHERE id=? FOR UPDATE');$q->execute([$appointmentId]);$parent=$q->fetch(PDO::FETCH_ASSOC);if(!$parent)throw new RuntimeException('Appointment not found.');require_once __DIR__.'/OfficePolicy.php';$operator=OfficePolicy::actor($this->conn,$actor);if($operator['role']==='guidance_advocate'&&(!$followup||(int)$parent['assigned_advocate_id']!==$actor))throw new RuntimeException('You can add follow-ups only to your own sessions.');
            if(!$followup&&$parent['appointment_date']===$date&&substr($parent['appointment_time'],0,5)===substr($time,0,5)){if($owns)$this->conn->commit();return ['id'=>$appointmentId,'user_id'=>(int)$parent['user_id']];}
            if($closure=(new SchoolClosure($this->conn))->forDate($date))throw new RuntimeException('School closed: '.$closure['reason'].'. Choose another date.');
            $duration=$followup?60:((int)$parent['session_duration']>0?(int)$parent['session_duration']:60);
            if($this->slotConflictCount($date,$time,$duration,$followup?0:$appointmentId)>=self::MAX_PER_TIME)throw new RuntimeException('This time is already booked. Please choose another time.');
            if($followup){$q=$this->conn->prepare("INSERT INTO counseling_appointments(user_id,appointment_date,appointment_time,concern_type,concern_description,urgency_level,status,assigned_advocate_id,is_follow_up,parent_appointment_id,session_duration,created_at) VALUES(?,?,?,'Follow-up','Follow-up session','normal','confirmed',?,1,?,60,NOW())");$q->execute([$parent['user_id'],$date,$time,$actor,$appointmentId]);$newId=(int)$this->conn->lastInsertId();$this->conn->prepare("UPDATE counseling_appointments SET status='completed',updated_at=NOW() WHERE id=?")->execute([$appointmentId]);}
            else{$this->conn->prepare('UPDATE counseling_appointments SET appointment_date=?,appointment_time=?,updated_at=NOW() WHERE id=?')->execute([$date,$time,$appointmentId]);$newId=$appointmentId;}
            SubmissionNotifications::change($this->conn,'counseling',(int)$newId,$followup?'followup':'rescheduled');if($owns)$this->conn->commit();return ['id'=>$newId,'user_id'=>(int)$parent['user_id']];
        }catch(Throwable $e){if($owns&&$this->conn->inTransaction())$this->conn->rollBack();throw $e;}
        finally{$this->conn->query("SELECT RELEASE_LOCK('guidanceversion2_counseling_booking')");}
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

    public function getUnassigned() {
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE (c.assigned_advocate_id IS NULL OR c.assigned_advocate_id = '') 
                  AND c.status IN ('pending', 'confirmed')
                  ORDER BY c.appointment_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getConfirmed() {
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                         adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
                  WHERE c.status = 'confirmed' 
                  AND c.assigned_advocate_id IS NOT NULL 
                  AND c.assigned_advocate_id != ''
                  ORDER BY c.appointment_date DESC";
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

    public function updateStatus($id,$status,?int $actorId=null) {
        if(!in_array($status,['pending','confirmed','in_progress','completed','cancelled','missed'],true))throw new InvalidArgumentException('Choose a valid appointment status.');$owns=!$this->conn->inTransaction();if($owns)$this->conn->beginTransaction();
        try{$q=$this->conn->prepare('SELECT status,assigned_advocate_id FROM counseling_appointments WHERE id=? FOR UPDATE');$q->execute([$id]);$locked=$q->fetch(PDO::FETCH_ASSOC);$before=$locked?$locked['status']:false;if($actorId!==null){require_once __DIR__.'/OfficePolicy.php';$actor=OfficePolicy::actor($this->conn,$actorId);if($actor['role']==='guidance_advocate'&&(!$locked||(int)$locked['assigned_advocate_id']!==$actorId||!in_array($status,['in_progress','completed','missed'],true)))throw new RuntimeException('You can work only on your assigned sessions.');}if($before===false){if($owns)$this->conn->rollBack();return false;}if($before!==$status){$this->conn->prepare('UPDATE counseling_appointments SET status=?,updated_at=NOW()'.($status==='confirmed'?',confirmed_at=NOW()':'').' WHERE id=?')->execute([$status,$id]);SubmissionNotifications::change($this->conn,'counseling',(int)$id,$status);}if($owns)$this->conn->commit();return true;
        }catch(Throwable $e){if($owns&&$this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }
    public function assignAdvocate($id,$advocate_id,?int $actorId=null) {
        $owns=!$this->conn->inTransaction();if($owns)$this->conn->beginTransaction();
        try{$q=$this->conn->prepare('SELECT assigned_advocate_id,status FROM counseling_appointments WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch(PDO::FETCH_ASSOC);if(!$before)throw new RuntimeException('Appointment unavailable.');if($actorId!==null){require_once __DIR__.'/OfficePolicy.php';$actor=OfficePolicy::actor($this->conn,$actorId);if($actor['role']==='guidance_advocate'&&((int)$advocate_id!==$actorId||!empty($before['assigned_advocate_id'])||!in_array($before['status'],['pending','confirmed'],true)))throw new RuntimeException('You may claim only an unassigned appointment for yourself.');}$q=$this->conn->prepare("SELECT id FROM users WHERE id=? AND role IN ('admin','guidance_advocate') AND is_active=1 AND COALESCE(archived,0)=0");$q->execute([$advocate_id]);if(!$q->fetchColumn())throw new RuntimeException('Choose an active guidance counselor or advocate.');if((int)$before['assigned_advocate_id']!==(int)$advocate_id){$this->conn->prepare('UPDATE counseling_appointments SET assigned_advocate_id=? WHERE id=?')->execute([$advocate_id,$id]);SubmissionNotifications::assigned($this->conn,(int)$id,(int)$advocate_id);}if($owns)$this->conn->commit();return true;
        }catch(Throwable $e){if($owns&&$this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }
    public function cancelAppointment($id){return $this->updateStatus($id,'cancelled');}

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

            SubmissionNotifications::change($this->conn,'counseling',(int)$id,'completed');$this->conn->commit();
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
                    SUM(CASE WHEN (assigned_advocate_id IS NULL OR assigned_advocate_id = '') AND status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'confirmed' AND assigned_advocate_id IS NOT NULL AND assigned_advocate_id != '' THEN 1 ELSE 0 END) as confirmed,
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
