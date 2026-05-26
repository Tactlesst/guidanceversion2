<?php
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

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

    $w = ["u.role IN ('student','examinee')", "(u.archived=0 OR u.archived IS NULL)"];
    $p_arr = [];
    if ($q) {
        $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
        $like = "%$q%"; $p_arr = [$like,$like,$like,$like];
    }
    if ($role !== 'all') { $w[] = "u.role = ?"; $p_arr[] = $role; }
    if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
    if ($strand) { $w[] = "sp.strand = ?"; $p_arr[] = $strand; }
    if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
    $where = implode(' AND ', $w);

    // Count
    $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where");
    $c_stmt->execute($p_arr);
    $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch page
    $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.strand, sp.grade_level FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY sp.department, sp.strand, sp.grade_level, u.last_name, u.first_name LIMIT $per OFFSET $off");
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
    'examinees' => $user_obj->getUsersByRole('examinee')->rowCount(),
    'with_pds' => $pds->getAllPDS()->rowCount(),
];
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-users mr-2 text-primary"></i>Student Records</h1>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_students'] ?></div>
            <div class="text-xs text-gray-500">Students</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-yellow-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['examinees'] ?></div>
            <div class="text-xs text-gray-500">Examinees</div>
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
            <select id="roleFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="all">All</option><option value="student">Students</option><option value="examinee">Examinees</option>
            </select>
            <select id="departmentFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="">All Departments</option>
                <?php
                try {
                    $depts = $db->query("SELECT DISTINCT department FROM student_profiles WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($depts as $dept) {
                        echo "<option value='" . htmlspecialchars($dept) . "'>" . htmlspecialchars($dept) . "</option>";
                    }
                } catch (Exception $e) {}
                ?>
            </select>
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="">All Strands</option>
                <?php
                try {
                    $strands = $db->query("SELECT DISTINCT strand FROM student_profiles WHERE strand IS NOT NULL AND strand != '' ORDER BY strand")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($strands as $strand) {
                        echo "<option value='" . htmlspecialchars($strand) . "'>" . htmlspecialchars($strand) . "</option>";
                    }
                } catch (Exception $e) {}
                ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudents()">
                <option value="">All Grade Levels</option>
                <?php
                try {
                    $grades = $db->query("SELECT DISTINCT grade_level FROM student_profiles WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($grades as $grade) {
                        echo "<option value='" . htmlspecialchars($grade) . "'>" . htmlspecialchars($grade) . "</option>";
                    }
                } catch (Exception $e) {}
                ?>
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
                <colgroup><col class="w-[18%]"><col class="w-[10%]"><col class="w-[18%]"><col class="w-[10%]"><col class="w-[10%]"><col class="w-[10%]"><col class="w-[8%]"><col class="w-[8%]"><col class="w-[8%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Strand</th><th class="px-4 py-3">Grade</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">PDS</th><th class="px-4 py-3">Status</th></tr></thead>
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

<script>
const BASE = 'layout.php?page=student_records';
let currentPage = 1;
let searchTimer;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchStudents(); }, 300);
}

function fetchStudents() {
    const q = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    fetch(BASE + `&action=fetch&p=${currentPage}&q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('studentsBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No students found</td></tr>';
        } else {
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const roleClass = u.role === 'student' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700';
                const pdsIcon = u.has_pds ? '<span class="text-green-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-gray-300"><i class="fas fa-minus-circle"></i></span>';
                const statusClass = u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                tbody.innerHTML += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'—')}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.department||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.strand||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.grade_level||'—')}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium ${roleClass} capitalize">${u.role}</span></td>
                    <td class="px-4 py-3">${pdsIcon}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${u.is_active?'Active':'Inactive'}</span></td>
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

async function printStudentRecords() {
    const q = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    // Fetch all students (no pagination for printing)
    const url = BASE + `&action=fetch&p=1&per=10000&q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`;
    
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
