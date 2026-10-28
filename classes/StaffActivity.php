<?php
require_once __DIR__.'/SystemLogger.php';
/** Safe audit summaries; never accepts record contents or request bodies as log text. */
class StaffActivity {
 public const ROLES=['admin'=>'Guidance counselor','guidance_advocate'=>'Guidance advocate'];
 public const MODULES=['approvals'=>'Report approvals','dashboard'=>'Office overview','student_records'=>'Student records','student_list_report'=>'Student lists','admin_view_pds'=>'Personal data sheets','view_pds'=>'Personal data sheets','survey_results'=>'Survey results','manage_counseling'=>'Counseling','counseling_history'=>'Counseling history','manage_exams'=>'Exam appointments','release_results'=>'Exam results','schedules'=>'Schedules','daily_limits'=>'Booking limits','manage_announcements'=>'Announcements','system_settings'=>'System settings','reports'=>'Reports overview','examinee_reports'=>'Examinee reports','passed_examinees_report'=>'Examinee reports','ses_analytics'=>'SES analytics','olsat_reports'=>'OLSAT reports','counseling_reports'=>'Counseling reports'];
 public const ACTIONS=[
 'request_report_approval'=>'Requested one report output','decide_report_approval'=>'Decided a report output request','use_report_approval'=>'Prepared an approved report output','view_student_pds'=>'Viewed a student personal data sheet',
 'assign_counselor'=>'Assigned a counseling counselor','start_session'=>'Started a counseling session','complete_appointment'=>'Completed a counseling session','cancel_appointment'=>'Cancelled a counseling appointment','mark_missed'=>'Marked a counseling appointment missed','reschedule_appointment'=>'Rescheduled a counseling appointment','schedule_followup'=>'Scheduled a counseling follow-up','save_counseling_form'=>'Saved a counseling form','save_form'=>'Saved a counseling form',
 'update_student_profile'=>'Updated student academic assignment','create_schedule'=>'Created a schedule','update_schedule'=>'Updated a schedule','delete_schedule'=>'Deleted a schedule','set_daily_limit'=>'Saved a daily booking limit','remove_daily_limit'=>'Removed a daily booking limit','create_school_closure'=>'Marked school closed','reopen_school_closure'=>'Reopened a school closure','create_holiday'=>'Added a holiday','delete_holiday'=>'Removed a holiday',
 'set_single_limit'=>'Saved a counseling booking limit','set_range_limits'=>'Saved counseling booking limits','remove_limit'=>'Removed a counseling booking limit','set_exam_single_limit'=>'Saved an exam booking limit','set_exam_range_limits'=>'Saved exam booking limits','remove_exam_limit'=>'Removed an exam booking limit',
 'create_announcement'=>'Created an announcement','update_visibility'=>'Updated announcement visibility','delete_announcement'=>'Deleted an announcement','save_website_settings'=>'Updated website address for email links','save_mail_settings'=>'Updated email delivery settings','save_settings'=>'Updated office settings','save_announcement'=>'Saved the homepage announcement','release_exam_results'=>'Saved and released exam results',
 'exam_confirmed'=>'Confirmed an exam appointment','exam_cancelled'=>'Cancelled an exam appointment','exam_awaiting_results'=>'Recorded exam attendance','exam_missed'=>'Marked an exam appointment missed',
 'view_student_record'=>'Viewed a student record','view_counseling_record'=>'Viewed a counseling record','view_assessment'=>'Viewed an assessment profile','print_assessment'=>'Prepared an assessment profile for printing',
 'view_pds_history'=>'Viewed a saved PDS version','print_pds_history'=>'Prepared a saved PDS version for printing'
 ];
 private PDO $db;public function __construct(PDO $db){$this->db=$db;}
 public function record(int $actorId,string $module,string $action,bool $navigation=false):bool{
  if($navigation||!isset(self::MODULES[$module],self::ACTIONS[$action]))return false;
  $q=$this->db->prepare('SELECT role,is_active,archived FROM users WHERE id=?');$q->execute([$actorId]);$actor=$q->fetch(PDO::FETCH_ASSOC);
  if(!$actor||!in_array($actor['role'],['admin','guidance_advocate','super_admin'],true)||!$actor['is_active']||$actor['archived'])return false;
  return (new SystemLogger($this->db))->log('admin_action',self::ACTIONS[$action],$actorId,['staff_activity'=>1,'module'=>$module,'action'=>$action,'actor_role'=>$actor['role']]);
 }
 public static function action(string $page,array $post):?string {
  $allowed=[
   'manage_counseling'=>['assign_counselor','start_session','complete_appointment','cancel_appointment','mark_missed','reschedule_appointment','schedule_followup','save_counseling_form','save_form'],
   'student_records'=>['update_student_profile'],'schedules'=>['create_schedule','update_schedule','delete_schedule','set_daily_limit','remove_daily_limit','create_school_closure','reopen_school_closure','create_holiday','delete_holiday'],
   'daily_limits'=>['set_single_limit','set_range_limits','remove_limit','set_exam_single_limit','set_exam_range_limits','remove_exam_limit'],'system_settings'=>['save_settings','save_announcement','save_mail_settings','save_website_settings']
  ];
  foreach($allowed[$page]??[] as $key)if(isset($post[$key]))return $key;
  if($page==='manage_announcements'&&is_string($post['action']??null)&&in_array($post['action'],['create_announcement','update_visibility','delete_announcement'],true))return $post['action'];
  if($page==='manage_exams'&&is_string($post['status']??null)&&isset(self::ACTIONS['exam_'.$post['status']]))return 'exam_'.$post['status'];
  if($page==='release_results')return 'release_exam_results';return null;
 }
 public static function filters(array $input):array{
  $out=[];foreach(['staff_role','staff_user','activity_kind','activity_module','activity_from','activity_to'] as $key){$v=$input[$key]??'';$out[$key]=is_string($v)&&strlen($v)<100?trim($v):'';}
  if(!isset(self::ROLES[$out['staff_role']]))$out['staff_role']='';
  if(!ctype_digit($out['staff_user']))$out['staff_user']='';
  if(!in_array($out['activity_kind'],['','action','access','print','export','profile'],true))$out['activity_kind']='';
  if(!isset(self::MODULES[$out['activity_module']]))$out['activity_module']='';
  foreach(['activity_from','activity_to'] as $k)if($out[$k]!==''){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$out[$k]);if(!$d||$d->format('Y-m-d')!==$out[$k])throw new InvalidArgumentException('Choose a valid activity date.');}
  if($out['activity_from']!==''&&$out['activity_to']!==''&&$out['activity_from']>$out['activity_to'])throw new InvalidArgumentException('The end date must be on or after the start date.');return $out;
 }
 public function history(array $input,string $viewerRole):array{
  if(!in_array($viewerRole,['admin','super_admin'],true))throw new RuntimeException('Only guidance counselors and superadmins can review staff activity.');
  $f=self::filters($input);$meta="CASE WHEN JSON_VALID(sl.additional_data) THEN sl.additional_data ELSE '{}' END";
  $role="COALESCE(JSON_UNQUOTE(JSON_EXTRACT($meta,'$.actor_role')),u.role)";$marked="JSON_UNQUOTE(JSON_EXTRACT($meta,'$.staff_activity'))='1'";
  $export="sl.message='Prepared school report export'";$profile="sl.message IN ('Updated own profile details','Changed own password')";
  $eventAction="JSON_UNQUOTE(JSON_EXTRACT($meta,'$.action'))";
  $kind="CASE WHEN $export AND JSON_UNQUOTE(JSON_EXTRACT($meta,'$.format'))='print' THEN 'print' WHEN $export THEN 'export' WHEN $profile THEN 'profile' WHEN $eventAction IN ('view_student_record','view_counseling_record','view_assessment','view_pds_history','view_student_pds') THEN 'access' WHEN $eventAction IN ('print_assessment','print_pds_history','use_report_approval') THEN 'print' ELSE 'action' END";
  $module="COALESCE(JSON_UNQUOTE(JSON_EXTRACT($meta,'$.module')),CASE WHEN $export THEN CASE JSON_UNQUOTE(JSON_EXTRACT($meta,'$.report')) WHEN 'ses' THEN 'ses_analytics' WHEN 'olsat' THEN 'olsat_reports' WHEN 'counseling' THEN 'counseling_reports' WHEN 'examinees' THEN 'examinee_reports' ELSE 'reports' END ELSE NULL END)";
  $allowedActions=implode(',',array_map([$this->db,'quote'],array_keys(self::ACTIONS)));
  $where=["$role IN ('admin','guidance_advocate')","(u.role IS NULL OR u.role<>'super_admin')","(($marked AND $eventAction IN ($allowedActions)) OR $export OR $profile)"];$params=[];
  foreach(['staff_role'=>$role,'staff_user'=>'sl.user_id','activity_kind'=>$kind,'activity_module'=>$module] as $k=>$sql)if($f[$k]!==''){$where[]="$sql=?";$params[]=$f[$k];}
  if($f['activity_from']!==''){$where[]='sl.created_at>=?';$params[]=$f['activity_from'].' 00:00:00';}
  if($f['activity_to']!==''){$where[]='sl.created_at<?';$params[]=(new DateTimeImmutable($f['activity_to']))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
  $from=' FROM system_logs sl LEFT JOIN users u ON u.id=sl.user_id WHERE '.implode(' AND ',$where);
  $q=$this->db->prepare('SELECT COUNT(*)'.$from);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/10));$page=min($pages,max(1,is_scalar($input['activity_page']??1)?(int)($input['activity_page']??1):1));$offset=($page-1)*10;
  $q=$this->db->prepare("SELECT sl.id,sl.created_at,sl.ip_address,u.first_name,u.last_name,$role AS actor_role,$kind AS activity_kind,$module AS module,CASE WHEN $marked THEN sl.message WHEN sl.log_type='login' THEN 'Signed in' WHEN sl.log_type='logout' THEN 'Signed out' WHEN $export THEN 'Prepared school report export' ELSE sl.message END AS summary".$from." ORDER BY sl.created_at DESC,sl.id DESC LIMIT 10 OFFSET $offset");$q->execute($params);
  $people=$this->db->query("SELECT id,first_name,last_name,role FROM users WHERE role IN ('admin','guidance_advocate') ORDER BY last_name,first_name,id")->fetchAll(PDO::FETCH_ASSOC);
  return ['filters'=>$f,'rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'people'=>$people];
 }
}
function staffActivitySaved():void{
 global $db,$page,$uid;static $saved=false;if($saved||!isset($db,$page,$uid))return;
 $action=StaffActivity::action($page,$_POST);if($action===null)return;
 try{$saved=(new StaffActivity($db))->record((int)$uid,$page,$action);}catch(Throwable $e){error_log('Staff action audit could not be recorded.');}
}
function staffActivityInteraction(string $module,string $action):void{
 global $db,$uid;if(!isset($db,$uid))return;
 try{(new StaffActivity($db))->record((int)$uid,$module,$action);}catch(Throwable $e){error_log('Staff record access audit could not be recorded.');}
}
