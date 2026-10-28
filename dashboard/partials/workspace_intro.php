<?php
// Each destination explains its purpose before presenting its records and controls.
$counselor_pages=[
 'reports'=>['Reports','Reports & insights','Prepare filtered school reports and exports.','fa-chart-simple','reports'],
 'examinee_reports'=>['Reports','Examinee reports','Review examination outcomes and printable lists.','fa-clipboard-check','reports'],
 'reports'=>['Reports','Reports & insights','Prepare filtered school reports and exports.','fa-chart-simple','reports'],
 'examinee_reports'=>['Reports','Examinee reports','Review examination outcomes and printable lists.','fa-clipboard-check','reports'],
 'reports'=>['Reports','Reports & insights','Prepare filtered school reports and exports.','fa-chart-simple','reports'],
 'examinee_reports'=>['Reports','Examinee reports','Review examination outcomes and printable lists.','fa-clipboard-check','reports'],
 'reports'=>['Reports','Reports & insights','Prepare filtered school reports and exports.','fa-chart-simple','reports'],
 'examinee_reports'=>['Reports','Examinee reports','Review examination outcomes and printable lists.','fa-clipboard-check','reports'],
 'reports'=>['Reports','Reports & insights','Prepare filtered school reports and exports.','fa-chart-simple','reports'],
 'examinee_reports'=>['Reports','Examinee reports','Review examination outcomes and printable lists.','fa-clipboard-check','reports'],
 'student_records'=>['Students','Student records','Find a student, review their academic placement, and keep their profile up to date.','fa-users','people'],
 'student_list_report'=>['Students','Student lists & printing','Create attendance sheets, acknowledgment lists, custom forms, or detailed student records in one place.','fa-address-book','people'],
 'admin_view_pds'=>['Students','Personal data sheets','Review submitted student information and open the relevant personal data sheet.','fa-file-lines','people'],
 'counseling_history'=>['Students','Counseling history','Find past sessions, review their status, and open the details of an appointment.','fa-clock-rotate-left','sessions'],
 'survey_results'=>['Students','Assessment results','Review completed learning-style and multiple-intelligence assessments.','fa-brain','people'],
 'manage_counseling'=>['Student support','Counseling appointments','Assign a counselor, manage upcoming sessions, and record completed appointments.','fa-comments','sessions'],
 'manage_exams'=>['Admissions','Entrance exam appointments','Confirm applicants, track exam attendance, and move completed tests to results processing.','fa-clipboard-list','exams'],
 'release_results'=>['Admissions','Exam results & qualification','Check the scores and qualification carefully. Saving releases the results to the applicant.','fa-certificate','exams'],
 'schedules'=>['Planning','Office calendar','Plan counseling sessions, entrance exams, holidays, and other guidance activities.','fa-calendar-days','planning'],
 'daily_limits'=>['Planning','Daily booking capacity','Set how many counseling or exam appointments can be accepted on each day.','fa-calendar-check','planning'],
 'manage_announcements'=>['Communication','Announcements','Write updates, select the intended audience, and manage published announcements.','fa-bullhorn','planning'],
 'counseling_reports'=>['Reports','Counseling reports','Review appointment trends, concerns, and counselor activity.','fa-chart-column','reports'],
 'ses_analytics'=>['Reports','Socioeconomic insights','Review recorded SES predictions and find students in each reported category.','fa-chart-line','reports'],
 'olsat_reports'=>['Reports','OLSAT results','Find exam results, review scores and qualification, and open individual results.','fa-chart-simple','reports'],
 'passed_examinees_report'=>['Reports','Qualified examinees','Review applicants marked qualified and prepare a printable admissions list.','fa-clipboard-check','reports'],
 'system_settings'=>['Administration','Office settings','Manage enabled services, booking defaults and guidance staff activity.','fa-sliders','settings'],
 'view_pds'=>['Students','Student personal data sheet','Review the selected student’s submitted information.','fa-file-lines','people'],
];
$workspace_info=$counselor_pages[$page]??['Guidance office',$page_title,'Review and manage this section of the guidance office.','fa-folder','people'];
?>
<header class="cw-page-header" data-workspace-area="<?=htmlspecialchars($workspace_info[4])?>">
    <div class="cw-page-heading"><span class="cw-page-symbol"><i class="fas <?=$workspace_info[3]?>" aria-hidden="true"></i></span><div><p class="cw-eyebrow"><?=htmlspecialchars($workspace_info[0])?></p><h1><?=htmlspecialchars($workspace_info[1])?></h1><p class="cw-description"><?=htmlspecialchars($workspace_info[2])?></p></div></div>
    <div id="workspacePageActions" class="cw-page-actions"></div>
</header>
