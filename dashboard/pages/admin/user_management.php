<?php
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/StudentImport.php';

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
                    logAdminAction('create_user', "Created user: {$_POST['first_name']} {$_POST['last_name']} ({$_POST['role']})", null, $db);
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
            if ($user_obj->updateUserComplete()) { 
                $_SESSION['success_message'] = "User updated!"; 
                logAdminAction('edit_user', "Updated user: {$_POST['first_name']} {$_POST['last_name']}", null, $db);
                header("Location: layout.php?page=user_management"); exit(); 
            }
            else $error_message = "Failed to update user.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
    if (isset($_POST['reset_password'])) {
        $pwd = $_POST['new_password'] ?? 'password123';
        if (strlen($pwd) < 8) $error_message = "Password must be at least 8 characters.";
        else { 
            $user_obj->resetPassword($_POST['user_id'], $pwd); 
            $_SESSION['success_message'] = "Password reset!"; 
            logAdminAction('reset_password', "Reset password for user ID: {$_POST['user_id']}", null, $db);
            header("Location: layout.php?page=user_management"); exit(); 
        }
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
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    try {
        switch ($_GET['action']) {
            case 'archive': 
                try {
                    $result = $user_obj->archiveUser($_GET['id']);
                    if ($result) {
                        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?"); $u->execute([$_GET['id']]); $un = $u->fetch(PDO::FETCH_ASSOC);
                        $uname = $un ? "{$un['first_name']} {$un['last_name']}" : "ID {$_GET['id']}";
                        logAdminAction('archive_user', "Archived: $uname", null, $db);
                    }
                    echo json_encode(['success'=>$result]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
                }
                break;
            case 'unarchive': 
                try {
                    $result = $user_obj->unarchiveUser($_GET['id']);
                    if ($result) {
                        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?"); $u->execute([$_GET['id']]); $un = $u->fetch(PDO::FETCH_ASSOC);
                        $uname = $un ? "{$un['first_name']} {$un['last_name']}" : "ID {$_GET['id']}";
                        logAdminAction('unarchive_user', "Restored: $uname", null, $db);
                    }
                    echo json_encode(['success'=>$result]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
                }
                break;
            case 'bulk_archive':
                $input = json_decode(file_get_contents('php://input'), true);
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['success'=>false, 'error'=>'No users selected']);
                    break;
                }
                $success_count = 0;
                foreach ($ids as $id) {
                    if ($user_obj->archiveUser($id)) {
                        $success_count++;
                    }
                }
                if ($success_count > 0) {
                    logAdminAction('bulk_archive', "Archived $success_count user(s)", null, $db);
                }
                echo json_encode(['success'=>true, 'archived_count'=>$success_count]);
                break;
            case 'bulk_unarchive':
                $input = json_decode(file_get_contents('php://input'), true);
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['success'=>false, 'error'=>'No users selected']);
                    break;
                }
                $success_count = 0;
                foreach ($ids as $id) {
                    if ($user_obj->unarchiveUser($id)) {
                        $success_count++;
                    }
                }
                if ($success_count > 0) {
                    logAdminAction('bulk_unarchive', "Restored $success_count user(s)", null, $db);
                }
                echo json_encode(['success'=>true, 'restored_count'=>$success_count]);
                break;
            case 'toggle_status': 
                try {
                    $id = (int)$_GET['id'];
                    error_log("Toggle status called for user ID: " . $id);
                    $result = $user_obj->toggleUserStatus($id);
                    error_log("Toggle status result: " . ($result ? 'true' : 'false'));
                    if ($result) {
                        $u = $db->prepare("SELECT first_name, last_name, is_active FROM users WHERE id = ?"); $u->execute([$id]); $un = $u->fetch(PDO::FETCH_ASSOC);
                        $uname = $un ? "{$un['first_name']} {$un['last_name']}" : "ID $id";
                        $status_word = ($un && $un['is_active']) ? 'Activated' : 'Deactivated';
                        logAdminAction('toggle_status', "$status_word: $uname", null, $db);
                    }
                    echo json_encode(['success'=>$result]);
                } catch (Exception $e) {
                    error_log("Toggle status error: " . $e->getMessage());
                    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
                }
                break;
            case 'get_user': echo json_encode($user_obj->getUserById($_GET['id'])); break;
            case 'fetch_active':
                $pg = max(1, intval($_GET['p'] ?? 1)); $per = max(1, min(50, intval($_GET['per'] ?? 10))); $off = ($pg-1)*$per;
                $q = trim($_GET['q'] ?? ''); $role = $_GET['role'] ?? ''; $filter = $_GET['filter'] ?? ''; $letter = $_GET['letter'] ?? ''; $status = $_GET['status'] ?? '';
                $w = ["(u.archived=0 OR u.archived IS NULL)"]; $p_arr = [];
                if ($q) { $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)"; $like = "%$q%"; array_push($p_arr, $like, $like, $like, $like); }
                if ($role) { $w[] = "u.role = ?"; $p_arr[] = $role; }
                if ($filter === 'missing_id') { $w[] = "u.role='student' AND (sp.student_id IS NULL OR sp.student_id='')"; }
                if ($letter) { $w[] = "(u.last_name LIKE ? OR u.first_name LIKE ?)"; $letterLike = "$letter%"; array_push($p_arr, $letterLike, $letterLike); }
                if ($status !== '') { $w[] = "u.is_active = ?"; $p_arr[] = (int)$status; }
                $where = implode(' AND ', $w);
                $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where"); $c_stmt->execute($p_arr); $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $f_stmt = $db->prepare("SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.role, u.is_active, u.created_at, u.archived, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY u.created_at DESC LIMIT $per OFFSET $off");
                $f_stmt->execute($p_arr);
                $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ensure proper serialization of is_active
                foreach ($rows as &$row) {
                    $row['is_active'] = (int)($row['is_active'] ?? 0);
                }
                unset($row);
                
                echo json_encode(['rows'=>$rows, 'total'=>(int)$total, 'per_page'=>(int)$per, 'page'=>(int)$pg]);
                break;
            case 'fetch_archived':
                $pg = max(1, intval($_GET['p'] ?? 1)); $per = max(1, min(50, intval($_GET['per'] ?? 10))); $off = ($pg-1)*$per;
                $q = trim($_GET['q'] ?? ''); $role = $_GET['role'] ?? ''; $letter = $_GET['letter'] ?? ''; $status = $_GET['status'] ?? '';
                $w = ["u.archived=1"]; $p_arr = [];
                if ($q) { $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)"; $like = "%$q%"; array_push($p_arr, $like, $like, $like, $like); }
                if ($role) { $w[] = "u.role = ?"; $p_arr[] = $role; }
                if ($letter) { $w[] = "(u.last_name LIKE ? OR u.first_name LIKE ?)"; $letterLike = "$letter%"; array_push($p_arr, $letterLike, $letterLike); }
                if ($status !== '') { $w[] = "u.is_active = ?"; $p_arr[] = (int)$status; }
                $where = implode(' AND ', $w);
                $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where"); $c_stmt->execute($p_arr); $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $f_stmt = $db->prepare("SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.role, u.is_active, u.created_at, u.archived, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY u.created_at DESC LIMIT $per OFFSET $off");
                $f_stmt->execute($p_arr);
                $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ensure proper serialization of is_active
                foreach ($rows as &$row) {
                    $row['is_active'] = (int)($row['is_active'] ?? 0);
                }
                unset($row);
                
                echo json_encode(['rows'=>$rows, 'total'=>(int)$total, 'per_page'=>(int)$per, 'page'=>(int)$pg]);
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

<script src="js/user_management.js" defer></script>

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
            <select id="roleFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="onRoleFilterChange()">
                <option value="">All Roles</option><option value="super_admin">Super Admin</option><option value="admin">Admin</option><option value="guidance_advocate">Guidance Advocate</option><option value="student">Student</option><option value="examinee">Examinee</option>
            </select>
            <select id="statusFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="onStatusFilterChange()">
                <option value="">All Status</option><option value="1">Active</option><option value="0">Inactive</option>
            </select>
            <select id="letterFilter" class="px-2 py-1.5 border rounded text-xs" onchange="onLetterFilterChange()">
                <option value="">All Letters</option>
                <?php foreach(range('A','Z') as $letter): ?>
                <option value="<?= $letter ?>"><?= $letter ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button onclick="switchUserTab('active')" id="tab-active" class="px-3 py-1 text-sm rounded-md bg-primary text-white">Active</button>
                <button onclick="switchUserTab('archived')" id="tab-archived" class="px-3 py-1 text-sm rounded-md text-gray-600">Archived</button>
                <button onclick="switchUserTab('imports')" id="tab-imports" class="px-3 py-1 text-sm rounded-md text-gray-600">Imports</button>
            </div>
        </div>
    </div>

    <!-- Active Users -->
    <div id="panel-active" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-3 border-b flex items-center justify-between bg-gray-50">
            <button type="button" onclick="bulkArchiveUsers()" id="bulkArchiveBtn" class="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 hidden">
                <i class="fas fa-archive mr-1"></i>Archive Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed" id="activeUsersTable">
                <colgroup><col class="w-[5%]"><col class="w-[20%]"><col class="w-[20%]"><col class="w-[12%]"><col class="w-[10%]"><col class="w-[10%]"><col class="w-[12%]"><col class="w-[11%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3"><input type="checkbox" id="selectAllUsers" onchange="toggleSelectAll()" class="rounded"></th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody id="activeUsersBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="activePageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1 items-center">
                <select id="activeItemsPerPage" class="px-2 py-1.5 text-sm border rounded" onchange="onItemsPerPageChange()">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
                <button onclick="activeChangePage(-1)" id="activePrevBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left mr-1"></i>Prev</button>
                <span id="activePageNums" class="flex gap-1"></span>
                <button onclick="activeChangePage(1)" id="activeNextBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next<i class="fas fa-chevron-right ml-1"></i></button>
            </div>
        </div>
    </div>

    <!-- Archived Users -->
    <div id="panel-archived" class="bg-white rounded-xl shadow-sm overflow-hidden hidden">
        <div class="p-4 bg-blue-50 text-blue-700 text-sm"><i class="fas fa-info-circle mr-1"></i>Archived users are inactive and cannot log in.</div>
        <div class="p-3 border-b flex items-center justify-between bg-gray-50">
            <button type="button" onclick="bulkUnarchiveUsers()" id="bulkUnarchiveBtn" class="px-3 py-1.5 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200 hidden">
                <i class="fas fa-undo mr-1"></i>Restore Selected (<span id="archivedSelectedCount">0</span>)
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <colgroup><col class="w-[5%]"><col class="w-[30%]"><col class="w-[30%]"><col class="w-[20%]"><col class="w-[15%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3"><input type="checkbox" id="selectAllArchived" onchange="toggleSelectAllArchived()" class="rounded"></th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody id="archivedUsersBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Archived Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="archivedPageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1 items-center">
                <select id="archivedItemsPerPage" class="px-2 py-1.5 text-sm border rounded" onchange="onItemsPerPageChange()">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
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
                        <option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance Advocate</option><option value="admin">Admin</option><option value="super_admin">Super Admin</option>
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
                        <option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance Advocate</option><option value="admin">Admin</option><option value="super_admin">Super Admin</option>
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
