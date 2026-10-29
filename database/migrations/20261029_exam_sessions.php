<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../../config/database.php';$db=(new Database)->getConnection();
foreach(['rescheduled_from_id'=>'INT NULL','rescheduled_to_id'=>'INT NULL','reschedule_pending'=>'TINYINT(1) NOT NULL DEFAULT 0'] as $name=>$type){$q=$db->prepare('SHOW COLUMNS FROM entrance_exam_appointments LIKE ?');$q->execute([$name]);if(!$q->fetch())$db->exec('ALTER TABLE entrance_exam_appointments ADD COLUMN '.$name.' '.$type);}
$indexes=array_column($db->query('SHOW INDEX FROM entrance_exam_appointments')->fetchAll(PDO::FETCH_ASSOC),'Key_name');if(!in_array('one_replacement_per_exam',$indexes,true))$db->exec('ALTER TABLE entrance_exam_appointments ADD UNIQUE KEY one_replacement_per_exam(rescheduled_from_id)');
if(in_array('--activate',$argv,true)){$db->exec("INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES('exam_auto_reschedule_started',1,NOW()) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)");echo "Automatic rescheduling enabled for deadlines from activation onward.\n";}
echo "Exam-session history links are ready. Existing attempts and scores were preserved.\n";
