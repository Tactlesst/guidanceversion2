<?php
if(!defined('IN_LAYOUT')){header('Location: ../dashboard/layout.php?page=multiple_intelligence_survey');exit;}
if(($role??$user_info['role']??'')!=='student'){http_response_code(403);echo '<p>This survey is available to student accounts.</p>';return;}
require_once __DIR__.'/../includes/surveys/intelligence_store.php';require_once __DIR__.'/../includes/surveys/assignment.php';
$miSections=require __DIR__.'/config/mi_questions.php';$miE=static fn($value)=>htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$q=$db->prepare('SELECT student_id,department,grade_level FROM student_profiles WHERE user_id=?');$q->execute([(int)$uid]);$miProfile=$q->fetch(PDO::FETCH_ASSOC);
$miAssigned=studentSurveyAssignment($miProfile?:[]);$miYear=SurveyAnnual::year($db,(int)$uid);
$miBlocked='';if(!$miProfile||empty($miProfile['student_id']))$miBlocked='Complete your student profile before taking this survey.';elseif(($miAssigned['kind']??'')!=='mi')$miBlocked='The Multiple Intelligence Survey is assigned to Grade 11, Grade 12 and College students. Check your assigned survey in the student portal.';
if(!$miBlocked&&$miYear==='')$miBlocked='Save a school year in your PDS before taking the yearly survey.';
$miExisting=null;$miError='';$miLegacy=false;$miResponses=[];$miReview=[];$miReload=false;$miUpdate=($_GET['update']??'')==='1';
$_SESSION['mi_survey_csrf']=$_SESSION['mi_survey_csrf']??bin2hex(random_bytes(32));
if(!$miBlocked){
    $miExisting=SurveyAnnual::record($db,(int)$uid,'mi',$miYear);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['submit_survey'])){
        if(!is_string($_POST['_survey_year']??null)||$_POST['_survey_year']!==$miYear){$miReload=true;$miBlocked='Your PDS school year changed. Open the current school-year survey to start again. Earlier years remain unchanged.';require __DIR__.'/../includes/surveys/intelligence_view.php';return;}
        try{
            if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['mi_survey_csrf'],$_POST['csrf']))throw new RuntimeException('Your form session expired. Reload the survey before submitting.');
            if(!is_string($_POST['revision']??null))throw new RuntimeException('Reload the survey before submitting.');
            MISurveyStore::save($db,$miProfile['student_id'],$miSections,$_POST,$_POST['revision'],(int)$uid);
            header('Location: layout.php?page=survey_thankyou');exit;
        }catch(InvalidArgumentException|RuntimeException $e){$miError=$e instanceof PDOException?'Your survey could not be saved. Your previous results are unchanged. Please try again.':$e->getMessage();}
        catch(Throwable $e){$miError='Your survey could not be saved. Your previous results are unchanged. Please try again.';}
    }
    if($miExisting&&!$miUpdate&&!$miError){header('Location: layout.php?page=survey_thankyou');return;}
    $miResponses=MISurveyStore::responses($miExisting,$miSections);
    foreach($miSections as $number=>$section){
        if((int)($miExisting['section'.$number.'_score']??0)>0&&!$miResponses[$number])$miLegacy=true;
        if($miError){$raw=$_POST['section'.$number]??[];$miResponses[$number]=is_array($raw)?array_values(array_filter($raw,static fn($v)=>is_scalar($v)&&ctype_digit((string)$v)&&(int)$v>=1&&(int)$v<=count($section['questions']))):[];}
        $miReview[$number]=$miError&&($_POST['reviewed'.$number]??'')==='1';
    }
}
require __DIR__.'/../includes/surveys/intelligence_view.php';
