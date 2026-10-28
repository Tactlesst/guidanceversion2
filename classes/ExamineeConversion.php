<?php
require_once __DIR__.'/NotificationService.php';require_once __DIR__.'/SystemLogger.php';require_once __DIR__.'/../includes/student_record_support.php';
final class ExamineeConversion {
 private PDO $db;
 public function __construct(PDO $db){$this->db=$db;}
 public function convert(int $actor,int $id,array $input):void {
  $studentId=trim((string)($input['student_id']??''));if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/D',$studentId))throw new InvalidArgumentException('Enter the official student ID, using letters, numbers, dots, underscores or hyphens (up to 50 characters).');
  $lock='guidance_student_conversion';$q=$this->db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if(!$q->fetchColumn())throw new RuntimeException('Account conversion is busy. Please try again.');
  try{$this->db->beginTransaction();$q=$this->db->prepare("SELECT role FROM users WHERE id=? AND is_active=1 AND COALESCE(archived,0)=0 FOR UPDATE");$q->execute([$actor]);if($q->fetchColumn()!=='super_admin')throw new RuntimeException('Only the superadmin can convert an examinee.');
   $q=$this->db->prepare('SELECT id,role,is_active,archived FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||$u['role']!=='examinee'||!(int)$u['is_active']||(int)$u['archived'])throw new RuntimeException('Choose an active examinee account.');
   $q=$this->db->prepare("SELECT id FROM entrance_exam_appointments WHERE user_id=? AND status='completed' AND exam_result='passed' ORDER BY exam_date DESC,id DESC LIMIT 1 FOR UPDATE");$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('A completed examination with an explicitly recorded Passed result is required. Have the Guidance Office review the result first.');
   $q=$this->db->prepare('SELECT id FROM student_profiles WHERE UPPER(TRIM(student_id))=UPPER(?) AND user_id<>? LIMIT 1');$q->execute([$studentId,$id]);if($q->fetchColumn())throw new RuntimeException('This student ID already belongs to another account.');
   $this->db->prepare("UPDATE users SET role='student',position=NULL,auth_version=auth_version+1 WHERE id=?")->execute([$id]);
   $q=$this->db->prepare('SELECT id FROM student_profiles WHERE user_id=?');$q->execute([$id]);if(!$q->fetchColumn())$this->db->prepare('INSERT INTO student_profiles(user_id,student_id) VALUES(?,?)')->execute([$id,$studentId]);
   saveStudentAcademicAssignment($this->db,array_merge($input,['user_id'=>$id]));$this->db->prepare('UPDATE student_profiles SET student_id=? WHERE user_id=?')->execute([$studentId,$id]);
   $this->db->prepare('UPDATE account_verification_codes SET revoked_at=NOW() WHERE user_id=? AND used_at IS NULL AND revoked_at IS NULL')->execute([$id]);$this->db->prepare("UPDATE email_outbox SET status='failed',body_html='',last_error='Account role changed. Request a new verification code.' WHERE user_id=? AND recipient_policy='verification' AND status='pending'")->execute([$id]);
   if(!(new NotificationService($this->db))->notify($id,'Your student account is ready','The school has converted your examinee account to a student account. Sign in with your official student ID or existing email and the same password. Your exam and application history have been preserved.','success',null,null,true,null,null,'converted-student:'.$id))throw new RuntimeException('The conversion notification could not be saved.');
   (new SystemLogger($this->db))->info('Converted examinee account to student with verified academic placement',$actor);$this->db->commit();
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&($e->errorInfo[1]??0)===1062)throw new RuntimeException('This student ID is already in use.');throw $e;}finally{$this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
 }
}
