<?php
if(!defined('IN_LAYOUT')){header('Location: layout.php?page=profile');exit;}
require_once __DIR__.'/../classes/identity/AccountSecurity.php';
$_SESSION['profile_csrf']=$_SESSION['profile_csrf']??bin2hex(random_bytes(24));$_SESSION['security_binding']=$_SESSION['security_binding']??bin2hex(random_bytes(32));
$profile_messages=fetchSessionMessages();$success_message=$profile_messages['success'];$error_message=$profile_messages['error'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['profile_csrf'],$_POST['csrf']))throw new RuntimeException('Please refresh the page and try again.');
  $action=$_POST['profile_action']??'';$securityPurpose=$_POST['purpose']??'';$securityService=new AccountSecurity($db);
  if($action==='details'){
   $first=trim((string)($_POST['first_name']??''));$middle=trim((string)($_POST['middle_name']??''));$last=trim((string)($_POST['last_name']??''));
   if($first===''||$last===''||mb_strlen($first)>50||mb_strlen($last)>50||mb_strlen($middle)>100)throw new RuntimeException('Enter valid first and last names.');
   $q=$db->prepare('SELECT email FROM users WHERE id=?');$q->execute([$uid]);$existingEmail=$q->fetchColumn();
   if(isset($_POST['email'])&&strtolower(trim((string)$_POST['email']))!==strtolower(trim((string)$existingEmail)))throw new RuntimeException('Use Change email address and verify the email code before changing your account email.');
   $db->prepare('UPDATE users SET first_name=?,middle_name=?,last_name=? WHERE id=?')->execute([$first,$middle?:null,$last,$uid]);$_SESSION['first_name']=$first;$_SESSION['last_name']=$last;$_SESSION['success_message']='Your personal details have been updated.';(new SystemLogger($db))->info('Updated own profile details',$uid);
  }elseif($action==='applicant'){
   require_once __DIR__.'/../classes/examinations/ExamBooking.php';(new ExamBooking($db))->updateApplicantGrade((int)$uid,$_POST['applicant_grade']??null);$_SESSION['success_message']='Your applicant grade/year level has been saved. You can now choose an entrance exam session.';
  }elseif($action==='security_start'){
   if(!is_string($securityPurpose))throw new RuntimeException('Choose an account security action.');
   $pending=$securityService->startOwn((int)$uid,$securityPurpose,$_POST['new_email']??null,$_POST['current_password']??'',$_SESSION['security_binding'],$_SERVER['REMOTE_ADDR']??'local');$_SESSION['security_pending'][$securityPurpose]=$pending;$_SESSION['success_message']='Verification code queued. Check your email and enter the code below.';
  }elseif($action==='security_finish'){
   if(!is_string($securityPurpose)||!isset($_SESSION['security_pending'][$securityPurpose]))throw new RuntimeException('Request a verification code first.');
   if($securityPurpose==='password_change'&&($_POST['new_password']??'')!==($_POST['confirm_password']??''))throw new RuntimeException('The new passwords do not match.');
   $result=$securityService->complete($_SESSION['security_pending'][$securityPurpose]['selector'],$_SESSION['security_binding'],$securityPurpose,$_POST['code']??'',(int)$uid,$_POST['new_password']??null);
   $_SESSION['auth_version']=$result['auth_version'];$_SESSION['user_email']=$result['email'];$_SESSION['security_pending']=[];session_regenerate_id(true);$_SESSION['success_message']=$securityPurpose==='email_change'?'Your new email address is verified and saved.':'Your password has been changed. Other sessions have been signed out.';
  }elseif($action==='security_restart'){
   if(is_string($securityPurpose)&&isset($_SESSION['security_pending'][$securityPurpose]))$securityService->cancel($_SESSION['security_pending'][$securityPurpose]['selector'],$_SESSION['security_binding'],(int)$uid);unset($_SESSION['security_pending'][$securityPurpose]);$_SESSION['success_message']='The previous code is no longer valid. Request a new code below.';
  }else throw new RuntimeException('Use the email-code verification form for account changes.');
  $destination='layout.php?page=profile';if(in_array($securityPurpose,['email_change','password_change'],true))$destination.='&security='.$securityPurpose;header('Location: '.portalSaveRedirect($destination));exit;
 }catch(Throwable $e){$error_message=$e instanceof PDOException?'Your changes could not be saved. Please try again.':$e->getMessage();}
}
$profile_stmt=$db->prepare('SELECT id,first_name,middle_name,last_name,email,role,position,is_active,created_at,grade_level_applying FROM users WHERE id=?');$profile_stmt->execute([$uid]);$profile_user=$profile_stmt->fetch(PDO::FETCH_ASSOC)?:$user_info;
$profile_stmt=$db->prepare('SELECT student_id,department,program,strand,grade_level FROM student_profiles WHERE user_id=?');$profile_stmt->execute([$uid]);$profile_student=$profile_stmt->fetch(PDO::FETCH_ASSOC);
// Academic labels and optional fields follow the student's education level.
$profile_academic_fields=['student_id'=>'Student ID','department'=>'Department','grade_level'=>'Grade level'];
if($profile_student){
    $academicDepartment=strtolower(preg_replace('/[\s_-]+/u','',(string)($profile_student['department']??'')));
    $academicGrade=strtolower(trim((string)($profile_student['grade_level']??'')));
    $academicCollege=str_contains($academicDepartment,'higher')||str_contains($academicDepartment,'college')||str_contains($academicDepartment,'tertiary');
    $academicSenior=str_contains($academicDepartment,'seniorhigh')||$academicDepartment==='shs';
    $academicBasic=str_contains($academicDepartment,'elementary')||str_contains($academicDepartment,'gradeschool')||str_contains($academicDepartment,'kinder')||str_contains($academicDepartment,'juniorhigh')||$academicDepartment==='highschool';
    if(!$academicCollege&&!$academicSenior&&!$academicBasic){
        $academicCollege=str_contains($academicGrade,'college');
        $academicSenior=(bool)preg_match('/^grade\s*(?:11|12)(?:\s|$)/u',$academicGrade);
        $academicBasic=(bool)preg_match('/^(?:grade\s*(?:[1-9]|10)(?:\s|$)|kinder|nursery|prep)/u',$academicGrade);
    }
    if($academicCollege){$profile_academic_fields['grade_level']='Year level';$profile_academic_fields['program']='College program';}
    elseif($academicSenior)$profile_academic_fields['strand']='SHS strand';
    elseif(!$academicBasic){
        $profile_academic_fields['grade_level']='Grade / year level';
        if(trim((string)($profile_student['program']??''))!=='')$profile_academic_fields['program']='Program / course';
        if(trim((string)($profile_student['strand']??''))!=='')$profile_academic_fields['strand']='Strand';
    }
}
$profile_roles=['super_admin'=>'Superadmin','admin'=>'Guidance counselor','guidance_advocate'=>'Guidance advocate','student'=>'Student','examinee'=>'Examinee'];
$profile_full_name=trim(implode(' ',array_filter([$profile_user['first_name'],$profile_user['middle_name']??'',$profile_user['last_name']])));
$profile_initials=mb_substr($profile_user['first_name'],0,1).mb_substr($profile_user['last_name'],0,1);
?>
<section class="profile-page" aria-labelledby="profile-title">
    <header class="profile-heading"><div><p class="profile-eyebrow">Your account</p><h1 id="profile-title">My profile</h1><p>Keep your personal details current and manage your sign-in password.</p></div><a href="layout.php?page=dashboard" class="profile-button"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to dashboard</a></header>
    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <div class="profile-layout"><aside class="profile-identity"><span class="profile-large-avatar"><?=htmlspecialchars(mb_strtoupper($profile_initials))?></span><h2><?=htmlspecialchars($profile_full_name)?></h2><p class="profile-role"><?=htmlspecialchars($profile_roles[$profile_user['role']]??ucwords(str_replace('_',' ',$profile_user['role'])))?></p><span class="profile-state <?=empty($profile_user['is_active'])?'is-disabled':''?>"><?=empty($profile_user['is_active'])?'Sign-in disabled':'Active account'?></span><div class="profile-joined"><i class="far fa-calendar" aria-hidden="true"></i><span>Member since<strong><?=htmlspecialchars(date('F j, Y',strtotime($profile_user['created_at'])))?></strong></span></div><p class="profile-office-note">Your role and school identifiers are managed by the Guidance Office.</p></aside>
    <div class="profile-panels"><section class="profile-panel"><div class="profile-panel-heading"><div><h2>Personal information</h2><p>Your name and account email address.</p></div><button type="button" class="profile-button primary" onclick="openProfileDialog('profileDetailsDialog')"><i class="fas fa-pen" aria-hidden="true"></i>Edit details</button></div><dl class="profile-details"><div><dt>First name</dt><dd><?=htmlspecialchars($profile_user['first_name'])?></dd></div><div><dt>Last name</dt><dd><?=htmlspecialchars($profile_user['last_name'])?></dd></div><div><dt>Middle name</dt><dd><?=htmlspecialchars($profile_user['middle_name']?:'Not provided')?></dd></div><div><dt>Email address</dt><dd><?=htmlspecialchars($profile_user['email']?:'Not provided')?></dd></div><?php if(!empty($profile_user['position'])):?><div><dt>Position</dt><dd><?=htmlspecialchars($profile_user['position'])?></dd></div><?php endif;?></dl></section>
<?php if($profile_user['role']==='examinee'):require_once __DIR__.'/../classes/examinations/ExamBooking.php';$profileBooking=new ExamBooking($db);$applicant=$profileBooking->applicantContext((int)$uid);$applicantChoices=$applicant['locked']?[]:array_unique($profileBooking->gradeOptions($applicant['type']));?>
<section class="profile-panel" id="applicantInfo"><div class="profile-panel-heading"><div><h2>Applicant information</h2><p>The grade or year you want to enter when applying to the school.</p></div></div><div class="profile-applicant"><p class="profile-applicant-current">Applying for: <strong><?=htmlspecialchars($applicant['grade']?:'Not selected yet',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></strong></p><?php if($applicant['locked']):?><p class="profile-applicant-help">An active or completed exam application already uses this level. Contact the Guidance Office for corrections.</p><?php else:?><form method="post" data-portal-save><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['profile_csrf'],ENT_QUOTES)?>"><input type="hidden" name="profile_action" value="applicant"><label for="applicantGrade">Applying for (grade / year) *<select id="applicantGrade" name="applicant_grade" required <?=$applicantChoices?'':'disabled'?>><option value="">Choose your applicant level</option><?php foreach($applicantChoices as $level):?><option value="<?=htmlspecialchars($level,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>" <?=$level===$applicant['grade']?'selected':''?>><?=htmlspecialchars($level,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></option><?php endforeach;?></select></label><p class="profile-applicant-help">You can change this before reserving an exam. Choices follow your registered applicant type. This is your application level; the school assigns your official academic placement during enrollment.</p><?php if(!$applicantChoices):?><p class="profile-applicant-help">No active grade levels are configured. Please contact the Guidance Office.</p><?php endif;?><button type="submit" class="profile-button primary" <?=$applicantChoices?'':'disabled'?>>Save applicant level</button></form><?php endif;?></div></section>
<?php endif;?>

    <?php if($profile_student):?><section class="profile-panel"><div class="profile-panel-heading"><div><h2>Academic information</h2><p>Your current school record. Contact the Guidance Office for corrections.</p></div></div><dl class="profile-details"><?php foreach($profile_academic_fields as $key=>$label):?><div><dt><?=$label?></dt><dd><?=htmlspecialchars(($profile_student[$key]??'')!==''&&$profile_student[$key]!==null?$profile_student[$key]:'Not provided',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></dd></div><?php endforeach;?></dl></section><?php endif;?>
    <?php require __DIR__.'/../includes/account/security_panel.php';?></div></div>
</section>
<div id="profileDetailsDialog" class="pd-overlay hidden" data-portal-dialog data-managed-dialog data-dialog-tone="edit" role="dialog" aria-modal="true" aria-labelledby="profileDetailsTitle" aria-describedby="profileDetailsHint"><div class="pd-card"><div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-user-pen" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="profileDetailsTitle">Edit personal details</h2><p id="profileDetailsHint">Update your name. Use Email & password security below to change your email.</p></div><button type="button" class="pd-close" data-dialog-close="profileDetailsDialog" aria-label="Close personal details"><i class="fas fa-xmark" aria-hidden="true"></i></button></div><form method="post" action="layout.php?page=profile" data-portal-save><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['profile_csrf'])?>"><input type="hidden" name="profile_action" value="details"><div class="pd-body"><div class="pd-grid"><?php foreach(['first_name'=>'First name *','last_name'=>'Last name *','middle_name'=>'Middle name'] as $key=>$label):?><div class="pd-field"><label for="profile-<?=$key?>"><?=$label?></label><input id="profile-<?=$key?>" name="<?=$key?>" type="<?=$key==='email'?'email':'text'?>" value="<?=htmlspecialchars($profile_user[$key]??'',ENT_QUOTES)?>" maxlength="<?=$key==='email'||$key==='middle_name'?100:50?>" <?=in_array($key,['first_name','last_name'])||$key==='email'&&$role!=='student'?'required':''?> autocomplete="<?=['first_name'=>'given-name','last_name'=>'family-name','middle_name'=>'additional-name','email'=>'email'][$key]?>"></div><?php endforeach;?></div></div><div class="pd-footer"><button type="button" class="pd-button" data-dialog-close="profileDetailsDialog">Cancel</button><button type="submit" class="pd-button pd-button-primary">Save changes</button></div></form></div></div>
<script>function openProfileDialog(id){const dialog=document.getElementById(id);window.PortalDialogs.open(dialog);dialog.querySelector('.pd-body input').focus({preventScroll:true});}</script>
