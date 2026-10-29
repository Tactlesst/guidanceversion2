<?php
require_once __DIR__.'/../includes/core/session.php';checkLogin();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){http_response_code(403);header('Content-Type: text/html; charset=utf-8');echo '<p>Academic assignments and official student IDs are managed by the Guidance Office.</p><a href="../dashboard/layout.php?page=profile">View your profile</a>';exit;}
header('Location: ../dashboard/layout.php?page=profile');exit;
