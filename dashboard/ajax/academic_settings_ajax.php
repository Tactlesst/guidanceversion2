<?php
// Academic settings use the protected dashboard forms and their shared session.
require_once __DIR__.'/../../includes/core/session.php';
header('Cache-Control: private, no-store');
if(!isLoggedIn()){http_response_code(401);header('Content-Type: application/json');echo json_encode(['error'=>'Please sign in.']);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){header('Location: ../layout.php?page=academic_settings');exit;}
http_response_code(410);header('Content-Type: application/json');echo json_encode(['error'=>'Use the Academic Settings controls in the dashboard.']);
