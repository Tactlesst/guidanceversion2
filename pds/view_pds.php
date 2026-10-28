<?php
$in_layout=defined('IN_LAYOUT');
if(!$in_layout){require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/session.php';checkLogin();$user_info=getUserInfo();if(!in_array($user_info['role'],['student','examinee'],true)){header('Location: ../dashboard/layout.php');exit;}$db=(new Database())->getConnection();}
require_once __DIR__.'/../includes/pds_paper.php';
$uid=$user_info['id'];
$stmt=$db->prepare('SELECT p.*,u.first_name AS account_first_name,u.middle_name AS account_middle_name,u.last_name AS account_last_name,u.email AS account_email,sp.student_id,sp.grade_level AS profile_grade,sp.program AS profile_program,sp.strand AS profile_strand FROM pds p JOIN users u ON p.user_id=u.id LEFT JOIN student_profiles sp ON p.user_id=sp.user_id WHERE p.user_id=? LIMIT 1');$stmt->execute([$uid]);$data=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$data){header('Location: '.($in_layout?'layout.php?page=fill_pds':'fill_pds.php'));exit;}
$related=[];foreach(['pds_siblings','pds_organizations','pds_test_results'] as $table){try{$s=$db->prepare("SELECT * FROM $table WHERE user_id=? ORDER BY id");$s->execute([$uid]);$related[$table]=$s->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){$related[$table]=[];}}
$base=PDSPaperDocument::baseUrl();$fill_pds_url=$in_layout?'layout.php?page=fill_pds':'fill_pds.php';$dashboard_url=$in_layout?'layout.php?page=dashboard':'../dashboard/layout.php?page=dashboard';
$paperMode=($_GET['format']??'')==='paper';
$digital_url=$in_layout?'layout.php?page=view_pds':'view_pds.php';$paper_url=$digital_url.(str_contains($digital_url,'?')?'&':'?').'format=paper';
if(!$paperMode){
    require_once __DIR__.'/../includes/pds_digital_view.php';
    if(!$in_layout)echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Personal Data Sheet</title></head><body>';
    echo PDSDigitalView::render($data,$related,$base,$fill_pds_url,$paper_url);
    if(!$in_layout)echo '</body></html>';return;
}
foreach(['first_name'=>'account_first_name','middle_name'=>'account_middle_name','last_name'=>'account_last_name','email'=>'account_email','grade_level'=>'profile_grade','course'=>'profile_program','strand'=>'profile_strand'] as $field=>$fallback)if(trim((string)($data[$field]??''))==='')$data[$field]=$data[$fallback]??'';
if(!$in_layout):?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Personal Data Sheet</title><link rel="stylesheet" href="<?=PDSPaperDocument::e($base)?>assets/css/pds-paper.css?v=20261028-4"><script src="<?=PDSPaperDocument::e($base)?>assets/js/pds-paper.js?v=20261028-2" defer></script></head><body><?php endif;?>
<div class="pds-own-view" id="ownPDSPaper"><div class="pds-own-actions"><h1>My Personal Data Sheet</h1><div><a href="<?=PDSPaperDocument::e($fill_pds_url)?>">Update information</a><button type="button" id="printOwnPDS">Print paper PDS</button><a href="<?=PDSPaperDocument::e($digital_url)?>">Digital PDS</a><a href="<?=PDSPaperDocument::e($dashboard_url)?>">Dashboard</a></div></div><?php readfile(__DIR__.'/../includes/pds_paper_toolbar.php');?><div class="pds-document-viewport"><?=PDSPaperDocument::render($data,$related['pds_siblings'],$related['pds_organizations'],$related['pds_test_results'],$base)?></div></div>
<script>(()=>{function initialize(){const scope=document.getElementById('ownPDSPaper');if(!scope)return;const root=scope.querySelector('[data-pds-document]');window.PDSPaper.mount(root,scope).catch(()=>{});document.getElementById('printOwnPDS').addEventListener('click',()=>window.PDSPaper.print(root).catch(error=>{scope.querySelector('[data-pds-status]').textContent=error.message;}));}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize,{once:true});else initialize();})();</script>
<?php if(!$in_layout):?></body></html><?php endif;?>
