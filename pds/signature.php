<?php
require_once __DIR__.'/../includes/core/session.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
if(!isLoggedIn()){http_response_code(401);exit;}
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/pds/signature_access.php';
try{
    $key=$_GET['key']??'';if(!is_string($key))throw new RuntimeException();
    $image=PDSSignatureAccess::read((new Database)->getConnection(),(int)$_SESSION['user_id'],$key);
    header('Content-Type: '.$image['mime']);header('Content-Length: '.strlen($image['bytes']));echo $image['bytes'];
}catch(Throwable $e){http_response_code(404);}
