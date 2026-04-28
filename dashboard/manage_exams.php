<?php
require_once __DIR__ . '/../classes/EntranceExam.php';
require_once __DIR__ . '/../classes/Notification.php';

$exam = new EntranceExam($db);
$notif = new Notification($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
    if (isset($_POST['confirm_appointment'])) {
        $exam->updateStatus($_POST['appointment_id'], 'confirmed');
        $_SESSION['success_message'] = "Appointment confirmed!"; header("Location: layout.php?page=manage_exams"); exit();
    }
    if (isset($_POST['cancel_appointment'])) {
        $exam->updateStatus($_POST['appointment_id'], 'cancelled');
        $_SESSION['success_message'] = "Appointment cancelled."; header("Location: layout.php?page=manage_exams"); exit();
    }
    if (isset($_POST['complete_appointment'])) {
        $exam->updateStatus($_POST['appointment_id'], 'completed');
        if (!empty($_POST['exam_result'])) {
            $exam->updateExamResult($_POST['appointment_id'], $_POST['exam_score'] ?? null, $_POST['exam_result'], null, null);
        }
        $_SESSION['success_message'] = "Exam completed!"; header("Location: layout.php?page=manage_exams"); exit();
    }
    if (isset($_POST['mark_no_show'])) {
        $exam->updateStatus($_POST['appointment_id'], 'no_show');
        $_SESSION['success_message'] = "Marked as no-show."; header("Location: layout.php?page=manage_exams"); exit();
    }
}

// Get data
$pending = $exam->getByStatus('pending');
$confirmed = $exam->getByStatus('confirmed');
$completed = $exam->getByStatus('completed');
$cancelled = $exam->getByStatus('cancelled');

$status_colors = ['pending'=>'yellow','confirmed'=>'blue','completed'=>'green','cancelled'=>'red','no_show'=>'gray'];
$status_icons = ['pending'=>'fa-clock','confirmed'=>'fa-check-circle','completed'=>'fa-check-double','cancelled'=>'fa-times-circle','no_show'=>'fa-user-slash'];
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-clipboard-list mr-2 text-primary"></i>Entrance Exam Management</h1>

    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-3">
        <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $st=>$label): $c=$status_colors[$st]??'gray'; ?>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-<?= $c ?>-500">
            <i class="fas <?= $status_icons[$st]??'fa-circle' ?> text-<?= $c ?>-500 mb-1"></i>
            <div class="text-xl font-bold text-gray-800"><?= ${$st}->rowCount() ?></div>
            <div class="text-xs text-gray-500"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex bg-gray-100 rounded-lg p-1 gap-1">
            <button onclick="switchTab('pending')" id="tab-pending" class="px-3 py-1.5 text-sm rounded-md bg-primary text-white">Pending</button>
            <button onclick="switchTab('confirmed')" id="tab-confirmed" class="px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200">Confirmed</button>
            <button onclick="switchTab('completed')" id="tab-completed" class="px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200">Completed</button>
            <button onclick="switchTab('cancelled')" id="tab-cancelled" class="px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200">Cancelled</button>
        </div>
    </div>

    <?php
    $renderExamRows = function($stmt, $actions) use ($status_colors) {
        $html = '';
        while ($a = $stmt->fetch(PDO::FETCH_ASSOC)):
            $sc = $status_colors[$a['status']]??'gray';
            $html .= '<tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">'.htmlspecialchars(($a['last_name']??'').', '.($a['first_name']??'')).'</td>
                <td class="px-4 py-3 text-gray-500">'.date('M d, Y',strtotime($a['preferred_date'])).'<br><span class="text-xs text-gray-400">'.date('g:i A',strtotime($a['preferred_time']??'00:00')).'</span></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-'.$sc.'-100 text-'.$sc.'-700 capitalize">'.str_replace('_',' ',$a['status']).'</span></td>
                <td class="px-4 py-3 text-sm text-gray-600">'.htmlspecialchars($a['examinee_type']??$a['grade_level_applying']??'—').'</td>
                <td class="px-4 py-3 text-right"><div class="flex justify-end gap-1">'.$actions($a).'</div></td>
            </tr>';
        endwhile;
        return $html ?: '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No appointments</td></tr>';
    };
    ?>

    <!-- Pending -->
    <div id="panel-pending" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Examinee</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderExamRows($pending, function($a) { return
                    '<form method="POST" class="inline"><input type="hidden" name="appointment_id" value="'.$a['id'].'"><button type="submit" name="confirm_appointment" value="1" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"><i class="fas fa-check mr-1"></i>Confirm</button></form>'.
                    '<form method="POST" class="inline"><input type="hidden" name="appointment_id" value="'.$a['id'].'"><button type="submit" name="cancel_appointment" value="1" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200" onclick="return confirm(\'Cancel?\')"><i class="fas fa-times mr-1"></i>Cancel</button></form>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmed -->
    <div id="panel-confirmed" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Examinee</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderExamRows($confirmed, function($a) { return
                    '<button onclick="openCompleteModal('.$a['id'].')" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"><i class="fas fa-check-double mr-1"></i>Complete</button>'.
                    '<form method="POST" class="inline"><input type="hidden" name="appointment_id" value="'.$a['id'].'"><button type="submit" name="mark_no_show" value="1" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">No Show</button></form>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Completed -->
    <div id="panel-completed" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Examinee</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Result</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderExamRows($completed, function($a) { return
                    '<span class="text-xs text-gray-500">'.htmlspecialchars($a['exam_result']??'—').'</span>';
                }) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cancelled -->
    <div id="panel-cancelled" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Examinee</th><th class="px-4 py-3">Date/Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?= $renderExamRows($cancelled, function($a) { return ''; }) ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Complete Exam Modal -->
<div id="completeModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-green-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-check-double mr-2"></i>Complete Exam</h3>
            <button onclick="closeModal('completeModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="complete_appointment" value="1">
            <input type="hidden" name="appointment_id" id="complete_appointment_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Exam Result *</label>
                <select name="exam_result" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="passed">Passed</option><option value="failed">Failed</option><option value="conditional">Conditional</option>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Score</label><input type="text" name="exam_score" placeholder="Optional score" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('completeModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Complete</button></div>
        </form>
    </div>
</div>

<script>
function openCompleteModal(id){
    document.getElementById('complete_appointment_id').value=id;
    openModal('completeModal');
}
</script>
