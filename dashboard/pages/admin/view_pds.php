<?php
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$pds = new PersonalDataSheet($db);

// AJAX endpoint for fetching PDS records
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $pg = max(1, intval($_GET['p'] ?? 1));
        $per = max(1, min(50, intval($_GET['per'] ?? 10)));
        $off = ($pg - 1) * $per;
        $q = trim($_GET['q'] ?? '');
        $education_level = $_GET['education_level'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $sort = $_GET['sort'] ?? 'latest';
        
        $w = [];
        $p_arr = [];
        
        if ($q) {
            $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_id LIKE ? OR p.nickname LIKE ?)";
            $like = "%$q%";
            $p_arr = [$like, $like, $like, $like];
        }
        
        if ($education_level) {
            $w[] = "sp.grade_level = ?";
            $p_arr[] = $education_level;
        }
        
        if ($date_from) {
            $w[] = "p.created_at >= ?";
            $p_arr[] = $date_from;
        }
        
        if ($date_to) {
            $w[] = "p.created_at <= ?";
            $p_arr[] = $date_to;
        }
        
        $where = !empty($w) ? 'WHERE ' . implode(' AND ', $w) : '';
        
        // Count
        $c_query = "SELECT COUNT(*) as total FROM pds p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id $where";
        $c_stmt = $db->prepare($c_query);
        $c_stmt->execute($p_arr);
        $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Fetch page
        $order_by = $sort === 'latest' ? 'p.created_at DESC' : 'p.created_at ASC';
        $f_query = "SELECT p.id, p.user_id, p.created_at, p.gender, p.education_level, p.contact_number, p.email as pds_email, p.nickname, u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level FROM pds p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id $where ORDER BY $order_by LIMIT $per OFFSET $off";
        $f_stmt = $db->prepare($f_query);
        $f_stmt->execute($p_arr);
        $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => (int)$per, 'page' => (int)$pg]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'rows' => [], 'total' => 0, 'per_page' => 10, 'page' => 1]);
    }
    exit();
}

// Get statistics
$stats = [
    'total' => 0,
    'kinder' => 0,
    'elementary' => 0,
    'junior_high' => 0,
    'senior_high' => 0,
    'college' => 0
];

try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM pds")->fetchColumn();
    $stats['kinder'] = $db->query("SELECT COUNT(*) FROM pds p JOIN student_profiles sp ON p.user_id = sp.user_id WHERE sp.grade_level LIKE '%Kindergarten%'")->fetchColumn();
    $stats['elementary'] = $db->query("SELECT COUNT(*) FROM pds p JOIN student_profiles sp ON p.user_id = sp.user_id WHERE sp.grade_level LIKE '%Elementary%'")->fetchColumn();
    $stats['junior_high'] = $db->query("SELECT COUNT(*) FROM pds p JOIN student_profiles sp ON p.user_id = sp.user_id WHERE sp.grade_level LIKE '%Junior%'")->fetchColumn();
    $stats['senior_high'] = $db->query("SELECT COUNT(*) FROM pds p JOIN student_profiles sp ON p.user_id = sp.user_id WHERE sp.grade_level LIKE '%Senior%'")->fetchColumn();
    $stats['college'] = $db->query("SELECT COUNT(*) FROM pds p JOIN student_profiles sp ON p.user_id = sp.user_id WHERE sp.grade_level LIKE '%College%' OR sp.grade_level LIKE '%Year%'")->fetchColumn();
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Personal Data Sheets</h1>
        <p class="text-sm text-gray-500">Manage and review student personal data sheets</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
            <div class="text-xs text-gray-500">Total Records</div>
            <div class="text-[10px] text-gray-400">All time records</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-pink-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['kinder'] ?></div>
            <div class="text-xs text-gray-500">Kinder</div>
            <div class="text-[10px] text-gray-400">Kindergarten records</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['elementary'] ?></div>
            <div class="text-xs text-gray-500">Elementary</div>
            <div class="text-[10px] text-gray-400">Elementary records</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-yellow-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['junior_high'] ?></div>
            <div class="text-xs text-gray-500">High School</div>
            <div class="text-[10px] text-gray-400">Junior High records</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-orange-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['senior_high'] ?></div>
            <div class="text-xs text-gray-500">Senior High</div>
            <div class="text-[10px] text-gray-400">Senior High records</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-purple-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['college'] ?></div>
            <div class="text-xs text-gray-500">College</div>
            <div class="text-[10px] text-gray-400">Higher Education</div>
        </div>
    </div>

    <!-- Search & Filter Options -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Search & Filter Options</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="text-xs text-gray-500">Filter by Education Level:</label>
                <select id="educationLevelFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchPDSRecords()">
                    <option value="">All Levels</option>
                    <option value="Kindergarten">Kindergarten</option>
                    <option value="Elementary">Elementary</option>
                    <option value="Junior High">Junior High</option>
                    <option value="Senior High">Senior High</option>
                    <option value="1st Year College">1st Year College</option>
                    <option value="2nd Year College">2nd Year College</option>
                    <option value="3rd Year College">3rd Year College</option>
                    <option value="4th Year College">4th Year College</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Search Records:</label>
                <input type="text" id="searchInput" placeholder="Search by name, student ID, or nickname..." class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onkeyup="debounceSearch()">
            </div>
            <div>
                <label class="text-xs text-gray-500">Filter by Date Range:</label>
                <div class="flex gap-2 mt-1">
                    <input type="date" id="dateFrom" class="flex-1 px-3 py-2 border rounded-lg text-sm" onchange="fetchPDSRecords()">
                    <input type="date" id="dateTo" class="flex-1 px-3 py-2 border rounded-lg text-sm" onchange="fetchPDSRecords()">
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-500">Sort Results:</label>
                <select id="sortFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchPDSRecords()">
                    <option value="latest">Latest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Records Per Page:</label>
                <select id="perPageFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="onPerPageChange()">
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="30">30 per page</option>
                    <option value="50">50 per page</option>
                </select>
            </div>
        </div>
    </div>

    <!-- PDS Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Personal Data Sheets</h3>
            <p class="text-xs text-gray-500" id="recordsInfo">Loading...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">Nickname</th>
                        <th class="px-4 py-3">Gender</th>
                        <th class="px-4 py-3">Education Level</th>
                        <th class="px-4 py-3">Contact Number</th>
                        <th class="px-4 py-3">Grade Level</th>
                        <th class="px-4 py-3">Submitted Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="pdsBody" class="divide-y divide-gray-100"></tbody>
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
const BASE = 'layout.php?page=admin_view_pds';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchPDSRecords(); }, 300);
}

function onPerPageChange() {
    itemsPerPage = parseInt(document.getElementById('perPageFilter').value);
    currentPage = 1;
    fetchPDSRecords();
}

function fetchPDSRecords() {
    const q = document.getElementById('searchInput').value;
    const education_level = document.getElementById('educationLevelFilter').value;
    const date_from = document.getElementById('dateFrom').value;
    const date_to = document.getElementById('dateTo').value;
    const sort = document.getElementById('sortFilter').value;
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&education_level=${encodeURIComponent(education_level)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&sort=${encodeURIComponent(sort)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('pdsBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No records found</td></tr>';
        } else {
            data.rows.forEach(record => {
                const name = (record.last_name||'') + ', ' + (record.first_name||'');
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.student_id||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.nickname||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.gender||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.education_level||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.contact_number||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.grade_level||'—')}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">${new Date(record.created_at).toLocaleDateString()}</td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="viewPDS(${record.id})" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</button>
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    });
}

function viewPDS(id) {
    // Open PDS view in modal
    fetch(`pages/admin/admin_view_pds.php?id=${id}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.text())
    .then(html => {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto m-4">
                <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center no-print">
                    <h2 class="text-lg font-bold text-primary">Personal Data Sheet</h2>
                    <div class="flex gap-2">
                        <button onclick="printPDS(${id})" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-print mr-1"></i>Print</button>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-4">${html}</div>
            </div>
        `;
        document.body.appendChild(modal);
    });
}

function printPDS(id) {
    // Open PDS in new window for printing
    window.open(`pages/admin/admin_view_pds.php?id=${id}`, '_blank');
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('recordsInfo').textContent = `${total} records found`;
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
        b.onclick = () => { currentPage = p; fetchPDSRecords(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchPDSRecords(); }

// Initial load
fetchPDSRecords();
</script>
