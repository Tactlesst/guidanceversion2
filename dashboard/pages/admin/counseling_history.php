<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session.php';
checkLogin();
$user_info = getUserInfo();
if (!in_array($user_info['role'], ['admin'])) {
    // Check if this is an AJAX request
    if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized', 'rows' => [], 'total' => 0, 'per_page' => 10, 'page' => 1]);
        exit();
    }
    header("Location: layout.php");
    exit();
}

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
        $order_by = $sort === 'latest' ? 'ca.appointment_date DESC, ca.appointment_time DESC' : 'ca.appointment_date ASC, ca.appointment_time ASC';
        $f_query = "SELECT ca.id, ca.user_id, ca.appointment_date, ca.appointment_time, ca.status, ca.concern_type, ca.concern_description, ca.assigned_advocate_id, ca.created_at, ca.updated_at,
                    u.first_name, u.last_name, u.email, sp.student_id, sp.grade_level,
                    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ca.assigned_advocate_id LIMIT 1) as counselor_name
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

// AJAX endpoint for fetching appointment details
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            echo json_encode(['error' => 'Invalid appointment ID']);
            exit();
        }
        
        $query = "SELECT ca.*, 
                  u.first_name, u.last_name, u.email,
                  sp.student_id, sp.grade_level,
                  (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ca.assigned_advocate_id LIMIT 1) as counselor_name
                  FROM counseling_appointments ca
                  JOIN users u ON ca.user_id = u.id
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  WHERE ca.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            echo json_encode(['error' => 'Appointment not found']);
            exit();
        }
        
        echo json_encode(['success' => true, 'appointment' => $appointment]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
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
    $stats['total'] = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE (u.archived=0 OR u.archived IS NULL)")->fetchColumn();
    $stats['completed'] = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE ca.status = 'completed' AND (u.archived=0 OR u.archived IS NULL)")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE ca.status = 'pending' AND (u.archived=0 OR u.archived IS NULL)")->fetchColumn();
    $stats['cancelled'] = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE ca.status = 'cancelled' AND (u.archived=0 OR u.archived IS NULL)")->fetchColumn();
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

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto relative z-[10000]" onclick="event.stopPropagation()">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center sticky top-0 z-50">
            <h3 class="text-lg font-bold"><i class="fas fa-calendar-check mr-2"></i>Appointment Details</h3>
            <button type="button" id="closeModalBtn" onclick="closeModalDirect(); return false;" class="flex items-center justify-center w-10 h-10 text-white hover:text-white hover:bg-white/20 rounded-lg transition-all duration-200 cursor-pointer hover:scale-110" title="Close modal">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="modalContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=counseling_history';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;

console.log('Counseling History page loaded');

// MODAL FUNCTIONS - Define first, always available
function closeModal(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const modal = document.getElementById('appointmentModal');
    if (!modal) return;
    
    // Remove flex display and add hidden
    modal.style.setProperty('display', 'none', 'important');
    modal.classList.add('hidden');
    console.log('Modal closed');
}

// Direct close function for inline onclick
function closeModalDirect() {
    console.log('closeModalDirect called');
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.style.setProperty('display', 'none', 'important');
        modal.classList.add('hidden');
        console.log('Modal hidden successfully');
        return false;
    }
    return false;
}

// Setup event listeners on close button - DO THIS FIRST
console.log('Setting up close button listener...');
const closeBtn = document.getElementById('closeModalBtn');
if (closeBtn) {
    closeBtn.addEventListener('click', function(e) {
        console.log('Close button clicked!');
        closeModal(e);
    });
    console.log('Close button listener successfully attached');
} else {
    console.warn('Close button not found - will try again after DOM loads');
}

// Also try when DOM is ready
function setupCloseButton() {
    const btn = document.getElementById('closeModalBtn');
    if (btn && !btn.dataset.listenerAttached) {
        btn.addEventListener('click', function(e) {
            console.log('Close button clicked (DOM ready)');
            closeModal(e);
        });
        btn.dataset.listenerAttached = 'true';
        console.log('Close button listener attached via DOMContentLoaded');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCloseButton);
} else {
    setupCloseButton();
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Close on overlay click
document.addEventListener('click', function(event) {
    const modal = document.getElementById('appointmentModal');
    if (modal && modal.style.display !== 'none') {
        if (event.target === modal) {
            closeModal(event);
        }
    }
});

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchCounselingHistory(); }, 300);
}

function fetchCounselingHistory() {
    console.log('fetchCounselingHistory called, page:', currentPage);
    const q = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const grade_level = document.getElementById('gradeLevelFilter').value;
    const date_from = document.getElementById('dateFrom').value;
    const date_to = document.getElementById('dateTo').value;
    const sort = document.getElementById('sortFilter').value;
    
    const url = BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&grade_level=${encodeURIComponent(grade_level)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&sort=${encodeURIComponent(sort)}`;
    console.log('Fetching URL:', url);
    
    fetch(url)
    .then(r => {
        console.log('Response status:', r.status);
        if (!r.ok) {
            console.error('Fetch error:', r.status, r.statusText);
            throw new Error('Network response was not ok');
        }
        return r.json();
    })
    .then(data => {
        console.log('Fetched data:', data);
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
                        <td class="px-4 py-3 text-gray-500">${new Date(session.appointment_date).toLocaleDateString()} ${session.appointment_time||''}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.counselor_name||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(session.concern_description||session.concern_type||'—')}</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium ${statusColor}">${session.status.replace(/_/g, ' ')}</span></td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="viewSession(${session.id})" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</button>
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    })
    .catch(error => {
        console.error('Error fetching counseling history:', error);
        const tbody = document.getElementById('counselingBody');
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-red-400">Error loading counseling sessions. Please refresh the page.</td></tr>';
    });
}

function viewSession(id) {
    // Fetch appointment details and show in modal
    fetch(BASE + `&action=get_details&id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        
        const apt = data.appointment;
        const modal = document.getElementById('appointmentModal');
        const content = document.getElementById('modalContent');
        
        // Format dates
        const appointmentDate = new Date(apt.appointment_date).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        const bookedDate = apt.created_at ? new Date(apt.created_at).toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
        const updatedDate = apt.updated_at ? new Date(apt.updated_at).toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
        
        // Format time
        const appointmentTime = apt.appointment_time ? new Date(`2000-01-01T${apt.appointment_time}`).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }) : 'N/A';
        
        // Status badge color
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-700',
            'confirmed': 'bg-blue-100 text-blue-700',
            'in_progress': 'bg-purple-100 text-purple-700',
            'completed': 'bg-green-100 text-green-700',
            'cancelled': 'bg-red-100 text-red-700',
            'missed': 'bg-gray-100 text-gray-700'
        };
        const statusColor = statusColors[apt.status] || 'bg-gray-100 text-gray-700';
        
        content.innerHTML = `
            <p class="text-sm text-gray-500 mb-6">Complete session information</p>
            
            <!-- Student Information -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Student Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-500 block">Name</span>
                        <span class="text-sm font-medium text-gray-800">${esc(apt.first_name + ' ' + apt.last_name)}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Student ID</span>
                        <span class="text-sm font-medium text-gray-800">${esc(apt.student_id || '—')}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Email</span>
                        <span class="text-sm font-medium text-gray-800">${esc(apt.email || '—')}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Grade Level</span>
                        <span class="text-sm font-medium text-gray-800">${esc(apt.grade_level || '—')}</span>
                    </div>
                </div>
            </div>
            
            <!-- Appointment Details -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Appointment Details</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-500 block">Date</span>
                        <span class="text-sm font-medium text-gray-800">${appointmentDate}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Time</span>
                        <span class="text-sm font-medium text-gray-800">${appointmentTime}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Counselor</span>
                        <span class="text-sm font-medium text-gray-800">${esc(apt.counselor_name || 'Not assigned')}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Status</span>
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColor}">${apt.status.replace(/_/g, ' ')}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs text-gray-500 block">Booked</span>
                        <span class="text-sm font-medium text-gray-800">${bookedDate}</span>
                    </div>
                </div>
            </div>
            
            <!-- Concern Description -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Concern Description</h4>
                <div class="mb-2">
                    <span class="text-xs text-gray-500 block">Type</span>
                    <span class="text-sm font-medium text-gray-800">${esc(apt.concern_type || '—')}</span>
                </div>
                <div>
                    <span class="text-xs text-gray-500 block">Description</span>
                    <p class="text-sm text-gray-800 mt-1">${esc(apt.concern_description || 'No description provided')}</p>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div class="text-xs text-gray-400 text-right">
                Last updated: ${updatedDate}
            </div>
        `;
        
        console.log('Showing modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    })
    .catch(error => {
        console.error('Error fetching appointment details:', error);
        alert('Error loading appointment details');
    });
}

function closeModal(event) {
    console.log('closeModal function called', event);
    const modal = document.getElementById('appointmentModal');
    if (!modal) {
        console.log('Modal element not found');
        return;
    }
    
    console.log('Closing modal, current display:', modal.style.display);
    modal.style.display = 'none';
    modal.classList.add('hidden');
    console.log('Modal closed, new display:', modal.style.display);
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        console.log('ESC key pressed');
        closeModal();
    }
});

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
