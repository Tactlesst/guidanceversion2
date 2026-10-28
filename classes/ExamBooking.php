<?php
require_once __DIR__.'/SchoolClosure.php';require_once __DIR__.'/SystemSettings.php';require_once __DIR__.'/SubmissionNotifications.php';
/** One source of availability rules for the calendar and booking submission. */
class ExamBooking {
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function slots($date) {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date);
        if (!$day || $day->format('Y-m-d') !== $date || $date <= date('Y-m-d') || $day->format('w') === '0') return [];
        if((new SchoolClosure($this->db))->forDate($date))return [];
        $q=$this->db->prepare('SELECT 1 FROM holidays WHERE date=?'); $q->execute([$date]);
        if ($q->fetchColumn()) return [];
        $q=$this->db->prepare("SELECT * FROM schedules WHERE event_type='entrance_exam' AND is_active=1 AND DATE(start_datetime)<=? AND DATE(end_datetime)>=? ORDER BY updated_at DESC,id DESC LIMIT 1");
        $q->execute([$date,$date]); $window=$q->fetch(PDO::FETCH_ASSOC);
        if (!$window) return [];
        $q=$this->db->prepare('SELECT * FROM entrance_exam_daily_limits WHERE date=?'); $q->execute([$date]); $limit=$q->fetch(PDO::FETCH_ASSOC);
        if (!$limit || (int)$limit['max_appointments']<1) return [];
        $q=$this->db->prepare("SELECT preferred_time,COUNT(*) n FROM entrance_exam_appointments WHERE preferred_date=? AND status IN ('pending','confirmed','awaiting_results') GROUP BY preferred_time");
        $q->execute([$date]); $counts=$q->fetchAll(PDO::FETCH_KEY_PAIR);
        $remaining=(int)$limit['max_appointments']-array_sum($counts);
        if ($remaining<1) return [];
        $start=strtotime($date.' '.substr($window['start_datetime'],11));
        $end=strtotime($date.' '.substr($window['end_datetime'],11));
        // Existing configurations have a single exam_time: retain that exact staff-selected time.
        $times=!empty($limit['exam_time']) ? [$limit['exam_time']] : [];
        if (!$times) for ($t=$start;$t+7200<=$end;$t+=3600) $times[]=date('H:i:s',$t);
        $slots=[];
        foreach ($times as $time) {
            $ts=strtotime($date.' '.$time);
            if ($ts<$start || $ts+7200>$end || $ts<=time()) continue;
            if (!empty($limit['exam_end_time']) && $ts+7200>strtotime($date.' '.$limit['exam_end_time'])) continue;
            $q=$this->db->prepare("SELECT COUNT(*) FROM schedules WHERE is_active=1 AND event_type<>'entrance_exam' AND start_datetime < ? AND end_datetime > ?");
            $q->execute([date('Y-m-d H:i:s',$ts+7200),date('Y-m-d H:i:s',$ts)]);
            if ($q->fetchColumn()) continue;
            $q=$this->db->prepare("SELECT COUNT(*) FROM counseling_appointments WHERE appointment_date=? AND status IN ('pending','confirmed','in_progress','rescheduled','follow_up_scheduled') AND TIMESTAMP(appointment_date,appointment_time) < ? AND DATE_ADD(TIMESTAMP(appointment_date,appointment_time),INTERVAL COALESCE(session_duration,60) MINUTE)>?");
            $q->execute([$date,date('Y-m-d H:i:s',$ts+7200),date('Y-m-d H:i:s',$ts)]);
            if ($q->fetchColumn()) continue;
            $slots[]=['value'=>date('H:i:s',$ts),'label'=>date('g:i A',$ts),'remaining'=>$remaining,'end_at'=>date('Y-m-d H:i:s',$ts+7200)];
        }
        return $slots;
    }
    public function book($userId,$date,$time,$school,$program=null) {
        // A database advisory lock serializes competing applications, including different dates.
        $locked=$this->db->query("SELECT GET_LOCK('guidanceversion2_exam_booking',5)")->fetchColumn();
        if (!$locked) throw new RuntimeException('Booking is busy. Please try again.');
        try {
            $this->db->beginTransaction();
            if(!(new SystemSettings($this->db))->isEntranceExamEnabled())throw new RuntimeException('Entrance exam booking is currently closed.');
            $q=$this->db->prepare("SELECT id FROM entrance_exam_appointments WHERE user_id=? AND status IN ('pending','confirmed','awaiting_results','completed') FOR UPDATE"); $q->execute([$userId]);
            if ($q->fetchColumn()) throw new RuntimeException('You already have an active or completed entrance exam.');
            $q=$this->db->prepare("SELECT grade_level_applying FROM users WHERE id=? AND role='examinee' AND is_active=1 AND COALESCE(archived,0)=0"); $q->execute([$userId]);$grade=$q->fetchColumn();
            if (!$grade) throw new RuntimeException('Please complete your applicant grade level first.');
            if (!is_string($school) || trim($school)==='' || strlen($school)>100) throw new RuntimeException('Enter your previous school (maximum 100 characters).');
            if ($program!==null && strlen($program)>100) throw new RuntimeException('Preferred program must be at most 100 characters.');
            $slots=$this->slots($date);
            if (!in_array($time,array_column($slots,'value'),true)) throw new RuntimeException('This exam slot is unavailable. Choose a staff-configured date and time.');
            $q=$this->db->prepare("INSERT INTO entrance_exam_appointments(user_id,preferred_date,preferred_time,grade_level_applying,previous_school,preferred_program,slot_end_at,status,confirmed_at) VALUES(?,?,?,?,?,?,?,'confirmed',NOW())");
            $selected=array_values(array_filter($slots,static fn($slot)=>$slot['value']===$time))[0];$q->execute([$userId,$date,$time,$grade,trim($school),$program,$selected['end_at']]); $id=$this->db->lastInsertId();
            SubmissionNotifications::appointment($this->db,'exam',(int)$id);$this->db->commit(); return $id;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
        finally { $this->db->query("SELECT RELEASE_LOCK('guidanceversion2_exam_booking')"); }
    }
}
