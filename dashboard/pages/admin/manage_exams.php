<?php
if (!defined('IN_LAYOUT')) exit;
require_once __DIR__.'/../../../classes/SubmissionNotifications.php';
checkRole(['admin','guidance_advocate']);
$office_messages=fetchSessionMessages();$success_message=$office_messages['success'];$error_message=$office_messages['error'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($_SESSION['exam_csrf']??'',$_POST['csrf']??'')) throw new RuntimeException('Please reload and try again.');
  $db->beginTransaction();
  $q=$db->prepare('SELECT status FROM entrance_exam_appointments WHERE id=? FOR UPDATE');$q->execute([$_POST['appointment_id']??0]);$status=$q->fetchColumn();
  $next=$_POST['status']??'';
  $allowed=['pending'=>['confirmed','cancelled'],'confirmed'=>['awaiting_results','missed','cancelled'],'awaiting_results'=>['cancelled']];
  if (!in_array($next,$allowed[$status]??[],true)) throw new RuntimeException('This appointment status cannot be changed that way.');
  $db->prepare('UPDATE entrance_exam_appointments SET status=?,updated_at=NOW() WHERE id=?')->execute([$next,$_POST['appointment_id']]);
  SubmissionNotifications::change($db,'exam',(int)$_POST['appointment_id'],$next);$db->commit();if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']='Exam appointment updated.';header('Location: ' . portalSaveRedirect('layout.php?page=manage_exams'));exit;
 } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();$error_message=$e->getMessage(); }
}
$_SESSION['exam_csrf']=$_SESSION['exam_csrf']??bin2hex(random_bytes(24));
$rows=$db->query('SELECT e.*,u.first_name,u.last_name FROM entrance_exam_appointments e JOIN users u ON u.id=e.user_id ORDER BY e.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$exLabels=['pending'=>'Pending','confirmed'=>'Confirmed','awaiting_results'=>'Awaiting results','completed'=>'Completed','cancelled'=>'Cancelled','missed'=>'Missed'];
$exHints=['pending'=>'Awaiting confirmation','confirmed'=>'Ready to take the exam','awaiting_results'=>'Ready for results','completed'=>'Results recorded','cancelled'=>'Cancelled appointments','missed'=>'Did not attend'];
$exColors=['pending'=>'#a77a23','confirmed'=>'#3477b6','awaiting_results'=>'#8759b0','completed'=>'#25876b','cancelled'=>'#b25958','missed'=>'#7c8799'];
$exIcons=['pending'=>'fa-clock','confirmed'=>'fa-calendar-check','awaiting_results'=>'fa-file-alt','completed'=>'fa-check-double','cancelled'=>'fa-times-circle','missed'=>'fa-user-clock'];
$exCounts=array_fill_keys(array_keys($exLabels),0);$exData=[];
$exEscape=static fn($value)=>htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$exFields=['id','first_name','last_name','preferred_date','preferred_time','grade_level_applying','previous_school','status','preferred_program','academic_strand','exam_score','exam_result','exam_date','qualified_grade','qualified_program','school_year','remarks'];
foreach($rows as $row){if(isset($exCounts[$row['status']]))$exCounts[$row['status']]++;$exData[]=array_intersect_key($row,array_flip($exFields));}
?>
<link rel="stylesheet" href="../assets/css/appointment-dialogs.css?v=20261028-1">
<link rel="stylesheet" href="../assets/css/exam-workspace.css?v=20261028-1">
<section class="ex-workspace" aria-labelledby="exTitle">
<header class="ex-heading"><div><p class="ex-eyebrow">EXAM APPOINTMENTS</p><h1 id="exTitle">Entrance exam appointments</h1><p>Confirm applicants, record attendance and manage exam results.</p></div><span class="ex-total"><strong><?=count($rows)?></strong>appointments</span></header>
<?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
<div class="ex-cards" role="group" aria-label="Filter exam appointments by status">
<?php foreach($exLabels as $status=>$label):?><button type="button" class="ex-status-card" data-exam-status="<?=$status?>" aria-pressed="false" style="--ex-color:<?=$exColors[$status]?>"><i class="fas <?=$exIcons[$status]?>" aria-hidden="true"></i><strong><?=$exCounts[$status]?></strong><span><?=$label?></span><small><?=$exHints[$status]?></small></button><?php endforeach;?>
</div>
<details class="ex-filters"><summary><span>Filters<span id="exFilterCount"></span></span><small>Search applicants and schedules</small></summary><div class="ex-filter-body"><label for="exSearch">Search appointments<input type="search" id="exSearch" autocomplete="off" placeholder="Search name, school, grade or program"></label><button type="button" class="ex-clear" id="exClearSearch" hidden>Clear search</button></div></details>
<div class="ex-list"><header class="ex-list-header"><div><h2 id="exListTitle">All exam appointments</h2><p id="exListHint" aria-live="polite">Choose a status card to filter this list.</p></div><button type="button" id="exShowAll" class="ex-clear" hidden>Show all</button></header><div class="ex-table-wrap"><table class="ex-table" data-cw-no-cards><thead><tr><th scope="col">Applicant</th><th scope="col">Exam schedule</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach($rows as $row):$id=(int)$row['id'];$status=$row['status'];$name=trim($row['first_name'].' '.$row['last_name']);?>
<tr class="ex-row" data-exam-id="<?=$id?>" data-status="<?=$exEscape($status)?>" data-search="<?=$exEscape(mb_strtolower(implode(' ',[$name,$row['previous_school']??'',$row['grade_level_applying']??'',$row['preferred_program']??'',$row['preferred_date']??''])))?>"><td data-label="Applicant"><strong><?=$exEscape($name)?></strong><small><?=$exEscape($row['grade_level_applying']?:'Grade not set')?></small></td><td data-label="Exam schedule"><strong><?=$row['preferred_date']?$exEscape(date('M j, Y',strtotime($row['preferred_date']))):'Not scheduled'?></strong><small><?=$row['preferred_time']?$exEscape(date('g:i A',strtotime($row['preferred_time']))):'Time not set'?></small></td><td data-label="Status"><span class="ad-status" data-status="<?=$exEscape($status)?>"><?=$exEscape($exLabels[$status]??ucfirst(str_replace('_',' ',$status)))?></span></td><td data-label="Actions"><div class="ex-actions"><button type="button" data-exam-view="<?=$id?>"><i class="fas fa-eye" aria-hidden="true"></i>View</button>
<?php $actions=['pending'=>['confirmed'=>'Confirm','cancelled'=>'Cancel'],'confirmed'=>['awaiting_results'=>'Exam taken','missed'=>'Mark missed','cancelled'=>'Cancel'],'awaiting_results'=>['cancelled'=>'Cancel']];foreach(($role==='guidance_advocate'?[]:($actions[$status]??[])) as $value=>$label):$danger=$value==='cancelled';?>
<form data-portal-save method="post" <?php if(in_array($value,['cancelled','missed','awaiting_results'])):?>data-confirm-title="<?=$danger?'Cancel exam appointment?':($value==='missed'?'Mark applicant as missed?':'Record exam attendance?')?>" data-confirm-message="<?=$danger?'This appointment will be marked as cancelled.':($value==='missed'?'Confirm that this applicant did not attend the exam.':'Confirm that this applicant has taken the entrance exam.')?>" data-confirm-label="<?=$label?>"<?php endif;?>><input type="hidden" name="csrf" value="<?=$exEscape($_SESSION['exam_csrf'])?>"><input type="hidden" name="appointment_id" value="<?=$id?>"><button name="status" value="<?=$value?>" class="<?=$danger?'ex-danger':($value==='missed'?'ex-muted':'ex-primary')?>"><?=$label?></button></form>
<?php endforeach;if(in_array($status,['awaiting_results','completed'])):?><a class="ex-primary" href="layout.php?page=release_results&amp;id=<?=$id?>"><i class="fas fa-file-alt" aria-hidden="true"></i><?=$status==='completed'?'Edit results':'Release results'?></a><?php endif;?></div></td></tr>
<?php endforeach;?></tbody></table></div><p class="ex-empty" id="exEmpty" hidden>No appointments match your selection.</p><footer class="ex-pagination"><span id="exPageInfo" aria-live="polite"></span><div class="ex-page-controls"><button type="button" class="ex-page-button" id="exPrevious" aria-label="Previous page">Previous</button><span id="exPagePosition"></span><button type="button" class="ex-page-button" id="exNext" aria-label="Next page">Next</button></div></footer></div>
</section>
<div id="examAppointmentModal" class="pd-overlay hidden appointment-dialog ad-record-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="examAppointmentTitle"><div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span><div class="pd-heading-copy"><p class="ad-eyebrow">EXAM APPOINTMENT</p><h2 id="examAppointmentTitle">Applicant details</h2><p>Application information, schedule and recorded results.</p></div><button type="button" class="pd-close" data-dialog-close="examAppointmentModal" aria-label="Close applicant details">×</button></header><div class="pd-body" id="examAppointmentContent"></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="examAppointmentModal">Close</button></footer></div></div>
<script type="application/json" id="examAppointmentData"><?=json_encode($exData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<script src="../assets/js/exam-workspace.js?v=20261028-1"></script>
