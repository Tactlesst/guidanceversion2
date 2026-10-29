<?php
require_once __DIR__.'/../scheduling/SchoolClosure.php';require_once __DIR__.'/../scheduling/Holiday.php';require_once __DIR__.'/../system/SystemSettings.php';require_once __DIR__.'/../notifications/SubmissionNotifications.php';
/** The office schedule defines exam sessions; daily limits define their shared capacity. */
class ExamBooking {
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    /** Scheduled events stay visible even when closed, full or not yet open for booking. */
    public function availability($date):array {
        $empty=['date'=>is_string($date)?$date:'','scheduled'=>[],'slots'=>[],'capacity'=>null,'used'=>0,'remaining'=>null,'closed'=>false,'past'=>false,'status'=>'invalid','message'=>'Choose a valid examination date.'];
        $day=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$day||$day->format('Y-m-d')!==$date)return $empty;
        $q=$this->db->prepare("SELECT id,title,start_datetime,end_datetime FROM schedules WHERE event_type='entrance_exam' AND is_active=1 AND DATE(start_datetime)<=? AND DATE(end_datetime)>=? ORDER BY start_datetime,id");$q->execute([$date,$date]);
        $sessions=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $event){
            // A multi-day calendar period offers its configured daily hours on each open date.
            $start=$date.' '.substr($event['start_datetime'],11);$end=$date.' '.substr($event['end_datetime'],11);$valid=$end>$start;
            $sessions[]=['schedule_id'=>(int)$event['id'],'title'=>$event['title'],'value'=>substr($start,11),'start_at'=>$start,'end_at'=>$end,'label'=>date('g:i A',strtotime($start)).' – '.date('g:i A',strtotime($end)),'bookable'=>false,'reason'=>'','valid'=>$valid];
        }
        $q=$this->db->prepare('SELECT max_appointments FROM entrance_exam_daily_limits WHERE date=?');$q->execute([$date]);$limit=$q->fetchColumn();$capacity=$limit===false?null:(int)$limit;
        $q=$this->db->prepare("SELECT COUNT(*) FROM entrance_exam_appointments WHERE preferred_date=? AND status IN ('pending','confirmed','rescheduled','awaiting_results')");$q->execute([$date]);$used=(int)$q->fetchColumn();$remaining=$capacity===null?null:max(0,$capacity-$used);
        $closed=$day->format('w')==='0'||(new Holiday($this->db))->isHoliday($date)||(bool)(new SchoolClosure($this->db))->forDate($date);$past=$date<=date('Y-m-d');$enabled=(new SystemSettings($this->db))->isEntranceExamEnabled();
        $status='available';$message='Select the scheduled exam session to reserve your place.';
        if($closed){$status='closed';$message='School is closed on this date. Exam booking is unavailable.';}
        elseif($past){$status='past';$message=$date===date('Y-m-d')?'Booking must be planned in advance. Choose tomorrow or a later date.':'Booking for this date has ended.';}
        elseif(!$sessions){$status='no_exam';$message='No entrance examination is scheduled for this date.';}
        elseif(!$enabled){$status='disabled';$message='An exam is scheduled, but entrance exam booking is currently closed.';}
        elseif($capacity!==null&&$capacity<1){$status='not_open';$message='An exam is scheduled. The Guidance Office has not opened booking for this date yet.';}
        elseif($remaining!==null&&$remaining<1){$status='full';$message='All examination places for this date have been reserved.';}
        $slots=[];
        foreach($sessions as &$session){
            $session['reason']=$message;
            if($status!=='available')continue;
            if(!$session['valid']){$session['reason']='The exam time needs review by the Guidance Office.';continue;}
            // Counseling uses separate resources and can run alongside entrance exams.
            $q=$this->db->prepare("SELECT COUNT(*) FROM schedules WHERE is_active=1 AND event_type NOT IN ('entrance_exam','counseling') AND start_datetime < ? AND end_datetime > ?");$q->execute([$session['end_at'],$session['start_at']]);
            if((int)$q->fetchColumn()>0){$session['reason']='Booking for this exam session is unavailable. Please contact the Guidance Office.';continue;}
            $session['bookable']=true;$session['reason']='Available';$slot=$session;$slot['remaining']=$remaining;$slots[]=$slot;
        }unset($session);
        usort($sessions,static fn($a,$b)=>strcmp($a['start_at'],$b['start_at'])?:($a['schedule_id']<=>$b['schedule_id']));usort($slots,static fn($a,$b)=>strcmp($a['start_at'],$b['start_at'])?:($a['schedule_id']<=>$b['schedule_id']));
        if($status==='available'&&!$slots){$status='unavailable';$message='An exam is scheduled, but no session is currently available for booking. Please contact the Guidance Office.';}
        return ['date'=>$date,'scheduled'=>$sessions,'slots'=>$slots,'capacity'=>$capacity,'used'=>$used,'remaining'=>$remaining,'closed'=>$closed,'past'=>$past,'status'=>$status,'message'=>$message];
    }
    /** Compatibility key for existing callers; entries are actual scheduled sessions, never generated hours. */
    public function slots($date):array{return $this->availability($date)['slots'];}
    /** Find the next published working-day session, respecting its optional daily capacity. */
    public function nextSession(DateTimeImmutable $now):?array {
        $tomorrow=$now->modify('+1 day')->format('Y-m-d');
        $q=$this->db->prepare("SELECT DATE(start_datetime) AS first_day,DATE(end_datetime) AS last_day FROM schedules WHERE event_type='entrance_exam' AND is_active=1 AND DATE(end_datetime)>=? ORDER BY start_datetime,id");$q->execute([$tomorrow]);$windows=$q->fetchAll(PDO::FETCH_ASSOC);if(!$windows)return null;
        $date=max($tomorrow,min(array_column($windows,'first_day')));$last=max(array_column($windows,'last_day'));
        while($date<=$last){$inside=false;$next=null;foreach($windows as $window){if($window['first_day']<=$date&&$window['last_day']>=$date)$inside=true;if($window['first_day']>$date&&($next===null||$window['first_day']<$next))$next=$window['first_day'];}
            if(!$inside){if($next===null)break;$date=$next;continue;}
            $available=$this->availability($date);if($available['slots'])return $available['slots'][0]+['date'=>$date];$date=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
        }return null;
    }
    /** Keep the original unfinished attempt; link one replacement, atomically with its notices. */
    public function rescheduleUnfinished(int $id,DateTimeImmutable $now,bool $dryRun=false):array {
        require_once __DIR__.'/../scheduling/AppointmentHeartbeat.php';require_once __DIR__.'/../system/SystemLogger.php';
        if(!$this->db->query("SELECT GET_LOCK('guidanceversion2_exam_booking',5)")->fetchColumn())throw new RuntimeException('Exam booking is busy.');
        try{$this->db->beginTransaction();$q=$this->db->prepare('SELECT * FROM entrance_exam_appointments WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);$deadline=$old?(new AppointmentHeartbeat($this->db))->examDeadline($old):null;
            if(!$old||!$deadline||$deadline>$now||!empty($old['rescheduled_to_id'])||(!in_array($old['status'],['pending','confirmed'],true)&&!($old['status']==='missed'&&!empty($old['reschedule_pending'])))||in_array($old['exam_result'],['passed','failed'],true)||$old['exam_date']||$old['total_score']!==null||$old['exam_score']!==null){$this->db->commit();return ['state'=>'skipped'];}
            $q=$this->db->prepare("SELECT id FROM entrance_exam_appointments WHERE user_id=? AND id<>? AND status IN ('pending','confirmed','awaiting_results','completed') LIMIT 1 FOR UPDATE");$q->execute([$old['user_id'],$id]);$other=$q->fetchColumn();
            $q=$this->db->prepare("SELECT 1 FROM users WHERE id=? AND role='examinee' AND is_active=1 AND COALESCE(archived,0)=0");$q->execute([$old['user_id']]);$eligible=(bool)$q->fetchColumn();
            if($other||!$eligible){if(!$dryRun)$this->db->prepare("UPDATE entrance_exam_appointments SET status='missed',reschedule_pending=0,updated_at=? WHERE id=?")->execute([$now->format('Y-m-d H:i:s'),$id]);$this->db->commit();return ['state'=>'ineligible'];}
            $session=$this->nextSession($now);if($dryRun){$this->db->commit();return ['state'=>$session?'rescheduled':'waiting','session'=>$session];}
            if(!$session){if(empty($old['reschedule_pending'])){
                $this->db->prepare("UPDATE entrance_exam_appointments SET status='missed',reschedule_pending=1,slot_end_at=COALESCE(slot_end_at,?),updated_at=? WHERE id=?")->execute([$deadline->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s'),$id]);
                if(!(new NotificationService($this->db))->notify((int)$old['user_id'],'Waiting for a new exam schedule','Your entrance exam was not recorded as completed before the scheduled end. No open future session is currently available. Your application will be moved automatically when Guidance publishes an available session.','info','entrance_exam_appointments',$id,true,null,null,'exam-reschedule-wait:'.$id))throw new RuntimeException('Exam waiting notice could not be saved.');
                if(!(new SystemLogger($this->db))->info('Unfinished exam is waiting for the next open scheduled session.',null,['appointment_id'=>$id]))throw new RuntimeException('Exam rescheduling audit could not be saved.');
            }$this->db->commit();return ['state'=>'waiting'];}
            $this->db->prepare("UPDATE entrance_exam_appointments SET status='missed',reschedule_pending=0,slot_end_at=COALESCE(slot_end_at,?),updated_at=? WHERE id=?")->execute([$deadline->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s'),$id]);
            $q=$this->db->prepare("INSERT INTO entrance_exam_appointments(user_id,preferred_date,preferred_time,grade_level_applying,previous_school,preferred_program,school_year,slot_end_at,status,confirmed_at,rescheduled_from_id) VALUES(?,?,?,?,?,?,?,?,'confirmed',?,?)");$q->execute([$old['user_id'],$session['date'],$session['value'],$old['grade_level_applying'],$old['previous_school'],$old['preferred_program'],$old['school_year'],$session['end_at'],$now->format('Y-m-d H:i:s'),$id]);$newId=(int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE entrance_exam_appointments SET rescheduled_to_id=? WHERE id=?')->execute([$newId,$id]);SubmissionNotifications::change($this->db,'exam',$newId,'rescheduled');
            if(!(new SystemLogger($this->db))->info('Unfinished exam automatically moved to the next open scheduled session.',null,['previous_appointment_id'=>$id,'appointment_id'=>$newId,'previous_date'=>$old['preferred_date'],'scheduled_date'=>$session['date']]))throw new RuntimeException('Exam rescheduling audit could not be saved.');
            $this->db->commit();return ['state'=>'rescheduled','appointment_id'=>$newId,'session'=>$session];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}finally{$this->db->query("SELECT RELEASE_LOCK('guidanceversion2_exam_booking')");}
    }

    public function book($userId,$date,$time,$school,$program=null,$scheduleId=null){
        $locked=$this->db->query("SELECT GET_LOCK('guidanceversion2_exam_booking',5)")->fetchColumn();if(!$locked)throw new RuntimeException('Booking is busy. Please try again.');
        try{
            $this->db->beginTransaction();
            if(!(new SystemSettings($this->db))->isEntranceExamEnabled())throw new RuntimeException('Entrance exam booking is currently closed.');
            $q=$this->db->prepare("SELECT id FROM entrance_exam_appointments WHERE user_id=? AND (status IN ('pending','confirmed','rescheduled','awaiting_results','completed') OR reschedule_pending=1) FOR UPDATE");$q->execute([$userId]);if($q->fetchColumn())throw new RuntimeException('You already have an active or completed entrance exam.');
            $q=$this->db->prepare("SELECT grade_level_applying FROM users WHERE id=? AND role='examinee' AND is_active=1 AND COALESCE(archived,0)=0");$q->execute([$userId]);$grade=$q->fetchColumn();if(!$grade)throw new RuntimeException('Please complete your applicant grade level first.');
            if(!is_string($school)||trim($school)===''||strlen($school)>100)throw new RuntimeException('Enter your previous school (maximum 100 characters).');
            if($program!==null&&(!is_string($program)||strlen($program)>100))throw new RuntimeException('Preferred program must be at most 100 characters.');
            if(!is_string($time))throw new RuntimeException('Choose a scheduled exam session.');
            if($scheduleId!==null&&!filter_var($scheduleId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))throw new RuntimeException('Choose a valid scheduled exam session.');
            $availability=$this->availability($date);$matches=array_values(array_filter($availability['slots'],static fn($session)=>$session['value']===$time&&($scheduleId===null||$session['schedule_id']===(int)$scheduleId)));
            if(count($matches)!==1)throw new RuntimeException($availability['status']==='available'?'This exam session is unavailable. Choose a scheduled session again.':$availability['message']);
            $selected=$matches[0];$q=$this->db->prepare("INSERT INTO entrance_exam_appointments(user_id,preferred_date,preferred_time,grade_level_applying,previous_school,preferred_program,slot_end_at,status,confirmed_at) VALUES(?,?,?,?,?,?,?,'confirmed',NOW())");$q->execute([$userId,$date,$selected['value'],$grade,trim($school),$program,$selected['end_at']]);$id=$this->db->lastInsertId();
            SubmissionNotifications::appointment($this->db,'exam',(int)$id);$this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        finally{$this->db->query("SELECT RELEASE_LOCK('guidanceversion2_exam_booking')");}
    }
}
