<?php
require_once '../config/database.php';
require_once '../includes/core/session.php';
checkLogin();
header('Content-Type: application/json');
if (($_SESSION['role']??'')!=='examinee') { http_response_code(403); echo json_encode(['slots'=>[]]); exit; }
require_once '../classes/examinations/ExamBooking.php';
try { $slots=(new ExamBooking((new Database())->getConnection()))->slots($_GET['date']??''); echo json_encode(['slots'=>$slots]); }
catch (Throwable $e) { http_response_code(503); echo json_encode(['slots'=>[],'error'=>'Availability could not be checked. Please try again.']); }
