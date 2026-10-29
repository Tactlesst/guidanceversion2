<?php
require_once __DIR__.'/../includes/core/session.php';header('Content-Type: application/json');header('Cache-Control: no-store');if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['error'=>'Please sign in.']);exit;}http_response_code(410);echo json_encode(['error'=>'This legacy endpoint has been replaced by the protected Counseling workspace.']);
