<?php
require_once __DIR__.'/../../../classes/system/SystemLogger.php';

$logger = new SystemLogger($db);

// POST: clear logs
if ($_POST) {
    if (isset($_POST['clear_old_logs'])) {
        $days = (int)($_POST['days'] ?? 30);
        $logger->cleanOldLogs($days);
        $_SESSION['success_message'] = "Logs older than $days days cleared!";
        header("Location: " . portalSaveRedirect("layout.php?page=system_logs")); exit();
    }
    if (isset($_POST['clear_all_logs'])) {
        $db->prepare("DELETE FROM system_logs")->execute();
        $_SESSION['success_message'] = "All logs cleared!";
        header("Location: " . portalSaveRedirect("layout.php?page=system_logs")); exit();
    }
}

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Normalize URL filters and reuse the same date/search scope for the table and type counts.
$event_labels=['login'=>'Sign-ins','logout'=>'Sign-outs','warning'=>'Warnings','error'=>'Errors','info'=>'Information','success'=>'Successful actions','admin_action'=>'Staff changes','system'=>'System events'];
$filter_type=is_string($_GET['type']??null)?$_GET['type']:'all';
if($filter_type!=='all'&&!array_key_exists($filter_type,$event_labels))$filter_type='all';
$filter_date=is_string($_GET['date']??null)?trim($_GET['date']):'';
if($filter_date!==''){$parsed_date=DateTimeImmutable::createFromFormat('!Y-m-d',$filter_date);if(!$parsed_date||$parsed_date->format('Y-m-d')!==$filter_date)$filter_date='';}
$search=is_string($_GET['search']??null)?trim($_GET['search']):'';
$pg=max(1,is_scalar($_GET['log_page']??1)?(int)($_GET['log_page']??1):1);
$per_page=10;
$scope=["COALESCE(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(sl.additional_data) THEN sl.additional_data ELSE '{}' END,'$.action')),'')<>'open_page'"];$scope_params=[];
if($filter_date!==''){$scope[]="sl.created_at >= ? AND sl.created_at < ?";$scope_params[]=$filter_date.' 00:00:00';$scope_params[]=(new DateTimeImmutable($filter_date))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
if($search!==''){
    $scope[]="(sl.message LIKE ? ESCAPE '!' OR u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!' OR CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name) LIKE ? ESCAPE '!' OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
    $pattern='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    for($i=0;$i<6;$i++)$scope_params[]=$pattern;
}
$where=$scope;$params=$scope_params;
if($filter_type!=='all'){$where[]="sl.log_type = ?";$params[]=$filter_type;}
$where_clause=$where?'WHERE '.implode(' AND ',$where):'';

// Count
$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $where_clause");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, (int)ceil($total_logs / $per_page));
$pg = min($pg,$total_pages);
$offset = ($pg - 1) * $per_page;

// Get logs
$stmt = $db->prepare("SELECT sl.*, u.first_name, u.last_name, u.email, u.role AS account_role FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $where_clause ORDER BY sl.created_at DESC,sl.id DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary
$scope_clause=$scope?'WHERE '.implode(' AND ',$scope):'';
$summary_stmt=$db->prepare("SELECT sl.log_type,COUNT(*) AS count FROM system_logs sl LEFT JOIN users u ON sl.user_id=u.id $scope_clause GROUP BY sl.log_type ORDER BY sl.log_type");
$summary_stmt->execute($scope_params);$summary=$summary_stmt->fetchAll(PDO::FETCH_ASSOC);
$scope_total=array_sum(array_column($summary,'count'));

$log_colors = ['login'=>'blue','logout'=>'gray','warning'=>'yellow','error'=>'red','info'=>'cyan','success'=>'green','admin_action'=>'purple'];
?>


<link rel="stylesheet" href="../assets/css/staff-activity.css?v=20261028-1"><p><a class="st-link" href="layout.php?page=system_settings&amp;section=activity">View guidance counselor &amp; advocate activity</a></p>
<section class="sa-page" aria-labelledby="sa-logs-title">
    <div class="sa-page-heading"><div><p class="sa-eyebrow">Accountability & activity</p><h1 id="sa-logs-title">System activity logs</h1><p class="sa-subtitle">See who signed in, what changed, and when it happened. Use the filters to find a specific event.</p></div><span class="sa-role-label">Superadmin access</span></div>
    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <p class="sa-filter-help">Activity counts use the selected date and search. Choosing an activity type keeps those filters.</p>
    <div class="sa-log-summary" aria-label="Filter by activity type">
    <a href="layout.php?<?=htmlspecialchars(http_build_query(['page'=>'system_logs','type'=>'all','date'=>$filter_date,'search'=>$search]),ENT_QUOTES)?>" class="<?=$filter_type==='all'?'is-selected':''?>" <?=$filter_type==='all'?'aria-current="true"':''?>>All activities<strong><?=number_format((int)$scope_total)?></strong></a>
    <?php foreach($summary as $entry):$log_type=$entry['log_type'];?><a href="layout.php?<?=htmlspecialchars(http_build_query(['page'=>'system_logs','type'=>$log_type,'date'=>$filter_date,'search'=>$search]),ENT_QUOTES)?>" class="<?=$filter_type===$log_type?'is-selected':''?>" <?=$filter_type===$log_type?'aria-current="true"':''?>><?=htmlspecialchars($event_labels[$log_type]??ucwords(str_replace('_',' ',$log_type)))?><strong><?=number_format((int)$entry['count'])?></strong></a><?php endforeach;?>
    </div>
    <section class="sa-panel"><div class="sa-panel-title"><div><h2>Find an activity</h2><p>Search a person or message, then narrow the results by activity type or date.</p></div></div><div class="sa-panel-body"><form method="get" action="layout.php" class="sa-log-filters"><input type="hidden" name="page" value="system_logs"><label class="sa-field">Person, email, or message<input type="search" name="search" value="<?=htmlspecialchars($search,ENT_QUOTES)?>" placeholder="Name, email, or activity message"></label><label class="sa-field">Activity type<select name="type"><option value="all">All activities</option><?php foreach($event_labels as $value=>$label):?><option value="<?=$value?>" <?=$filter_type===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><label class="sa-field">Date<input type="date" name="date" value="<?=htmlspecialchars($filter_date,ENT_QUOTES)?>"></label><button class="sa-button primary" type="submit">Apply filters</button><a class="sa-button" href="layout.php?page=system_logs">Reset</a></form></div></section>
    <section class="sa-panel"><div class="sa-panel-title"><div><h2><?=$filter_type==='all'?'Activity history':htmlspecialchars($event_labels[$filter_type]??'Filtered activities')?></h2><p><?=number_format((int)$total_logs)?> matching <?=((int)$total_logs===1)?'record':'records'?> · Newest first</p><p class="sa-filter-help" id="logFilterContext">Date: <?=$filter_date!==''?htmlspecialchars(date('M j, Y',strtotime($filter_date))):'All dates'?> · Search: <?=$search!==''?'“'.htmlspecialchars($search).'”':'Any person or message'?></p></div></div><div class="sa-table-wrap"><table class="sa-table sa-activity-table"><thead><tr><th>When</th><th>Who</th><th>Activity</th><th>What happened</th><th>IP address</th></tr></thead><tbody>
    <?php foreach($logs as $log):$logMeta=json_decode($log['additional_data']??'{}',true);$actorLogRole=is_array($logMeta)?($logMeta['actor_role']??$log['account_role']??''):($log['account_role']??'');$staffRoleLabels=['admin'=>'Guidance counselor','guidance_advocate'=>'Guidance advocate','super_admin'=>'Superadmin'];?><tr data-log-id="<?=(int)$log['id']?>"><td data-label="When"><strong><?=date('M j, Y',strtotime($log['created_at']))?></strong><small><?=date('g:i:s A',strtotime($log['created_at']))?></small></td><td data-label="Who"><?=htmlspecialchars(trim(($log['first_name']??'System').' '.($log['last_name']??'')))?><?php if(isset($staffRoleLabels[$actorLogRole])):?><small><?=htmlspecialchars($staffRoleLabels[$actorLogRole])?></small><?php endif;?></td><td data-label="Activity"><span class="sa-log-badge <?=htmlspecialchars(in_array($log['log_type'],['warning','error','admin_action'],true)?$log['log_type']:'info')?>"><?=htmlspecialchars($event_labels[$log['log_type']]??ucwords(str_replace('_',' ',$log['log_type'])))?></span></td><td class="sa-message" data-label="What happened"><?=htmlspecialchars($log['message'])?></td><td data-label="IP address"><?=htmlspecialchars($log['ip_address']??'Not recorded')?><?php if(SystemLogger::isLoopbackAddress($log['ip_address']??null)):?><small>This computer (localhost)</small><?php endif;?></td></tr><?php endforeach;?>
    <?php if(!$logs):?><tr><td colspan="5" class="sa-empty"><strong>No matching activity</strong><p>Try another date, name, or activity type.</p></td></tr><?php endif;?>
    </tbody></table></div><div class="sa-pagination"><span>Page <?=$pg?> of <?=max(1,(int)$total_pages)?></span><nav class="sa-page-links" aria-label="Activity pages"><?php for($i=max(1,$pg-2);$i<=min((int)$total_pages,$pg+2);$i++):?><a href="layout.php?<?=htmlspecialchars(http_build_query(['page'=>'system_logs','type'=>$filter_type,'date'=>$filter_date,'search'=>$search,'log_page'=>$i]),ENT_QUOTES)?>" <?=$i===$pg?'aria-current="page"':''?> aria-label="Activity page <?=$i?>"><?=$i?></a><?php endfor;?></nav></div></section>
    <details class="sa-maintenance"><summary>Log maintenance — remove old activity records</summary><div class="sa-maintenance-content"><div><p>Remove older log entries while keeping recent activity. This removes log history only; user accounts stay in place.</p><form data-portal-save method="post" data-confirm-title="Remove older activity logs?" data-confirm-message="Activity older than the selected period will be permanently removed. User accounts stay in place." data-confirm-label="Remove older logs"><input type="hidden" name="clear_old_logs" value="1"><label class="sa-field">Keep the most recent<select name="days"><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option><option value="180">180 days</option></select></label><button type="submit" class="sa-button danger">Remove older logs</button></form></div><div><p>Clear the entire activity history. Deleted log entries cannot be recovered through this page.</p><form data-portal-save method="post" data-confirm-title="Delete all activity logs?" data-confirm-message="This clears the entire recorded activity history and cannot be undone through this page." data-confirm-label="Delete all logs"><input type="hidden" name="clear_all_logs" value="1"><button type="submit" class="sa-button danger">Delete all activity logs</button></form></div></div></details>
</section>
