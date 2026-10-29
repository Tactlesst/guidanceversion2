<?php
require_once __DIR__.'/../../../classes/identity/User.php';
require_once __DIR__.'/../../../classes/identity/StudentImport.php';

$_SESSION['user_management_csrf']=$_SESSION['user_management_csrf']??bin2hex(random_bytes(24));
$requestAction=$_GET['action']??'';
if(in_array($requestAction,['archive','unarchive','bulk_archive','bulk_unarchive'],true)&&($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Content-Type: application/json');echo json_encode(['error'=>'Use the protected account controls. GET requests cannot change accounts.']);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 $requestPayload=$_POST;if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json'))$requestPayload=json_decode(file_get_contents('php://input'),true)?:[];
 if(!is_string($requestPayload['_users_csrf']??null)||!hash_equals($_SESSION['user_management_csrf'],$requestPayload['_users_csrf'])){http_response_code(403);header('Content-Type: application/json');echo json_encode(['error'=>'Refresh Users & accounts before submitting an action.']);exit;}
}
$user_obj = new User($db);
$import_obj = new StudentImport($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
    if (isset($_POST['create_user'])) {
        try {
            $db->beginTransaction();
            $default_password = 'password123';
            $selected_examinee_id = !empty($_POST['completed_examinee_id']) ? (int)$_POST['completed_examinee_id'] : 0;
            if ($selected_examinee_id > 0) {
                throw new RuntimeException('Use Convert examinee to student to preserve the existing account and validate its academic placement.');
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
                        if ($chk->rowCount() > 0) throw new InvalidArgumentException('Student ID already exists. No account was created.');
                        else $db->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?,?)")->execute([$new_uid, $sid]);
                    }
                    $_SESSION['success_message'] = $_SESSION['success_message'] ?? "User created!";
                    logAdminAction('create_user', "Created user: {$_POST['first_name']} {$_POST['last_name']} ({$_POST['role']})", null, $db);
                } else { throw new RuntimeException('Failed to create user.'); }
            }
            $db->commit();
        } catch (Throwable $e) { if($db->inTransaction())$db->rollBack();$error_message = $e instanceof PDOException?'The account could not be saved. Please check its details.':$e->getMessage(); }
        if (empty($error_message)) { header("Location: " . portalSaveRedirect("layout.php?page=user_management")); exit(); }
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
                header("Location: " . portalSaveRedirect("layout.php?page=user_management")); exit(); 
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
            header("Location: " . portalSaveRedirect("layout.php?page=user_management")); exit(); 
        }
    }
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        $file=$_FILES['csv_file'];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024||strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='csv'){
            $_SESSION['error_message']='Choose a CSV file up to 5 MB.';header('Location: '.portalSaveRedirect('layout.php?page=user_management'));exit;
        }
        $dir = __DIR__.'/../../../storage/uploads/'; if (!file_exists($dir)) mkdir($dir, 0700, true);
        $fp = $dir . 'import_' . bin2hex(random_bytes(16)) . '.csv';
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $fp)) {
            try{$import_obj->processCSV($fp,$_SESSION['user_id']);$summary=(int)$import_obj->successful_imports.' accounts imported; '.(int)$import_obj->failed_imports.' rows need review.';
                if($import_obj->import_status==='failed')$_SESSION['error_message']='The CSV could not be imported. Check the import history for details.';
                elseif((int)$import_obj->failed_imports>0)$_SESSION['error_message']=$summary.' Check the import history for details.';
                else $_SESSION['success_message']=$summary;
            }catch(Throwable $e){$_SESSION['error_message']='The import could not finish. Check the import history before retrying.';}
            finally{if(is_file($fp))unlink($fp);}
        }
        else $_SESSION['error_message'] = "Upload failed.";
        header("Location: " . portalSaveRedirect("layout.php?page=user_management")); exit();
    }
    if (isset($_POST['generate_sample'])) {
        $sf = $import_obj->generateSampleCSV();
        if ($sf) { ob_end_clean(); header('Content-Type: application/csv'); header('Content-Disposition: attachment; filename="'.$sf.'"'); readfile(__DIR__.'/../../../storage/uploads/'.$sf); unlink(__DIR__.'/../../../storage/uploads/'.$sf); exit; }
    }
    if (isset($_POST['export_users'])) {
        ob_end_clean();
        $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.program, sp.strand, sp.grade_level FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.archived=0 OR u.archived IS NULL ORDER BY u.created_at DESC");
        $stmt->execute(); $exportRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        require_once __DIR__.'/../../../classes/system/SystemLogger.php';
        if(!(new SystemLogger($db))->adminAction('Exported account list',$_SESSION['user_id'],['format'=>'csv','matching_records'=>count($exportRows)])){http_response_code(503);echo 'The export could not be recorded. Try again.';exit;}
        $safeCell=static function($v){return is_string($v)&&preg_match('/^[\s]*[=+@\-\t\r]/u',$v)?"'".$v:$v;};
        $fn = 'users_export_'.date('Y-m-d_His').'.csv';
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$fn.'"');
        $out = fopen('php://output','w'); fputcsv($out,['ID','First','Middle','Last','Email','Role','Status','StudentID','Dept','Program','Strand','Grade','Created']);
        foreach ($exportRows as $u) fputcsv($out,array_map($safeCell,[$u['id'],$u['first_name'],$u['middle_name'],$u['last_name'],$u['email'],ucfirst(str_replace('_',' ',$u['role'])),$u['is_active']?'Active':'Inactive',$u['student_id']?:'N/A',$u['department']?:'N/A',$u['program']?:'N/A',$u['strand']?:'N/A',$u['grade_level']?:'N/A',$u['created_at']]));
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
                    $result = $user_obj->archiveUser($_POST['id']);
                    if ($result) {
                        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?"); $u->execute([$_POST['id']]); $un = $u->fetch(PDO::FETCH_ASSOC);
                        $uname = $un ? "{$un['first_name']} {$un['last_name']}" : "ID {$_POST['id']}";
                        logAdminAction('archive_user', "Archived: $uname", null, $db);
                    }
                    echo json_encode(['success'=>$result]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
                }
                break;
            case 'unarchive': 
                try {
                    $result = $user_obj->unarchiveUser($_POST['id']);
                    if ($result) {
                        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?"); $u->execute([$_POST['id']]); $un = $u->fetch(PDO::FETCH_ASSOC);
                        $uname = $un ? "{$un['first_name']} {$un['last_name']}" : "ID {$_POST['id']}";
                        logAdminAction('unarchive_user', "Restored: $uname", null, $db);
                    }
                    echo json_encode(['success'=>$result]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
                }
                break;
            case 'bulk_archive':
                $input=$requestPayload;
                $ids=$input['ids']??[];if(!is_array($ids)||count($ids)>500)throw new RuntimeException('Choose up to 500 valid accounts.');$ids=array_values(array_unique(array_map(static fn($id)=>filter_var($id,FILTER_VALIDATE_INT),$ids)));if(in_array(false,$ids,true)||in_array(0,$ids,true))throw new RuntimeException('Choose valid accounts.');
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
                $input=$requestPayload;
                $ids=$input['ids']??[];if(!is_array($ids)||count($ids)>500)throw new RuntimeException('Choose up to 500 valid accounts.');$ids=array_values(array_unique(array_map(static fn($id)=>filter_var($id,FILTER_VALIDATE_INT),$ids)));if(in_array(false,$ids,true)||in_array(0,$ids,true))throw new RuntimeException('Choose valid accounts.');
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
                    $id = (int)$_POST['id'];
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
            case 'get_user': echo json_encode($user_obj->getUserById($_POST['id'])); break;
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



<script type="application/json" id="userManagementSecurity"><?=json_encode(['csrf'=>$_SESSION['user_management_csrf']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<link rel="stylesheet" href="../assets/css/user-management.css?v=20261027-2">
<script src="../assets/js/user-management.js?v=20261029-security2" defer></script>
<?php
$role_names=['student'=>'Students','examinee'=>'Examinees','guidance_advocate'=>'Guidance advocates','admin'=>'Admins / Counselors','super_admin'=>'Superadmins'];
$role_descriptions=[''=>'Every current user role','student'=>'Enrolled student accounts','examinee'=>'Entrance exam applicants','guidance_advocate'=>'Staff who support counseling','admin'=>'Guidance office administrators','super_admin'=>'System and account administrators'];
$role_symbols=[''=>'fa-users','student'=>'fa-user-graduate','examinee'=>'fa-clipboard-list','guidance_advocate'=>'fa-hands-holding-circle','admin'=>'fa-user-shield','super_admin'=>'fa-user-gear'];
$role_counts=[];$total_accounts=0;foreach($user_stats as $stat){$role_counts[$stat['role']]=$stat;$total_accounts+=(int)$stat['count'];}
?>
<section class="user-management" aria-labelledby="um-title">
    <div class="um-heading"><div><p class="um-eyebrow">Account administration</p><h1 id="um-title">User management</h1><p>Manage accounts, roles, and access in one place.</p></div><div class="um-heading-actions"><a class="um-button um-secondary" href="layout.php?page=convert_examinee"><i class="fas fa-user-graduate" aria-hidden="true"></i>Enroll passed examinees</a><form method="post"><input type="hidden" name="_users_csrf" value="<?=htmlspecialchars($_SESSION['user_management_csrf'],ENT_QUOTES)?>"><button name="export_users" value="1" class="um-button um-secondary"><i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i>Export CSV</button></form><button type="button" class="um-button um-secondary" onclick="openUserDialog('importModal')"><i class="fas fa-arrow-down-to-bracket" aria-hidden="true"></i>Import CSV</button><button type="button" class="um-button um-primary" onclick="openUserDialog('createUserModal')"><i class="fas fa-plus" aria-hidden="true"></i>Add user</button></div></div>
<?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <h2 class="um-chooser-title">Which users do you want to manage? <span>Choose a role to see its accounts.</span></h2>
    <div class="um-role-summary" aria-label="Filter accounts by role">
    <?php foreach([''=>'All accounts']+$role_names as $value=>$label):$count=$value===''?$total_accounts:(int)($role_counts[$value]['count']??0);?>
        <button type="button" class="um-role-card <?=$value===''?'is-selected':''?>" data-um-role="<?=$value?>" aria-pressed="<?=$value===''?'true':'false'?>" onclick="chooseUserRole('<?=$value?>')"><span class="um-role-top"><span class="um-role-icon"><i class="fas <?=$role_symbols[$value]?>" aria-hidden="true"></i></span><span class="um-role-selected"><i class="fas fa-circle-check" aria-hidden="true"></i>Selected</span></span><span class="um-role-name"><?=$label?></span><span class="um-role-description"><?=$role_descriptions[$value]?></span><span class="um-role-footer"><span><b><?=number_format($count)?></b> current accounts</span><span class="um-role-open">View <i class="fas fa-arrow-right" aria-hidden="true"></i></span></span></button>
    <?php endforeach;?></div>
    <div class="um-workspace" data-role="">
        <div class="um-tabs" role="tablist" aria-label="Account lists"><button type="button" id="tab-active" role="tab" aria-selected="true" aria-controls="panel-active" class="um-tab is-active" onclick="switchUserTab('active')"><i class="fas fa-users" aria-hidden="true"></i>Current accounts</button><button type="button" id="tab-archived" role="tab" aria-selected="false" aria-controls="panel-archived" tabindex="-1" class="um-tab" onclick="switchUserTab('archived')"><i class="fas fa-box-archive" aria-hidden="true"></i>Archived accounts</button><button type="button" id="tab-imports" role="tab" aria-selected="false" aria-controls="panel-imports" tabindex="-1" class="um-tab" onclick="switchUserTab('imports')"><i class="fas fa-file-arrow-up" aria-hidden="true"></i>Import history</button></div>
        <div class="um-filters" id="userFilters"><div class="um-search-row"><label class="um-search"><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input type="search" id="searchInput" aria-label="Search accounts" placeholder="Search name, email, or student ID" oninput="debounceSearch()"></label><button type="button" class="um-filter-toggle um-button um-secondary" aria-controls="umFilterFields" aria-expanded="false" onclick="toggleUserFilters(this)"><i class="fas fa-sliders" aria-hidden="true"></i>Filters</button></div>
            <div class="um-filter-fields" id="umFilterFields"><label>Role<select id="roleFilter" onchange="onRoleFilterChange()"><option value="">All roles</option><option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance advocate</option><option value="admin">Admin / Counselor</option><option value="super_admin">Superadmin</option></select></label><label>Status<select id="statusFilter" onchange="onStatusFilterChange()"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select></label><label>Name starts with<select id="letterFilter" onchange="onLetterFilterChange()"><option value="">Any letter</option><?php foreach(range('A','Z') as $letter):?><option value="<?=$letter?>"><?=$letter?></option><?php endforeach;?></select></label><button type="button" class="um-clear" onclick="clearUserFilters()">Reset filters</button></div>
            <div id="umMissingIdNotice" class="um-filter-notice hidden">Showing students without an ID.<button type="button" onclick="clearUserFilters()">Clear this filter</button></div>
        </div>
        <?php foreach(['active','archived'] as $list):$archived=$list==='archived';?>
        <section id="panel-<?=$list?>" class="um-list-panel <?=$archived?'hidden':''?>" role="tabpanel" aria-labelledby="tab-<?=$list?>">
            <div class="um-list-heading"><div><span class="um-list-eyebrow">You are viewing</span><h2 id="umListTitle-<?=$list?>"><?=$archived?'Archived accounts':'All current accounts'?></h2><p id="umListHint-<?=$list?>"><?=$archived?'These accounts are archived and cannot sign in. Restore an account to make it available again.':'Current accounts include Active (can sign in) and Inactive (sign-in disabled) users.'?></p></div><button type="button" id="<?=$archived?'bulkUnarchiveBtn':'bulkArchiveBtn'?>" class="um-button <?=$archived?'um-secondary':'um-danger'?> hidden" onclick="<?=$archived?'bulkUnarchiveUsers':'bulkArchiveUsers'?>()"><i class="fas <?=$archived?'fa-rotate-left':'fa-box-archive'?>" aria-hidden="true"></i><?=$archived?'Restore':'Archive'?> selected (<span id="<?=$archived?'archivedSelectedCount':'selectedCount'?>">0</span>)</button></div>
            <div class="um-table-wrap"><table class="um-user-table" id="<?=$archived?'archivedUsersTable':'activeUsersTable'?>"><caption class="um-sr-only"><?=$archived?'Archived':'Current'?> user accounts</caption><thead><tr><th class="um-check-cell"><input type="checkbox" id="<?=$archived?'selectAllArchived':'selectAllUsers'?>" aria-label="Select all <?=$archived?'archived':'current'?> users on this page" onchange="<?=$archived?'toggleSelectAllArchived':'toggleSelectAll'?>()"></th><th>Account</th><th>Role</th><th>Student ID</th><th>Status</th><th>Joined</th><th class="um-actions-cell">Actions</th></tr></thead><tbody id="<?=$archived?'archivedUsersBody':'activeUsersBody'?>"><tr><td colspan="7" class="um-empty">Loading accounts…</td></tr></tbody></table></div>
            <div class="um-pagination"><span id="<?=$list?>PageInfo" role="status"></span><div class="um-pagination-controls"><label class="um-page-size"><span>Rows</span><select id="<?=$list?>ItemsPerPage" onchange="onItemsPerPageChange(event)" aria-label="<?=$archived?'Archived':'Current'?> accounts per page"><option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="50">50</option></select></label><button type="button" id="<?=$list?>PrevBtn" onclick="<?=$list?>ChangePage(-1)" class="um-page-button" aria-label="Previous <?=$list?> accounts page"><i class="fas fa-chevron-left" aria-hidden="true"></i><span>Previous</span></button><span id="<?=$list?>PageNums" class="um-page-numbers"></span><span id="<?=$list?>PageIndicator" class="um-page-indicator"></span><button type="button" id="<?=$list?>NextBtn" onclick="<?=$list?>ChangePage(1)" class="um-page-button" aria-label="Next <?=$list?> accounts page"><span>Next</span><i class="fas fa-chevron-right" aria-hidden="true"></i></button></div></div>
        </section><?php endforeach;?>
        <section id="panel-imports" class="um-list-panel hidden" role="tabpanel" aria-labelledby="tab-imports"><div class="um-list-heading"><div><h2>Recent imports</h2><p>The latest 10 import jobs and their results.</p></div><button type="button" class="um-button um-secondary" onclick="openUserDialog('importModal')">Import users</button></div><div class="um-import-wrap"><table class="um-import-table"><thead><tr><th>Date</th><th>File</th><th>Type</th><th>Total</th><th>Imported</th><th>Failed</th><th>Status</th><th>Imported by</th></tr></thead><tbody>
        <?php if($import_history->rowCount()>0):while($imp=$import_history->fetch(PDO::FETCH_ASSOC)):?><tr><td data-label="Date"><?=date('M j, Y g:i A',strtotime($imp['created_at']))?></td><td data-label="File"><?=htmlspecialchars($imp['filename'])?></td><td data-label="Type"><?=htmlspecialchars($imp['file_type'])?></td><td data-label="Total"><?=(int)$imp['total_records']?></td><td data-label="Imported"><?=(int)$imp['successful_imports']?></td><td data-label="Failed"><?=(int)$imp['failed_imports']?></td><td data-label="Status"><?=htmlspecialchars(ucfirst($imp['import_status']))?></td><td data-label="Imported by"><?=htmlspecialchars($imp['first_name'].' '.$imp['last_name'])?></td></tr><?php endwhile;else:?><tr><td colspan="8" class="um-empty"><span class="um-empty-icon"><i class="fas fa-file-arrow-up" aria-hidden="true"></i></span><strong>No imports yet</strong><p>Imported files and their results will appear here.</p></td></tr><?php endif;?></tbody></table></div></section>
    </div>
</section>


<!-- Create User Modal -->
<div id="createUserModal" class="um-modal pd-overlay hidden" data-portal-dialog data-dialog-tone="add" role="dialog" aria-modal="true" aria-labelledby="createUserModalTitle" aria-describedby="createUserModalHint">
    <div class="pd-card"><div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="createUserModalTitle">Add a user</h2><p id="createUserModalHint">Create an account and choose what this person can access.</p></div><button type="button" class="pd-close" aria-label="Close add a user" onclick="closeUserDialog('createUserModal')"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <form data-portal-save method="POST" ><input type="hidden" name="_users_csrf" value="<?=htmlspecialchars($_SESSION['user_management_csrf'],ENT_QUOTES)?>"><input type="hidden" name="create_user" value="1"><div class="pd-body"><p class="pd-required-note">Fields marked * are required.</p><div class="pd-callout"><a href="layout.php?page=convert_examinee">Enroll passed examinees</a><p class="pd-hint">Preserve the existing account and assign an official student ID and academic placement.</p></div><fieldset class="pd-group"><legend>Personal details</legend><div class="pd-grid"><div class="pd-field"><label for="create_first_name">First name *</label><input type="text" name="first_name" id="create_first_name" required autocomplete="given-name"></div><div class="pd-field"><label for="create_last_name">Last name *</label><input type="text" name="last_name" id="create_last_name" required autocomplete="family-name"></div></div><div class="pd-grid"><div class="pd-field"><label for="create_middle_name">Middle name <span class=pd-optional>Optional</span></label><input type="text" name="middle_name" id="create_middle_name" autocomplete="additional-name"></div><div class="pd-field"><label for="create_email">Email</label><input type="email" name="email" id="create_email" autocomplete="email" aria-describedby="create_email-hint"><p class=pd-hint><span id="create_email-hint">Required for staff and examinee accounts.</span></p></div></div></fieldset><fieldset class="pd-group"><legend>Account & access</legend><div class="pd-grid"><div class="pd-field"><label for="createRole">Account role *</label><select name="role" id="createRole" required onchange="toggleStudentFields()"><option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance advocate</option><option value="admin">Admin / Counselor</option><option value="super_admin">Superadmin</option></select><p class="pd-hint">Controls which pages this person can access.</p></div><div id="positionField"><div class="pd-field"><label for="create_position">Position <span class=pd-optional>Optional</span></label><input type="text" name="position" id="create_position" ></div></div></div><div id="studentIdField" class="pd-grid" style="margin-top:16px"><div class="pd-field"><label for="create_student_id">Student ID</label><input type="text" name="student_id" id="create_student_id" ><p class=pd-hint>Use the official school ID. Required when converting an examinee.</p></div></div><div class="pd-grid"><div class="pd-field pd-span"><label for="create_password">Initial password</label><div class="pd-password"><input type="password" name="password" id="create_password" autocomplete="new-password" ><button type="button" data-password-toggle="create_password" aria-controls="create_password" aria-pressed="false">Show</button></div><p class="pd-hint" id="create_password-hint">Leave blank to use the default: password123. Share it with the account holder.</p></div></div></fieldset></div><div class="pd-footer"><button type="button" class="pd-button" onclick="closeUserDialog('createUserModal')">Cancel</button><button type="submit" class="pd-button pd-button-primary">Create account</button></div></form></div></div>
<div id="editUserModal" class="um-modal pd-overlay hidden" data-portal-dialog data-dialog-tone="edit" role="dialog" aria-modal="true" aria-labelledby="editUserModalTitle" aria-describedby="editUserModalHint">
    <div class="pd-card"><div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-user-pen" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="editUserModalTitle">Edit account details</h2><p id="editUserModalHint">Update this person’s information, role, and sign-in access.</p></div><button type="button" class="pd-close" aria-label="Close edit account details" onclick="closeUserDialog('editUserModal')"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <form data-portal-save method="POST" ><input type="hidden" name="_users_csrf" value="<?=htmlspecialchars($_SESSION['user_management_csrf'],ENT_QUOTES)?>"><input type="hidden" name="edit_user" value="1"><input type="hidden" name="user_id" id="edit_user_id"><div class="pd-body"><p class="pd-required-note">Fields marked * are required.</p><fieldset class="pd-group"><legend>Personal details</legend><div class="pd-grid"><div class="pd-field"><label for="edit_first_name">First name *</label><input type="text" name="first_name" id="edit_first_name" required autocomplete="given-name"></div><div class="pd-field"><label for="edit_last_name">Last name *</label><input type="text" name="last_name" id="edit_last_name" required autocomplete="family-name"></div></div><div class="pd-grid"><div class="pd-field"><label for="edit_middle_name">Middle name <span class=pd-optional>Optional</span></label><input type="text" name="middle_name" id="edit_middle_name" ></div><div class="pd-field"><label for="edit_email">Email</label><input type="email" name="email" id="edit_email" autocomplete="email"></div></div></fieldset><fieldset class="pd-group"><legend>Account & access</legend><div class="pd-grid"><div class="pd-field"><label for="edit_role">Account role *</label><select name="role" id="edit_role" required><option value="student">Student</option><option value="examinee">Examinee</option><option value="guidance_advocate">Guidance advocate</option><option value="admin">Admin / Counselor</option><option value="super_admin">Superadmin</option></select><p class="pd-hint">Controls which pages this person can access.</p></div><div class="pd-field"><label for="edit_student_id">Student ID</label><input type="text" name="student_id" id="edit_student_id" ><p class=pd-hint>Used for student accounts.</p></div></div><label class="pd-status" for="edit_is_active" style="margin-top:17px"><input type="checkbox" name="is_active" id="edit_is_active" value="1"><span><strong>Allow sign-in</strong><small>Checked: this account can sign in. Unchecked: sign-in is disabled.</small></span></label></fieldset></div><div class="pd-footer"><button type="button" class="pd-button" onclick="closeUserDialog('editUserModal')">Cancel</button><button type="submit" class="pd-button pd-button-primary">Save changes</button></div></form></div></div>
<div id="resetPasswordModal" class="um-modal pd-overlay hidden" data-portal-dialog data-dialog-tone="security" role="dialog" aria-modal="true" aria-labelledby="resetPasswordModalTitle" aria-describedby="resetPasswordModalHint">
    <div class="pd-card pd-card-small"><div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-key" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="resetPasswordModalTitle">Reset password</h2><p id="resetPasswordModalHint">Replace the current password with a new one.</p></div><button type="button" class="pd-close" aria-label="Close reset password" onclick="closeUserDialog('resetPasswordModal')"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <form data-portal-save method="POST" ><input type="hidden" name="_users_csrf" value="<?=htmlspecialchars($_SESSION['user_management_csrf'],ENT_QUOTES)?>"><input type="hidden" name="reset_password" value="1"><input type="hidden" name="user_id" id="reset_user_id"><div class="pd-body"><div class="pd-callout pd-callout-warning"><strong>You are resetting the password for</strong><span id="reset_user_name" class="pd-account-name"></span></div><div class="pd-field pd-span"><label for="reset_new_password">New password *</label><div class="pd-password"><input type="password" name="new_password" id="reset_new_password" autocomplete="new-password" required minlength="8" aria-describedby="reset_new_password-hint"><button type="button" data-password-toggle="reset_new_password" aria-controls="reset_new_password" aria-pressed="false">Show</button></div><p class="pd-hint" id="reset_new_password-hint">Use at least 8 characters. The account holder will need this new password to sign in.</p></div></div><div class="pd-footer"><button type="button" class="pd-button" onclick="closeUserDialog('resetPasswordModal')">Cancel</button><button type="submit" class="pd-button pd-button-primary">Reset password</button></div></form></div></div>
<div id="importModal" class="um-modal pd-overlay hidden" data-portal-dialog data-dialog-tone="import" role="dialog" aria-modal="true" aria-labelledby="importModalTitle" aria-describedby="importModalHint">
    <div class="pd-card pd-card-small"><div class="pd-header"><span class="pd-heading-icon"><i class="fas fa-file-arrow-up" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="importModalTitle">Import users from CSV</h2><p id="importModalHint">Add multiple accounts using the provided spreadsheet template.</p></div><button type="button" class="pd-close" aria-label="Close import users from csv" onclick="closeUserDialog('importModal')"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <form data-portal-save method="POST" enctype="multipart/form-data"><input type="hidden" name="_users_csrf" value="<?=htmlspecialchars($_SESSION['user_management_csrf'],ENT_QUOTES)?>"><div class="pd-body"><div class="pd-callout"><strong>Start with the sample CSV</strong>Download the template, fill in the user details, then upload the completed file.</div><div class="pd-upload pd-field"><label for="userCsvFile">CSV file *</label><input type="file" name="csv_file" id="userCsvFile" accept=".csv" required aria-describedby="userCsvHint"><p class="pd-hint" id="userCsvHint">Choose a .csv file with one user per row.</p></div><p class="pd-schema">Column order: password, email, first_name, middle_name, last_name, student_id, role.</p><button type="submit" name="generate_sample" value="1" data-full-navigation formnovalidate class="pd-button" style="margin-top:16px"><i class="fas fa-download" aria-hidden="true"></i>Download sample CSV</button></div><div class="pd-footer"><button type="button" class="pd-button" onclick="closeUserDialog('importModal')">Cancel</button><button type="submit" name="import_csv" value="1" class="pd-button pd-button-primary">Import users</button></div></form></div></div>
