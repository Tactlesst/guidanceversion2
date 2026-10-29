<?php
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/core/session.php';require_once __DIR__.'/../classes/reporting/ReportApproval.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_SESSION['user_id'])){http_response_code(403);throw new RuntimeException('Please sign in and use the approval controls.');}
    if(!is_string($_POST['csrf']??null)||!isset($_SESSION['office_approval_csrf'])||!hash_equals($_SESSION['office_approval_csrf'],$_POST['csrf'])){http_response_code(403);throw new RuntimeException('Refresh this page before requesting approval.');}
    $db=(new Database)->getConnection();$uid=(int)$_SESSION['user_id'];$actor=OfficePolicy::actor($db,$uid);
    if($actor['role']!=='guidance_advocate'){http_response_code(403);throw new RuntimeException('Use the advocate account to request permission.');}
    $resource=is_string($_POST['resource']??null)?$_POST['resource']:'';$format=is_string($_POST['format']??null)?$_POST['format']:'print';$scope=json_decode($_POST['scope']??'',true,16,JSON_THROW_ON_ERROR);
    if(!is_array($scope))throw new InvalidArgumentException('Check the report options.');$scope=ReportApproval::scope($resource,$scope);$service=new ReportApproval($db);$row=$service->approved($uid,$resource,$format,$scope);
    if(!$row){$row=$service->request($uid,$resource,$format,$scope);if($row['status']==='approved'){if(!in_array($resource,['assessment','pds_version'],true))$service->consume($uid,(int)$row['id'],$resource,$format,$scope);echo json_encode(['ok'=>true,'approval_id'=>(int)$row['id']]);exit;}http_response_code(202);echo json_encode(['approval_required'=>true,'request_id'=>(int)$row['id'],'message'=>'Approval request #'.$row['id'].' is awaiting the counselor/admin. After approval, return here with the same options and print again.']);exit;}
    // Assessment document generation consumes this approval at its protected HTML endpoint.
    if(!in_array($resource,['assessment','pds_version'],true))$service->consume($uid,(int)$row['id'],$resource,$format,$scope);
    echo json_encode(['ok'=>true,'approval_id'=>(int)$row['id']]);
}catch(ApprovalRequestBlocked $e){http_response_code(429);header('Retry-After: '.max(1,$e->retryAfter));echo json_encode(['error'=>$e->getMessage(),'retry_after'=>$e->retryAfter,'pending_id'=>$e->pendingId]);}catch(Throwable $e){if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException||$e instanceof JsonException?422:403);echo json_encode(['error'=>$e instanceof PDOException?'The approval request could not be saved.':$e->getMessage()]);}
