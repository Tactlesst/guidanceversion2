<?php
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

// Handle update student profile
if (isset($_POST['update_student_profile'])) {
    $user_id = $_POST['user_id'];
    $department = $_POST['department'] ?? null;
    $strand = $_POST['strand'] ?? null;
    $program = $_POST['program'] ?? null;
    $grade_level = $_POST['grade_level'] ?? null;
    
    // Check if student profile exists
    $check_stmt = $db->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
    $check_stmt->execute([$user_id]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing profile
        $update_stmt = $db->prepare("UPDATE student_profiles SET department = ?, strand = ?, program = ?, grade_level = ? WHERE user_id = ?");
        $update_stmt->execute([$department, $strand, $program, $grade_level, $user_id]);
    } else {
        // Create new profile
        $insert_stmt = $db->prepare("INSERT INTO student_profiles (user_id, department, strand, program, grade_level) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->execute([$user_id, $department, $strand, $program, $grade_level]);
    }
    
    $_SESSION['success_message'] = "Student profile updated successfully!";
    header("Location: layout.php?page=student_records");
    exit();
}

// AJAX endpoint for server-side pagination
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    $pg = max(1, intval($_GET['p'] ?? 1));
    $per = 10;
    $off = ($pg - 1) * $per;
    $q = trim($_GET['q'] ?? '');
    $role = $_GET['role'] ?? 'all';
    $department = $_GET['department'] ?? '';
    $strand = $_GET['strand'] ?? '';
    $grade = $_GET['grade'] ?? '';

    $w = ["u.role = 'student'", "(u.archived=0 OR u.archived IS NULL)"];
    $p_arr = [];
    if ($q) {
        $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
        $like = "%$q%"; $p_arr = [$like,$like,$like,$like];
    }
    if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
    if ($strand) { $w[] = "(sp.strand = ? OR sp.program = ?)"; $p_arr[] = $strand; $p_arr[] = $strand; }
    if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
    $where = implode(' AND ', $w);

    // Count
    $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where");
    $c_stmt->execute($p_arr);
    $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch page
    $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.strand, sp.program, sp.grade_level FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY sp.department, sp.strand, sp.program, sp.grade_level, u.last_name, u.first_name LIMIT $per OFFSET $off");
    $stmt->execute($p_arr);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check PDS for each row
    $pds_set = [];
    foreach (['pds_college','pds_seniorhigh','pds_highschool'] as $tbl) {
        try {
            $ids = array_column($rows, 'id');
            if (empty($ids)) continue;
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $ps = $db->prepare("SELECT DISTINCT user_id FROM $tbl WHERE user_id IN ($ph)");
            $ps->execute($ids);
            while ($r = $ps->fetch(PDO::FETCH_ASSOC)) $pds_set[$r['user_id']] = true;
        } catch (Exception $e) {}
    }

    foreach ($rows as &$row) {
        $row['has_pds'] = !empty($pds_set[$row['id']]);
    }
    unset($row);

    echo json_encode(['rows'=>$rows, 'total'=>$total, 'per_page'=>$per, 'page'=>$pg]);
    exit();
}

$stats = [
    'total_students' => $user_obj->getUsersByRole('student')->rowCount(),
    'with_pds' => $pds->getAllPDS()->rowCount(),
];

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
        'grades' => $dept_grades,
        'has_strands' => (stripos(strtolower($dept_name), 'senior') !== false) // Senior High has strands
    ];
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-users mr-2 text-primary"></i>Student Records</h1>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_students'] ?></div>
            <div class="text-xs text-gray-500">Students</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['with_pds'] ?></div>
            <div class="text-xs text-gray-500">With PDS</div>
        </div>
    </div>

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
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="">All Strands</option>
                <?php foreach ($strands as $strand): ?>
                <option value="<?= htmlspecialchars($strand['name']) ?>"><?= htmlspecialchars($strand['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="">All Grade Levels</option>
                <?php foreach ($grade_levels as $grade): ?>
                <option value="<?= htmlspecialchars($grade['name']) ?>"><?= htmlspecialchars($grade['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="printStudentRecords()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed" id="studentsTable">
                <colgroup><col class="w-[18%]"><col class="w-[10%]"><col class="w-[18%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[10%]"><col class="w-[8%]"><col class="w-[8%]"><col class="w-[8%]"></colgroup>
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

<!-- Edit Student Profile Modal -->
<div id="editStudentProfileModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Student Profile</h3>
            <button onclick="closeModal('editStudentProfileModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="update_student_profile" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <select name="department" id="edit_department" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateEditFiltersByDepartment()">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Strand</label>
                <select name="strand" id="edit_strand" class="w-full px-3 py-2 border rounded-lg text-sm hidden">
                    <option value="">Select Strand</option>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
                <select name="program" id="edit_program" class="w-full px-3 py-2 border rounded-lg text-sm hidden">
                    <option value="">Select Program</option>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Grade Level</label>
                <select name="grade_level" id="edit_grade_level" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select Department First</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('editStudentProfileModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Update</button></div>
        </form>
    </div>
</div>

<script>
const BASE = 'layout.php?page=student_records';
let currentPage = 1;
let searchTimer;

// Department data structure from PHP
const departmentData = <?= json_encode($department_data) ?>;

// Store all strands and grades for reference
const allStrands = <?= json_encode(array_column($strands, 'name')) ?>;
const allGrades = <?= json_encode(array_column($grade_levels, 'name')) ?>;

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
    fetch(BASE + `&action=fetch&p=${currentPage}&q=${encodeURIComponent(q)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('studentsBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No students found</td></tr>';
        } else {
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const pdsIcon = u.has_pds ? '<span class="text-green-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-gray-300"><i class="fas fa-minus-circle"></i></span>';
                const statusClass = u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                // Show strand for Senior High, program for Higher Education
                const strandOrProgram = u.strand || u.program || '—';
                tbody.innerHTML += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'—')}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.department||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(strandOrProgram)}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.grade_level||'—')}</td>
                    <td class="px-4 py-3">${pdsIcon}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${u.is_active?'Active':'Inactive'}</span></td>
                    <td class="px-4 py-3">
                        <button onclick="editStudentProfile(${u.id}, '${esc(u.department||'')}', '${esc(u.strand||'')}', '${esc(u.program||'')}', '${esc(u.grade_level||'')}')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Edit Profile"><i class="fas fa-edit"></i></button>
                    </td>
                </tr>`;
            });
        }
        renderPagination(data.total, data.per_page, data.page);
    });
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

function updateEditFiltersByDepartment() {
    const dept = document.getElementById('edit_department').value;
    const strandFilter = document.getElementById('edit_strand');
    const programFilter = document.getElementById('edit_program');
    const gradeFilter = document.getElementById('edit_grade_level');
    
    // Reset filters
    strandFilter.innerHTML = '<option value="">Select Strand</option>';
    programFilter.innerHTML = '<option value="">Select Program</option>';
    gradeFilter.innerHTML = '<option value="">Select Grade Level</option>';
    
    // Hide both strand and program initially
    strandFilter.classList.add('hidden');
    programFilter.classList.add('hidden');
    
    if (!dept) {
        return;
    }
    
    const deptInfo = departmentData[dept] || { programs: [], grades: [], has_strands: false };
    
    // Show strands only if department has strands (Senior High)
    if (deptInfo.has_strands) {
        strandFilter.classList.remove('hidden');
        allStrands.forEach(s => {
            strandFilter.innerHTML += `<option value="${s}">${s}</option>`;
        });
    } else if (deptInfo.programs.length > 0) {
        // Show programs for Higher Education
        programFilter.classList.remove('hidden');
        deptInfo.programs.forEach(p => {
            programFilter.innerHTML += `<option value="${p}">${p}</option>`;
        });
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

function editStudentProfile(userId, department, strand, program, gradeLevel) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_department').value = department;
    
    // Update filters based on selected department
    updateEditFiltersByDepartment();
    
    // Set strand and program after filters are updated
    setTimeout(() => {
        document.getElementById('edit_strand').value = strand || '';
        document.getElementById('edit_program').value = program || '';
        document.getElementById('edit_grade_level').value = gradeLevel;
    }, 50);
    
    openModal('editStudentProfileModal');
}

async function printStudentRecords() {
    const q = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    // Fetch all students (no pagination for printing)
    const url = BASE + `&action=fetch&p=1&per=10000&q=${encodeURIComponent(q)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.rows || data.rows.length === 0) {
            Swal.fire('No Data', 'No students found for the selected filters.', 'warning');
            return;
        }
        
        // Group students by department, then strand, then grade
        const grouped = {};
        data.rows.forEach(student => {
            const dept = student.department || 'No Department';
            const str = student.strand || 'No Strand';
            const grd = student.grade_level || 'No Grade';
            
            if (!grouped[dept]) grouped[dept] = {};
            if (!grouped[dept][str]) grouped[dept][str] = {};
            if (!grouped[dept][str][grd]) grouped[dept][str][grd] = [];
            
            grouped[dept][str][grd].push(student);
        });
        
        // Generate print HTML
        let printHTML = `
            <html>
            <head>
                <title>Student Records</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12px; }
                    h1 { text-align: center; margin-bottom: 20px; }
                    h2 { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; }
                    h3 { margin-top: 15px; margin-bottom: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                    th { background-color: #f0f0f0; font-weight: bold; }
                    .group-header { background-color: #e0e0e0; font-weight: bold; }
                    @media print { .no-print { display: none; } }
                </style>
            </head>
            <body>
                <h1>Student Records</h1>
                <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
                <p><strong>Filters:</strong> Role: ${role === 'all' ? 'All' : role} | Department: ${department || 'All'} | Strand: ${strand || 'All'} | Grade: ${grade || 'All'}</p>
                <p><strong>Total Students:</strong> ${data.rows.length}</p>
        `;
        
        for (const dept in grouped) {
            printHTML += `<h2>${dept}</h2>`;
            for (const str in grouped[dept]) {
                printHTML += `<h3>${str}</h3>`;
                for (const grd in grouped[dept][str]) {
                    const students = grouped[dept][str][grd];
                    printHTML += `<p><strong>Grade Level:</strong> ${grd} (${students.length} students)</p>`;
                    printHTML += `<table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>PDS</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    
                    students.forEach((student, index) => {
                        const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
                        const pdsStatus = student.has_pds ? '✓' : '✗';
                        const status = student.is_active ? 'Active' : 'Inactive';
                        
                        printHTML += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${name}</td>
                                <td>${student.student_id || '—'}</td>
                                <td>${student.email || '—'}</td>
                                <td>${student.role}</td>
                                <td>${pdsStatus}</td>
                                <td>${status}</td>
                            </tr>`;
                    });
                    
                    printHTML += `</tbody></table>`;
                }
            }
        }
        
        printHTML += `
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.print();
        
    } catch (error) {
        console.error('Error generating print view:', error);
        Swal.fire('Error', 'Failed to generate print view', 'error');
    }
}

// Initial load
fetchStudents();
</script>
