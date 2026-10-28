<?php
$isSystemAdmin=$role==='super_admin';
$pendingRequests=0;$pendingExams=0;$pendingCounseling=0;$agenda=[];
try {
    $pendingCounseling=(int)$db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status='pending'")->fetchColumn();
    $pendingExams=(int)$db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='pending'")->fetchColumn();
    $pendingRequests=$pendingCounseling+$pendingExams;
    $q=$db->query("SELECT preferred_time AS time,CONCAT(u.first_name,' ',u.last_name) AS title,'Entrance exam' AS category FROM entrance_exam_appointments e JOIN users u ON e.user_id=u.id WHERE e.preferred_date=CURDATE() AND e.status IN ('pending','confirmed') ORDER BY preferred_time LIMIT 5");
    $agenda=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$db->prepare("SELECT appointment_time AS time,CONCAT(u.first_name,' ',u.last_name) AS title,'Counseling' AS category FROM counseling_appointments c JOIN users u ON c.user_id=u.id WHERE c.appointment_date=CURDATE() AND c.status IN ('pending','confirmed','in_progress') ".($role==='guidance_advocate'?'AND c.assigned_advocate_id=? ':'')."ORDER BY appointment_time LIMIT 5");
    $q->execute($role==='guidance_advocate'?[$uid]:[]);$agenda=array_merge($agenda,$q->fetchAll(PDO::FETCH_ASSOC));
}catch(Exception $e){}
foreach($today_schedules??[] as $event){$agenda[]=['time'=>date('H:i:s',strtotime($event['start_datetime'])),'title'=>$event['title'],'category'=>ucwords(str_replace('_',' ',$event['event_type']))];}
usort($agenda,fn($a,$b)=>strcmp($a['time'],$b['time']));
$agendaCount=count($agenda);$agenda=array_slice($agenda,0,5);
$cards=$isSystemAdmin ? [
    ['Total users',$stats['total_users'],'Registered accounts','fa-users','user_management'],
    ['Active accounts',$stats['active_users'],'Enabled for sign-in','fa-user-check','user_management'],
    ['Students',$db->query("SELECT COUNT(*) FROM users WHERE role='student' AND COALESCE(archived,0)=0")->fetchColumn(),'Enrolled student accounts','fa-graduation-cap','user_management'],
    ['Examinees',$stats['total_examinees'],'Entrance exam applicants','fa-clipboard-list','user_management']
] : [
    ['Exams today',$stats['exams_today'],'Pending & confirmed','fa-clipboard-list','manage_exams'],
    ['Pending requests',$pendingRequests,'Counseling & exam requests','fa-inbox','manage_counseling'],
    ['Results to release',$stats['awaiting_results'],'Exams awaiting results','fa-file-circle-check','manage_exams'],
    ['Submitted PDS',$stats['submitted_pds'],'Personal data sheets on file','fa-file-lines','admin_view_pds']
];
$attentionCount=(int)($students_without_id??0)+($isSystemAdmin?0:$pendingRequests+(int)$stats['awaiting_results']);
?>
<section class="overview" aria-labelledby="overview-title">
    <div class="overview-intro">
        <div><p class="overview-eyebrow"><?=$isSystemAdmin?'System administration':'Your daily workspace'?></p><h1 id="overview-title"><?=$isSystemAdmin?'Superadmin dashboard':'Good '.((int)date('G')<12?'morning':((int)date('G')<18?'afternoon':'evening')).', '.htmlspecialchars($first_name).'.'?></h1><p><?=$isSystemAdmin?'Choose a task below. Each section explains what you can manage.':'Here’s what needs your attention today.'?></p></div>
        <a class="overview-button" href="layout.php?page=<?=$isSystemAdmin?'user_management':'schedules'?>"><i class="fas <?=$isSystemAdmin?'fa-users-gear':'fa-calendar-plus'?>" aria-hidden="true"></i><?=$isSystemAdmin?'Manage users':'Manage schedule'?></a>
    </div>
<?php if($isSystemAdmin):?>
<h2 style="font-size:17px;font-weight:650;margin:0 0 14px">What would you like to do?</h2>
<nav class="sa-home-choices" aria-label="Superadmin tasks">
    <a class="sa-choice" href="layout.php?page=user_management"><span class="sa-choice-icon"><i class="fas fa-users-gear" aria-hidden="true"></i></span><div><h2>Manage users & accounts</h2><p>Add users, identify their roles, update account details, or manage sign-in access.</p><span class="sa-choice-action">Open user management <i class="fas fa-arrow-right" aria-hidden="true"></i></span></div></a>
    <a class="sa-choice academics" href="layout.php?page=academic_settings"><span class="sa-choice-icon"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span><div><h2>Set up academics</h2><p>Manage departments, college programs, SHS strands, and grade or year levels.</p><span class="sa-choice-action">Open academic settings <i class="fas fa-arrow-right" aria-hidden="true"></i></span></div></a>
    <a class="sa-choice backups" href="layout.php?page=backup_restore"><span class="sa-choice-icon"><i class="fas fa-database" aria-hidden="true"></i></span><div><h2>Back up or recover data</h2><p>Create and download a database copy, or restore an earlier backup when needed.</p><span class="sa-choice-action">Open backup & restore <i class="fas fa-arrow-right" aria-hidden="true"></i></span></div></a>
    <a class="sa-choice logs" href="layout.php?page=system_logs"><span class="sa-choice-icon"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i></span><div><h2>Review system activity</h2><p>Find sign-ins, account changes, warnings, and other recorded events.</p><span class="sa-choice-action">Open activity logs <i class="fas fa-arrow-right" aria-hidden="true"></i></span></div></a>
</nav>
<?php endif;?>

    <?php include __DIR__.'/counselor_tasks.php';?>
    <div class="overview-stats" aria-label="Overview statistics">
        <?php foreach($cards as [$label,$value,$description,$icon,$destination]):?><a href="layout.php?page=<?=$destination?>" class="overview-stat"><div class="overview-stat-top"><span class="overview-stat-label"><?=$label?></span><span class="overview-stat-icon"><i class="fas <?=$icon?>" aria-hidden="true"></i></span></div><strong class="overview-stat-number"><?=number_format((int)$value)?></strong><span class="overview-stat-description"><?=$description?></span></a><?php endforeach;?>
    </div>
    <div class="overview-grid">
        <section class="overview-panel overview-attention" aria-labelledby="attention-title">
            <div class="overview-panel-header"><h2 id="attention-title">Needs attention</h2><span class="overview-pill <?=$attentionCount?'attention':''?>"><?=$attentionCount?> <?=$attentionCount===1?'item':'items'?></span></div>
            <div class="overview-attention-list">
            <?php if((int)($students_without_id??0)>0):?><button type="button" class="overview-attention-item" onclick="openMissingIdModal()"><span class="overview-attention-icon"><i class="fas fa-id-card" aria-hidden="true"></i></span><span><strong>Missing student IDs</strong><small><?=(int)$students_without_id?> students need their records completed</small></span><i class="fas fa-arrow-right" aria-hidden="true"></i></button><?php endif;?>
            <?php if(!$isSystemAdmin && $pendingCounseling):?><a href="layout.php?page=manage_counseling" class="overview-attention-item"><span class="overview-attention-icon"><i class="fas fa-comments" aria-hidden="true"></i></span><span><strong>Counseling requests</strong><small><?=$pendingCounseling?> waiting for review</small></span><i class="fas fa-arrow-right" aria-hidden="true"></i></a><?php endif;?>
            <?php if(!$isSystemAdmin && $pendingExams):?><a href="layout.php?page=manage_exams" class="overview-attention-item"><span class="overview-attention-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></span><span><strong>Exam requests</strong><small><?=$pendingExams?> waiting for confirmation</small></span><i class="fas fa-arrow-right" aria-hidden="true"></i></a><?php endif;?>
            <?php if(!$isSystemAdmin && (int)$stats['awaiting_results']):?><a href="layout.php?page=manage_exams" class="overview-attention-item"><span class="overview-attention-icon"><i class="fas fa-file-circle-check" aria-hidden="true"></i></span><span><strong>Results ready for processing</strong><small><?=(int)$stats['awaiting_results']?> exams awaiting release</small></span><i class="fas fa-arrow-right" aria-hidden="true"></i></a><?php endif;?>
            </div>
            <?php if(!$attentionCount):?><div class="overview-empty"><span class="overview-empty-icon"><i class="far fa-circle-check" aria-hidden="true"></i></span><strong>You’re up to date</strong><p>No outstanding items need your attention.</p></div><?php endif;?>
            <div class="overview-shortcuts" aria-label="Quick links">
                <?php if($isSystemAdmin):?><a href="layout.php?page=academic_settings"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Academics</a><a href="layout.php?page=system_logs"><i class="fas fa-file-lines" aria-hidden="true"></i>System logs</a>
                <?php else:?><a href="layout.php?page=manage_counseling"><i class="far fa-comment-dots" aria-hidden="true"></i>Counseling</a><a href="layout.php?page=manage_exams"><i class="far fa-file-lines" aria-hidden="true"></i>Exams</a><a href="layout.php?page=student_records"><i class="fas fa-users" aria-hidden="true"></i>Students</a><?php endif;?>
            </div>
        </section>
        <section class="overview-panel overview-schedule" aria-labelledby="schedule-title">
            <div class="overview-panel-header"><div><h2 id="schedule-title"><?=$isSystemAdmin?'System at a glance':'Today’s schedule'?></h2><p><?=date('l, F j, Y')?></p></div><?php if(!$isSystemAdmin):?><a href="layout.php?page=schedules" class="overview-link">View schedule <i class="fas fa-arrow-right" aria-hidden="true"></i></a><?php endif;?></div>
            <?php if($isSystemAdmin):?><div class="overview-empty"><span class="overview-empty-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span><strong>A clear view of your system</strong><p>Review account activity, keep academic settings current, and maintain regular database backups.</p><a href="layout.php?page=backup_restore" class="overview-button secondary">Backup & restore</a></div>
            <?php elseif(!$agenda):?><div class="overview-empty"><span class="overview-empty-icon"><i class="far fa-calendar-check" aria-hidden="true"></i></span><strong>A clear day ahead</strong><p>No exams, counseling appointments, or events are scheduled for today.</p><a href="layout.php?page=schedules" class="overview-link">Plan an upcoming activity <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
            <?php else:foreach($agenda as $event):?><div class="overview-agenda-item"><time class="overview-agenda-time"><?=date('g:i A',strtotime($event['time']))?></time><div class="overview-agenda-detail"><strong><?=htmlspecialchars($event['title'])?></strong><span><?=htmlspecialchars($event['category'])?></span></div></div><?php endforeach;if($agendaCount>5):?><div class="overview-shortcuts"><a href="layout.php?page=schedules">View all <?=$agendaCount?> activities</a></div><?php endif;endif;?>
        </section>
        <section class="overview-panel overview-activity" aria-labelledby="activity-title">
            <div class="overview-panel-header"><h2 id="activity-title">Recent activity</h2><span class="overview-pill">Last 30 days</span></div>
            <?php foreach(array_slice($recent??[],0,4) as $activity):$verbs=['registration'=>'registered as '.str_replace('_',' ',$activity['extra']),'counseling'=>'requested a counseling appointment','exam'=>'submitted an exam application'];$icons=['registration'=>'fa-user-plus','counseling'=>'fa-comment','exam'=>'fa-clipboard-list'];?>
            <div class="overview-activity-row"><span class="overview-activity-dot"><i class="fas <?=$icons[$activity['type']]??'fa-clock'?>" aria-hidden="true"></i></span><p class="overview-activity-text"><strong><?=htmlspecialchars($activity['first_name'].' '.$activity['last_name'])?></strong> <?=htmlspecialchars($verbs[$activity['type']]??'updated a record')?>.</p><time datetime="<?=htmlspecialchars($activity['created_at'])?>"><?=htmlspecialchars(timeAgo($activity['created_at']))?></time></div>
            <?php endforeach;if(empty($recent)):?><div class="overview-empty"><p>New registrations and appointment requests will appear here.</p></div><?php endif;?>
        </section>
    </div>
    <div class="overview-footer"><span>St. Rita’s College of Balingasag · Guidance Office</span><span><?=htmlspecialchars(ucwords(str_replace('_',' ',$role)))?> workspace</span></div>
</section>
