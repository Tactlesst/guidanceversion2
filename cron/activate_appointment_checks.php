<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../config/database.php';require __DIR__.'/../classes/AppointmentHeartbeat.php';
$db=(new Database)->getConnection();$db->exec("INSERT IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('appointment_automation_started',1,NOW())");
$service=new AppointmentHeartbeat($db);$service->setLogging(false);$result=$service->run();echo json_encode($result).PHP_EOL;exit(empty($result['errors'])?0:1);
