<?php
require_once __DIR__.'/ExamBooking.php';require_once __DIR__.'/SystemSettings.php';
final class ExamineePortal {
 private PDO $db;
 public function __construct(PDO $db){$this->db=$db;}
 public function applications(int $uid):array{$q=$this->db->prepare('SELECT * FROM entrance_exam_appointments WHERE user_id=? ORDER BY created_at DESC,id DESC');$q->execute([$uid]);return $q->fetchAll(PDO::FETCH_ASSOC);}
 public function cancel(int $uid,int $id):bool {
  try{$this->db->beginTransaction();$q=$this->db->prepare('SELECT id,user_id,status,preferred_date FROM entrance_exam_appointments WHERE id=? AND user_id=? FOR UPDATE');$q->execute([$id,$uid]);$r=$q->fetch(PDO::FETCH_ASSOC);
   if(!$r||!in_array($r['status'],['pending','confirmed'],true)||$r['preferred_date']<date('Y-m-d'))throw new RuntimeException('This application can no longer be cancelled online. Contact the Guidance Office.');
   $this->db->prepare("UPDATE entrance_exam_appointments SET status='cancelled',updated_at=NOW() WHERE id=? AND user_id=?")->execute([$id,$uid]);SubmissionNotifications::change($this->db,'exam',$id,'cancelled');$this->db->commit();return true;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public static function active(array $rows):?array{foreach($rows as $r)if(in_array($r['status'],['pending','confirmed','awaiting_results','completed','rescheduled'],true))return $r;return null;}
 public static function label(string $s):string{return ['pending'=>'Pending confirmation','confirmed'=>'Confirmed','rescheduled'=>'Rescheduled','awaiting_results'=>'Attended · awaiting results','completed'=>'Results released','missed'=>'Missed / absent','cancelled'=>'Cancelled'][$s]??ucwords(str_replace('_',' ',$s));}
 public static function description(string $s):string{return ['pending'=>'Your booking is awaiting confirmation. Check your notifications for updates.','confirmed'=>'Your exam schedule is reserved. Arrive on time and bring the requirements provided by the Guidance Office.','rescheduled'=>'Your appointment has a revised schedule. Check the date and time below.','awaiting_results'=>'Your attendance has been recorded. The Guidance Office will notify you when results are released.','completed'=>'Your saved exam results are available in Exam results.','missed'=>'Attendance was not recorded before the booked exam session ended. Contact the Guidance Office if this is incorrect, or choose another available date.','cancelled'=>'This booking was cancelled. You can choose another available exam date.'][$s]??'Contact the Guidance Office for details.';}
}
