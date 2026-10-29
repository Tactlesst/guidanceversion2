<?php
declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require QA_ROOT.'/config/database.php';
require QA_ROOT.'/classes/counseling/CounselingAppointment.php';
require QA_ROOT.'/classes/examinations/ExamBooking.php';
$db=(new Database)->getConnection();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if(($argv[1]??'')==='child'){
 $schema=$argv[2];if(!preg_match('/^guidancev2_qa_[a-f0-9]{12}$/D',$schema))throw new RuntimeException('Invalid isolated schema.');
 $owner=json_decode(file_get_contents(QA_CASE_DIR.'/race-owner.json'),true);if(($owner['schema']??'')!==$schema)throw new RuntimeException('Fixture ownership mismatch.');
 $db->exec('USE `'.$schema.'`');$uid=(int)$argv[4];$case=$argv[3];$date=$argv[5];$time=$argv[6];$start=(float)$argv[7];while(microtime(true)<$start)usleep(1000);
 try{if($case==='exam'){$id=(new ExamBooking($db))->book($uid,$date,$time,'Synthetic school');}else{$a=new CounselingAppointment($db);$a->user_id=$uid;$a->appointment_date=$date;$a->appointment_time=$time;$a->concern_type='personal';$a->concern_description='Synthetic concurrency fixture';$a->urgency_level='normal';$id=$a->createWithDailyLimit();}echo json_encode(['saved'=>(bool)$id]);}
 catch(Throwable $e){echo json_encode(['saved'=>false,'error'=>$e->getMessage()]);}exit;
}
$schema='guidancev2_qa_'.bin2hex(random_bytes(6));$created=false;$checks=[];
function good(string $n,bool $v):void{global $checks;if(!$v)throw new RuntimeException($n);$checks[]=$n;}
$tables=['users','student_profiles','notifications','email_outbox','system_logs','system_settings','counseling_appointments','entrance_exam_appointments','entrance_exam_daily_limits','daily_booking_limits','schedules','holidays','school_closures'];$defs=[];
foreach($tables as $t){$r=$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_ASSOC);$lines=array_values(array_filter(explode("\n",$r['Create Table']),static fn($line)=>!preg_match('/^\s*CONSTRAINT\b/i',$line)));$defs[]=preg_replace('/,\n\)/',"\n)",implode("\n",$lines));}
try{
 $db->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;file_put_contents(QA_CASE_DIR.'/race-owner.json',json_encode(['schema'=>$schema]));$db->exec('USE `'.$schema.'`');foreach($defs as $sql)$db->exec($sql);
 $db->exec("INSERT INTO users(id,role,first_name,last_name,email,grade_level_applying,is_active,archived) VALUES(98001,'student','Synthetic','One','one@example.invalid','Grade 7',1,0),(98002,'student','Synthetic','Two','two@example.invalid','Grade 7',1,0)");
 $db->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES('entrance_exam_enabled','1')");
 $d=new DateTimeImmutable('tomorrow',new DateTimeZone('Asia/Manila'));while($d->format('N')==='7')$d=$d->modify('+1 day');$date=$d->format('Y-m-d');
 foreach(['counseling-slot','counseling-limit','exam'] as $case){
  foreach(['counseling_appointments','entrance_exam_appointments','notifications','email_outbox','daily_booking_limits','entrance_exam_daily_limits','schedules'] as $t)$db->exec('DELETE FROM '.$t);
  $db->exec("UPDATE users SET role='".($case==='exam'?'examinee':'student')."'");
  if($case==='exam'){$db->prepare("INSERT INTO schedules(title,event_type,start_datetime,end_datetime,created_by,is_active) VALUES('Synthetic exam','entrance_exam',?,?,98001,1)")->execute([$date.' 08:00:00',$date.' 12:00:00']);$db->prepare("INSERT INTO entrance_exam_daily_limits(date,max_appointments,exam_time,exam_end_time,set_by) VALUES(?,1,'08:00:00','12:00:00',98001)")->execute([$date]);}
  else $db->prepare('INSERT INTO daily_booking_limits(date,max_appointments,set_by) VALUES(?,?,98001)')->execute([$date,$case==='counseling-limit'?1:4]);
  $start=microtime(true)+.4;$processes=[];
  foreach([98001,98002] as $index=>$uid){$time=$case==='counseling-limit'&&$index===1?'09:00:00':'08:00:00';$cmd=[PHP_BINARY,__FILE__,'child',$schema,$case,(string)$uid,$date,$time,(string)$start];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('Could not start concurrency fixture.');fclose($pipes[0]);$processes[]=[$proc,$pipes];}
  $results=[];foreach($processes as [$proc,$pipes]){$body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0||$err!=='')throw new RuntimeException('Concurrency child failed: '.$err);$results[]=json_decode($body,true,512,JSON_THROW_ON_ERROR);}
  good($case.': exactly one competing request saves',count(array_filter($results,static fn($r)=>$r['saved']))===1);$table=$case==='exam'?'entrance_exam_appointments':'counseling_appointments';good($case.': final stored count stays within slot/capacity limit',(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===1);
 }
 file_put_contents(QA_RESULTS.'/QA-Concurrent-Booking-Checks.json',json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." true concurrent booking checks passed in an isolated schema.\n";
}finally{
 if($created&&preg_match('/^guidancev2_qa_[a-f0-9]{12}$/D',$schema)){$db->exec('USE information_schema');$db->exec('DROP DATABASE `'.$schema.'`');if(is_file(QA_CASE_DIR.'/race-owner.json'))unlink(QA_CASE_DIR.'/race-owner.json');}
}
