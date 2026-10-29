<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('This worker runs from the command line.');}
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../classes/notifications/EmailQueueWorker.php';
try{$options=getopt('',['limit::','id::']);$result=(new EmailQueueWorker((new Database)->getConnection()))->process((int)($options['limit']??10),isset($options['id'])?(int)$options['id']:null);echo json_encode($result).PHP_EOL;exit(0);}catch(Throwable $e){error_log('The guidance email worker could not complete its run.');echo "Email queue unavailable.\n";exit(1);}
