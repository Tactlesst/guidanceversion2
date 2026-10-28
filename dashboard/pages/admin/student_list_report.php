<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

// AJAX endpoint for fetching student list
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $department = $_GET['department'] ?? '';
        $search = trim($_GET['q'] ?? '');
        $strand = $_GET['strand'] ?? '';
        $program = $_GET['program'] ?? '';
        $grade = $_GET['grade'] ?? '';
        
        $w = ["u.role = 'student'", "(u.archived=0 OR u.archived IS NULL)"];
        $p_arr = [];
        
        if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
        if ($strand) { $w[] = "sp.strand = ?"; $p_arr[] = $strand; }
        if ($program) { $w[] = "sp.program = ?"; $p_arr[] = $program; }
        if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
        
        if ($search !== '') { $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_id LIKE ?)"; $term = '%'.$search.'%'; array_push($p_arr, $term, $term, $term); }
        $where = implode(' AND ', $w);
        
        // Fetch all students ordered by department, strand, program, last name, first name
        $stmt = $db->prepare("SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, sp.student_id, sp.department, sp.strand, sp.program, sp.grade_level
            FROM users u 
            LEFT JOIN student_profiles sp ON u.id=sp.user_id
            WHERE $where ORDER BY sp.department, sp.strand, sp.program, u.last_name, u.first_name");
        $stmt->execute($p_arr);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Get departments, strands, grades, programs for filters
$departments = [];
$strands = [];
$grades = [];
$programs = [];

try {
    $departments = $db->query("SELECT DISTINCT department FROM student_profiles WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    
    // Fetch strands with their department relationships
    $strands_query = $db->query("
        SELECT DISTINCT sp.strand, s.department_id, d.name as department_name
        FROM student_profiles sp
        LEFT JOIN academic_strands s ON sp.strand = s.name
        LEFT JOIN academic_departments d ON s.department_id = d.id
        WHERE sp.strand IS NOT NULL AND sp.strand != ''
        ORDER BY sp.strand
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $strands = [];
    $strand_departments = [];
    foreach ($strands_query as $row) {
        $strands[] = $row['strand'];
        $strand_departments[$row['strand']] = $row['department_name'];
    }
    
    // Fetch programs with their department relationships
    $programs_query = $db->query("
        SELECT DISTINCT sp.program, p.department_id, d.name as department_name
        FROM student_profiles sp
        LEFT JOIN academic_programs p ON sp.program = p.name
        LEFT JOIN academic_departments d ON p.department_id = d.id
        WHERE sp.program IS NOT NULL AND sp.program != ''
        ORDER BY sp.program
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $programs = [];
    $program_departments = [];
    foreach ($programs_query as $row) {
        $programs[] = $row['program'];
        $program_departments[$row['program']] = $row['department_name'];
    }
    
    $grades = $db->query("SELECT DISTINCT grade_level FROM student_profiles WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<?php $approvedList=null;if($role==='guidance_advocate'&&!empty($_GET['approval_run'])){require_once __DIR__.'/../../../classes/ReportApproval.php';$r=(new ReportApproval($db))->find((int)$_GET['approval_run'],(int)$uid);if($r&&$r['resource']==='student_list'&&$r['status']==='approved')$approvedList=json_decode($r['scope_json'],true);}?>
<script type="application/json" id="approvedStudentListOptions"><?=json_encode($approvedList,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<div class="space-y-6" id="studentListBuilder" data-signer="<?=htmlspecialchars(trim(($user_info['first_name']??'').' '.($user_info['middle_name']??'').' '.($user_info['last_name']??'')),ENT_QUOTES)?>">
    <div class="flex items-center justify-between"><h1 data-cw-legacy-title>Student lists &amp; printing</h1></div>
    <section class="bg-white rounded-xl p-4" aria-label="Choose students">
        <div class="flex flex-wrap gap-3 items-center">
            <input id="searchInput" type="search" placeholder="Search name or student ID" aria-label="Search students">
            <select id="departmentFilter" aria-label="Department"><option value="">All departments</option><?php foreach($departments as $dept):?><option value="<?=htmlspecialchars($dept,ENT_QUOTES)?>"><?=htmlspecialchars($dept)?></option><?php endforeach;?></select>
            <select id="strandFilter" aria-label="SHS strand" class="hidden"><option value="">All strands</option><?php foreach($strands as $strand):?><option value="<?=htmlspecialchars($strand,ENT_QUOTES)?>" data-department="<?=htmlspecialchars($strand_departments[$strand]??'',ENT_QUOTES)?>"><?=htmlspecialchars($strand)?></option><?php endforeach;?></select>
            <select id="programFilter" aria-label="College program" class="hidden"><option value="">All programs</option><?php foreach($programs as $program):?><option value="<?=htmlspecialchars($program,ENT_QUOTES)?>" data-department="<?=htmlspecialchars($program_departments[$program]??'',ENT_QUOTES)?>"><?=htmlspecialchars($program)?></option><?php endforeach;?></select>
            <select id="gradeFilter" aria-label="Grade / year"><option value="">All grades / years</option><?php foreach($grades as $grade):?><option value="<?=htmlspecialchars($grade,ENT_QUOTES)?>"><?=htmlspecialchars($grade)?></option><?php endforeach;?></select>
        </div>
    </section>
    <section class="bg-white rounded-xl tpl-editor" data-template-editor aria-labelledby="templateEditorTitle">
        <div class="tpl-heading"><div><h2 id="templateEditorTitle">Build your printable list</h2><p id="templateSummary">Choose a format and the columns to include.</p></div><button type="button" id="templateEditorToggle" class="wf-toggle" aria-controls="templateEditorBody" aria-expanded="false">Show options</button></div>
        <div id="templateEditorBody" class="tpl-editor-body" hidden>
        <div class="tpl-grid">
            <label>List format<select id="listTemplate"><option value="custom">Custom / blank sheet</option><option value="attendance">Attendance</option><option value="acknowledgment">Acknowledgment</option><option value="records">Detailed student records</option></select></label>
            <label class="tpl-title-field">Report title<input id="listTitle" maxlength="120" value="Student List" required></label>
            <label>Arrange students<select id="listGrouping"><option value="program">Separate each program / strand</option><option value="combined">One alphabetical list (A–Z)</option></select></label>
        </div>
        <fieldset class="tpl-column-picker"><legend>Columns to include</legend><div id="listColumnChoices" class="tpl-column-choices"></div><p id="listColumnHint" aria-live="polite">Choose at least one column.</p></fieldset>
        <div id="templateBlankColumns" class="tpl-grid tpl-columns"><label>Column 1 heading<input id="listColumn1" maxlength="40" placeholder="Leave blank or enter a heading"></label><label>Column 2 heading<input id="listColumn2" maxlength="40" placeholder="Leave blank or enter a heading"></label><label>Column 3 heading<input id="listColumn3" maxlength="40" placeholder="Leave blank or enter a heading"></label></div>
        <div class="tpl-bottom"><label class="tpl-signature-option"><input type="checkbox" id="listSignatures" checked> Include Guidance Counselor and School Administrator approval lines</label><p id="templateDescription">Student ID and name, plus three empty columns for writing on the printed sheet.</p></div>
        <span class="tpl-local-note">Last format remembered in this browser session</span>
        </div>
    </section>
    <section class="bg-white rounded-xl tpl-preview" aria-labelledby="listPreviewHeading">
        <div class="tpl-preview-bar"><div><p class="tpl-eyebrow">Print preview</p><h2 id="listPreviewHeading">Student List</h2><p id="listScope">Loading students…</p></div><button type="button" id="printSelectedList" class="bg-primary"><i class="fas fa-print" aria-hidden="true"></i> Print selected list</button></div>
        <p id="studentListStatus" role="status" aria-live="polite"></p><div id="studentGroups"></div>
    </section>
</div>
<script><?php readfile(__DIR__.'/../../../assets/js/student-list-builder.js'); ?></script>
