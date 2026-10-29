<?php
require_once __DIR__.'/../notifications/NotificationService.php';
/** Deadline checks never infer attendance, results, cancellation or completion. */
class AppointmentHeartbeat {
 private PDO $db;private bool $logging=true;private $clock;
 public function __construct(PDO $db,?callable $clock=null){$this->db=$db;$this->clock=$clock??static fn()=>new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));}
 public function setLogging($enabled=true):void{$this->logging=(bool)$enabled;}
 private static function date(?string $date):?DateTimeImmutable {if(!$date)return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));return $d&&$d->format('Y-m-d')===$date?$d:null;}
 public function examDeadline(array $row):?DateTimeImmutable {
  if(!empty($row['slot_end_at'])){$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['slot_end_at'],new DateTimeZone('Asia/Manila'));if($d&&$d->format('Y-m-d H:i:s')===$row['slot_end_at'])return $d;}
  $day=self::date($row['preferred_date']??null);$time=$row['preferred_time']??null;if(!$day||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D',$time))return null;
  $start=new DateTimeImmutable($row['preferred_date'].' '.$time,new DateTimeZone('Asia/Manila'));$end=$start->modify('+2 hours');
  $q=$this->db->prepare('SELECT exam_end_time FROM entrance_exam_daily_limits WHERE date=?');$q->execute([$row['preferred_date']]);$configured=$q->fetchColumn();
  if(is_string($configured)&&preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$configured)){$limit=new DateTimeImmutable($row['preferred_date'].' '.$configured,new DateTimeZone('Asia/Manila'));if($limit>$start&&$limit<$end)$end=$limit;}
  return $end;
 }
 private function due(string $kind,array $r,DateTimeImmutable $now):?DateTimeImmutable {
  if(!in_array($r['status'],['pending','confirmed','rescheduled','follow_up_scheduled'],true)&&!($kind==='exam'&&$r['status']==='missed'&&!empty($r['reschedule_pending'])))return null;
  if($kind==='exam'){if(in_array($r['exam_result'],['passed','failed'],true)||$r['exam_date']||$r['total_score']!==null||$r['exam_score']!==null)return null;$deadline=$this->examDeadline($r);}
  else{$day=self::date($r['appointment_date']??null);$deadline=$day?$day->modify('+1 day'):null;$q=$this->db->prepare('SELECT 1 FROM counseling_forms WHERE appointment_id=? LIMIT 1');$q->execute([$r['id']]);if($q->fetchColumn())return null;}
  return $deadline&&$deadline<=$now?$deadline:null;
 }
 public function run(bool $dryRun=false):array {
  $now=($this->clock)();$today=$now->format('Y-m-d');$result=['counseling_missed'=>0,'counseling_completed'=>0,'exam_missed'=>0,'exam_completed'=>0,'exam_rescheduled'=>0,'exam_waiting'=>0,'errors'=>[],'dry_run'=>$dryRun];
  $lock='guidance_appointment_check_'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,25);$q=$this->db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if(!(int)$q->fetchColumn())return $result;
  try{
   $started=$this->db->query("SELECT updated_at FROM system_settings WHERE setting_key='appointment_automation_started'")->fetchColumn();
   $autoStarted=$this->db->query("SELECT updated_at FROM system_settings WHERE setting_key='exam_auto_reschedule_started' AND setting_value=1")->fetchColumn();
   foreach(['counseling'=>'counseling_appointments','exam'=>'entrance_exam_appointments'] as $kind=>$table){$date=$kind==='exam'?'preferred_date':'appointment_date';$q=$this->db->prepare("SELECT * FROM $table WHERE (status IN ('pending','confirmed','rescheduled','follow_up_scheduled')".(($kind==='exam'&&$autoStarted)?" OR (status='missed' AND reschedule_pending=1)":"").") AND $date<=? AND ".($kind==='exam'?"exam_date IS NULL AND total_score IS NULL AND exam_score IS NULL AND (exam_result IS NULL OR exam_result NOT IN ('passed','failed'))":"$date < ? AND NOT EXISTS(SELECT 1 FROM counseling_forms f WHERE f.appointment_id=$table.id)")." ORDER BY $date,".($kind==='exam'?'preferred_time,':'')."id");$q->execute($kind==='exam'?[$today]:[$today,$today]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row){if(!($due=$this->due($kind,$row,$now)))continue;
     if($kind==='exam'&&$autoStarted&&$due->format('Y-m-d H:i:s')>=$autoStarted){try{require_once __DIR__.'/../examinations/ExamBooking.php';$moved=(new ExamBooking($this->db))->rescheduleUnfinished((int)$row['id'],$now,$dryRun);if($moved['state']==='rescheduled')$result['exam_rescheduled']++;elseif($moved['state']==='waiting')$result['exam_waiting']++;elseif($moved['state']==='ineligible')$result['exam_missed']++;}catch(Throwable $e){$result['errors'][]='An unfinished exam could not be rescheduled. It will be retried.';}continue;}
     if($dryRun){$result[$kind==='exam'?'exam_missed':'counseling_missed']++;continue;}
     try{$this->db->beginTransaction();$q=$this->db->prepare('SELECT * FROM '.$table.' WHERE id=? FOR UPDATE');$q->execute([$row['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current||!($deadline=$this->due($kind,$current,$now))){$this->db->commit();continue;}
      $q=$this->db->prepare("UPDATE $table SET status='missed',updated_at=?".($kind==='exam'?',slot_end_at=COALESCE(slot_end_at,?)':'').' WHERE id=?');$q->execute($kind==='exam'?[$now->format('Y-m-d H:i:s'),$deadline->format('Y-m-d H:i:s'),$row['id']]:[$now->format('Y-m-d H:i:s'),$row['id']]);
      $label=$kind==='exam'?'entrance exam':'counseling appointment';$scheduled=$kind==='exam'?$current['preferred_date']:$current['appointment_date'];$sendEmail=$started&&$deadline->format('Y-m-d H:i:s')>=$started;
      if(!(new NotificationService($this->db))->notify((int)$current['user_id'],$kind==='exam'?'Entrance exam missed':'Counseling appointment missed','Your '.$label.' on '.date('M j, Y',strtotime($scheduled)).' was marked as missed because attendance was not recorded before its deadline. Open your appointments for the next available options.',$kind==='exam'?'exam_missed':'appointment_missed',$table,(int)$row['id'],(bool)$sendEmail,null,null,'automatic-missed:'.$table.':'.$row['id']))throw new RuntimeException('The missed appointment notice could not be saved.');
      $this->db->commit();$result[$kind==='exam'?'exam_missed':'counseling_missed']++;
     }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();$result['errors'][]='An appointment could not be checked. It will be retried.';}
    }
   }
   if(!$dryRun)$this->db->exec("INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES('appointment_worker_last_run',1,NOW()) ON DUPLICATE KEY UPDATE setting_value=1,updated_at=NOW()");
   if($this->logging&&!$dryRun&&($result['counseling_missed']+$result['exam_missed']>0))error_log('Guidance appointment check: '.($result['counseling_missed']+$result['exam_missed']).' missed statuses recorded.');
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();$result['errors'][]='Appointment checking is temporarily unavailable.';}
  finally{$this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}return $result;
 }
 public function markMissedAppointments():array{$r=$this->run();return ['affected_count'=>$r['counseling_missed']+$r['exam_missed'],'message'=>'Appointment deadlines checked.','errors'=>$r['errors']];}
 public function getAppointmentStatus($type,$id):?array {if(!in_array($type,['exam','counseling'],true))return null;$q=$this->db->prepare('SELECT * FROM '.($type==='exam'?'entrance_exam_appointments':'counseling_appointments').' WHERE id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
 public static function shouldBeMissed($date):bool{$d=self::date(is_string($date)?$date:null);return $d&&$d<new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));}
 public static function shouldBeCompleted($date):bool{return false;}
}
