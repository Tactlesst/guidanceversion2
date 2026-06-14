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
    $department = $_GET['department'] ?? '';
    $strand = $_GET['strand'] ?? '';
    $grade = $_GET['grade'] ?? '';

    $w = ["u.role = 'examinee'", "(u.archived=0 OR u.archived IS NULL)"];
    $p_arr = [];
    if ($q) {
        $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
        $like = "%$q%"; $p_arr = [$like,$like,$like,$like];
    }
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

    echo json_encode(['rows'=>$rows, 'total'=>$total, 'per_page'=>$per, 'page'=>$pg]);
    exit();
}

$stats = [
    'total_examinees' => $user_obj->getUsersByRole('examinee')->rowCount(),
];

// Fetch academic data for filters
$departments = $db->query("SELECT * FROM academic_departments WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$programs = $db->query("SELECT * FROM academic_programs WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$strands = $db->query("SELECT * FROM academic_strands WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$grade_levels = $db->query("SELECT * FROM academic_grade_levels WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-user-graduate mr-2 text-primary"></i>Examinee Records</h1>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-yellow-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_examinees'] ?></div>
            <div class="text-xs text-gray-500">Examinees</div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input type="text" id="searchInput" placeholder="Search examinees..." class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none" onkeyup="debounceSearch()">
            <select id="departmentFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchExaminees()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchExaminees()">
                <option value="">All Strands</option>
                <?php foreach ($strands as $strand): ?>
                <option value="<?= htmlspecialchars($strand['name']) ?>"><?= htmlspecialchars($strand['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchExaminees()">
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
            <table class="w-full text-sm table-fixed" id="examineesTable">
                <colgroup><col class="w-[25%]"><col class="w-[15%]"><col class="w-[20%]"><col class="w-[15%]"><col class="w-[15%]"><col class="w-[10%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Strand</th><th class="px-4 py-3">Grade</th></tr></thead>
                <tbody id="examineesBody" class="divide-y divide-gray-100"></tbody>
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
const BASE = 'layout.php?page=examinee_records';
let currentPage = 1;
let searchTimer;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchExaminees(); }, 300);
}

function fetchExaminees() {
    const q = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    fetch(BASE + `&action=fetch&p=${currentPage}&q=${encodeURIComponent(q)}&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('examineesBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No examinees found</td></tr>';
        } else {
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                tbody.innerHTML += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'—')}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.department||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.strand||'—')}</td>
                    <td class="px-4 py-3 text-gray-500">${esc(u.grade_level||'—')}</td>
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
        b.onclick = () => { currentPage = p; fetchExaminees(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchExaminees(); }

// Load examinees on page load
document.addEventListener('DOMContentLoaded', function() {
    fetchExaminees();
});
</script>
