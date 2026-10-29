<?php
require_once __DIR__.'/../../config/database.php';require_once __DIR__.'/../core/session.php';require_once __DIR__.'/../../classes/identity/OfficePolicy.php';require_once __DIR__.'/../../classes/scheduling/AppointmentHeartbeat.php';
header('Content-Type: application/json');header('Cache-Control: private, no-store');
try{
 if(empty($_SESSION['user_id'])){http_response_code(401);throw new RuntimeException('Please sign in again.');}
 $db=(new Database)->getConnection();$actor=OfficePolicy::actor($db,(int)$_SESSION['user_id']);if(!in_array($actor['role'],['admin','super_admin'],true)){http_response_code(403);throw new RuntimeException('Only the counselor/admin can check appointment deadlines.');}
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);throw new RuntimeException('Automatic checking is already scheduled. Use a protected POST request for an extra check.');}
 $payload=$_POST;if(!$payload)$payload=json_decode(file_get_contents('php://input'),true)?:[];
 if(!is_string($payload['csrf']??null)||!isset($_SESSION['office_settings_csrf'])||!hash_equals($_SESSION['office_settings_csrf'],$payload['csrf'])){http_response_code(403);throw new RuntimeException('Refresh System Settings before running a manual check.');}
 $result=(new AppointmentHeartbeat($db))->markMissedAppointments();echo json_encode(['success'=>empty($result['errors'])]+$result);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['success'=>false,'error'=>$e instanceof PDOException?'Appointment checking could not be completed.':$e->getMessage()]);}exit;
