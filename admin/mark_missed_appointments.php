<?php
require_once __DIR__.'/../includes/core/session.php';checkLogin();checkRole(['admin','guidance_advocate']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){require __DIR__.'/../includes/appointments/heartbeat_control.php';exit;}
header('Location: ../dashboard/layout.php?page=system_settings&section=delivery');exit;
