<?php
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/core/session.php';
require_once __DIR__.'/../classes/reporting/ReportApproval.php';require_once __DIR__.'/../classes/reporting/ApprovalBoardRenderer.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('Vary: Cookie');header('X-Content-Type-Options: nosniff');
try {
    if(empty($_SESSION['user_id'])){http_response_code(401);throw new RuntimeException('Please sign in again to view approvals.');}
    $db=(new Database)->getConnection();$uid=(int)$_SESSION['user_id'];
    try{$actor=OfficePolicy::actor($db,$uid);}catch(RuntimeException $e){http_response_code(403);throw $e;}$role=$actor['role'];
    if(!in_array($role,['admin','super_admin','guidance_advocate'],true)){http_response_code(403);throw new RuntimeException('You do not have access to report approvals.');}
    $method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');throw new RuntimeException('Use the approval controls to update this page.');}
    $input=$method==='POST'?$_POST:$_GET;$page=filter_var($input['p']??1,FILTER_VALIDATE_INT);if(!$page||$page<1)throw new InvalidArgumentException('Choose a valid approval page.');
    $service=new ReportApproval($db);$message='';
    if($method==='POST'){
        if(!in_array($role,['admin','super_admin'],true)){http_response_code(403);throw new RuntimeException('Only the counselor/admin can decide requests.');}
        if(!isset($_SESSION['office_approval_csrf'])||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['office_approval_csrf'],$_POST['csrf'])){http_response_code(403);throw new RuntimeException('Refresh the approval page before saving a decision.');}
        $id=filter_var($_POST['approval_id']??null,FILTER_VALIDATE_INT);$decision=$_POST['decision']??null;$note=$_POST['note']??'';
        if(!$id||$id<1||!is_string($decision)||!in_array($decision,['approved','rejected','revoked'],true)||!is_string($note)||mb_strlen($note)>500)throw new InvalidArgumentException('Choose a valid request, decision and note.');
        $service->decide($uid,(int)$id,$decision,$note);$message='Request #'.$id.' '.$decision.'.';
    }
    $_SESSION['office_approval_csrf']=$_SESSION['office_approval_csrf']??bin2hex(random_bytes(32));
    $csrf=$_SESSION['office_approval_csrf'];$listing=$service->listing($uid,$role,(int)$page);$revision=ApprovalBoardRenderer::revision($listing,$role);
    $unchanged=$method==='GET'&&is_string($_GET['since']??null)&&hash_equals($revision,$_GET['since']);
    $result=['ok'=>true,'page'=>$listing['page'],'pages'=>$listing['pages'],'total'=>$listing['total'],'revision'=>$revision,'csrf'=>$csrf,'unchanged'=>$unchanged];
    if(!$unchanged)$result['html']=ApprovalBoardRenderer::html($listing,$role,$csrf);
    if($message!=='')$result['message']=$message;
    session_write_close();echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?422:($e instanceof PDOException?500:409));
    echo json_encode(['error'=>$e instanceof PDOException?'Approvals could not be loaded. Please try again.':$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
}
