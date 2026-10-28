<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../config/database.php';require __DIR__.'/../classes/AppointmentHeartbeat.php';
try{$result=(new AppointmentHeartbeat((new Database)->getConnection()))->run(in_array('--dry-run',$argv,true));echo json_encode($result).PHP_EOL;exit(empty($result['errors'])?0:1);}catch(Throwable $e){fwrite(STDERR,"Appointment checking could not run.\n");exit(1);}
