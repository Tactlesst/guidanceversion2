<?php
if (!defined('IN_LAYOUT')) exit;
checkRole(['admin','guidance_advocate']);
require_once __DIR__.'/../../../classes/EntranceExamResults.php';
$id=(int)($_GET['id']??0);$q=$db->prepare('SELECT e.*,u.first_name,u.last_name FROM entrance_exam_appointments e JOIN users u ON u.id=e.user_id WHERE e.id=?');$q->execute([$id]);$exam=$q->fetch(PDO::FETCH_ASSOC);
if(!$exam || !in_array($exam['status'],['awaiting_results','completed'],true)) { echo '<p>No exam awaiting results found.</p>';return; }
$fields=['exam_date','olsat_level','olsat_form','verbal_abilities_score','verbal_comprehension_score','verbal_reasoning_score','nonverbal_abilities_score','figural_reasoning_score','quantitative_reasoning_score','total_score','total_items','stanine_score','interpretation','verbal_stanine','verbal_interpretation','nonverbal_stanine','nonverbal_interpretation','qualified_grade','academic_strand','qualified_program','school_year','testing_in_charge'];
$assessmentKind=$_POST['assessment_kind']??(!empty(trim((string)$exam['olsat_level']))||!empty(trim((string)$exam['olsat_form']))?'olsat':'entrance');
$error='';$_SESSION['exam_csrf']=$_SESSION['exam_csrf']??bin2hex(random_bytes(24));
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if(!hash_equals($_SESSION['exam_csrf'],$_POST['csrf']??''))throw new RuntimeException('Please reload and try again.');
  $score=filter_var($_POST['total_score']??'',FILTER_VALIDATE_INT);$items=filter_var($_POST['total_items']??'',FILTER_VALIDATE_INT);
  if($score===false || $items===false || $score<0 || $items<1 || $score>$items)throw new RuntimeException('Enter a valid total score and number of items.');
  foreach(['stanine_score','verbal_stanine','nonverbal_stanine'] as $f) if(($_POST[$f]??'')!=='' && (!ctype_digit($_POST[$f]) || (int)$_POST[$f]<1 || (int)$_POST[$f]>9))throw new RuntimeException('Stanines must be between 1 and 9.');
  foreach(['verbal_abilities_score','verbal_comprehension_score','verbal_reasoning_score','nonverbal_abilities_score','figural_reasoning_score','quantitative_reasoning_score'] as $f)if(($_POST[$f]??'')!=='' && (!ctype_digit($_POST[$f]) || (int)$_POST[$f]>$items))throw new RuntimeException('Subscores must be between zero and the number of items.');
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$_POST['exam_date']??'');if(!$date || $date->format('Y-m-d')!==$_POST['exam_date'])throw new RuntimeException('Enter the actual exam date.');
  if(!in_array($assessmentKind,['entrance','olsat'],true))throw new RuntimeException('Choose a valid assessment type.');
  if($assessmentKind==='olsat')foreach(['olsat_level','olsat_form'] as $f)if(!is_string($_POST[$f]??null)||in_array(strtolower(trim($_POST[$f])),['','n/a','na','none','unknown','-'],true))throw new RuntimeException('Enter the actual OLSAT level and form from the administered test.');
  $result=new EntranceExamResults($db);$result->appointment_id=$id;
  foreach($fields as $f)$result->$f=($_POST[$f]??'')===''?null:trim($_POST[$f]);
  if($assessmentKind==='entrance'){$result->olsat_level=null;$result->olsat_form=null;}
  $result->assisted_by=$uid;$result->exam_result=$_POST['exam_result']??'';
  $db->beginTransaction();$q=$db->prepare('SELECT status FROM entrance_exam_appointments WHERE id=? FOR UPDATE');$q->execute([$id]);
  if(!in_array($q->fetchColumn(),['awaiting_results','completed']))throw new RuntimeException('The appointment status changed. Reload before releasing results.');
  $result->saveResults();
  require_once __DIR__.'/../../../classes/NotificationService.php';
  if(!(new NotificationService($db))->notify((int)$exam['user_id'],'Entrance exam results released','Your results are now available in the examinee portal. Sign in to review them.','info','entrance_exam_appointments',$id,true,null,null,'exam-results:'.$id.':'.bin2hex(random_bytes(12))))throw new RuntimeException('The results notification could not be saved.');
  $db->commit();if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']='Exam results have been saved and released.';header('Location: ' . portalSaveRedirect('layout.php?page=manage_exams'));exit;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
}
?>
<h1 class="text-xl font-bold text-primary mb-5">Release Entrance Exam Results</h1><p class="mb-4"><?=htmlspecialchars($exam['first_name'].' '.$exam['last_name'])?></p>
<?php $error_message=$error;$success_message='';?><?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
<form data-portal-save method="post" class="bg-white rounded-xl p-5"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['exam_csrf'])?>">
<label class="block mb-5" for="assessmentKind">Assessment type *<select name="assessment_kind" id="assessmentKind" class="block border rounded p-2 w-full"><option value="entrance" <?=$assessmentKind==='entrance'?'selected':''?>>Other entrance examination</option><option value="olsat" <?=$assessmentKind==='olsat'?'selected':''?>>OLSAT assessment</option></select><small>Choose OLSAT only when that test was administered. Its report updates automatically when these results are released.</small></label>
<label class="block mb-5">Admission decision *<select name="exam_result" class="block border rounded p-2 w-full" required><option value="">Choose recorded decision</option><?php foreach(['passed'=>'Passed','failed'=>'Failed'] as $key=>$label):?><option value="<?=$key?>" <?=($_POST['exam_result']??$exam['exam_result']??'')===$key?'selected':''?>><?=$label?></option><?php endforeach;?></select><small>Use the school’s approved assessment decision. An empty qualification never means Failed automatically.</small></label>
<?php $result_groups=[
 'Exam details'=>['exam_date','school_year','testing_in_charge','olsat_level','olsat_form'],
 'Scores'=>['total_score','total_items','verbal_abilities_score','verbal_comprehension_score','verbal_reasoning_score','nonverbal_abilities_score','figural_reasoning_score','quantitative_reasoning_score'],
 'Stanines & interpretation'=>['stanine_score','verbal_stanine','nonverbal_stanine','interpretation','verbal_interpretation','nonverbal_interpretation'],
 'Qualification'=>['qualified_grade','academic_strand','qualified_program']
];foreach($result_groups as $group=>$group_fields):?><fieldset class="cw-results-group"><legend><?=$group?></legend><div class="cw-results-grid">
<?php foreach($group_fields as $f):$numeric=str_contains($f,'score')||str_contains($f,'stanine')||$f==='total_items';$required=in_array($f,['exam_date','total_score','total_items','testing_in_charge','school_year']);$value=(string)($_POST[$f]??$exam[$f]??($f==='total_items'?72:''));?>
<label for="result-<?=$f?>"><?=htmlspecialchars(ucwords(str_replace('_',' ',$f)))?><?=$required?' *':''?>
<?php if(str_contains($f,'interpretation')):?><textarea id="result-<?=$f?>" name="<?=$f?>" rows="3" class="block border rounded p-2 w-full"><?=htmlspecialchars($value)?></textarea><?php else:?><input id="result-<?=$f?>" name="<?=$f?>" type="<?=$f==='exam_date'?'date':($numeric?'number':'text')?>" <?=$numeric?(str_contains($f,'stanine')?'min="1" max="9" step="1"':'min="0" step="1"'):''?> <?=$required?'required':''?> value="<?=htmlspecialchars($value,ENT_QUOTES)?>" class="block border rounded p-2 w-full"><?php endif;?></label>
<?php endforeach;?></div><?php if($group==='Qualification'):?><p class="cw-muted-note" style="margin:15px 0 0">Enter any counselor-approved grade, strand or program qualification. The admission decision is selected separately above.</p><?php endif;?></fieldset><?php endforeach;?>
<div class="cw-record-heading"><i class="fas fa-circle-info" aria-hidden="true"></i>Saving makes these results available to the applicant. Check the scores and qualification before releasing them.</div><div class="cw-save-bar"><button class="bg-primary text-white px-5 py-3 rounded" type="submit">Save and release results</button><a href="layout.php?page=manage_exams">Back to exam appointments</a></div></form>

<script>
(()=>{const kind=document.getElementById('assessmentKind');if(!kind)return;const update=()=>{for(const id of['result-olsat_level','result-olsat_form']){const input=document.getElementById(id);input.disabled=kind.value!=='olsat';input.required=kind.value==='olsat';input.closest('label').hidden=kind.value!=='olsat';}};kind.addEventListener('change',update);update();})();
</script>
