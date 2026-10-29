<?php
require_once __DIR__.'/../includes/core/session.php';checkLogin();checkRole(['admin','guidance_advocate']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){http_response_code(410);header('Content-Type: application/json');echo json_encode(['error'=>'Use the current protected workspace to perform this action.']);exit;}
header('Location: ../dashboard/layout.php?page=manage_counseling');exit;
