<?php
require_once __DIR__.'/NotificationService.php';
final class SubmissionNotifications {
    private static function receipt(NotificationService $service,int $uid,string $title,string $message,string $table,int $id,string $key,string $type='success'):void {if(!$service->notify($uid,$title,$message,$type,$table,$id,true,null,null,$key))throw new RuntimeException('The submission notification could not be recorded. Please try again.');}
    public static function pds(PDO $db,int $uid,int $version,string $year,bool $review):void {
        $s=new NotificationService($db);$key='pds_version:'.$version;$title=$review?'Annual PDS submitted':'PDS saved';$message=($review?'Your annual PDS review was submitted':'Your personal data sheet was saved').($year!==''?' for school year '.$year:'').'.';self::receipt($s,$uid,$title,$message,'pds_versions',$version,$key);
        $s->staff(['admin'],$review?'Annual PDS ready for review':'Student PDS updated','A student personal data sheet '.($review?'was submitted':'was saved').($year!==''?' for school year '.$year:'').'. Sign in to review the record.','pds_versions',$version,$key);
    }
    public static function survey(PDO $db,int $uid,string $kind,int $id,string $year,bool $update):void {
        $map=['mi'=>['multiple_intelligence_survey','Multiple Intelligence'],'ls'=>['learning_style_inventory','Learning Style'],'ls_intermediate'=>['learning_style_inventory_intermediate','Learning Style (Intermediate)']];if(!isset($map[$kind]))throw new InvalidArgumentException('Invalid survey notification.');[$table,$label]=$map[$kind];$key='survey:'.$kind.':'.$id.':'.bin2hex(random_bytes(12));$s=new NotificationService($db);
        self::receipt($s,$uid,$update?'Survey updated':'Survey submitted','Your '.$label.' survey '.($update?'was updated':'was submitted').' for school year '.$year.'.',$table,$id,$key);
        $s->staff(['admin','guidance_advocate'],'Student survey '.($update?'updated':'submitted'),'A '.$label.' survey is ready for review for school year '.$year.'.',$table,$id,$key);
    }
    public static function appointment(PDO $db,string $kind,int $id):void {
        $exam=$kind==='exam';$table=$exam?'entrance_exam_appointments':'counseling_appointments';$q=$db->prepare('SELECT user_id,'.($exam?'preferred_date AS day,preferred_time AS time':'appointment_date AS day,appointment_time AS time').' FROM '.$table.' WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Appointment unavailable.');
        $label=$exam?'entrance exam':'counseling';$when=date('M j, Y',strtotime($r['day'])).' at '.date('g:i A',strtotime($r['time']));$key=($exam?'exam':'counseling').'_booking:'.$id;$s=new NotificationService($db);
        self::receipt($s,(int)$r['user_id'],'Appointment confirmed','Your '.$label.' appointment is confirmed for '.$when.'.',$table,$id,$key,'appointment_confirmed');$s->staff(['admin','guidance_advocate'],'New '.($exam?'exam':'counseling').' appointment','A new '.$label.' appointment is scheduled for '.$when.'. Sign in to view the record.',$table,$id,$key);
    }
    public static function change(PDO $db,string $kind,int $id,string $event):void {
        $exam=$kind==='exam';$table=$exam?'entrance_exam_appointments':'counseling_appointments';$q=$db->prepare('SELECT user_id,'.($exam?'preferred_date AS day,preferred_time AS time':'appointment_date AS day,appointment_time AS time').' FROM '.$table.' WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return;
        $labels=['confirmed'=>'confirmed','cancelled'=>'cancelled','completed'=>'completed','missed'=>'marked as missed','in_progress'=>'started','rescheduled'=>'rescheduled','followup'=>'scheduled for follow-up','awaiting_results'=>'recorded as attended'];$label=$exam?'entrance exam':'counseling';$title=$event==='followup'?'Follow-up scheduled':'Appointment update';$message='Your '.$label.' appointment was '.($labels[$event]??'updated').'.';if($r['day']&&$r['time'])$message.=' Schedule: '.date('M j, Y',strtotime($r['day'])).' at '.date('g:i A',strtotime($r['time'])).'.';
        $key=$kind.':'.$id.':'.$event.':'.bin2hex(random_bytes(12));$service=new NotificationService($db);$type=in_array('appointment_'.$event,Notification::TYPES,true)?'appointment_'.$event:'info';self::receipt($service,(int)$r['user_id'],$title,$message,$table,$id,$key,$type);
        if($event!=='in_progress')$service->staff(['admin','guidance_advocate'],'Appointment '.($event==='followup'?'follow-up':'updated'),'A '.$label.' appointment was '.($labels[$event]??'updated').'. Sign in to review its schedule and status.',$table,$id,$key);
    }
    public static function assigned(PDO $db,int $id,int $assignee):void {
        $q=$db->prepare('SELECT user_id FROM counseling_appointments WHERE id=?');$q->execute([$id]);$owner=(int)$q->fetchColumn();if(!$owner)return;$s=new NotificationService($db);$key='assignment:'.$id.':'.bin2hex(random_bytes(12));self::receipt($s,$owner,'Counselor assigned','A counselor has been assigned to your appointment. Sign in to view the details.','counseling_appointments',$id,$key);self::receipt($s,$assignee,'New counseling assignment','You have been assigned a counseling appointment. Sign in to review its schedule.','counseling_appointments',$id,$key,'advocate_assignment');
    }
}
