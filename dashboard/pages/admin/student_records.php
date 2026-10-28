<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

require_once __DIR__.'/../../../includes/student_record_support.php';
$_SESSION['student_record_token']=$_SESSION['student_record_token']??bin2hex(random_bytes(32));
if(isset($_POST['update_student_profile'])){
    try{
        if(!in_array($_SESSION['role']??'',['admin','guidance_advocate','super_admin'],true))throw new InvalidArgumentException('You do not have permission to assign academics.');
        $token=$_POST['student_record_token']??'';
        if(!is_string($token)||!hash_equals($_SESSION['student_record_token'],$token))throw new InvalidArgumentException('Your form has expired. Reload the student list and try again.');
        saveStudentAcademicAssignment($db,$_POST);if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']='Academic assignment saved.';
    }catch(InvalidArgumentException $e){$_SESSION['error_message']=$e->getMessage();}
    catch(Throwable $e){error_log('Student academic assignment: '.$e->getMessage());$_SESSION['error_message']='The academic assignment could not be saved. Please try again.';}
    header('Location: '.portalSaveRedirect('layout.php?page=student_records'));exit;
}
if(($_GET['action']??'')==='get_record'){
    ob_end_clean();header('Content-Type: application/json');header('Cache-Control: private, no-store');
    if(!in_array($_SESSION['role']??'',['admin','guidance_advocate','super_admin'],true)){http_response_code(403);echo json_encode(['error'=>'Access denied.']);exit;}
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
    if(!$id||$id<1){http_response_code(400);echo json_encode(['error'=>'Choose a valid student.']);exit;}
    $record=studentRecordSummary($db,$id,in_array($role,['admin','guidance_advocate','super_admin'],true));
    if(!$record){http_response_code(404);echo json_encode(['error'=>'Student record unavailable.']);exit;}
    if(function_exists('staffActivityInteraction'))staffActivityInteraction('student_records','view_student_record');
    echo json_encode(['record'=>$record],JSON_INVALID_UTF8_SUBSTITUTE);exit;
}

// AJAX endpoint for server-side pagination
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    $pg = max(1, intval($_GET['p'] ?? 1));
    $per = isset($_GET['per']) ? max(1, intval($_GET['per'])) : 10;
    if ($per > 10000) $per = 10000;
    $off = ($pg - 1) * $per;
    $q = trim($_GET['q'] ?? '');
    $role = $_GET['role'] ?? 'all';
    $department = $_GET['department'] ?? '';
    $strand = $_GET['strand'] ?? '';
    $grade = $_GET['grade'] ?? '';
    $pdsFilter = ($_GET['pds']??'')==='with'?'with':'all';

    $w = ["u.role = 'student'", "(u.archived=0 OR u.archived IS NULL)"];
    $p_arr = [];
    if ($q) {
        $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
        $like = "%$q%"; $p_arr = [$like,$like,$like,$like];
    }
    if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
    if ($strand) { $w[] = "(sp.strand = ? OR sp.program = ?)"; $p_arr[] = $strand; $p_arr[] = $strand; }
    if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
    if($pdsFilter==='with')$w[]="EXISTS (SELECT 1 FROM pds saved_pds WHERE saved_pds.user_id=u.id)";
    $where = implode(' AND ', $w);

    // Count
    $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where");
    $c_stmt->execute($p_arr);
    $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $pg=min($pg,max(1,(int)ceil($total/$per)));$off=($pg-1)*$per;

    // Fetch page
    $stmt = $db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.email,u.is_active,u.created_at,sp.student_id,sp.department,sp.strand,sp.program,sp.grade_level FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY sp.department, sp.strand, sp.program, sp.grade_level, u.last_name, u.first_name LIMIT $per OFFSET $off");
    $stmt->execute($p_arr);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pds_set=[];$ids=array_column($rows,'id');
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$ps=$db->prepare("SELECT DISTINCT user_id FROM pds WHERE user_id IN ($marks)");$ps->execute($ids);foreach($ps->fetchAll(PDO::FETCH_COLUMN) as $pds_user)$pds_set[$pds_user]=true;}

    foreach ($rows as &$row) {
        $row['has_pds'] = !empty($pds_set[$row['id']]);
    }
    unset($row);

    echo json_encode(['rows'=>$rows, 'total'=>$total, 'per_page'=>$per, 'page'=>$pg]);
    exit();
}

$record_messages=fetchSessionMessages();
$success_message=$record_messages['success'];$error_message=$record_messages['error'];
// Cards and PDS badges use the same saved PDS records and student scope as the list.
$summaryQuery=$db->query("SELECT COUNT(*) AS total_students,COALESCE(SUM(EXISTS (SELECT 1 FROM pds saved_pds WHERE saved_pds.user_id=u.id)),0) AS with_pds FROM users u WHERE u.role='student' AND COALESCE(u.archived,0)=0");
$stats=$summaryQuery->fetch(PDO::FETCH_ASSOC);
$selectedPdsFilter=($_GET['pds']??'')==='with'?'with':'all';

// Fetch academic data for filters
$departments = $db->query("SELECT * FROM academic_departments WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$programs = $db->query("SELECT * FROM academic_programs WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$strands = $db->query("SELECT * FROM academic_strands WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$grade_levels = $db->query("SELECT * FROM academic_grade_levels WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Build department data structure for JavaScript
$department_data = [];
foreach ($departments as $dept) {
    $dept_id = $dept['id'];
    $dept_name = $dept['name'];
    
    // Get programs for this department (for Higher Education)
    $dept_programs = [];
    foreach ($programs as $prog) {
        if ($prog['department_id'] == $dept_id) {
            $dept_programs[] = $prog['name'];
        }
    }
    
    // Get grade levels for this department
    $dept_grades = [];
    foreach ($grade_levels as $grade) {
        if ($grade['department_id'] == $dept_id) {
            $dept_grades[] = $grade['name'];
        }
    }
    
    $department_data[$dept_name] = [
        'programs' => $dept_programs,
        'strands' => array_values(array_column(array_filter($strands,static fn($strand)=>(int)$strand['department_id']===(int)$dept_id),'name')),
        'grades' => $dept_grades,
        'has_strands' => (stripos(strtolower($dept_name), 'senior') !== false) // Senior High has strands
    ];
}
?>

<link rel="stylesheet" href="../assets/css/student-record-actions.css?v=20261029-history">
<div class="space-y-6">
<?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-users mr-2 text-primary"></i>Student Records</h1>
    </div>

    <!-- Summary filters -->
    <div class="grid grid-cols-2 gap-3" role="group" aria-label="Filter students by personal data sheet">
        <button type="button" class="sr-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500" data-pds-filter="all" aria-pressed="<?=$selectedPdsFilter==='all'?'true':'false'?>" onclick="setStudentPdsFilter('all')">
            <span class="text-2xl font-bold text-gray-800"><?=(int)$stats['total_students']?></span>
            <span class="text-xs text-gray-500">Students</span><small>Show all students</small>
        </button>
        <button type="button" class="sr-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500" data-pds-filter="with" aria-pressed="<?=$selectedPdsFilter==='with'?'true':'false'?>" onclick="setStudentPdsFilter('with')">
            <span class="text-2xl font-bold text-gray-800"><?=(int)$stats['with_pds']?></span>
            <span class="text-xs text-gray-500">With PDS</span><small>Show students with a saved PDS</small>
        </button>
    </div>
    <p id="studentPdsScope" class="sr-pds-scope" role="status" aria-live="polite">Showing: <?=$selectedPdsFilter==='with'?'Students with PDS':'All students'?></p>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input type="text" id="searchInput" placeholder="Search students..." class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none" onkeyup="debounceSearch()">
            <select id="departmentFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="updateFiltersByDepartment()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="currentPage=1;fetchStudents()">
                <option value="">All Strands</option>
                <?php foreach ($strands as $strand): ?>
                <option value="<?= htmlspecialchars($strand['name']) ?>"><?= htmlspecialchars($strand['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="currentPage=1;fetchStudents()">
                <option value="">All Grade Levels</option>
                <?php foreach ($grade_levels as $grade): ?>
                <option value="<?= htmlspecialchars($grade['name']) ?>"><?= htmlspecialchars($grade['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed" id="studentsTable">
                <colgroup><col style="width:15%"><col style="width:10%"><col style="width:17%"><col style="width:11%"><col style="width:11%"><col style="width:9%"><col style="width:7%"><col style="width:7%"><col style="width:13%"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Strand/Program</th><th class="px-4 py-3">Grade</th><th class="px-4 py-3">PDS</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                <tbody id="studentsBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="pageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1">
                <button onclick="changePage(-1)" id="prevBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left mr-1"></i>Prev</button>
                <span id="pageNums" class="flex gap-1"></span>
                <button onclick="changePage(1)" id="nextBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next<i class="fas fa-chevron-right ml-1"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Academic placement is separate from viewing a student's record. -->
<div id="editStudentProfileModal" class="pd-overlay hidden sr-academic-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="srAssignTitle">
    <div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-graduation-cap"></i></span><div class="pd-heading-copy"><p class="sr-eyebrow">ACADEMIC PLACEMENT</p><h2 id="srAssignTitle">Assign academics</h2><p>Set the department, grade/year, and strand or program.</p></div><button type="button" class="pd-close" aria-label="Close academic assignment" data-dialog-close="editStudentProfileModal">×</button></header>
        <form data-portal-save method="POST" id="srAcademicForm"><input type="hidden" name="update_student_profile" value="1"><input type="hidden" name="student_record_token" value="<?=htmlspecialchars($_SESSION['student_record_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="user_id" id="edit_user_id">
            <div class="pd-body"><div class="sr-assignment-student"><strong id="srAssignStudentName"></strong><span id="srAssignStudentId"></span></div>
                <div class="pd-field"><label for="edit_department">Department <span aria-hidden="true">*</span></label><select name="department" id="edit_department" required onchange="updateEditFiltersByDepartment()"><option value="">Select department</option><?php foreach($departments as $dept):?><option value="<?=htmlspecialchars($dept['name'],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($dept['name'])?></option><?php endforeach;?></select></div>
                <div class="sr-academic-fields"><div class="pd-field"><label for="edit_grade_level">Grade / year level <span aria-hidden="true">*</span></label><select name="grade_level" id="edit_grade_level"><option value="">Select department first</option></select></div>
                <div class="pd-field" id="srStrandField" hidden><label for="edit_strand">Strand <span aria-hidden="true">*</span></label><select name="strand" id="edit_strand"><option value="">Select strand</option></select></div>
                <div class="pd-field" id="srProgramField" hidden><label for="edit_program">Program <span aria-hidden="true">*</span></label><select name="program" id="edit_program"><option value="">Select program</option></select></div></div>
                <p class="sr-academic-hint" id="srAcademicHint">Choose a department to see its available academic options.</p><p class="sr-required">* Required fields</p>
            </div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="editStudentProfileModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-check" aria-hidden="true"></i>Save assignment</button></footer>
        </form>
    </div>
</div>
<div id="studentRecordModal" class="pd-overlay hidden sr-record-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="srRecordTitle"><div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-address-card"></i></span><div class="pd-heading-copy"><p class="sr-eyebrow">STUDENT RECORD</p><h2 id="srRecordTitle">Student record</h2><p>Student information, academic assignment and all saved PDS school years.</p></div><button type="button" class="pd-close" aria-label="Close student record" data-dialog-close="studentRecordModal">×</button></header><div class="pd-body" id="srRecordContent" aria-live="polite"></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="studentRecordModal">Close</button><button type="button" class="pd-button pd-button-primary" id="srRecordAssign" hidden>Assign academics</button></footer></div></div>
<div id="studentPdsModal" class="pd-overlay hidden" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="srPdsTitle"><div class="pd-card pd-card-wide pds-paper-modal"><header class="pd-header"><div class="pd-heading-copy"><h2 id="srPdsTitle">Current personal data sheet</h2><p>Saved annual records and earlier revisions are available in the student record.</p></div><button type="button" class="pd-button" id="srPdsPrint">Print PDS</button><button type="button" class="pd-close" aria-label="Close student personal data sheet" onclick="closeStudentPds()">×</button></header><div class="pd-body pds-paper-modal-body"><div class="pds-document-toolbar"><label>Paper <select data-pds-paper-select aria-label="PDS paper size"><option value="long">Long bond (8.5 × 13 in)</option><option value="a4">A4</option><option value="letter">Letter</option></select></label><button type="button" data-pds-zoom="fit" aria-pressed="true">Fit width</button><button type="button" data-pds-zoom="actual" aria-pressed="false">Actual size</button></div><p class="pds-paper-status" data-pds-status role="status"></p><div class="pds-document-viewport" id="srPdsContent"></div></div></div></div>


<script>
const BASE = 'layout.php?page=student_records';
let currentPage = 1;
let searchTimer;
let selectedStudentPdsFilter=<?=json_encode($selectedPdsFilter)?>;
let studentFetchController=null,studentRequestId=0;
function setStudentPdsFilter(value){
    selectedStudentPdsFilter=value==='with'?'with':'all';currentPage=1;
    for(const card of document.querySelectorAll('[data-pds-filter]'))card.setAttribute('aria-pressed',String(card.dataset.pdsFilter===selectedStudentPdsFilter));
    document.getElementById('studentPdsScope').textContent=selectedStudentPdsFilter==='with'?'Showing: Students with PDS':'Showing: All students';
    const url=new URL(location.href);if(selectedStudentPdsFilter==='with')url.searchParams.set('pds','with');else url.searchParams.delete('pds');
    history.replaceState(history.state,'',url.pathname+url.search+url.hash);fetchStudents();
}
const studentRowsById=new Map();

// Department data structure from PHP
const departmentData = <?= json_encode($department_data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?>;

// Store all strands and grades for reference
const allStrands = <?= json_encode(array_column($strands, 'name'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const allGrades = <?= json_encode(array_column($grade_levels, 'name'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?>;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function updateFiltersByDepartment() {
    const dept = document.getElementById('departmentFilter').value;
    const strandFilter = document.getElementById('strandFilter');
    const gradeFilter = document.getElementById('gradeFilter');
    
    // Reset filters
    strandFilter.innerHTML = '<option value="">All Strands</option>';
    gradeFilter.innerHTML = '<option value="">All Grade Levels</option>';
    
    if (!dept) {
        // Show all strands and grades when no department selected
        allStrands.forEach(s => {
            strandFilter.innerHTML += `<option value="${s}">${s}</option>`;
        });
        allGrades.forEach(g => {
            gradeFilter.innerHTML += `<option value="${g}">${g}</option>`;
        });
    } else {
        const deptInfo = departmentData[dept] || { programs: [], grades: [], has_strands: false };
        
        // Show strands only if department has strands (Senior High)
        if (deptInfo.has_strands) {
            allStrands.forEach(s => {
                strandFilter.innerHTML += `<option value="${s}">${s}</option>`;
            });
        } else if (deptInfo.programs.length > 0) {
            // Show programs for Higher Education
            deptInfo.programs.forEach(p => {
                strandFilter.innerHTML += `<option value="${p}">${p}</option>`;
            });
        } else {
            // No strands/programs for this department
            strandFilter.innerHTML += '<option value="">N/A</option>';
        }
        
        // Show grade levels only for this department
        if (deptInfo.grades.length > 0) {
            deptInfo.grades.forEach(g => {
                gradeFilter.innerHTML += `<option value="${g}">${g}</option>`;
            });
        } else {
            gradeFilter.innerHTML += '<option value="">N/A</option>';
        }
    }
    
    currentPage=1;
    // Reset selections and fetch
    strandFilter.value = '';
    gradeFilter.value = '';
    fetchStudents();
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchStudents(); }, 300);
}

function fetchStudents() {
    const q = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    const requestId=++studentRequestId;studentFetchController?.abort();studentFetchController=new AbortController();
    fetch(BASE + `&pds=${selectedStudentPdsFilter}&action=fetch&p=${currentPage}&q=${encodeURIComponent(q)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`,{signal:studentFetchController.signal})
    .then(r => {if(!r.ok)throw new Error('Students could not be loaded.');return r.json();}).then(data => {
        if(requestId!==studentRequestId)return;
        const tbody = document.getElementById('studentsBody');
        studentRowsById.clear();for(const row of data.rows||[])studentRowsById.set(String(row.id),row);
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No students found</td></tr>';
        } else {
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const pdsIcon = u.has_pds ? '<span class="text-green-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-gray-300"><i class="fas fa-minus-circle"></i></span>';
                const isStudentActive = Number(u.is_active) === 1;
                const statusClass = isStudentActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                // Show strand for Senior High, program for Higher Education
                const strandOrProgram = u.strand || u.program || 'N/A';
                tbody.innerHTML += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'N/A')}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'N/A')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.department||'N/A')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(strandOrProgram)}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.grade_level||'N/A')}</td>
                    <td class="px-4 py-3">${pdsIcon}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${isStudentActive?'Active':'Inactive'}</span></td>
                    <td class="px-4 py-3">
                        <div class="sr-row-actions"><button type="button" data-view-student="${Number(u.id)}" title="View student record"><i class="fas fa-eye" aria-hidden="true"></i>View record</button><button type="button" data-assign-student="${Number(u.id)}" title="Assign department, grade/year and strand or program"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Assign academics</button></div>
                    </td>
                </tr>`;
            });
        }
        currentPage=data.page;renderPagination(data.total, data.per_page, data.page);
    }).catch(error=>{if(error.name==='AbortError'||requestId!==studentRequestId)return;document.getElementById('studentsBody').innerHTML='<tr><td colspan="9" class="px-4 py-8 text-center">Students could not be loaded. Please try again.</td></tr>';});
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('pageInfo').textContent = total === 0 ? 'No records' : `Showing ${start}-${end} of ${total}`;
    document.getElementById('prevBtn').disabled = page <= 1;
    document.getElementById('nextBtn').disabled = page >= totalPages;
    const nums = document.getElementById('pageNums');
    nums.innerHTML = '';
    const maxBtns = 5;
    let sp = Math.max(1, page - Math.floor(maxBtns/2));
    let ep = Math.min(totalPages, sp + maxBtns - 1);
    if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);
    for (let p = sp; p <= ep; p++) {
        const b = document.createElement('button');
        b.textContent = p;
        b.className = p === page ? 'px-2.5 py-1 text-sm rounded-lg bg-primary text-white' : 'px-2.5 py-1 text-sm rounded-lg border hover:bg-gray-50';
        b.onclick = () => { currentPage = p; fetchStudents(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchStudents(); }

// Initial load
fetchStudents();
</script>

<script src="../assets/js/student-record-actions.js?v=20261029-history"></script>
