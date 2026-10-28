<?php
/** Shared escaped markup for the initial board and AJAX refreshes. */
final class ApprovalBoardRenderer {
    public static function revision(array $listing,string $role):string{return hash('sha256',json_encode([$role,$listing],JSON_THROW_ON_ERROR));}
    public static function html(array $listing,string $role,string $csrf):string {
        $ae=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$resourceLabels=['examinees'=>'Examinee report','ses'=>'SES analytics','olsat'=>'OLSAT report','counseling'=>'Counseling report','student_list'=>'Student list','assessment'=>'Assessment profile','pds'=>'Personal data sheet','pds_version'=>'Saved school-year PDS'];
$routes=['examinees'=>'examinee_reports','ses'=>'ses_analytics','olsat'=>'olsat_reports','counseling'=>'counseling_reports'];

ob_start();?><div data-approval-items><?php if(!$listing['rows']):?><div class="oa-empty"><h2>No requests yet</h2><p>Advocate print and export requests will appear here.</p></div><?php endif;?>
<?php foreach($listing['rows'] as $request):$scope=json_decode($request['scope_json'],true)?:[];$status=$request['status'];$resource=$request['resource'];?>
<article class="oa-request" data-approval-request="<?=(int)$request['id']?>" data-approval-revision="<?=hash('sha256',json_encode($request))?>"><header><div><span class="oa-eyebrow">REQUEST #<?=(int)$request['id']?></span><h2><?=$ae($resourceLabels[$resource]??$resource)?> · <?=$ae(strtoupper($request['output_format']))?></h2><p><?=$ae(trim($request['first_name'].' '.$request['last_name']))?> · <?=$ae($request['requested_at'])?></p></div><strong class="oa-status" data-status="<?=$ae($status)?>"><?=$ae(ucfirst($status))?></strong></header>
<details><summary>Requested filters and document options</summary><dl class="oa-scope"><?php foreach($scope as $key=>$value):if($value===''||$value===null)continue;?><div><dt><?=$ae(ucwords(str_replace('_',' ',$key)))?></dt><dd><?=$ae(is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):(is_bool($value)?($value?'Yes':'No'):$value))?></dd></div><?php endforeach;?></dl></details>
<?php if($request['decided_at']):?><p class="oa-decision">Decision: <?=$ae(trim(($request['admin_first']??'').' '.($request['admin_last']??''))?:'Counselor/admin')?> · <?=$ae($request['decided_at'])?><?=$request['decision_note']?' · '.$ae($request['decision_note']):''?></p><?php endif;?>
<?php if($request['used_at']):?><p class="oa-decision">Output prepared: <?=$ae($request['used_at'])?></p><?php endif;?>
<?php if(in_array($role,['admin','super_admin'],true)&&in_array($status,['pending','approved'],true)):?><form method="post" data-portal-save class="oa-decide"><input type="hidden" name="csrf" value="<?=$ae($csrf)?>"><input type="hidden" name="approval_id" value="<?=(int)$request['id']?>"><label>Decision note (optional)<input name="note" maxlength="500" placeholder="Instructions for this request"></label><div><?php if($status==='pending'):?><button type="submit" name="decision" value="approved" class="oa-primary">Approve once</button><button type="submit" name="decision" value="rejected">Reject</button><?php else:?><button type="submit" name="decision" value="revoked">Revoke unused approval</button><?php endif;?></div></form><?php endif;?>
<?php if($role==='guidance_advocate'&&$status==='approved'):?>
<?php if(isset($routes[$resource])):?><a class="oa-link" href="layout.php?page=<?=$routes[$resource]?>&amp;approval_run=<?=(int)$request['id']?>">Open approved report</a>
<?php elseif($resource==='assessment'):?><a class="oa-link" href="layout.php?page=survey_results&amp;open_type=<?=$ae($scope['type'])?>&amp;open_id=<?=(int)$scope['id']?>">Open approved assessment</a>
<?php elseif($resource==='pds_version'):?><a class="oa-link" href="layout.php?page=pds_history&amp;user_id=<?=(int)$scope['user_id']?>&amp;version=<?=(int)$scope['version']?>&amp;format=paper&amp;paper=<?=$ae($scope['paper'])?>">Open approved PDS version</a><?php elseif($resource==='pds'):?><a class="oa-link" href="layout.php?page=admin_view_pds&amp;open_pds=<?=(int)$scope['id']?>">Open personal data sheet</a><?php else:?><a class="oa-link" href="layout.php?page=student_list_report&amp;approval_run=<?=(int)$request['id']?>">Open student list</a><?php endif;?>
<?php endif;?></article><?php endforeach;?>
</div><nav class="oa-pagination" aria-label="Approval pages"><span>Page <?=$listing['page']?> of <?=$listing['pages']?></span><?php if($listing['page']>1):?><a href="layout.php?page=approvals&amp;p=<?=$listing['page']-1?>">Previous</a><?php endif;?><?php if($listing['page']<$listing['pages']):?><a href="layout.php?page=approvals&amp;p=<?=$listing['page']+1?>">Next</a><?php endif;?></nav><?php return ob_get_clean();
    }
}
