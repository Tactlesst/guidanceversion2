<?php
ob_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
checkLogin();
define('IN_LAYOUT', true);

$user_info = getUserInfo();
$role = $user_info['role'];
$uid = $user_info['id'];

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

// Ensure we have complete user info (e.g., email) from the database
try {
    $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $u_stmt->execute([$uid]);
    $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
    if ($u_row) {
        // Database values should take precedence for completeness
        $user_info = array_merge($user_info, $u_row);
        $role = $user_info['role'] ?? $role;
    }
} catch (Exception $e) {}

// Allowed pages and their titles
$pages = [
    'reports' => 'Reports & Insights',
    'examinee_reports' => 'Examinee Reports',
    'reports' => 'Reports & Insights',
    'examinee_reports' => 'Examinee Reports',
    'reports' => 'Reports & Insights',
    'examinee_reports' => 'Examinee Reports',
    'reports' => 'Reports & Insights',
    'examinee_reports' => 'Examinee Reports',
    'reports' => 'Reports & Insights',
    'examinee_reports' => 'Examinee Reports',
    'release_results' => 'Release Exam Results',
    'dashboard' => 'Dashboard',
    'profile' => 'My Profile',
    // Student pages
    'fill_pds' => 'Personal Data Sheet',
    'view_pds' => 'View PDS',
    'book_appointment' => 'Book Counseling',
    'view_appointments' => 'My Appointments',
    'complete_profile' => 'Complete Profile',
    'multiple_intelligence_survey' => 'Multiple Intelligence Survey',
    'survey_thankyou' => 'Survey Results',
    // Examinee pages
    'book_exam' => 'Book Entrance Exam',
    'view_exam_results' => 'View Exam Results',
    'view_application' => 'My Application Status',
    // Admin pages
    'student_records' => 'Student Records',
    'student_list_report' => 'Student List',
    'manage_counseling' => 'Counseling',
    'manage_exams' => 'Entrance Exams',
    'manage_announcements' => 'Announcements',
    'schedules' => 'Schedules',
    'daily_limits' => 'Daily Booking Limits',
    'counseling_reports' => 'Counseling Reports',
    'ses_analytics' => 'SES Analytics',
    'olsat_reports' => 'OLSAT Reports',
    'user_management' => 'User Management',
    'missing_student_ids' => 'Missing Student IDs',
    'academic_settings' => 'Academic Settings',
    'admin_view_pds' => 'View PDS',
    'system_settings' => 'System Settings',
    'backup_restore' => 'Backup & Restore',
    'system_logs' => 'System Logs',
    'counseling_history' => 'Counseling History',
    'survey_results' => 'Survey Results',
    'passed_examinees_report' => 'Passed Examinees',
];

$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $pages)) $page = 'dashboard';

// Role-based access check
$student_pages = ['dashboard','profile','fill_pds','view_pds','book_appointment','view_appointments','complete_profile','multiple_intelligence_survey','survey_thankyou'];
$examinee_pages = ['dashboard','profile','book_exam','view_exam_results','view_application'];
$admin_pages = ['reports','examinee_reports','reports','examinee_reports','reports','examinee_reports','reports','examinee_reports','reports','examinee_reports','release_results','missing_student_ids','dashboard','profile','student_records','student_list_report','manage_counseling','manage_exams','manage_announcements','schedules','daily_limits','counseling_reports','ses_analytics','olsat_reports','user_management','academic_settings','system_settings','backup_restore','system_logs','admin_view_pds','counseling_history','survey_results','passed_examinees_report'];

if (in_array($role, ['student']) && !in_array($page, $student_pages)) $page = 'dashboard';
if ($role === 'examinee' && !in_array($page, $examinee_pages)) $page = 'dashboard';
if (in_array($role, ['super_admin','admin','guidance_advocate']) && !in_array($page, $admin_pages)) $page = 'dashboard';

$restricted = [
 'user_management'=>['super_admin'], 'academic_settings'=>['super_admin'],
 'backup_restore'=>['super_admin'], 'system_logs'=>['super_admin'],
 'system_settings'=>['super_admin','admin'],
 'release_results'=>['super_admin','admin','guidance_advocate']
];
if (isset($restricted[$page]) && !in_array($role,$restricted[$page],true)) { header('Location: ../unauthorized.php'); exit; }
$page_title = $pages[$page];

// Map page to file path
$file_map = [
    'reports' => __DIR__ . '/pages/reports/reports.php',
    'examinee_reports' => __DIR__ . '/pages/reports/examinee_reports.php',
    'reports' => __DIR__ . '/pages/reports/reports.php',
    'examinee_reports' => __DIR__ . '/pages/reports/examinee_reports.php',
    'reports' => __DIR__ . '/pages/reports/reports.php',
    'examinee_reports' => __DIR__ . '/pages/reports/examinee_reports.php',
    'reports' => __DIR__ . '/pages/reports/reports.php',
    'examinee_reports' => __DIR__ . '/pages/reports/examinee_reports.php',
    'reports' => __DIR__ . '/pages/reports/reports.php',
    'examinee_reports' => __DIR__ . '/pages/reports/examinee_reports.php',
    'release_results' => __DIR__ . '/pages/admin/release_results.php',
    'dashboard' => __DIR__ . '/pages/index.php',
    'profile' => __DIR__ . '/profile.php',
    // Student pages
    'fill_pds' => __DIR__ . '/../pds/fill_pds.php',
    'view_pds' => __DIR__ . '/../pds/view_pds.php',
    'book_appointment' => __DIR__ . '/../counseling/book_appointment.php',
    'view_appointments' => __DIR__ . '/../counseling/view_appointments.php',
    'complete_profile' => __DIR__ . '/../profile/complete_profile.php',
    'multiple_intelligence_survey' => __DIR__ . '/../surveys/multiple_intelligence_survey.php',
    'survey_thankyou' => __DIR__ . '/../surveys/survey_thankyou.php',
    // Examinee pages
    'book_exam' => __DIR__ . '/pages/examinee/book_exam.php',
    'view_exam_results' => __DIR__ . '/pages/examinee/view_exam_results.php',
    'view_application' => __DIR__ . '/pages/examinee/view_application.php',
    // Admin pages
    'student_records' => __DIR__ . '/pages/admin/student_records.php',
    'student_list_report' => __DIR__ . '/pages/admin/student_list_report.php',
    'manage_counseling' => __DIR__ . '/pages/admin/manage_counseling.php',
    'manage_exams' => __DIR__ . '/pages/admin/manage_exams.php',
    'manage_announcements' => __DIR__ . '/pages/admin/manage_announcements.php',
    'schedules' => __DIR__ . '/pages/admin/schedules.php',
    'daily_limits' => __DIR__ . '/pages/admin/daily_limits.php',
    'counseling_reports' => __DIR__ . '/pages/reports/counseling_reports.php',
    'ses_analytics' => __DIR__ . '/pages/reports/ses_analytics.php',
    'olsat_reports' => __DIR__ . '/pages/reports/olsat_reports.php',
    'user_management' => __DIR__ . '/pages/admin/user_management.php',
    'academic_settings' => __DIR__ . '/pages/admin/academic_settings.php',
    'system_settings' => __DIR__ . '/pages/admin/system_settings.php',
    'backup_restore' => __DIR__ . '/pages/admin/backup_restore.php',
    'system_logs' => __DIR__ . '/pages/admin/system_logs.php',
    'admin_view_pds' => __DIR__ . '/pages/admin/view_pds.php',
    'counseling_history' => __DIR__ . '/pages/admin/counseling_history.php',
    'survey_results' => __DIR__ . '/pages/admin/survey_results.php',
    'passed_examinees_report' => __DIR__ . '/pages/admin/passed_examinees_report.php',
];
$content_file = $file_map[$page] ?? __DIR__ . '/pages/index.php';

$first_name = $user_info['first_name'] ?? 'User';
$last_name = $user_info['last_name'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
// Capture the page before deciding between a full document and a navigation fragment.
function portalInertScripts($html) {
    return preg_replace_callback('/<script\b([^>]*)>/i', function($match) {
        $attrs=$match[1];$type='text/javascript';
        if(preg_match('/\btype=[\'"]([^\'"]+)[\'"]/i',$attrs,$types))$type=$types[1];
        $attrs=preg_replace('/\btype=[\'"][^\'"]*[\'"]/i','',$attrs);
        return '<script type="text/portal-script" data-portal-type="'.htmlspecialchars($type,ENT_QUOTES).'"'.$attrs.'>';
    },$html);
}
// Keep the current list/section after a progressive save. Only local UI parameters are accepted.
function portalSaveRedirect($fallback) {
    if (($_SERVER['HTTP_X_PORTAL_NAVIGATION']??'')!=='1') return $fallback;
    $requested=parse_url($_POST['_portal_return']??'');$target=parse_url($fallback);
    if (!$requested || isset($requested['host']) || isset($requested['scheme']) || ($requested['path']??'')!=='layout.php') return $fallback;
    parse_str($requested['query']??'', $current);parse_str($target['query']??'', $destination);
    if (($current['page']??'dashboard')!==($destination['page']??'dashboard')) return $fallback;
    $allowed=array_flip(['page','section','tab','filter','type','date','search','log_page','id','p','per_page','month','year','status','q','view','department','program','strand','grade_level','filter_ses','staff_role','staff_user','activity_kind','activity_module','activity_from','activity_to','activity_page','pds']);
    $current=array_filter(array_intersect_key($current,$allowed),'is_string');
    return 'layout.php?'.http_build_query(array_merge($current,$destination));
}
require_once __DIR__.'/../classes/StaffActivity.php';
$portal_page=$page;$portal_title=$page_title;
ob_start();
if(file_exists($content_file))include $content_file;else echo '<p>Page not found.</p>';
$portal_content=ob_get_clean();
$page=$portal_page;$page_title=$portal_title;
if(in_array($role,['admin','guidance_advocate'],true)&&$portal_page!=='profile'){
    if($portal_page!=='dashboard'){
        $portal_content=preg_replace('/<h1\b/i','<h1 data-cw-legacy-title',$portal_content,1);
        ob_start();include __DIR__.'/partials/workspace_intro.php';$office_intro=ob_get_clean();
    }else{$office_intro='';$workspace_info=['','','','','sessions'];}
    $portal_content='<section class="cw-workspace" data-workspace-page="'.htmlspecialchars($portal_page,ENT_QUOTES).'" data-workspace-area="'.htmlspecialchars($workspace_info[4],ENT_QUOTES).'">'.$office_intro.'<div class="cw-content">'.$portal_content.'</div></section>';
}

$page=$portal_page;$page_title=$portal_title;
$first_name=$user_info['first_name']??'User';$last_name=$user_info['last_name']??'';
$initials=strtoupper(substr($first_name,0,1).substr($last_name,0,1));
ob_start();include __DIR__.'/partials/page_assets.php';$portal_head=ob_get_clean();
if(($_SERVER['HTTP_X_PORTAL_NAVIGATION']??'')==='1' && in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) {
    ob_start();include __DIR__.'/partials/header.php';$portal_header=ob_get_clean();
    ob_clean();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode(['page'=>$page,'title'=>$page_title,'identity'=>$uid.':'.$role,'header'=>$portal_header,'content'=>portalInertScripts($portal_content),'head'=>portalInertScripts($portal_head),'feedback'=>['success'=>$success_message??'','error'=>$error_message??'']],JSON_INVALID_UTF8_SUBSTITUTE);
    ob_end_flush();exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
    <script src="../js/shared.js?v=20261027-2" defer></script>
    <script src="../assets/vendor/acorn/acorn.js" defer></script>
    <link rel="stylesheet" href="../assets/css/portal-dialogs.css?v=20261027-2">
    <script src="../assets/js/portal-dialogs.js?v=20261027-3" defer></script>
    <?php if(in_array($role,['admin','guidance_advocate'],true)):?><link rel="stylesheet" href="../assets/css/counselor-workspace.css?v=20261027-2"><script src="../assets/js/counselor-workspace.js?v=20261028-1" defer></script><link rel="stylesheet" href="../assets/css/workspace-tools.css?v=20261028-4"><script src="../assets/js/office-print.js?v=20261028-2" defer></script><script src="../assets/js/workspace-filters.js?v=20261028-1" defer></script><?php endif;?>
    <link rel="stylesheet" href="../assets/css/pds-paper.css?v=20261028-4"><script src="../assets/js/pds-paper.js?v=20261028-2" defer></script>
    <script src="../assets/js/portal-navigation.js?v=20261028-pds-sidebar" defer></script>
    <?= portalInertScripts($portal_head) ?>
    <link rel="stylesheet" href="../assets/css/portal.css?v=20261027-2">
<?php if($role==='super_admin'):?><link rel="stylesheet" href="../assets/css/superadmin.css?v=20261027-2"><?php endif;?>
</head>
<body class="portal min-h-screen bg-gray-50 <?=$role==='super_admin'?'superadmin-ui':(in_array($role,['admin','guidance_advocate'],true)?'counselor-ui':'')?>" data-portal-shell="<?= bin2hex(random_bytes(8)) ?>" data-portal-user="<?= htmlspecialchars($uid.':'.$role,ENT_QUOTES) ?>">
<a class="portal-skip" href="#mainContent">Skip to content</a>

<!-- Sidebar -->
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<?php include __DIR__ . '/partials/header.php'; ?>

<div id="sidebarOverlay" class="portal-overlay hidden" aria-hidden="true"></div>

<!-- Main Content Area -->
<div class="portal-shell">
    <main id="mainContent" tabindex="-1">
        <?= portalInertScripts($portal_content) ?>
    </main>
</div>

<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const sidebarClose = document.getElementById('sidebarClose');
const desktopQuery = window.matchMedia('(min-width: 1024px)');
let previousFocus = null;
function setDrawer(open, restoreFocus = true) {
    const mobile = !desktopQuery.matches;
    const visible = mobile && open;
    sidebar.classList.toggle('is-open', visible);
    sidebarOverlay.classList.toggle('hidden', !visible);
    sidebarToggle.setAttribute('aria-expanded', String(visible));
    sidebarToggle.setAttribute('aria-label', visible ? 'Close navigation' : 'Open navigation');
    sidebar.inert = mobile && !visible;
    document.querySelector('.portal-shell').inert = visible;
    document.querySelector('.portal-header').inert = visible;
    document.body.style.overflow = visible ? 'hidden' : '';
    if (visible) { previousFocus = document.activeElement; sidebarClose.focus(); }
    else if (restoreFocus && mobile && previousFocus) { previousFocus.focus(); previousFocus = null; }
}
sidebarToggle.addEventListener('click', () => setDrawer(!sidebar.classList.contains('is-open')));
sidebarClose.addEventListener('click', () => setDrawer(false));
sidebarOverlay.addEventListener('click', () => setDrawer(false));
desktopQuery.addEventListener('change', () => setDrawer(false, false));
setDrawer(false, false);
sidebar.addEventListener('keydown', event => {
    if (event.key !== 'Tab' || desktopQuery.matches || !sidebar.classList.contains('is-open')) return;
    const items = [...sidebar.querySelectorAll('a, button')].filter(el => el.offsetParent !== null);
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
});
document.querySelectorAll('[data-nav-group]').forEach(button => {
    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        document.getElementById(button.getAttribute('aria-controls')).classList.toggle('hidden', expanded);
    });
});
const notifBtn = document.getElementById('notifBtn');
const notifMenu = document.getElementById('notifMenu');
const accountBtn = document.getElementById('accountBtn');
const accountMenu = document.getElementById('accountMenu');
function hideMenus() {
    notifMenu.classList.add('hidden'); accountMenu.classList.add('hidden');
    notifBtn.setAttribute('aria-expanded', 'false'); accountBtn.setAttribute('aria-expanded', 'false');
}
for (const [button, menu] of [[notifBtn,notifMenu],[accountBtn,accountMenu]]) {
    button.addEventListener('click', event => {
        event.stopPropagation(); const opening = menu.classList.contains('hidden'); hideMenus();
        menu.classList.toggle('hidden', !opening); button.setAttribute('aria-expanded', String(opening));
    });
    menu.addEventListener('click', event => event.stopPropagation());
}
document.addEventListener('click', hideMenus);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { setDrawer(false); hideMenus(); }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
