<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../config/database.php';require __DIR__.'/../classes/AppointmentHeartbeat.php';
$service=new AppointmentHeartbeat((new Database)->getConnection());$service->setLogging(false);echo json_encode($service->run(true),JSON_PRETTY_PRINT).PHP_EOL;
