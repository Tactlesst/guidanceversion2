<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__.'/../../../config/database.php';
require_once __DIR__.'/../../../classes/counseling/CounselingAppointment.php';
require_once __DIR__.'/../../../classes/counseling/CounselingRemarks.php';
require_once __DIR__.'/../../../classes/identity/User.php';
require_once __DIR__.'/../../../classes/notifications/Notification.php';

date_default_timezone_set('Asia/Manila');
if(isset($_POST['reschedule_appointment'])||isset($_POST['schedule_followup'])){
    $target_date=isset($_POST['reschedule_appointment'])?($_POST['new_date']??''):($_POST['followup_date']??'');
    if(is_string($target_date)&&$closure=(new SchoolClosure($db))->forDate($target_date)){
        $_SESSION['error_message']='School closed: '.$closure['reason'].'. Choose another date.';
        header('Location: '.portalSaveRedirect('layout.php?page=manage_counseling'));exit;
    }
}


// AJAX endpoint for getting completed session details
if (isset($_GET['action']) && $_GET['action'] === 'get_completed_details') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    // Initialize database connection if not already available
    if (!isset($db) || !$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    $counseling = new CounselingAppointment($db);
    $id = intval($_GET['id'] ?? 0);
    
    try {
        $appointment = $counseling->getById($id);
        if($role==='guidance_advocate')OfficePolicy::ownSession($db,(int)$uid,$id);
        if (!$appointment) {
            echo json_encode(['error' => 'Appointment not found']);
            exit();
        }
        
        if(function_exists('staffActivityInteraction'))staffActivityInteraction('manage_counseling','view_counseling_record');
        // Get counseling remarks
        $stmt = $db->prepare("SELECT * FROM counseling_remarks WHERE appointment_id = ? ORDER BY session_date DESC LIMIT 1");
        $stmt->execute([$id]);
        $remarks = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'appointment' => $appointment,
            'remarks' => $remarks
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

$counseling = new CounselingAppointment($db);
$remarks_obj = new CounselingRemarks($db);
$user_obj = new User($db);
$notif = new Notification($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
    if (isset($_POST['assign_counselor'])) {
        $appointment_id = $_POST['appointment_id'];
        $advocate_id = $_POST['assigned_advocate_id'] ?? null;
        if (!$advocate_id) { $error_message = "Select a counselor to assign."; }
        else {
            $counseling->assignAdvocate($appointment_id, $advocate_id, (int)$uid);
            $app = $counseling->getById($appointment_id);
            if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Counselor assigned successfully!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
        }
    }

    if (isset($_POST['start_session'])) {
        $counseling->updateStatus($_POST['appointment_id'], 'in_progress', (int)$uid);
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Session started!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['complete_appointment'])) {
        $appointment_id = $_POST['appointment_id'];
        $counseling->updateStatus($appointment_id, 'completed', (int)$uid);
        if (!empty($_POST['remarks'])) {
            $remarks_obj->appointment_id = $appointment_id;
            $remarks_obj->counselor_id = $_SESSION['user_id'];
            $remarks_obj->session_date = date('Y-m-d');
            $remarks_obj->remarks = $_POST['remarks'];
            $remarks_obj->create();
        }
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Appointment completed!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['cancel_appointment'])) {
        $counseling->cancelAppointment($_POST['appointment_id']);
        $app = $counseling->getById($_POST['appointment_id']);
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Appointment cancelled."; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['mark_missed'])) {
        $counseling->updateStatus($_POST['appointment_id'], 'missed', (int)$uid);
        $app = $counseling->getById($_POST['appointment_id']);
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Marked as missed."; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['reschedule_appointment'])) {
        $id = $_POST['appointment_id']; $nd = $_POST['new_date']; $nt = $_POST['new_time'];
        try{$counseling->saveStaffSchedule((int)$id,$nd,$nt,false,(int)$_SESSION['user_id']);}
        catch(Throwable $e){$_SESSION['error_message']=$e instanceof PDOException?'The appointment could not be rescheduled. Please try again.':$e->getMessage();header('Location: '.portalSaveRedirect('layout.php?page=manage_counseling'));exit;}
        $app = $counseling->getById($id);
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Appointment rescheduled!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['schedule_followup'])) {
        $parent_id = $_POST['appointment_id']; $user_id = $_POST['user_id'];
        $fdate = $_POST['followup_date']; $ftime = $_POST['followup_time'];
        try{$saved=$counseling->saveStaffSchedule((int)$parent_id,$fdate,$ftime,true,(int)$_SESSION['user_id']);$user_id=$saved['user_id'];}
        catch(Throwable $e){$_SESSION['error_message']=$e instanceof PDOException?'The follow-up could not be scheduled. Please try again.':$e->getMessage();header('Location: '.portalSaveRedirect('layout.php?page=manage_counseling'));exit;}
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Follow-up scheduled!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }

    if (isset($_POST['save_counseling_form'])) {
        $data = [$_POST['appointment_id'], $_POST['student_id'] ?? null, $_SESSION['user_id'], $_POST['student_name'] ?? '', $_POST['problem'] ?? '', $_POST['counselor_observation'] ?? '', $_POST['student_reaction'] ?? '', $_POST['discussion_topics'] ?? '', $_POST['advice_given'] ?? '', date('Y-m-d')];
        $is_edit = !empty($_POST['is_edit']);
        if ($is_edit) {
            $stmt = $db->prepare("UPDATE counseling_forms SET problem=?, counselor_observation=?, student_reaction=?, discussion_topics=?, advice_given=?, updated_at=NOW() WHERE id=? AND appointment_id=?");
            $stmt->execute(array_slice($data, 4, 5).[$_POST['form_id'], $_POST['appointment_id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO counseling_forms (appointment_id, student_id, counselor_id, student_name, problem, counselor_observation, student_reaction, discussion_topics, advice_given, session_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute($data);
        }
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message'] = "Counseling form saved!"; header("Location: " . portalSaveRedirect("layout.php?page=manage_counseling")); exit();
    }
}

// Get data
$stats = $counseling->getAppointmentStats();
$pending = $counseling->getUnassigned();
$confirmed = $counseling->getConfirmed();
$in_progress = $counseling->getByStatus('in_progress');
$completed = $counseling->getByStatus('completed');
$cancelled = $counseling->getByStatus('cancelled');
$missed = $counseling->getByStatus('missed');

// Get advocates for assignment
$advocates = $user_obj->getUsersByRole('guidance_advocate');
$admins = $user_obj->getUsersByRole('admin');
$counselors = [];
$ca = $admins->fetchAll(PDO::FETCH_ASSOC); $cb = $advocates->fetchAll(PDO::FETCH_ASSOC);
$counselors = array_merge($ca, $cb);
if($role==='guidance_advocate')$counselors=array_values(array_filter($counselors,fn($c)=>(int)$c['id']===(int)$uid));

$status_colors = ['pending'=>'yellow','confirmed'=>'blue','in_progress'=>'orange','completed'=>'green','cancelled'=>'red','missed'=>'gray'];
$status_icons = ['pending'=>'fa-clock','confirmed'=>'fa-check-circle','in_progress'=>'fa-comments','completed'=>'fa-check-double','cancelled'=>'fa-times-circle','missed'=>'fa-exclamation-triangle'];
?>

<link rel="stylesheet" href="../assets/css/appointment-dialogs.css?v=20261028-1">
<link rel="stylesheet" href="../assets/css/manage-counseling-workspace.css?v=20261028-1"><?php if($role==='guidance_advocate'):?><p class="oa-notice">You can claim an unassigned session for yourself, work on your own sessions, and add follow-ups. Ask the counselor/admin to reassign, reschedule or cancel an appointment.</p><?php endif;?>
<div class="space-y-6 mc-workspace">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-comments mr-2 text-primary"></i>Counseling Management</h1>
    </div>

    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>

    <div class="mc-status-cards" role="tablist" aria-label="Filter counseling appointments by status">
        <?php $mc_hints=['pending'=>'Assign a counselor','confirmed'=>'Ready for the session','in_progress'=>'Sessions in progress','completed'=>'Finished sessions','cancelled'=>'Cancelled appointments','missed'=>'Missed appointments'];
        foreach(['pending'=>'Unassigned','confirmed'=>'Confirmed','in_progress'=>'In progress','completed'=>'Completed','cancelled'=>'Cancelled','missed'=>'Missed'] as $st=>$label):?>
        <button type="button" class="mc-status-card" id="tab-<?=$st?>" role="tab" aria-selected="<?=$st==='pending'?'true':'false'?>" aria-controls="panel-<?=$st?>" data-counseling-status="<?=$st?>" onclick="selectCounselingStatus('<?=$st?>')"><span class="mc-card-icon" aria-hidden="true"><i class="fas <?=$status_icons[$st]?>"></i></span><strong><?=(int)($stats[$st]??0)?></strong><span class="mc-card-label"><?=$label?></span><small><?=$mc_hints[$st]?></small><span class="mc-card-active" aria-hidden="true">Selected</span></button>
        <?php endforeach;?>
    </div>
    <details class="record-filter-options mc-filters"><summary><span>Filters<span id="mcFilterCount"></span></span><small>Search within appointments</small></summary><div class="record-filter-body"><label class="mc-search-label" for="mcSearch">Search appointments<input type="search" id="mcSearch" placeholder="Search student, ID, counselor or concern" autocomplete="off"></label><button type="button" class="mc-clear" id="mcClearSearch" hidden>Clear search</button></div></details>
    <header class="mc-list-heading"><div><p class="mc-eyebrow">APPOINTMENT WORKSPACE</p><h2 id="mcActiveTitle">Unassigned appointments</h2><p id="mcSelection" role="status">Choose a status card to review its appointments.</p></div><span id="mcShowing"></span></header>


    <?php
    // Keep existing action handlers; present identity and purpose without oversized rows.
    $renderRows = function($stmt,$actions) use($role,$uid) {
        $html='';$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        while($a=$stmt->fetch(PDO::FETCH_ASSOC)){
            $name=trim(($a['last_name']??'').', '.($a['first_name']??''),', ');
            $advocate=trim(($a['advocate_first_name']??'').' '.($a['advocate_last_name']??''));
            $full=(string)($a['concern_description']??$a['concern_type']??'');$preview=preg_replace('/\s+/u',' ',trim($full));$long=mb_strlen($preview)>110;
            if($long){$preview=mb_substr($preview,0,110);$space=mb_strrpos($preview,' ');if($space!==false&&$space>65)$preview=mb_substr($preview,0,$space);$preview.='…';}
            $initials=mb_strtoupper(mb_substr($a['first_name']??'',0,1).mb_substr($a['last_name']??'',0,1));
            $status=str_replace('_',' ',(string)$a['status']);
            $html.='<tr class="mc-row" data-appointment-id="'.(int)$a['id'].'" data-search="'.$e(mb_strtolower($name.' '.($a['student_id']??'').' '.$advocate.' '.$full)).'">';
            $html.='<td class="mc-student"><div class="mc-identity"><span class="mc-avatar" aria-hidden="true">'.$e($initials).'</span><div><strong>'.$e($name).'</strong><small>'.$e($a['student_id']??'No student ID').'</small><small>'.$e($a['grade_level']??'Grade not set').'</small></div></div></td>';
            $html.='<td class="mc-date" data-label="Session date"><strong>'.date('M j, Y',strtotime($a['appointment_date'])).'</strong><span>'.date('g:i A',strtotime($a['appointment_time'])).'</span></td>';
            $html.='<td class="mc-purpose" data-label="Concern"><span class="mc-concern-type">'.$e(str_replace('_',' ',$a['concern_type']??'Concern')).'</span><p>'.$e($preview?:'No description provided.').'</p>'.($long?'<button type="button" class="mc-read-purpose" data-purpose="'.$e($full).'" data-student="'.$e($name).'">Read concern</button>':'').'</td>';
            $html.='<td class="mc-status"><span data-status="'.$e($a['status']).'">'.$e(ucfirst($status)).'</span></td>';
            $html.='<td class="mc-counselor" data-label="Counselor">'.($advocate?$e($advocate):'<span class="mc-unassigned">Not assigned</span>').'</td>';
            $actionHtml=$actions($a);
            if($role==='guidance_advocate'){
                $actionHtml='';$record=(int)$a['id'];$mine=(int)$a['assigned_advocate_id']===(int)$uid;
                if(empty($a['assigned_advocate_id'])&&in_array($a['status'],['pending','confirmed'],true))$actionHtml='<button type="button" onclick="openAssignModal('.$record.')" title="Assign to myself"><i class="fas fa-user-check"></i> Assign to myself</button>';
                elseif($mine){
                    if(in_array($a['status'],['pending','confirmed'],true))$actionHtml='<button type="button" onclick="startSession('.$record.')">Start session</button>';
                    elseif($a['status']==='in_progress')$actionHtml='<button type="button" onclick="completeAppt('.$record.')">Complete session</button>';
                    elseif($a['status']==='completed')$actionHtml='<button type="button" onclick="viewCompletedSession('.$record.')">View my session</button><button type="button" onclick="openFollowupModal('.$record.','.(int)$a['user_id'].')">Add follow-up</button>';
                }else $actionHtml='<span>Assigned to another counselor</span>';
            }
            $html.='<td class="mc-actions"><div>'.$actionHtml.'</div></td></tr>';
        }
        return $html?:'<tr class="mc-no-records"><td colspan="6"><i class="fas fa-calendar-check" aria-hidden="true"></i><strong>No appointments in this status</strong><p>Choose another status card to view its appointments.</p></td></tr>';
    };
    ?>


    <!-- Pending Tab -->
    <div id="panel-pending" class="mc-panel" role="tabpanel" aria-labelledby="tab-pending">
        <div class="p-4 bg-yellow-50 text-yellow-700 text-sm"><i class="fas fa-info-circle mr-1"></i>These appointments are awaiting counselor assignment.</div>
        <div class="mc-table-wrap">
            <table class="mc-table" data-cw-no-cards>
                <thead><tr><th scope="col">Student</th><th scope="col">Session date</th><th scope="col">Concern</th><th scope="col">Status</th><th scope="col">Counselor</th><th scope="col">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderRows($pending, function($a) { return
                    '<button onclick="openAssignModal('.$a['id'].')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Assign"><i class="fas fa-user-plus"></i></button>'.
                    '<button onclick="openRescheduleModal('.$a['id'].',\''.$a['appointment_date'].'\',\''.$a['appointment_time'].'\')" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded" title="Reschedule"><i class="fas fa-calendar-alt"></i></button>'.
                    '<button onclick="cancelAppt('.$a['id'].')" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Cancel"><i class="fas fa-times"></i></button>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmed Tab -->
    <div id="panel-confirmed" class="mc-panel hidden" role="tabpanel" aria-labelledby="tab-confirmed">
        <div class="mc-table-wrap">
            <table class="mc-table" data-cw-no-cards>
                <thead><tr><th scope="col">Student</th><th scope="col">Session date</th><th scope="col">Concern</th><th scope="col">Status</th><th scope="col">Counselor</th><th scope="col">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderRows($confirmed, function($a) { return
                    '<button onclick="startSession('.$a['id'].')" class="p-1.5 text-green-600 hover:bg-green-50 rounded" title="Start"><i class="fas fa-play"></i></button>'.
                    (empty($a['assigned_advocate_id']) ? '<button onclick="openAssignModal('.$a['id'].')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Assign"><i class="fas fa-user-plus"></i></button>' : '').
                    '<button onclick="openRescheduleModal('.$a['id'].',\''.$a['appointment_date'].'\',\''.$a['appointment_time'].'\')" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded" title="Reschedule"><i class="fas fa-calendar-alt"></i></button>'.
                    '<button onclick="cancelAppt('.$a['id'].')" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Cancel"><i class="fas fa-times"></i></button>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- In Progress Tab -->
    <div id="panel-in_progress" class="mc-panel hidden" role="tabpanel" aria-labelledby="tab-in_progress">
        <div class="p-4 bg-orange-50 text-orange-700 text-sm"><i class="fas fa-comments mr-1"></i>Active counseling sessions.</div>
        <div class="mc-table-wrap">
            <table class="mc-table" data-cw-no-cards>
                <thead><tr><th scope="col">Student</th><th scope="col">Session date</th><th scope="col">Concern</th><th scope="col">Status</th><th scope="col">Counselor</th><th scope="col">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderRows($in_progress, function($a) { return
                    '<button onclick="completeAppt('.$a['id'].')" class="p-1.5 text-green-600 hover:bg-green-50 rounded" title="Complete"><i class="fas fa-check-double"></i></button>'.
                    '<button onclick="openFollowupModal('.$a['id'].','.$a['user_id'].')" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded" title="Follow-up"><i class="fas fa-redo"></i></button>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Completed Tab -->
    <div id="panel-completed" class="mc-panel hidden" role="tabpanel" aria-labelledby="tab-completed">
        <div class="mc-table-wrap">
            <table class="mc-table" data-cw-no-cards>
                <thead><tr><th scope="col">Student</th><th scope="col">Session date</th><th scope="col">Concern</th><th scope="col">Status</th><th scope="col">Counselor</th><th scope="col">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderRows($completed, function($a) { return
                    '<button onclick="viewCompletedSession('.$a['id'].')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="View Details"><i class="fas fa-eye"></i></button>'.
                    '<button onclick="openFollowupModal('.$a['id'].','.$a['user_id'].')" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded" title="Follow-up"><i class="fas fa-redo"></i></button>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="panel-cancelled" class="mc-panel hidden" role="tabpanel" aria-labelledby="tab-cancelled"><div class="mc-table-wrap"><table class="mc-table" data-cw-no-cards><thead><tr><th>Student</th><th>Session date</th><th>Concern</th><th>Status</th><th>Counselor</th><th>Actions</th></tr></thead><tbody>
                <?= $renderRows($cancelled, function($a){return '<button type="button" onclick="viewCancelledMissedSession('.(int)$a['id'].', \'cancelled\')" title="View Details"><i class="fas fa-eye" aria-hidden="true"></i></button>';}) ?>
            </tbody></table></div></div>
    <div id="panel-missed" class="mc-panel hidden" role="tabpanel" aria-labelledby="tab-missed"><div class="mc-table-wrap"><table class="mc-table" data-cw-no-cards><thead><tr><th>Student</th><th>Session date</th><th>Concern</th><th>Status</th><th>Counselor</th><th>Actions</th></tr></thead><tbody>
                <?= $renderRows($missed, function($a){return '<button type="button" onclick="viewCancelledMissedSession('.(int)$a['id'].', \'missed\')" title="View Details"><i class="fas fa-eye" aria-hidden="true"></i></button>';}) ?>
            </tbody></table></div></div>
    <footer class="mc-pagination"><p id="mcPageInfo" role="status" aria-live="polite"></p><nav aria-label="Appointment pages"><button type="button" id="mcPrevious">‹ Previous</button><span id="mcPagePosition"></span><button type="button" id="mcNext">Next ›</button></nav></footer>
</div>
<div id="counselingPurposeModal" class="pd-overlay hidden mc-purpose-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="mcPurposeTitle"><div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-comments"></i></span><div class="pd-heading-copy"><p class="mc-eyebrow">APPOINTMENT CONCERN</p><h2 id="mcPurposeTitle">Full concern</h2><p id="mcPurposeStudent"></p></div><button type="button" class="pd-close" aria-label="Close full concern" data-dialog-close="counselingPurposeModal">×</button></header><div class="pd-body"><p id="mcPurposeText"></p></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="counselingPurposeModal">Close</button></footer></div></div>


<?php
$adHeader=static function($id,$eyebrow,$title,$subtitle,$icon){?>
<header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas <?=htmlspecialchars($icon)?>"></i></span><div class="pd-heading-copy"><p class="ad-eyebrow"><?=htmlspecialchars($eyebrow)?></p><h2 id="<?=$id?>Title"><?=htmlspecialchars($title)?></h2><p><?=htmlspecialchars($subtitle)?></p></div><button type="button" class="pd-close" aria-label="Close <?=htmlspecialchars(strtolower($title))?>" data-dialog-close="<?=$id?>">×</button></header>
<?php };
$adContext=static function($id){?><div class="ad-form-student" data-appointment-context="<?=$id?>"><span class="ad-avatar" aria-hidden="true"><i class="fas fa-user"></i></span><div><strong>Selected student</strong><p>Appointment details</p></div></div><?php };
?>
<div id="assignModal" class="pd-overlay hidden appointment-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="assignModalTitle"><div class="pd-card">
<?php $adHeader('assignModal','COUNSELOR ASSIGNMENT',$role==='guidance_advocate'?'Assign to myself':'Assign counselor',$role==='guidance_advocate'?'Claim this unassigned session as its assigned advocate.':'Choose who will handle this counseling appointment.','fa-user-plus');?>
<form data-portal-save method="POST"><input type="hidden" name="assign_counselor" value="1"><input type="hidden" name="appointment_id" id="assign_appointment_id"><div class="pd-body"><div class="ad-form-student"><span class="ad-avatar" aria-hidden="true"><i class="fas fa-user"></i></span><div><strong id="assign_student_name">Selected student</strong><p><span id="assign_student_id"></span><span class="ad-separator"> · </span><span id="assign_grade_level"></span></p></div></div><div class="ad-schedule-note"><i class="fas fa-calendar" aria-hidden="true"></i><span id="assign_appointment_datetime"></span></div><div class="pd-field"><label for="assign_counselor_select">Guidance counselor / advocate <span aria-hidden="true">*</span></label><select name="assigned_advocate_id" id="assign_counselor_select" required><option value="">Choose a counselor</option><?php foreach($counselors as $c):?><option value="<?=(int)$c['id']?>"><?=htmlspecialchars($c['first_name'].' '.$c['last_name'].' ('.ucwords(str_replace('_',' ',$c['role'])).')',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></option><?php endforeach;?></select><p class="pd-hint">The selected counselor will be responsible for this session.</p></div></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="assignModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-check" aria-hidden="true"></i>Assign counselor</button></footer></form></div></div>

<div id="rescheduleModal" class="pd-overlay hidden appointment-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="rescheduleModalTitle"><div class="pd-card">
<?php $adHeader('rescheduleModal','UPDATE APPOINTMENT','Reschedule appointment','Choose a new date and time for the existing appointment.','fa-calendar-days');?>
<form data-portal-save method="POST"><input type="hidden" name="reschedule_appointment" value="1"><input type="hidden" name="appointment_id" id="reschedule_appointment_id"><div class="pd-body"><?php $adContext('rescheduleModal');?><fieldset class="ad-date-fieldset"><legend>New schedule</legend><div class="pd-grid"><div class="pd-field"><label for="reschedule_date">Date <span aria-hidden="true">*</span></label><input type="date" name="new_date" id="reschedule_date" required></div><div class="pd-field"><label for="reschedule_time">Time <span aria-hidden="true">*</span></label><input type="time" name="new_time" id="reschedule_time" required></div></div></fieldset><p class="ad-form-hint">School closure restrictions still apply to the new date.</p></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="rescheduleModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-check" aria-hidden="true"></i>Save new schedule</button></footer></form></div></div>

<div id="completeModal" class="pd-overlay hidden appointment-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="completeModalTitle"><div class="pd-card">
<?php $adHeader('completeModal','SESSION OUTCOME','Complete session','Mark the session as completed and optionally add notes.','fa-check-double');?>
<form data-portal-save method="POST"><input type="hidden" name="complete_appointment" value="1"><input type="hidden" name="appointment_id" id="complete_appointment_id"><div class="pd-body"><?php $adContext('completeModal');?><div class="pd-field"><label for="complete_session_remarks">Session notes <span class="pd-optional">Optional</span></label><textarea name="remarks" id="complete_session_remarks" rows="5" placeholder="Record session notes and observations"></textarea></div><p class="ad-form-hint">These notes will be available in the completed session record.</p></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="completeModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-check-double" aria-hidden="true"></i>Complete session</button></footer></form></div></div>

<div id="followupModal" class="pd-overlay hidden appointment-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="followupModalTitle"><div class="pd-card">
<?php $adHeader('followupModal','CONTINUING SUPPORT','Schedule follow-up','Set the next appointment for this student.','fa-calendar-plus');?>
<form data-portal-save method="POST"><input type="hidden" name="schedule_followup" value="1"><input type="hidden" name="appointment_id" id="followup_parent_id"><input type="hidden" name="user_id" id="followup_user_id"><div class="pd-body"><?php $adContext('followupModal');?><fieldset class="ad-date-fieldset"><legend>Follow-up schedule</legend><div class="pd-grid"><div class="pd-field"><label for="followup_date">Date <span aria-hidden="true">*</span></label><input type="date" name="followup_date" id="followup_date" min="<?=date('Y-m-d')?>" required></div><div class="pd-field"><label for="followup_time">Time <span aria-hidden="true">*</span></label><input type="time" name="followup_time" id="followup_time" required></div></div></fieldset><p class="ad-form-hint">This creates a new follow-up appointment. School closure restrictions still apply.</p></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="followupModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-calendar-plus" aria-hidden="true"></i>Schedule follow-up</button></footer></form></div></div>

<?php foreach([['viewCompletedModal','viewCompletedContent','Completed session','SESSION RECORD','fa-check-double'],['viewCancelledMissedModal','viewCancelledMissedContent','Appointment details','APPOINTMENT RECORD','fa-calendar-check']] as [$id,$contentId,$title,$eyebrow,$icon]):?>
<div id="<?=$id?>" class="pd-overlay hidden appointment-dialog ad-record-dialog" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="<?=$id?>Title"><div class="pd-card"><?php $adHeader($id,$eyebrow,$title,'Student information and the saved counseling record.',$icon);?><div class="pd-body" id="<?=$contentId?>" aria-live="polite"></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="<?=$id?>">Close</button></footer></div></div>
<?php endforeach;?>

<script src="../assets/js/counseling-dialogs.js?v=20261028-1"></script>
<script src="../assets/js/manage-counseling-workspace.js?v=20261029-label-fix"></script>
