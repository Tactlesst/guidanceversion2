<?php
// Academic Settings Management
// Included by layout.php - $db variable is already available

// Role-based access control - Only super admins can manage academic settings
if ($role != 'super_admin') {
    header('Location: layout.php?page=dashboard');
    exit();
}

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Handle form submissions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                
                switch ($type) {
                    case 'department':
                        $stmt = $db->prepare("INSERT INTO academic_departments (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $success_message = "Department added successfully!";
                        logAdminAction('add', "Added department: $name", null, $db);
                        break;
                    case 'program':
                        $stmt = $db->prepare("INSERT INTO academic_programs (name, description, department_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $department_id]);
                        $success_message = "Program added successfully!";
                        logAdminAction('add', "Added program: $name", null, $db);
                        break;
                    case 'strand':
                        $stmt = $db->prepare("INSERT INTO academic_strands (name, description, department_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $department_id]);
                        $success_message = "Strand added successfully!";
                        logAdminAction('add', "Added strand: $name", null, $db);
                        break;
                    case 'grade':
                        $stmt = $db->prepare("INSERT INTO academic_grade_levels (department_id, name) VALUES (?, ?)");
                        $stmt->execute([$department_id, $name]);
                        $success_message = "Grade level added successfully!";
                        logAdminAction('add', "Added grade level: $name", null, $db);
                        break;
                }
                // Redirect to refresh data
                $_SESSION['success_message']=$success_message;
                $saved_section=$type?:array_search($_POST['table']??'', ['department'=>'academic_departments','program'=>'academic_programs','strand'=>'academic_strands','grade'=>'academic_grade_levels'],true);
                header("Location: " . portalSaveRedirect("layout.php?page=academic_settings&section=" . urlencode($saved_section?:'department')));
                exit();
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                
                switch ($type) {
                    case 'department':
                        $stmt = $db->prepare("UPDATE academic_departments SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $id]);
                        $success_message = "Department updated successfully!";
                        logAdminAction('edit', "Updated department: $name", null, $db);
                        break;
                    case 'program':
                        $stmt = $db->prepare("UPDATE academic_programs SET name = ?, description = ?, department_id = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $department_id, $id]);
                        $success_message = "Program updated successfully!";
                        logAdminAction('edit', "Updated program: $name", null, $db);
                        break;
                    case 'strand':
                        $stmt = $db->prepare("UPDATE academic_strands SET name = ?, description = ?, department_id = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $department_id, $id]);
                        $success_message = "Strand updated successfully!";
                        logAdminAction('edit', "Updated strand: $name", null, $db);
                        break;
                    case 'grade':
                        $stmt = $db->prepare("UPDATE academic_grade_levels SET department_id = ?, name = ? WHERE id = ?");
                        $stmt->execute([$department_id, $name, $id]);
                        $success_message = "Grade level updated successfully!";
                        logAdminAction('edit', "Updated grade level: $name", null, $db);
                        break;
                }
                // Redirect to refresh data
                $_SESSION['success_message']=$success_message;
                $saved_section=$type?:array_search($_POST['table']??'', ['department'=>'academic_departments','program'=>'academic_programs','strand'=>'academic_strands','grade'=>'academic_grade_levels'],true);
                header("Location: " . portalSaveRedirect("layout.php?page=academic_settings&section=" . urlencode($saved_section?:'department')));
                exit();
                break;
                
            case 'toggle_status':
                $table = $_POST['table'];
                $id = $_POST['id'];
                $status = $_POST['status'];
                
                $allowed_tables = ['academic_departments', 'academic_programs', 'academic_strands', 'academic_grade_levels'];
                if (in_array($table, $allowed_tables)) {
                    $stmt = $db->prepare("UPDATE {$table} SET is_active = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $success_message = "Status updated successfully!";
                    
                    // Look up the item name for a clean log message
                    $item_name = '';
                    try {
                        $name_stmt = $db->prepare("SELECT name FROM {$table} WHERE id = ? LIMIT 1");
                        $name_stmt->execute([$id]);
                        $item_name = $name_stmt->fetchColumn() ?: "ID $id";
                    } catch (Exception $e) { $item_name = "ID $id"; }
                    
                    $action_word = $status ? 'Activated' : 'Deactivated';
                    logAdminAction('toggle_status', "$action_word: $item_name", null, $db);
                }
                // Redirect to refresh data
                $_SESSION['success_message']=$success_message;
                $saved_section=$type?:array_search($_POST['table']??'', ['department'=>'academic_departments','program'=>'academic_programs','strand'=>'academic_strands','grade'=>'academic_grade_levels'],true);
                header("Location: " . portalSaveRedirect("layout.php?page=academic_settings&section=" . urlencode($saved_section?:'department')));
                exit();
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success_message = is_string($_GET['success']) ? $_GET['success'] : ''; // Legacy saved links; rendered as plain text.
}

// Fetch all academic data
$departments = $db->query("SELECT * FROM academic_departments ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$programs = $db->query("
    SELECT p.*, d.name as department_name 
    FROM academic_programs p 
    LEFT JOIN academic_departments d ON p.department_id = d.id 
    ORDER BY p.sort_order, p.name
")->fetchAll(PDO::FETCH_ASSOC);
$strands = $db->query("
    SELECT s.*, d.name as department_name 
    FROM academic_strands s 
    LEFT JOIN academic_departments d ON s.department_id = d.id 
    ORDER BY s.sort_order, s.name
")->fetchAll(PDO::FETCH_ASSOC);
$grade_levels = $db->query("
    SELECT gl.*, d.name as department_name 
    FROM academic_grade_levels gl 
    JOIN academic_departments d ON gl.department_id = d.id 
    ORDER BY d.sort_order, gl.sort_order
")->fetchAll(PDO::FETCH_ASSOC);

// Combine all data for dynamic rendering
$academic_data = [
    'department' => $departments,
    'program' => $programs,
    'strand' => $strands,
    'grade' => $grade_levels
];

// Define field configurations for each type
$field_configs = [
    'department' => [
        'name_label' => 'Department Name',
        'show_department' => false,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Status', 'Actions']
    ],
    'program' => [
        'name_label' => 'Program Name',
        'show_department' => true,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Status', 'Actions']
    ],
    'strand' => [
        'name_label' => 'Strand Name',
        'show_department' => true,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Department', 'Status', 'Actions']
    ],
    'grade' => [
        'name_label' => 'Grade Level Name',
        'show_department' => true,
        'show_description' => false,
        'table_columns' => ['Grade Level', 'Department', 'Status', 'Actions']
    ]
];
?>

<link rel="stylesheet" href="../assets/css/academic-settings.css?v=20261027-2">
<?php $academic_sections=[
    'department'=>['Departments','fa-building-columns','School divisions','e.g., Junior High, Higher Education'],
    'program'=>['College programs','fa-graduation-cap','College courses / degrees','e.g., BSIT, BSED'],
    'strand'=>['SHS strands','fa-diagram-project','Senior high school tracks','e.g., STEM, ABM, HUMSS'],
    'grade'=>['Grade & year levels','fa-layer-group','Students’ level in school','e.g., Grade 7, 1st Year College']
]; ?>
<section class="academic-settings" aria-labelledby="academic-title">
    <div class="ac-heading"><div><p class="ac-eyebrow">School administration</p><h1 id="academic-title">Academic settings</h1><p>Choose what you want to manage below.</p></div></div>
<?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <h2 class="ac-chooser-title">What would you like to manage? <span>Select a section to view or update its records.</span></h2>
    <div class="ac-sections" role="tablist" aria-label="Academic sections">
    <?php foreach($academic_sections as $key=>[$label,$icon,$description,$examples]):$records=$academic_data[$key];?>
        <button type="button" id="academicTab-<?=$key?>" class="ac-section <?=$key==='department'?'is-selected':''?>" role="tab" aria-selected="<?=$key==='department'?'true':'false'?>" aria-controls="academicPanel" tabindex="<?=$key==='department'?'0':'-1'?>" data-ac-section="<?=$key?>" onclick="selectAcademicSection('<?=$key?>')">
            <span class="ac-section-identity"><span class="ac-section-icon"><i class="fas <?=$icon?>" aria-hidden="true"></i></span><span class="ac-viewing"><i class="fas fa-circle-check" aria-hidden="true"></i>Selected</span></span>
            <span class="ac-section-name"><?=$label?></span><span class="ac-section-description"><?=$description?></span><span class="ac-section-examples"><?=$examples?></span>
            <span class="ac-section-bottom"><span><b><?=count($records)?></b> records</span><span class="ac-section-open">Open <i class="fas fa-arrow-right" aria-hidden="true"></i></span></span>
        </button>
    <?php endforeach;?></div>
    <section id="academicPanel" class="ac-panel" data-section="department" role="tabpanel" aria-labelledby="academicTab-department">
        <div class="ac-panel-heading"><div class="ac-panel-context"><span class="ac-panel-eyebrow"><i class="fas fa-circle" aria-hidden="true"></i>You are managing</span><h2 id="academicListTitle">Departments</h2><p id="academicListHint">The main divisions of your school. Programs, strands, and grade levels belong to a department.</p><p id="academicListExamples" class="ac-panel-examples">Examples: Junior High School, Senior High School, Higher Education</p></div><div class="ac-panel-tools"><span class="ac-count" id="academicCount"><?=count($departments)?> records</span><button type="button" id="academicAddButton" class="ac-button ac-primary" onclick="openAcademicAdd()"><i class="fas fa-plus" aria-hidden="true"></i><span id="academicAddLabel">Add department</span></button></div></div>
        <div class="ac-filters"><label class="ac-search"><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input type="search" id="academicSearch" aria-label="Search academic records" placeholder="Search departments" oninput="filterAcademicRecords()"></label><div class="ac-filter-options"><label>Status<select id="academicStatus" onchange="filterAcademicRecords()"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select></label><label id="academicDepartmentFilter" class="hidden">Department<select id="academicDepartment" onchange="filterAcademicRecords()"><option value="">All departments</option><?php foreach($departments as $department):?><option value="<?=(int)$department['id']?>"><?=htmlspecialchars($department['name'])?></option><?php endforeach;?></select></label><button type="button" class="ac-reset" onclick="resetAcademicFilters()">Reset filters</button></div></div>
        <div class="ac-table-wrap"><table class="ac-table"><caption class="ac-sr-only" id="academicCaption">Academic department records</caption><thead><tr id="academicTableHead"><th scope="col">Department</th><th scope="col">Status</th><th scope="col" class="ac-actions-cell">Actions</th></tr></thead><tbody id="academicTableBody"></tbody></table></div>
        <div class="ac-pagination"><span id="academicPageInfo" role="status" aria-live="polite"></span><div class="ac-page-controls"><label>Rows<select id="academicPageSize" aria-label="Academic records per page" onchange="resizeAcademicPage()"><option value="10">10</option><option value="20">20</option><option value="50">50</option></select></label><button type="button" class="ac-page-button" id="academicPrevious" onclick="changeAcademicPage(-1)" aria-label="Previous academic records page"><i class="fas fa-chevron-left" aria-hidden="true"></i></button><span id="academicPageNumber"></span><button type="button" class="ac-page-button" id="academicNext" onclick="changeAcademicPage(1)" aria-label="Next academic records page"><i class="fas fa-chevron-right" aria-hidden="true"></i></button></div></div>
    </section>
    <p class="ac-help"><i class="far fa-circle-question" aria-hidden="true"></i>Deactivate an item to keep its record while removing it from active academic choices.</p>
</section>
<?php foreach(['add'=>'Add','edit'=>'Edit'] as $mode=>$verb):?>
<div id="academicDialog-<?=$mode?>" class="ac-modal pd-overlay hidden" data-portal-dialog data-dialog-tone="<?=$mode==='add'?'add':'edit'?>" role="dialog" aria-modal="true" aria-labelledby="academicDialogTitle-<?=$mode?>" aria-describedby="academicDialogHint-<?=$mode?>">
    <div class="ac-dialog pd-card pd-card-small"><div class="ac-dialog-heading pd-header"><span class="pd-heading-icon"><i class="fas <?=$mode==='add'?'fa-plus':'fa-pen-to-square'?>" aria-hidden="true"></i></span><div class="pd-heading-copy"><h2 id="academicDialogTitle-<?=$mode?>"><?=$verb?> department</h2><p id="academicDialogHint-<?=$mode?>"><?=$mode==='add'?'Enter the details for the new record.':'Update the details for this record.'?></p></div><button type="button" class="ac-close pd-close" aria-label="Close <?=$mode?> dialog" onclick="closeAcademicDialog()"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <form data-portal-save method="post" action="layout.php?page=academic_settings" id="academicForm-<?=$mode?>"><input type="hidden" name="action" value="<?=$mode?>"><input type="hidden" name="type" id="academicType-<?=$mode?>"><?php if($mode==='edit'):?><input type="hidden" name="id" id="academicId-edit"><?php endif;?>
        <div class="ac-dialog-fields pd-body"><label for="academicName-<?=$mode?>" id="academicNameLabel-<?=$mode?>">Department name <span aria-hidden="true">*</span></label><input name="name" type="text" id="academicName-<?=$mode?>" required maxlength="100" autocomplete="off" placeholder="e.g., Higher Education">
        <div id="academicParentField-<?=$mode?>" class="ac-form-field hidden"><label for="academicParent-<?=$mode?>">Department <span id="academicParentRequired-<?=$mode?>" aria-hidden="true">*</span></label><select name="department_id" id="academicParent-<?=$mode?>"><option value="">Select department</option><?php foreach($departments as $department):?><option value="<?=(int)$department['id']?>"><?=htmlspecialchars($department['name'])?><?=!(int)$department['is_active']?' (inactive)':''?></option><?php endforeach;?></select><p class="ac-field-hint">Choose the department this record belongs to.</p></div>
        <div id="academicDescriptionField-<?=$mode?>" class="ac-form-field"><label for="academicDescription-<?=$mode?>">Description <span class="ac-optional">Optional</span></label><textarea name="description" id="academicDescription-<?=$mode?>" rows="3" placeholder="Add a short description"></textarea></div></div>
        <div class="ac-dialog-footer pd-footer"><button type="button" class="ac-button ac-secondary pd-button" onclick="closeAcademicDialog()">Cancel</button><button type="submit" id="academicSave-<?=$mode?>" class="ac-button ac-primary pd-button pd-button-primary"><?=$mode==='add'?'Add department':'Save changes'?></button></div>
    </form></div>
</div><?php endforeach;?>
<script>const academicSettingsPayload = <?=json_encode($academic_data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;</script>
<script src="js/academic_settings.js?v=20261027-5" defer></script>
