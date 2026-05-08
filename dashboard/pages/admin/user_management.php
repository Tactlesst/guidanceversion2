<?php
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/StudentImport.php';

$user_obj = new User($db);
$import_obj = new StudentImport($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Examinees with completed exams for conversion
$completed_examinees = [];
try {
    $stmt = $db->prepare("SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, MAX(ea.preferred_date) as last_exam_date FROM users u JOIN entrance_exam_appointments ea ON ea.user_id = u.id WHERE u.role = 'examinee' AND ea.status = 'completed' AND (u.archived = 0 OR u.archived IS NULL) GROUP BY u.id ORDER BY last_exam_date DESC");
    $stmt->execute();
    $completed_examinees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// POST handlers
if ($_POST) {
    if (isset($_POST['create_user'])) {
        try {
            $default_password = 'password123';
            $selected_examinee_id = !empty($_POST['completed_examinee_id']) ? (int)$_POST['completed_examinee_id'] : 0;
            if ($selected_examinee_id > 0) {
                $sid = trim($_POST['student_id'] ?? '');
                if ($sid === '') throw new Exception("Student ID required for examinee conversion.");
                $db->beginTransaction();
                $ex = $db->prepare("SELECT id, role FROM users WHERE id = ? AND (archived=0 OR archived IS NULL) LIMIT 1");
                $ex->execute([$selected_examinee_id]); $erow = $ex->fetch(PDO::FETCH_ASSOC);
                if (!$erow || $erow['role'] !== 'examinee') throw new Exception("Invalid examinee.");
                $db->prepare("UPDATE users SET password=?, email=?, role='student', first_name=?, middle_name=?, last_name=?, position=NULL WHERE id=?")
                   ->execute([password_hash($default_password, PASSWORD_DEFAULT), $_POST['email']?:null, $_POST['first_name'], $_POST['middle_name']?:null, $_POST['last_name'], $selected_examinee_id]);
                $pc = $db->prepare("SELECT id FROM student_profiles WHERE user_id=?"); $pc->execute([$selected_examinee_id]);
                if ($pc->rowCount() === 0) $db->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?,?)")->execute([$selected_examinee_id, $sid]);
                else $db->prepare("UPDATE student_profiles SET student_id=? WHERE user_id=?")->execute([$sid, $selected_examinee_id]);
                $db->commit();
                $_SESSION['success_message'] = "Examinee converted to student!";
            } else {
                $user_obj->password = !empty($_POST['password']) ? $_POST['password'] : $default_password;
                $user_obj->email = $_POST['email']; $user_obj->role = $_POST['role'];
                $user_obj->first_name = $_POST['first_name']; $user_obj->last_name = $_POST['last_name'];
                $user_obj->middle_name = $_POST['middle_name'] ?: null;
                $user_obj->position = in_array($_POST['role'], ['admin','guidance_advocate','super_admin']) ? ($_POST['position']?:null) : null;
                if ($user_obj->register()) {
                    $new_uid = $db->lastInsertId();
                    if (!empty($_POST['student_id']) && $_POST['role'] === 'student') {
                        $sid = trim($_POST['student_id']);
                        $chk = $db->prepare("SELECT id FROM student_profiles WHERE student_id=?"); $chk->execute([$sid]);
                        if ($chk->rowCount() > 0) $error_message = "Student ID exists. User created without profile.";
                        else $db->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?,?)")->execute([$new_uid, $sid]);
                    }
                    $_SESSION['success_message'] = $_SESSION['success_message'] ?? "User created!";
                } else { $error_message = "Failed to create user."; }
            }
        } catch (Exception $e) { $error_message = $e->getMessage(); }
        if (empty($error_message)) { header("Location: layout.php?page=user_management"); exit(); }
    }
    if (isset($_POST['edit_user'])) {
        try {
            $user_obj->id = $_POST['user_id']; $user_obj->first_name = $_POST['first_name'];
            $user_obj->last_name = $_POST['last_name']; $user_obj->email = $_POST['email'];
            $user_obj->role = $_POST['role']; $user_obj->is_active = isset($_POST['is_active']) ? 1 : 0;
            $user_obj->student_id = $_POST['student_id'] ?? '';
            if ($user_obj->updateUserComplete()) { $_SESSION['success_message'] = "User updated!"; header("Location: layout.php?page=user_management"); exit(); }
            else $error_message = "Failed to update user.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
    if (isset($_POST['reset_password'])) {
        $pwd = $_POST['new_password'] ?? 'password123';
        if (strlen($pwd) < 8) $error_message = "Password must be at least 8 characters.";
        else { $user_obj->resetPassword($_POST['user_id'], $pwd); $_SESSION['success_message'] = "Password reset!"; header("Location: layout.php?page=user_management"); exit(); }
    }
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        $dir = __DIR__.'/../uploads/'; if (!file_exists($dir)) mkdir($dir, 0777, true);
        $fp = $dir . time() . '_' . $_FILES['csv_file']['name'];
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $fp)) { $import_obj->processCSV($fp, $_SESSION['user_id']); $_SESSION['success_message'] = "CSV import completed!"; unlink($fp); }
        else $_SESSION['error_message'] = "Upload failed.";
        header("Location: layout.php?page=user_management"); exit();
    }
    if (isset($_POST['generate_sample'])) {
        $sf = $import_obj->generateSampleCSV();
        if ($sf) { ob_end_clean(); header('Content-Type: application/csv'); header('Content-Disposition: attachment; filename="'.$sf.'"'); readfile(__DIR__.'/../uploads/'.$sf); unlink(__DIR__.'/../uploads/'.$sf); exit; }
    }
    if (isset($_POST['export_users'])) {
        ob_end_clean();
        $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.program, sp.strand, sp.grade_level FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.archived=0 OR u.archived IS NULL ORDER BY u.created_at DESC");
        $stmt->execute(); $fn = 'users_export_'.date('Y-m-d_His').'.csv';
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$fn.'"');
        $out = fopen('php://output','w'); fputcsv($out,['ID','First','Middle','Last','Email','Role','Status','StudentID','Dept','Program','Strand','Grade','Created']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) fputcsv($out,[$u['id'],$u['first_name'],$u['middle_name'],$u['last_name'],$u['email'],ucfirst(str_replace('_',' ',$u['role'])),$u['is_active']?'Active':'Inactive',$u['student_id']?:'N/A',$u['department']?:'N/A',$u['program']?:'N/A',$u['strand']?:'N/A',$u['grade_level']?:'N/A',$u['created_at']]);
        fclose($out); exit;
    }
}

// AJAX actions
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'archive': $user_obj->archiveUser($_GET['id']); echo json_encode(['success'=>true]); break;
            case 'unarchive': $user_obj->unarchiveUser($_GET['id']); echo json_encode(['success'=>true]); break;
            case 'toggle_status': $user_obj->toggleUserStatus($_GET['id']); echo json_encode(['success'=>true]); break;
            case 'get_user': echo json_encode($user_obj->getUserById($_GET['id'])); break;
            case 'fetch_active':
                $page = max(1, intval($_GET['p'] ?? 1)); $per = 10; $off = ($page-1)*$per;
                $q = trim($_GET['q'] ?? ''); $role = $_GET['role'] ?? '';
                $w = ["(u.archived=0 OR u.archived IS NULL)"]; $p_arr = [];
                if ($q) { $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)"; $like = "%$q%"; $p_arr = [$like,$like,$like,$like]; }
                if ($role) { $w[] = "u.role = ?"; $p_arr[] = $role; }
                $where = implode(' AND ', $w);
                $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where"); $c_stmt->execute($p_arr); $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $stmt = $db->prepare("SELECT u.*, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY u.created_at DESC LIMIT $per OFFSET $off"); $stmt->execute($p_arr);
                echo json_encode(['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'total'=>$total, 'per_page'=>$per, 'page'=>$page]);
                break;
            case 'fetch_archived':
                $page = max(1, intval($_GET['p'] ?? 1)); $per = 10; $off = ($page-1)*$per;
                $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.archived=1"); $c_stmt->execute(); $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $stmt = $db->prepare("SELECT u.*, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.archived=1 ORDER BY u.created_at DESC LIMIT $per OFFSET $off"); $stmt->execute();
                echo json_encode(['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'total'=>$total, 'per_page'=>$per, 'page'=>$page]);
                break;
            default: echo json_encode(['error'=>'Invalid']);
        }
    } catch (Exception $e) { echo json_encode(['error'=>$e->getMessage()]); }
    exit();
}

$user_stats = $user_obj->getUserStats();
$import_history = $import_obj->getImportHistory(10);
$role_colors = ['super_admin'=>'purple','admin'=>'red','guidance_advocate'=>'green','student'=>'blue','examinee'=>'yellow'];
$role_icons = ['super_admin'=>'fa-crown','admin'=>'fa-user-shield','guidance_advocate'=>'fa-hands-helping','student'=>'fa-user-graduate','examinee'=>'fa-file-alt'];
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-users-cog mr-2 text-primary"></i>User Management</h1>
        <div class="flex gap-2">
            <form method="POST" class="inline"><button type="submit" name="export_users" value="1" class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"><i class="fas fa-file-export mr-1"></i>Export</button></form>
            <button onclick="openModal('importModal')" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"><i class="fas fa-file-import mr-1"></i>Import</button>
            <button onclick="openModal('createUserModal')" class="px-3 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add User</button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <?php foreach ($user_stats as $stat): $c = $role_colors[$stat['role']]??'gray'; $ic = $role_icons[$stat['role']]??'fa-user'; ?>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-<?= $c ?>-500">
            <i class="fas <?= $ic ?> text-<?= $c ?>-500 text-lg mb-1"></i>
            <div class="text-2xl font-bold text-gray-800"><?= $stat['count'] ?></div>
            <div class="text-xs text-gray-500 capitalize"><?= str_replace('_',' ',$stat['role']) ?></div>
            <div class="text-xs text-green-600"><?= $stat['active_count'] ?> active</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Search & Tabs -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input type="text" id="searchInput" placeholder="Search name, email, student ID..." class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none" oninput="debounceSearch()">
            <select id="roleFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="debounceSearch()">
                <option value="">All Roles</option><option value="super_admin">Super Admin</option><option value="admin">Admin</option><option value="guidance_advocate">Guidance Advocate</option><option value="student">Student</option><option value="examinee">Examinee</option>
            </select>
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button onclick="switchTab('active')" id="tab-active" class="px-3 py-1 text-sm rounded-md bg-primary text-white">Active</button>
                <button onclick="switchTab('archived')" id="tab-archived" class="px-3 py-1 text-sm rounded-md text-gray-600">Archived</button>
                <button onclick="switchTab('imports')" id="tab-imports" class="px-3 py-1 text-sm rounded-md text-gray-600">Imports</button>
            </div>
        </div>
    </div>

    <!-- Active Users -->
    <div id="panel-active" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed" id="activeUsersTable">
                <colgroup><col class="w-[20%]"><col class="w-[20%]"><col class="w-[12%]"><col class="w-[10%]"><col class="w-[10%]"><col class="w-[12%]"><col class="w-[16%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody id="activeUsersBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="activePageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1">
                <button onclick="activeChangePage(-1)" id="activePrevBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left mr-1"></i>Prev</button>
                <span id="activePageNums" class="flex gap-1"></span>
                <button onclick="activeChangePage(1)" id="activeNextBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next<i class="fas fa-chevron-right ml-1"></i></button>
            </div>
        </div>
    </div>

    <!-- Archived Users -->
    <div id="panel-archived" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="p-4 bg-blue-50 text-blue-700 text-sm"><i class="fas fa-info-circle mr-1"></i>Archived users are inactive and cannot log in.</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <colgroup><col class="w-[30%]"><col class="w-[30%]"><col class="w-[20%]"><col class="w-[20%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody id="archivedUsersBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Archived Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="archivedPageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1">
                <button onclick="archivedChangePage(-1)" id="archivedPrevBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left mr-1"></i>Prev</button>
                <span id="archivedPageNums" class="flex gap-1"></span>
                <button onclick="archivedChangePage(1)" id="archivedNextBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next<i class="fas fa-chevron-right ml-1"></i></button>
            </div>
        </div>
    </div>

    <!-- Import History -->
    <div id="panel-imports" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <colgroup><col class="w-[16%]"><col class="w-[20%]"><col class="w-[10%]"><col class="w-[8%]"><col class="w-[8%]"><col class="w-[8%]"><col class="w-[10%]"><col class="w-[20%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">File</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Success</th><th class="px-4 py-3">Failed</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">By</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php if ($import_history->rowCount() > 0): while ($imp = $import_history->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= date('M d, Y g:i A', strtotime($imp['created_at'])) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($imp['filename']) ?></td>
                        <td class="px-4 py-3 capitalize"><?= $imp['file_type'] ?></td>
                        <td class="px-4 py-3"><?= $imp['total_records'] ?></td>
                        <td class="px-4 py-3 text-green-600"><?= $imp['successful_imports'] ?></td>
                        <td class="px-4 py-3 text-red-600"><?= $imp['failed_imports'] ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs <?= $imp['import_status']==='completed'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>"><?= $imp['import_status'] ?></span></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($imp['first_name'].' '.$imp['last_name']) ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No import history</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-user-plus mr-2"></i>Create User</h3>
            <button onclick="closeModal('createUserModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_user" value="1">
            <?php if (!empty($completed_examinees)): ?>
            <div class="bg-blue-50 rounded-lg p-4 space-y-3">
                <label class="flex items-center gap-2 text-sm font-medium text-blue-800">
                    <input type="checkbox" id="convertExaminee" onchange="toggleExamineeConversion()" class="rounded"> Convert examinee to student
                </label>
                <div id="examineeFields" class="hidden">
                    <select name="completed_examinee_id" id="examineeSelect" onchange="fillExamineeData()" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select examinee...</option>
                        <?php foreach ($completed_examinees as $ex): ?>
                        <option value="<?= $ex['id'] ?>" data-first="<?= htmlspecialchars($ex['first_name']) ?>" data-middle="<?= htmlspecialchars($ex['middle_name']??'') ?>" data-last="<?= htmlspecialchars($ex['last_name']) ?>" data-email="<?= htmlspecialchars($ex['email']??'') ?>"><?= htmlspecialchars($ex['last_name'].', '.$ex['first_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label><input type="text" name="first_name" id="create_first_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label><input type="text" name="last_name" id="create_last_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label><input type="text" name="middle_name" id="create_middle_name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" id="create_email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role" id="createRole" required onchange="toggleStudentFields()" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance Advocate</option><option value="admin">Admin</option>
                    </select>
                </div>
                <div id="positionField"><label class="block text-sm font-medium text-gray-700 mb-1">Position</label><input type="text" name="position" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div id="studentIdField" class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Student ID</label><input type="text" name="student_id" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label><input type="password" name="password" placeholder="Default: password123" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('createUserModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-green-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-user-edit mr-2"></i>Edit User</h3>
            <button onclick="closeModal('editUserModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label><input type="text" name="first_name" id="edit_first_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label><input type="text" name="last_name" id="edit_last_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label><input type="text" name="middle_name" id="edit_middle_name" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" id="edit_email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role" id="edit_role" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance Advocate</option><option value="admin">Admin</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Student ID</label><input type="text" name="student_id" id="edit_student_id" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded"><label for="edit_is_active" class="text-sm text-gray-700">Active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('editUserModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-orange-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-key mr-2"></i>Reset Password</h3>
            <button onclick="closeModal('resetPasswordModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" id="reset_user_id">
            <p class="text-sm text-gray-600">Reset password for: <strong id="reset_user_name"></strong></p>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label><input type="password" name="new_password" placeholder="Min 8 chars (default: password123)" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('resetPasswordModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm hover:bg-orange-600">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-blue-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-file-import mr-2"></i>Import Users</h3>
            <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">CSV File</label><input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <p class="text-xs text-gray-500">CSV format: password, email, first_name, middle_name, last_name, student_id, role</p>
            <div class="flex justify-end gap-3">
                <button type="submit" name="generate_sample" value="1" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-download mr-1"></i>Sample CSV</button>
                <button type="submit" name="import_csv" value="1" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"><i class="fas fa-upload mr-1"></i>Import</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE = 'layout.php?page=user_management';
let searchTimer;

function toggleStudentFields() {
    const role = document.getElementById('createRole').value;
    document.getElementById('studentIdField').style.display = role==='student' ? 'grid' : 'none';
    document.getElementById('positionField').style.display = ['admin','guidance_advocate','super_admin'].includes(role) ? 'block' : 'none';
}

function toggleExamineeConversion() {
    document.getElementById('examineeFields').classList.toggle('hidden', !document.getElementById('convertExaminee').checked);
}

function fillExamineeData() {
    const sel = document.getElementById('examineeSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('create_first_name').value = opt.dataset.first || '';
        document.getElementById('create_middle_name').value = opt.dataset.middle || '';
        document.getElementById('create_last_name').value = opt.dataset.last || '';
        document.getElementById('create_email').value = opt.dataset.email || '';
        document.getElementById('createRole').value = 'student';
        toggleStudentFields();
    }
}

function editUser(data) {
    document.getElementById('edit_user_id').value = data.id;
    document.getElementById('edit_first_name').value = data.first_name || '';
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_last_name').value = data.last_name || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_role').value = data.role || 'student';
    document.getElementById('edit_student_id').value = data.student_id || '';
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    openModal('editUserModal');
}

function openResetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    openModal('resetPasswordModal');
}

function archiveUser(id, name) {
    Swal.fire({ title: 'Archive User?', html: `Are you sure you want to archive <strong>${name}</strong>?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Archive', cancelButtonText: 'Cancel' })
    .then(r => { if (r.isConfirmed) fetch(BASE+'&action=archive&id='+id).then(()=>Swal.fire('Archived!','User has been archived.','success').then(()=>location.reload())); });
}

function unarchiveUser(id, name) {
    Swal.fire({ title: 'Restore User?', html: `Restore <strong>${name}</strong>?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#16a34a', confirmButtonText: 'Restore' })
    .then(r => { if (r.isConfirmed) fetch(BASE+'&action=unarchive&id='+id).then(()=>Swal.fire('Restored!','User has been restored.','success').then(()=>location.reload())); });
}

function toggleStatus(id) {
    fetch(BASE+'&action=toggle_status&id='+id).then(r=>r.json()).then(()=>location.reload());
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { activePage = 1; fetchActiveUsers(); }, 300);
}

// Init
toggleStudentFields();

// Server-side pagination — Active Users
let activePage = 1;
const RC_MAP = {'super_admin':'purple','admin':'red','guidance_advocate':'green','student':'blue','examinee':'yellow'};

function fetchActiveUsers() {
    const q = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const url = BASE + `&action=fetch_active&p=${activePage}&q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}`;
    fetch(url).then(r=>r.json()).then(data => {
        const tbody = document.getElementById('activeUsersBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No users found</td></tr>';
        } else {
            data.rows.forEach(u => {
                const rc = RC_MAP[u.role]||'gray';
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const escName = (u.first_name||'') + ' ' + (u.last_name||'');
                tbody.innerHTML += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-${rc}-100 text-${rc}-700 capitalize">${u.role.replace(/_/g,' ')}</span></td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'—')}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${u.is_active?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${u.is_active?'Active':'Inactive'}</span></td>
                    <td class="px-4 py-3 text-gray-400 text-xs">${new Date(u.created_at).toLocaleDateString()}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-1">
                            <button onclick='editUser(${JSON.stringify(u).replace(/'/g,"&#39;")})' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="fas fa-edit"></i></button>
                            <button onclick="toggleStatus(${u.id})" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded" title="Toggle"><i class="fas fa-power-off"></i></button>
                            <button onclick="openResetPassword(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="p-1.5 text-orange-600 hover:bg-orange-50 rounded" title="Reset Password"><i class="fas fa-key"></i></button>
                            <button onclick="archiveUser(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Archive"><i class="fas fa-archive"></i></button>
                        </div>
                    </td>
                </tr>`;
            });
        }
        renderPagination('active', data.total, data.per_page, data.page);
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// Server-side pagination — Archived Users
let archivedPage = 1;

function fetchArchivedUsers() {
    fetch(BASE + `&action=fetch_archived&p=${archivedPage}`).then(r=>r.json()).then(data => {
        const tbody = document.getElementById('archivedUsersBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No archived users</td></tr>';
        } else {
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'');
                const escName = (u.first_name||'') + ' ' + (u.last_name||'');
                tbody.innerHTML += `<tr class="hover:bg-gray-50 opacity-70">
                    <td class="px-4 py-3 break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3 capitalize text-gray-500">${u.role.replace(/_/g,' ')}</td>
                    <td class="px-4 py-3 text-right"><button onclick="unarchiveUser(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"><i class="fas fa-undo mr-1"></i>Restore</button></td>
                </tr>`;
            });
        }
        renderPagination('archived', data.total, data.per_page, data.page);
    });
}

// Shared pagination renderer
function renderPagination(prefix, total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById(prefix + 'PageInfo').textContent = total === 0 ? 'No records' : `Showing ${start}-${end} of ${total}`;
    document.getElementById(prefix + 'PrevBtn').disabled = page <= 1;
    document.getElementById(prefix + 'NextBtn').disabled = page >= totalPages;
    const nums = document.getElementById(prefix + 'PageNums');
    nums.innerHTML = '';
    const maxBtns = 5;
    let sp = Math.max(1, page - Math.floor(maxBtns/2));
    let ep = Math.min(totalPages, sp + maxBtns - 1);
    if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);
    for (let p = sp; p <= ep; p++) {
        const b = document.createElement('button');
        b.textContent = p;
        b.className = p === page ? 'px-2.5 py-1 text-sm rounded-lg bg-primary text-white' : 'px-2.5 py-1 text-sm rounded-lg border hover:bg-gray-50';
        b.onclick = () => {
            if (prefix === 'active') { activePage = p; fetchActiveUsers(); }
            else { archivedPage = p; fetchArchivedUsers(); }
        };
        nums.appendChild(b);
    }
}

function activeChangePage(delta) { activePage += delta; fetchActiveUsers(); }
function archivedChangePage(delta) { archivedPage += delta; fetchArchivedUsers(); }

// Initial load
fetchActiveUsers();

// Also load archived when tab is switched
const origSwitchTab = switchTab;
switchTab = function(tab) {
    origSwitchTab(tab);
    if (tab === 'archived') fetchArchivedUsers();
};
</script>
