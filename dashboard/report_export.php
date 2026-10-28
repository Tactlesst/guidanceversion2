<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';
require_once __DIR__.'/../includes/reporting/SchoolReportService.php';
require_once __DIR__.'/../includes/reporting/SchoolReportRenderer.php';
require_once __DIR__.'/../classes/SystemLogger.php';
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
 $report=(new SchoolReportService($db))->build($kind,$filters,$actor);
 // No search terms, student identifiers, filter values or narratives are written to audit logs.
 $audit=['report'=>$kind,'format'=>$format,'scope'=>$report['filters']['include_records']?'authorized records':'aggregate','matching_records'=>$report['record_count'],'filter_keys'=>array_keys(array_filter($report['filters'],static fn($v)=>$v!==''&&$v!==false))];
 if(!(new SystemLogger($db))->adminAction('Prepared school report export',$actor['id'],$audit))reportFailure(503,'The export could not be recorded. Please try again.');
 session_write_close();
 if($format==='preview'||$format==='print'){header('Content-Type: application/json; charset=utf-8');echo json_encode(['html'=>SchoolReportRenderer::html($report),'title'=>$report['title'],'report_id'=>$report['report_id']],JSON_INVALID_UTF8_SUBSTITUTE);exit;}
 elseif($format==='pdf'){$bytes=SchoolReportRenderer::pdf($report);$mime='application/pdf';$extension='pdf';}
 elseif($format==='xlsx'){$bytes=SchoolReportRenderer::xlsx($report);$mime='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';$extension='xlsx';}
 else{$bytes=SchoolReportRenderer::csv($report);$mime='text/csv; charset=utf-8';$extension='csv';}
 header('Content-Type: '.$mime);header('Content-Disposition: '.(in_array($format,['preview','print'],true)?'inline':'attachment').'; filename="'.$kind.'-report-'.date('Y-m-d').'.'.$extension.'"');echo $bytes;
}catch(InvalidArgumentException $e){reportFailure(422,$e->getMessage());}catch(Throwable $e){reportFailure(500,'The report could not be generated. Please try again or contact the system administrator.');}
