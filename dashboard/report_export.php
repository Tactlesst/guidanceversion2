<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';
require_once __DIR__.'/../includes/reporting/SchoolReportService.php';
require_once __DIR__.'/../includes/reporting/SchoolReportRenderer.php';
require_once __DIR__.'/../classes/SystemLogger.php';require_once __DIR__.'/../classes/ReportApproval.php';
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
function reportFailure(int $status,string $message):void{http_response_code($status);header('Content-Type: application/json');echo json_encode(['error'=>$message]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')reportFailure(405,'Use the report buttons to prepare an export.');
if(empty($_SESSION['user_id']))reportFailure(403,'Please sign in again.');
if(!is_string($_POST['csrf']??null)||!isset($_SESSION['report_csrf'])||!hash_equals($_SESSION['report_csrf'],$_POST['csrf']))reportFailure(403,'Refresh the report and try again.');
try{
 $db=(new Database)->getConnection();$q=$db->prepare('SELECT id,role,first_name,last_name,is_active,archived FROM users WHERE id=?');$q->execute([$_SESSION['user_id']]);$actor=$q->fetch(PDO::FETCH_ASSOC);
 if(!$actor||!$actor['is_active']||$actor['archived']||!SchoolReportService::allowed($actor['role']))reportFailure(403,'You do not have access to these reports.');
 $kind=$_POST['kind']??'';$format=$_POST['format']??'';
 if(!is_string($kind)||!in_array($kind,SchoolReportService::KINDS,true)||!in_array($format,['preview','print','pdf','xlsx','csv'],true))reportFailure(422,'Choose a valid report and export format.');
 $filters=$_SESSION['school_report_filters'][$kind]??['include_records'=>$kind!=='counseling'];
 if(isset($_POST['result'])){$filters['result']=$_POST['result'];}
 $approvalId=0;$approvalService=new ReportApproval($db);
 if($actor['role']==='guidance_advocate'){
   if(!empty($_POST['approval_id'])){
     $approved=$approvalService->find((int)$_POST['approval_id'],(int)$actor['id']);
     if(!$approved||$approved['status']!=='approved'||$approved['resource']!==$kind||$approved['output_format']!==$format)reportFailure(403,'This approval is unavailable, already used or for another output.');
     $approvedFilters=json_decode($approved['scope_json'],true,16,JSON_THROW_ON_ERROR);
     if(ReportApproval::key($kind,$format,$filters)!==$approved['scope_hash'])reportFailure(403,'Report options changed. Return to the approved report or request another approval.');
     $filters=$approvedFilters;$approvalId=(int)$approved['id'];
   }else{
     $approved=$approvalService->approved((int)$actor['id'],$kind,$format,$filters);
     if(!$approved){$request=$approvalService->request((int)$actor['id'],$kind,$format,$filters);if($request['status']==='approved'){$approved=$request;$approvalId=(int)$request['id'];}else{http_response_code(202);header('Content-Type: application/json');echo json_encode(['approval_required'=>true,'request_id'=>(int)$request['id'],'message'=>'Request #'.$request['id'].' is awaiting the counselor/admin. No output has been generated. Check My report approvals, then return to these report options.']);exit;}}
     $approvalId=(int)$approved['id'];
   }
 }
 $report=(new SchoolReportService($db))->build($kind,$filters,$actor);
 // No search terms, student identifiers, filter values or narratives are written to audit logs.
 $audit=['report'=>$kind,'format'=>$format,'scope'=>$report['filters']['include_records']?'authorized records':'aggregate','matching_records'=>$report['record_count'],'filter_keys'=>array_keys(array_filter($report['filters'],static fn($v)=>$v!==''&&$v!==false))];
 if(!(new SystemLogger($db))->adminAction('Prepared school report export',$actor['id'],$audit))reportFailure(503,'The export could not be recorded. Please try again.');

 if($format==='preview'||$format==='print'){$html=SchoolReportRenderer::html($report);if($approvalId)$approvalService->consume((int)$actor['id'],$approvalId,$kind,$format,$filters);session_write_close();header('Content-Type: application/json; charset=utf-8');echo json_encode(['html'=>$html,'title'=>$report['title'],'report_id'=>$report['report_id']],JSON_INVALID_UTF8_SUBSTITUTE);exit;}
 elseif($format==='pdf'){$bytes=SchoolReportRenderer::pdf($report);$mime='application/pdf';$extension='pdf';}
 elseif($format==='xlsx'){$bytes=SchoolReportRenderer::xlsx($report);$mime='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';$extension='xlsx';}
 else{$bytes=SchoolReportRenderer::csv($report);$mime='text/csv; charset=utf-8';$extension='csv';}
 if($approvalId)$approvalService->consume((int)$actor['id'],$approvalId,$kind,$format,$filters);session_write_close();
 header('Content-Type: '.$mime);header('Content-Disposition: '.(in_array($format,['preview','print'],true)?'inline':'attachment').'; filename="'.$kind.'-report-'.date('Y-m-d').'.'.$extension.'"');echo $bytes;
}catch(ApprovalRequestBlocked $e){http_response_code(429);header('Content-Type: application/json');header('Retry-After: '.max(1,$e->retryAfter));echo json_encode(['error'=>$e->getMessage(),'retry_after'=>$e->retryAfter,'pending_id'=>$e->pendingId]);}catch(InvalidArgumentException $e){reportFailure(422,$e->getMessage());}catch(Throwable $e){reportFailure(500,'The report could not be generated. Please try again or contact the system administrator.');}
