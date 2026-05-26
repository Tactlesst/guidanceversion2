<?php
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

// AJAX endpoint for fetching student list
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    
    $department = $_GET['department'] ?? '';
    $strand = $_GET['strand'] ?? '';
    $grade = $_GET['grade'] ?? '';
    
    $w = ["u.role = 'student'", "(u.archived=0 OR u.archived IS NULL)"];
    $p_arr = [];
    
    if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
    if ($strand) { $w[] = "sp.strand = ?"; $p_arr[] = $strand; }
    if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
    
    $where = implode(' AND ', $w);
    
    // Fetch all students ordered by gender, then last name, first name
    $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.strand, sp.grade_level, sp.gender FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY sp.gender, u.last_name, u.first_name");
    $stmt->execute($p_arr);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['rows'=>$rows]);
    exit();
}

// Get departments, strands, grades for filters
$departments = [];
$strands = [];
$grades = [];

try {
    $departments = $db->query("SELECT DISTINCT department FROM student_profiles WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $strands = $db->query("SELECT DISTINCT strand FROM student_profiles WHERE strand IS NOT NULL AND strand != '' ORDER BY strand")->fetchAll(PDO::FETCH_COLUMN);
    $grades = $db->query("SELECT DISTINCT grade_level FROM student_profiles WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-list mr-2 text-primary"></i>Student List Report</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <select id="departmentFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudentList()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudentList()">
                <option value="">All Strands</option>
                <?php foreach ($strands as $strand): ?>
                    <option value="<?= htmlspecialchars($strand) ?>"><?= htmlspecialchars($strand) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudentList()">
                <option value="">All Grade Levels</option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="printStudentListReport()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Student List Report -->
    <div class="bg-white rounded-xl shadow-sm p-6" id="reportContainer">
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Student List Report</h2>
            <p class="text-sm text-gray-500" id="reportSubtitle">All Students</p>
        </div>

        <!-- Male Students -->
        <div id="maleSection">
            <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b-2 border-gray-300 pb-2">Male Students</h3>
            <table class="w-full text-sm mb-6" id="maleTable">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left border">No.</th>
                        <th class="px-4 py-2 text-left border">ID / LRN</th>
                        <th class="px-4 py-2 text-left border">Name</th>
                        <th class="px-4 py-2 text-left border">Gender</th>
                    </tr>
                </thead>
                <tbody id="maleBody" class="divide-y divide-gray-200"></tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-semibold">
                        <td class="px-4 py-2 border" colspan="3">Total Male Students:</td>
                        <td class="px-4 py-2 border" id="maleTotal">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Female Students -->
        <div id="femaleSection">
            <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b-2 border-gray-300 pb-2">Female Students</h3>
            <table class="w-full text-sm mb-6" id="femaleTable">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left border">No.</th>
                        <th class="px-4 py-2 text-left border">ID / LRN</th>
                        <th class="px-4 py-2 text-left border">Name</th>
                        <th class="px-4 py-2 text-left border">Gender</th>
                    </tr>
                </thead>
                <tbody id="femaleBody" class="divide-y divide-gray-200"></tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-semibold">
                        <td class="px-4 py-2 border" colspan="3">Total Female Students:</td>
                        <td class="px-4 py-2 border" id="femaleTotal">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Grand Total -->
        <div class="bg-gray-100 p-4 rounded-lg mb-6">
            <div class="flex justify-between items-center">
                <span class="font-semibold text-gray-700">Grand Total:</span>
                <span class="font-bold text-gray-800 text-lg" id="grandTotal">0</span>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="grid grid-cols-2 gap-8 mt-12">
            <div class="text-center">
                <div class="border-b border-gray-400 mb-2 h-12"></div>
                <p class="text-sm text-gray-600">Prepared by:</p>
                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($user_info['first_name'] ?? '') . ' ' . htmlspecialchars($user_info['last_name'] ?? '') ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($role) ?></p>
            </div>
            <div class="text-center">
                <div class="border-b border-gray-400 mb-2 h-12"></div>
                <p class="text-sm text-gray-600">Noted by:</p>
                <p class="text-xs text-gray-500 mt-1">_____________________</p>
                <p class="text-xs text-gray-500">Administrator</p>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=student_list_report';

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function fetchStudentList() {
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    fetch(BASE + `&action=fetch&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json()).then(data => {
        const maleBody = document.getElementById('maleBody');
        const femaleBody = document.getElementById('femaleBody');
        
        maleBody.innerHTML = '';
        femaleBody.innerHTML = '';
        
        let maleCount = 0;
        let femaleCount = 0;
        
        data.rows.forEach(student => {
            const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
            const gender = (student.gender || '').toLowerCase();
            const isMale = gender === 'male' || gender === 'm';
            
            const row = `
                <tr>
                    <td class="px-4 py-2 border">${isMale ? maleCount + 1 : femaleCount + 1}</td>
                    <td class="px-4 py-2 border">${esc(student.student_id || '—')}</td>
                    <td class="px-4 py-2 border">${esc(name)}</td>
                    <td class="px-4 py-2 border">${esc(student.gender || '—')}</td>
                </tr>
            `;
            
            if (isMale) {
                maleBody.innerHTML += row;
                maleCount++;
            } else {
                femaleBody.innerHTML += row;
                femaleCount++;
            }
        });
        
        document.getElementById('maleTotal').textContent = maleCount;
        document.getElementById('femaleTotal').textContent = femaleCount;
        document.getElementById('grandTotal').textContent = maleCount + femaleCount;
        
        // Update subtitle
        let subtitle = 'All Students';
        if (department || strand || grade) {
            const filters = [];
            if (department) filters.push(department);
            if (strand) filters.push(strand);
            if (grade) filters.push(grade);
            subtitle = filters.join(' - ');
        }
        document.getElementById('reportSubtitle').textContent = subtitle;
    });
}

function printStudentListReport() {
    const content = document.getElementById('reportContainer').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Student List Report</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
                h2 { text-align: center; margin-bottom: 5px; }
                p { text-align: center; margin-bottom: 20px; }
                h3 { margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                th { background-color: #f0f0f0; }
                tfoot tr { background-color: #f0f0f0; font-weight: bold; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 3rem; }
                .text-center { text-align: center; }
                .border-b { border-bottom: 1px solid #000; margin-bottom: 0.5rem; height: 3rem; }
                .text-sm { font-size: 12px; }
                .text-xs { font-size: 10px; color: #666; }
                .bg-gray-100 { background-color: #f0f0f0; padding: 1rem; border-radius: 8px; }
                .flex { display: flex; justify-content: space-between; align-items: center; }
                .font-semibold { font-weight: 600; }
                .font-bold { font-weight: bold; }
                .text-lg { font-size: 18px; }
            </style>
        </head>
        <body>
            ${content}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Initial load
fetchStudentList();
</script>
