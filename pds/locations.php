<?php
require_once __DIR__.'/../includes/core/session.php';
header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');
if(!isLoggedIn()){http_response_code(401);echo '{"error":"Please sign in again."}';exit;}
require_once __DIR__.'/../includes/pds/address.php';
try{
    $level=$_GET['level']??'';if(!is_string($level)||!in_array($level,['region','province','city','barangay'],true))throw new InvalidArgumentException();
    $scope=[];foreach(['region','province','city'] as $key){$v=$_GET[$key]??'';if(!is_string($v)||!preg_match('/^(?:\d{10}|__none__)?$/D',$v))throw new InvalidArgumentException();$scope[$key]=$v;}
    echo json_encode(['items'=>PDSAddress::choices($level,$scope),'version'=>'2026-10-12'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(400);echo '{"error":"Choose a valid location."}';}
catch(Throwable $e){http_response_code(503);echo '{"error":"Location choices are unavailable. Your saved address is preserved."}';}
