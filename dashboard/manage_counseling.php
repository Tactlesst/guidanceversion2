<?php
require_once __DIR__ . '/../classes/CounselingAppointment.php';
require_once __DIR__ . '/../classes/CounselingRemarks.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Notification.php';

date_default_timezone_set('Asia/Manila');

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
            $counseling->assignAdvocate($appointment_id, $advocate_id);
            $app = $counseling->getById($appointment_id);
            if ($app) {
                try { $notif->create($advocate_id, "New Assignment", "You have been assigned to a counseling session on " . date('M j, Y', strtotime($app['appointment_date'])) . " at " . date('g:i A', strtotime($app['appointment_time'])), 'advocate_assignment'); } catch(Exception $e) {}
            }
            $_SESSION['success_message'] = "Counselor assigned successfully!"; header("Location: layout.php?page=manage_counseling"); exit();
        }
    }

    if (isset($_POST['start_session'])) {
        $counseling->updateStatus($_POST['appointment_id'], 'in_progress');
        $_SESSION['success_message'] = "Session started!"; header("Location: layout.php?page=manage_counseling"); exit();
    }

    if (isset($_POST['complete_appointment'])) {
        $appointment_id = $_POST['appointment_id'];
        $counseling->updateStatus($appointment_id, 'completed');
        if (!empty($_POST['remarks'])) {
            $remarks_obj->appointment_id = $appointment_id;
            $remarks_obj->counselor_id = $_SESSION['user_id'];
            $remarks_obj->session_date = date('Y-m-d');
            $remarks_obj->remarks = $_POST['remarks'];
            $remarks_obj->create();
        }
        $_SESSION['success_message'] = "Appointment completed!"; header("Location: layout.php?page=manage_counseling"); exit();
    }

    if (isset($_POST['cancel_appointment'])) {
        $counseling->cancelAppointment($_POST['appointment_id']);
        $app = $counseling->getById($_POST['appointment_id']);
        if ($app) { try { $notif->create($app['user_id'], "Appointment Cancelled", "Your counseling appointment has been cancelled.", 'appointment_cancelled'); } catch(Exception $e) {} }
        $_SESSION['success_message'] = "Appointment cancelled."; header("Location: layout.php?page=manage_counseling"); exit();
    }

    if (isset($_POST['mark_missed'])) {
        $counseling->updateStatus($_POST['appointment_id'], 'missed');
        $app = $counseling->getById($_POST['appointment_id']);
        if ($app) { try { $notif->create($app['user_id'], "Appointment Missed", "Your counseling appointment was marked as missed.", 'warning'); } catch(Exception $e) {} }
        $_SESSION['success_message'] = "Marked as missed."; header("Location: layout.php?page=manage_counseling"); exit();
    }

    if (isset($_POST['reschedule_appointment'])) {
        $id = $_POST['appointment_id']; $nd = $_POST['new_date']; $nt = $_POST['new_time'];
        $stmt = $db->prepare("UPDATE counseling_appointments SET appointment_date=?, appointment_time=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$nd, $nt, $id]);
        $app = $counseling->getById($id);
        if ($app) { try { $notif->create($app['user_id'], "Appointment Rescheduled", "Your appointment has been rescheduled to " . date('M j, Y', strtotime($nd)) . " at " . date('g:i A', strtotime($nt)), 'appointment_rescheduled'); } catch(Exception $e) {} }
        $_SESSION['success_message'] = "Appointment rescheduled!"; header("Location: layout.php?page=manage_counseling"); exit();
    }

    if (isset($_POST['schedule_followup'])) {
        $parent_id = $_POST['appointment_id']; $user_id = $_POST['user_id'];
        $fdate = $_POST['followup_date']; $ftime = $_POST['followup_time'];
        $stmt = $db->prepare("INSERT INTO counseling_appointments (user_id, appointment_date, appointment_time, concern_type, concern_description, urgency_level, status, assigned_advocate_id, is_follow_up, parent_appointment_id, created_at) VALUES (?, ?, ?, 'Follow-up', 'Follow-up session', 'normal', 'confirmed', ?, 1, ?, NOW())");
        $stmt->execute([$user_id, $fdate, $ftime, $_SESSION['user_id'], $parent_id]);
        $db->prepare("UPDATE counseling_appointments SET status='completed', updated_at=NOW() WHERE id=?")->execute([$parent_id]);
        try { $notif->create($user_id, "Follow-up Scheduled", "A follow-up session has been scheduled for " . date('M j, Y', strtotime($fdate)) . " at " . date('g:i A', strtotime($ftime)), 'follow_up_scheduled'); } catch(Exception $e) {}
        $_SESSION['success_message'] = "Follow-up scheduled!"; header("Location: layout.php?page=manage_counseling"); exit();
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
        $_SESSION['success_message'] = "Counseling form saved!"; header("Location: layout.php?page=manage_counseling"); exit();
    }
}

// Get data
$stats = $counseling->getAppointmentStats();
$pending = $counseling->getByStatus('pending');
$confirmed = $counseling->getByStatus('confirmed');
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

$status_colors = ['pending'=>'yellow','confirmed'=>'blue','in_progress'=>'orange','completed'=>'green','cancelled'=>'red','missed'=>'gray'];
$status_icons = ['pending'=>'fa-clock','confirmed'=>'fa-check-circle','in_progress'=>'fa-comments','completed'=>'fa-check-double','cancelled'=>'fa-times-circle','missed'=>'fa-exclamation-triangle'];
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-comments mr-2 text-primary"></i>Counseling Management</h1>
    </div>

    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-7 gap-3">
        <?php foreach (['pending'=>'Unassigned','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled','missed'=>'Missed'] as $st=>$label):
            $c = $status_colors[$st]??'gray'; $ic = $status_icons[$st]??'fa-circle'; $cnt = $stats[$st]??0;
        ?>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center border-l-4 border-<?= $c ?>-500">
            <i class="fas <?= $ic ?> text-<?= $c ?>-500 mb-1"></i>
            <div class="text-xl font-bold text-gray-800"><?= $cnt ?></div>
            <div class="text-xs text-gray-500"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap bg-gray-100 rounded-lg p-1 gap-1">
            <?php $tabs = ['pending'=>'Unassigned','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled/missed']; ?>
            <?php foreach ($tabs as $k=>$v): ?>
            <button onclick="switchTab('<?= $k ?>')" id="tab-<?= $k ?>" class="px-3 py-1.5 text-sm rounded-md <?= $k==='pending'?'bg-primary text-white':'text-gray-600 hover:bg-gray-200' ?>"><?= $v ?> <span class="ml-1 text-xs opacity-75"><?= $stats[$k]??0 + ($k==='cancelled'?($stats['missed']??0):0) ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Helper to render appointment rows
    $renderRows = function($stmt, $actions) use ($status_colors, $status_icons) {
        $html = '';
        while ($a = $stmt->fetch(PDO::FETCH_ASSOC)):
            $sc = $status_colors[$a['status']]??'gray';
            $advocate = trim(($a['advocate_first_name']??'').' '.($a['advocate_last_name']??''));
            $html .= '<tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">'.htmlspecialchars(($a['last_name']??$a['first_name']??'').', '.($a['first_name']??'')).'</td>
                <td class="px-4 py-3 text-gray-500">'.htmlspecialchars($a['student_id']??'—').'</td>
                <td class="px-4 py-3">'.date('M d, Y',strtotime($a['appointment_date'])).'<br><span class="text-xs text-gray-400">'.date('g:i A',strtotime($a['appointment_time'])).'</span></td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px] truncate">'.htmlspecialchars($a['concern_description']??$a['concern_type']??'').'</td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-'.$sc.'-100 text-'.$sc.'-700 capitalize">'.str_replace('_',' ',$a['status']).'</span></td>
                <td class="px-4 py-3 text-sm">'.($advocate?htmlspecialchars($advocate):'<span class="text-gray-400">Unassigned</span>').'</td>
                <td class="px-4 py-3 text-right"><div class="flex justify-end gap-1">'.$actions($a).'</div></td>
            </tr>';
        endwhile;
        return $html ?: '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No appointments</td></tr>';
    };
    ?>

    <!-- Pending Tab -->
    <div id="panel-pending" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 bg-yellow-50 text-yellow-700 text-sm"><i class="fas fa-info-circle mr-1"></i>These appointments are awaiting counselor assignment.</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">ID</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Concern</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Counselor</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
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
    <div id="panel-confirmed" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">ID</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Concern</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Counselor</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
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
    <div id="panel-in_progress" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="p-4 bg-orange-50 text-orange-700 text-sm"><i class="fas fa-comments mr-1"></i>Active counseling sessions.</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">ID</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Concern</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Counselor</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
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
    <div id="panel-completed" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">ID</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Concern</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Counselor</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderRows($completed, function($a) { return
                    '<button onclick="openFollowupModal('.$a['id'].','.$a['user_id'].')" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded" title="Follow-up"><i class="fas fa-redo"></i></button>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cancelled/Missed Tab -->
    <div id="panel-cancelled" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">ID</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Concern</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Counselor</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php
                $cancelled_rows = '';
                while ($a = $cancelled->fetch(PDO::FETCH_ASSOC)):
                    $sc = $status_colors[$a['status']]??'gray';
                    $advocate = trim(($a['advocate_first_name']??'').' '.($a['advocate_last_name']??''));
                    $cancelled_rows .= '<tr class="hover:bg-gray-50 opacity-70">
                        <td class="px-4 py-3">'.htmlspecialchars(($a['last_name']??'').', '.($a['first_name']??'')).'</td>
                        <td class="px-4 py-3 text-gray-500">'.htmlspecialchars($a['student_id']??'—').'</td>
                        <td class="px-4 py-3">'.date('M d, Y',strtotime($a['appointment_date'])).'</td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px] truncate">'.htmlspecialchars($a['concern_description']??'').'</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-'.$sc.'-100 text-'.$sc.'-700 capitalize">'.str_replace('_',' ',$a['status']).'</span></td>
                        <td class="px-4 py-3 text-sm">'.($advocate?htmlspecialchars($advocate):'—').'</td>
                        <td></td></tr>';
                endwhile;
                while ($a = $missed->fetch(PDO::FETCH_ASSOC)):
                    $advocate = trim(($a['advocate_first_name']??'').' '.($a['advocate_last_name']??''));
                    $cancelled_rows .= '<tr class="hover:bg-gray-50 opacity-70">
                        <td class="px-4 py-3">'.htmlspecialchars(($a['last_name']??'').', '.($a['first_name']??'')).'</td>
                        <td class="px-4 py-3 text-gray-500">'.htmlspecialchars($a['student_id']??'—').'</td>
                        <td class="px-4 py-3">'.date('M d, Y',strtotime($a['appointment_date'])).'</td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px] truncate">'.htmlspecialchars($a['concern_description']??'').'</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Missed</span></td>
                        <td class="px-4 py-3 text-sm">'.($advocate?htmlspecialchars($advocate):'—').'</td>
                        <td></td></tr>';
                endwhile;
                echo $cancelled_rows ?: '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No cancelled/missed appointments</td></tr>';
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Counselor Modal -->
<div id="assignModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-user-plus mr-2"></i>Assign Counselor</h3>
            <button onclick="closeModal('assignModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="assign_counselor" value="1">
            <input type="hidden" name="appointment_id" id="assign_appointment_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Select Counselor *</label>
                <select name="assigned_advocate_id" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Choose a counselor...</option>
                    <?php foreach ($counselors as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name'].' ('.ucfirst(str_replace('_',' ',$c['role'])).')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('assignModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Assign</button></div>
        </form>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-calendar-alt mr-2"></i>Reschedule</h3>
            <button onclick="closeModal('rescheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="reschedule_appointment" value="1">
            <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">New Date *</label><input type="date" name="new_date" id="reschedule_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">New Time *</label><input type="time" name="new_time" id="reschedule_time" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('rescheduleModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Reschedule</button></div>
        </form>
    </div>
</div>

<!-- Complete Session Modal -->
<div id="completeModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-green-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-check-double mr-2"></i>Complete Session</h3>
            <button onclick="closeModal('completeModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="complete_appointment" value="1">
            <input type="hidden" name="appointment_id" id="complete_appointment_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Session Remarks</label><textarea name="remarks" rows="4" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Session notes and observations..."></textarea></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('completeModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Complete</button></div>
        </form>
    </div>
</div>

<!-- Follow-up Modal -->
<div id="followupModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-purple-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-redo mr-2"></i>Schedule Follow-up</h3>
            <button onclick="closeModal('followupModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="schedule_followup" value="1">
            <input type="hidden" name="appointment_id" id="followup_parent_id">
            <input type="hidden" name="user_id" id="followup_user_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Follow-up Date *</label><input type="date" name="followup_date" required min="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Follow-up Time *</label><input type="time" name="followup_time" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('followupModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm">Schedule</button></div>
        </form>
    </div>
</div>

<script>
function openAssignModal(id) { document.getElementById('assign_appointment_id').value = id; openModal('assignModal'); }
function openRescheduleModal(id, date, time) { document.getElementById('reschedule_appointment_id').value = id; document.getElementById('reschedule_date').value = date; document.getElementById('reschedule_time').value = time; openModal('rescheduleModal'); }
function openFollowupModal(id, userId) { document.getElementById('followup_parent_id').value = id; document.getElementById('followup_user_id').value = userId; openModal('followupModal'); }

function startSession(id) {
    Swal.fire({ title:'Start Session?', text:'This will change the status to In Progress.', icon:'question', showCancelButton:true, confirmButtonColor:'#16a34a', confirmButtonText:'Start' })
    .then(r => { if(r.isConfirmed) { const f=document.createElement('form'); f.method='POST'; f.innerHTML='<input name="start_session" value="1"><input name="appointment_id" value="'+id+'">'; document.body.appendChild(f); f.submit(); } });
}

function completeAppt(id) { document.getElementById('complete_appointment_id').value = id; openModal('completeModal'); }

function cancelAppt(id) {
    Swal.fire({ title:'Cancel Appointment?', text:'The student will be notified.', icon:'warning', showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'Cancel Appt' })
    .then(r => { if(r.isConfirmed) { const f=document.createElement('form'); f.method='POST'; f.innerHTML='<input name="cancel_appointment" value="1"><input name="appointment_id" value="'+id+'">'; document.body.appendChild(f); f.submit(); } });
}
</script>
