<?php
// Keep role-specific destinations in one navigation definition.
$navGroups = [];
if (in_array($role,['admin','guidance_advocate'],true)) {
    $navGroups = [
        'Workspace' => [
            ['dashboard','Office overview','fa-house'],
            ['students','Students & records','fa-users',[
                ['student_records','Student records'],['student_list_report','Student lists & printing'],
                ['admin_view_pds','Personal data sheets'],['counseling_history','Counseling history'],['survey_results','Survey results']
            ]],
            ['manage_counseling','Counseling','fa-comments'],
            ['manage_exams','Exam appointments','fa-clipboard-list']
        ],
        'Planning' => [['schedules','Schedule','fa-calendar-days'],['manage_announcements','Announcements','fa-bullhorn']],
        'Insights' => [['reports','Reports & insights','fa-chart-simple',[
            ['reports','Reports overview'],['examinee_reports','Examinee reports'],['ses_analytics','SES analytics'],['olsat_reports','OLSAT reports'],['counseling_reports','Counseling reports']
        ]]]
    ];
    if ($role==='admin') $navGroups['Administration']=[['system_settings','System settings','fa-gear']];
} elseif ($role==='super_admin') {
    $navGroups=['Workspace'=>[['dashboard','Superadmin home','fa-house'],['user_management','Users & accounts','fa-users-gear'],['academic_settings','Academic settings','fa-graduation-cap']],
        'Administration'=>[['backup_restore','Backup & restore','fa-database'],['system_logs','Activity logs','fa-file-lines']]];
} elseif ($role==='student') {
    $navGroups=['My workspace'=>[['dashboard','Overview','fa-house'],['view_pds','Personal data sheet','fa-file-lines'],['counseling','Counseling','fa-comments',[
        ['book_appointment','Book an appointment'],['view_appointments','My appointments']
    ]]]];
    $sg=$db->prepare('SELECT grade_level FROM student_profiles WHERE user_id=?');$sg->execute([$uid]);
    if(in_array($sg->fetchColumn(),['Grade 11','Grade 12','1st Year College','2nd Year College','3rd Year College','4th Year College'],true)) $navGroups['My workspace'][]=['multiple_intelligence_survey','Intelligence survey','fa-brain'];
} else {
    $completed=$db->prepare("SELECT id FROM entrance_exam_appointments WHERE user_id=? AND status='completed' AND (total_score IS NOT NULL OR qualified_grade IS NOT NULL) LIMIT 1");$completed->execute([$uid]);
    $examLink=$completed->fetchColumn()?['view_exam_results','Exam results','fa-certificate']:['book_exam','Book an exam','fa-calendar-plus'];
    $navGroups=['My application'=>[['dashboard','Overview','fa-house'],$examLink,['view_application','Application status','fa-clipboard-list']]];
}
if($role==='guidance_advocate')foreach($navGroups as &$office_links)foreach($office_links as &$office_link)if(isset($office_link[3]))$office_link[3]=array_values(array_filter($office_link[3],fn($child)=>$child[0]!=='counseling_history'));
unset($office_links,$office_link);
?>
<aside id="sidebar" class="portal-sidebar" aria-label="Main navigation">
    <a class="portal-brand" href="layout.php?page=dashboard" aria-label="SRCB Guidance home">
        <img src="../assets/images/srcblogo.png" alt="SRCB seal" width="42" height="42">
        <span><strong>SRCB Guidance</strong><small>Care. Support. Growth.</small></span>
    </a>
    <button type="button" class="portal-drawer-close" id="sidebarClose" aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <nav class="portal-nav">
    <?php foreach($navGroups as $group=>$links): ?>
        <div class="portal-nav-group"><p class="portal-nav-label"><?=htmlspecialchars($group)?></p>
        <?php foreach($links as $link): $children=$link[3]??[];$childPages=array_column($children,0);
            $active=$page===$link[0] || in_array($page,$childPages,true) || (in_array($link[0],['fill_pds','view_pds'],true)&&in_array($page,['fill_pds','view_pds'],true)) || ($link[0]==='manage_exams'&&$page==='release_results'); ?>
            <?php if($children): ?>
                <button type="button" class="portal-nav-link <?=$active?'is-active':''?>" data-nav-group="<?=$link[0]?>" aria-controls="<?=$link[0]?>-submenu" aria-expanded="<?=$active?'true':'false'?>">
                    <i class="fas <?=$link[2]?>" aria-hidden="true"></i><span class="cw-nav-copy"><?=htmlspecialchars($link[1])?><?php if(in_array($role,['admin','guidance_advocate'],true)):?><small><?=$link[0]==='students'?'Profiles, PDS & assessments':'Trends, results & printable lists'?></small><?php endif;?></span><i class="fas fa-chevron-down portal-chevron aria-hidden="true"></i>
                </button>
                <div id="<?=$link[0]?>-submenu" class="portal-submenu <?=$active?'':'hidden'?>">
                <?php foreach($children as $child): ?><a href="layout.php?page=<?=$child[0]?>" class="<?=$page===$child[0]?'is-current':''?>" <?=$page===$child[0]?'aria-current="page"':''?>><?=htmlspecialchars($child[1])?></a><?php endforeach;?>
                </div>
            <?php else: ?>
                <a href="layout.php?page=<?=$link[0]?>" class="portal-nav-link <?=$active?'is-active':''?>" <?=$active?'aria-current="page"':''?>><i class="fas <?=$link[2]?>" aria-hidden="true"></i><span class="<?=$role==='super_admin'?'sa-nav-copy':(in_array($role,['admin','guidance_advocate'],true)?'cw-nav-copy':'')?>"><?=htmlspecialchars($link[1])?><?php if(in_array($role,['admin','guidance_advocate'],true)): $office_nav_hints=['dashboard'=>'Today’s priorities & tasks','manage_counseling'=>'Requests, sessions & follow-ups','manage_exams'=>'Applicants, attendance & results','schedules'=>'Events, holidays & booking limits','manage_announcements'=>'Publish guidance updates','system_settings'=>'Services & homepage notice'];?><small><?=htmlspecialchars($office_nav_hints[$link[0]]??'')?></small><?php endif;?><?php if($role==='super_admin'): $nav_hints=['dashboard'=>'Choose a task','user_management'=>'People, roles & access','academic_settings'=>'School structure & levels','backup_restore'=>'Save or recover records','system_logs'=>'Who did what, and when'];?><small><?=htmlspecialchars($nav_hints[$link[0]]??'')?></small><?php endif;?></span></a>
            <?php endif;?>
        <?php endforeach;?></div>
    <?php endforeach;?>
    </nav>
    <div class="portal-sidebar-footer"><span class="portal-avatar"><?=htmlspecialchars($initials)?></span><span class="portal-user"><strong><?=htmlspecialchars($first_name.' '.$last_name)?></strong><small><?=htmlspecialchars(ucwords(str_replace('_',' ',$role)))?></small></span><a href="../auth/logout.php" aria-label="Log out" title="Log out"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i></a></div>
</aside>
