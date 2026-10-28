<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__.'/survey_assignment.php';require_once __DIR__.'/learning_survey_store.php';
$lsE=static fn($value)=>htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$q=$db->prepare('SELECT student_id,department,grade_level FROM student_profiles WHERE user_id=?');$q->execute([(int)$uid]);$lsProfile=$q->fetch(PDO::FETCH_ASSOC)?:[];$lsAssignment=studentSurveyAssignment($lsProfile);$lsBlocked='';
if(($role??'')!=='student'){$lsBlocked='This survey is available to student accounts.';http_response_code(403);}
elseif(($lsAssignment['kind']??'')!==$lsKind)$lsBlocked='This survey is assigned to '.($lsKind==='ls'?'Grades 1–3':'Grades 4–10').' students. Open the assessment assigned to your profile.';
$lsYear=SurveyAnnual::year($db,(int)$uid);if(!$lsBlocked&&$lsYear==='')$lsBlocked='Save a school year in your PDS before taking the yearly survey.';
$lsDefinition=require __DIR__.'/../surveys/config/learning_questions.php';$lsQuestions=$lsDefinition[$lsKind];$lsPer=$lsKind==='ls'?4:10;$lsTitle=$lsKind==='ls'?'Learning Style':'Learning Style (Intermediate)';$lsRoute=$lsKind==='ls'?'learning_style_inventory':'learning_style_inventory_intermediate';$lsRecord=null;$lsError='';$lsAnswers=[];$lsRetake=($_GET['retake']??'')==='1';
$_SESSION['learning_survey_csrf']=$_SESSION['learning_survey_csrf']??bin2hex(random_bytes(32));
if(!$lsBlocked){$table=LearningSurveyStore::table($lsKind);$lsRecord=SurveyAnnual::record($db,(int)$uid,$lsKind,$lsYear);
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['submit_learning'])){
  if(!is_string($_POST['_survey_year']??null)||$_POST['_survey_year']!==$lsYear){$lsBlocked='Your PDS school year changed. Open the current school-year survey to start again. Earlier years remain unchanged.';require __DIR__.'/learning_survey_view.php';return;}
  try{if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['learning_survey_csrf'],$_POST['csrf']))throw new RuntimeException('Your form session expired. Reload before submitting.');if(!is_string($_POST['revision']??null))throw new RuntimeException('Reload the survey before submitting.');LearningSurveyStore::save($db,(int)$uid,$lsKind,$_POST,$_POST['revision']);header('Location: layout.php?page='.$lsRoute);exit;}
  catch(PDOException $e){$lsError='Your survey could not be saved. Your previous results are unchanged. Please try again.';}
  catch(InvalidArgumentException|RuntimeException $e){$lsError=$e->getMessage();}
  $lsRetake=true;foreach($lsQuestions as $i=>$text)if(is_string($_POST['q'.($i+1)]??null))$lsAnswers[$i+1]=$_POST['q'.($i+1)];
 }
}
require __DIR__.'/learning_survey_view.php';
