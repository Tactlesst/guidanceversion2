<?php
if(!defined('IN_LAYOUT')||!in_array($role,['admin','super_admin','guidance_advocate'],true)){http_response_code(403);return;}
require_once __DIR__.'/../../../classes/ReportApproval.php';require_once __DIR__.'/../../../classes/ApprovalBoardRenderer.php';
$approvalService=new ReportApproval($db);$ae=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['office_approval_csrf'],$_POST['csrf']))throw new RuntimeException('Refresh the page before deciding a request.');
        $approvalService->decide((int)$uid,(int)($_POST['approval_id']??0),(string)($_POST['decision']??''),(string)($_POST['note']??''));
        $_SESSION['success_message']='Approval decision saved.';header('Location: layout.php?page=approvals');exit;
    }catch(Throwable $e){http_response_code(403);$error=$e instanceof PDOException?'The decision could not be saved.':$e->getMessage();}
}
$listing=$approvalService->listing((int)$uid,$role,max(1,(int)($_GET['p']??1)));
?>
<link rel="stylesheet" href="../assets/css/office-approvals.css?v=20261029-wait">
<section class="oa-panel" id="approvalLiveBoard" data-approval-page="<?=$listing['page']?>" data-approval-revision="<?=ApprovalBoardRenderer::revision($listing,$role)?>" data-approval-csrf="<?=$ae($_SESSION['office_approval_csrf'])?>"><header class="oa-heading"><div><p class="oa-eyebrow">COUNSELOR REVIEW</p><h1><?=$role==='guidance_advocate'?'My report approvals':'Report approvals'?></h1><p>One approval permits one generated printout or export, for the specified report, format and options.</p></div><span data-approval-total><?=$listing['total']?> requests</span></header>
<?php if($error):?><p class="oa-error" role="alert"><?=$ae($error)?></p><?php endif;?>
<?php if(!empty($_SESSION['success_message'])):?><p class="oa-notice" role="status"><?=$ae($_SESSION['success_message'])?></p><?php unset($_SESSION['success_message']);endif;?>
<p class="oa-notice"><?=$role==='guidance_advocate'?'Send one request at a time using a report’s Print or Export button. Wait for a decision before requesting again. After rejection, wait five seconds; after approval, use the same report options for one output.':'Review the requested scope before approving. Changing filters or output format requires another approval. You can revoke an unused approval.'?></p>
<div class="oa-live-toolbar"><p class="oa-live-status" data-approval-live-status role="status" aria-live="polite">Live updates · Checks every 5 seconds while this page is visible.</p><button type="button" class="oa-refresh" data-approval-refresh>Refresh now</button></div><p class="oa-error" data-approval-live-error role="alert" hidden></p><div data-approval-list><?=ApprovalBoardRenderer::html($listing,$role,$_SESSION['office_approval_csrf'])?></div></section>

<script src="../assets/js/approvals-live.js?v=20261029-2"></script>
