<?php
require_once __DIR__ . '/../../../classes/CounselingAppointment.php';

$counseling = new CounselingAppointment($db);

// AJAX endpoint for fetching counseling history
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $pg = max(1, intval($_GET['p'] ?? 1));
        $per = max(1, min(50, intval($_GET['per'] ?? 10)));
        $off = ($pg - 1) * $per;
        $q = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $grade_level = $_GET['grade_level'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $sort = $_GET['sort'] ?? 'latest';
        
        $w = ["(u.archived=0 OR u.archived IS NULL)"];
        $p_arr = [];
        
        if ($q) {
            $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_id LIKE ?)";
            $like = "%$q%";
            $p_arr = [$like, $like, $like];
        }
        
        if ($status) {
            $w[] = "ca.status = ?";
            $p_arr[] = $status;
        }
        
        if ($grade_level) {
            $w[] = "sp.grade_level = ?";
            $p_arr[] = $grade_level;
        }
        
        if ($date_from) {
            $w[] = "ca.appointment_date >= ?";
            $p_arr[] = $date_from;
        }
        
        if ($date_to) {
            $w[] = "ca.appointment_date <= ?";
            $p_arr[] = $date_to;
        }
        
        $where = implode(' AND ', $w);
        
        // Count
        $c_query = "SELECT COUNT(*) as total FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE $where";
        $c_stmt = $db->prepare($c_query);
        $c_stmt->execute($p_arr);
        $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Fetch page
        $order_by = $sort === 'latest' ? 'ca.appointment_date DESC, ca.preferred_time DESC' : 'ca.appointment_date ASC, ca.preferred_time ASC';
        $f_query = "SELECT ca.id, ca.user_id, ca.appointment_date, ca.preferred_time, ca.status, ca.appointment_type, ca.concern, ca.remarks, ca.counselor_id,
                    u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ca.counselor_id LIMIT 1) as counselor_name
                    FROM counseling_appointments ca 
                    JOIN users u ON ca.user_id = u.id 
                    LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                    WHERE $where 
                    ORDER BY $order_by 
                    LIMIT $per OFFSET $off";
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
    'completed' => 0,
    'pending' => 0,
    'cancelled' => 0
];

try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM counseling_appointments")->fetchColumn();
    $stats['completed'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status = 'completed'")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status = 'pending'")->fetchColumn();
    $stats['cancelled'] = $db->query("SELECT COUNT(*) FROM counseling_appointments WHERE status = 'cancelled'")->fetchColumn();
} catch (Exception $e) {}

// Get unique grade levels for filter
$grade_levels = [];
try {
    $grade_levels = $db->query("SELECT DISTINCT grade_level FROM student_profiles WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Counseling History</h1>
        <p class="text-sm text-gray-500">Complete record of all counseling sessions and appointments</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
            <div class="text-xs text-gray-500">Total Sessions</div>
            <div class="text-[10px] text-gray-400">All appointments</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['completed'] ?></div>
            <div class="text-xs text-gray-500">Completed</div>
            <div class="text-[10px] text-gray-400">Finished sessions</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-yellow-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['pending'] ?></div>
            <div class="text-xs text-gray-500">Pending</div>
            <div class="text-[10px] text-gray-400">Awaiting confirmation</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-red-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['cancelled'] ?></div>
            <div class="text-xs text-gray-500">Cancelled</div>
            <div class="text-[10px] text-gray-400">Cancelled sessions</div>
        </div>
    </div>

    <!-- Search & Filter Options -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Search Students</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="text-xs text-gray-500">Search by name or ID...</label>
                <input type="text" id="searchInput" placeholder="Search by name or ID..." class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onkeyup="debounceSearch()">
            </div>
            <div>
                <label class="text-xs text-gray-500">Status</label>
                <select id="statusFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchCounselingHistory()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_show">No Show</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Grade Level</label>
                <select id="gradeLevelFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchCounselingHistory()">
                    <option value="">All Grades</option>
                    <?php foreach ($grade_levels as $grade): ?>
                        <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Date From</label>
                <input type="date" id="dateFrom" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchCounselingHistory()">
            </div>
            <div>
                <label class="text-xs text-gray-500">Date To</label>
                <input type="date" id="dateTo" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchCounselingHistory()">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="text-xs text-gray-500">Sort By</label>
                <select id="sortFilter" class="w-full px-3 py-2 border rounded-lg text-sm mt-1" onchange="fetchCounselingHistory()">
                    <option value="latest">Latest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
            <div class="flex items-end">
                <p class="text-xs text-gray-400 italic">Filters apply automatically</p>
            </div>
        </div>
    </div>

    <!-- Counseling Sessions Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Counseling Sessions</h3>
            <p class="text-xs text-gray-500" id="recordsInfo">Loading...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">Grade Level</th>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Counselor</th>
                        <th class="px-4 py-3">Purpose</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="counselingBody" class="divide-y divide-gray-100"></tbody>
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
const BASE = 'layout.php?page=counseling_history';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchCounselingHistory(); }, 300);
}

function fetchCounselingHistory() {
    const q = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const grade_level = document.getElementById('gradeLevelFilter').value;
    const date_from = document.getElementById('dateFrom').value;
    const date_to = document.getElementById('dateTo').value;
    const sort = document.getElementById('sortFilter').value;
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&grade_level=${encodeURIComponent(grade_level)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&sort=${encodeURIComponent(sort)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('counselingBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No counseling sessions found</td></tr>';
        } else {
            data.rows.forEach(session => {
                const name = (session.last_name||'') + ', ' + (session.first_name||'');
                const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-700',
                    'confirmed': 'bg-blue-100 text-blue-700',
                    'in_progress': 'bg-purple-100 text-purple-700',
                    'completed': 'bg-green-100 text-green-700',
                    'cancelled': 'bg-red-100 text-red-700',
                    'no_show': 'bg-gray-100 text-gray-700'
                };
                const statusColor = statusColors[session.status] || 'bg-gray-100 text-gray-700';
                
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.student_id||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.grade_level||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${new Date(session.appointment_date).toLocaleDateString()} ${session.preferred_time||''}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.counselor_name||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.concern||session.appointment_type||'—')}</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium ${statusColor}">${session.status.replace(/_/g, ' ')}</span></td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="viewSession(${session.id})" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</button>
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    });
}

function viewSession(id) {
    // Open session view in modal or new page
    window.open(`../counseling/view_appointments.php?id=${id}`, '_blank');
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('recordsInfo').textContent = `Showing ${start} to ${end} of ${total} appointments`;
    document.getElementById('pageInfo').textContent = total === 0 ? 'No records' : `Page ${page} of ${totalPages}`;
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
        b.onclick = () => { currentPage = p; fetchCounselingHistory(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchCounselingHistory(); }

// Initial load
fetchCounselingHistory();
</script>
