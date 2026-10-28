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
        $total = (int)$c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $pg=min($pg,max(1,(int)ceil($total/$per)));$off=($pg-1)*$per;
        
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

<link rel="stylesheet" href="../assets/css/counseling-history.css?v=20261028-1">
<div class="space-y-6 ch-workspace">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Counseling History</h1>
        <p class="text-sm text-gray-500">Complete record of all counseling sessions and appointments</p>
    </div>

<div class="record-summary-grid" role="group" aria-label="Filter counseling sessions by status"><button type="button" class="record-summary-card" data-history-status="" data-summary-count="<?= (int)$stats['total'] ?>" aria-pressed="true" aria-controls="counselingBody" onclick="switchStatusTab('')" style="--record-accent:#32699b"><span class="record-summary-number"><?= (int)$stats['total'] ?></span><span class="record-summary-label">Total Sessions</span><span class="record-summary-hint">All appointments</span></button>
<button type="button" class="record-summary-card" data-history-status="completed" data-summary-count="<?= (int)$stats['completed'] ?>" aria-pressed="false" aria-controls="counselingBody" onclick="switchStatusTab('completed')" style="--record-accent:#21866f"><span class="record-summary-number"><?= (int)$stats['completed'] ?></span><span class="record-summary-label">Completed</span><span class="record-summary-hint">Finished sessions</span></button>
<button type="button" class="record-summary-card" data-history-status="pending" data-summary-count="<?= (int)$stats['pending'] ?>" aria-pressed="false" aria-controls="counselingBody" onclick="switchStatusTab('pending')" style="--record-accent:#b17b24"><span class="record-summary-number"><?= (int)$stats['pending'] ?></span><span class="record-summary-label">Pending</span><span class="record-summary-hint">Awaiting confirmation</span></button>
<button type="button" class="record-summary-card" data-history-status="cancelled" data-summary-count="<?= (int)$stats['cancelled'] ?>" aria-pressed="false" aria-controls="counselingBody" onclick="switchStatusTab('cancelled')" style="--record-accent:#b25364"><span class="record-summary-number"><?= (int)$stats['cancelled'] ?></span><span class="record-summary-label">Cancelled</span><span class="record-summary-hint">Cancelled sessions</span></button></div>
<div class="record-filter-selection"><p id="historyFilterSelection" role="status">Showing: All appointments</p><button type="button" id="resetHistoryFilters" onclick="resetHistoryFilters()" hidden>Clear all filters</button></div>
<details class="record-filter-options" id="historyFilterOptions"><summary><span>Filters<span id="historyFilterCount"></span></span><small>Search, status, grade and dates</small></summary><div class="record-filter-body"><div class="record-filter-tabs" id="statusTabs" role="group" aria-label="Appointment status">
            <button type="button" onclick="switchStatusTab('')" id="tab-" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary text-white">All</button>
            <button type="button" onclick="switchStatusTab('pending')" id="tab-pending" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Pending</button>
            <button type="button" onclick="switchStatusTab('confirmed')" id="tab-confirmed" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Confirmed</button>
            <button type="button" onclick="switchStatusTab('in_progress')" id="tab-in_progress" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">In Progress</button>
            <button type="button" onclick="switchStatusTab('completed')" id="tab-completed" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Completed</button>
            <button type="button" onclick="switchStatusTab('cancelled')" id="tab-cancelled" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Cancelled</button>
            <button type="button" onclick="switchStatusTab('missed')" id="tab-missed" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">No Show</button>
        </div><div class="record-filter-fields"><label class="record-filter-search">Search students<input type="search" id="searchInput" placeholder="Search by name or student ID" oninput="debounceSearch()"></label><label>Grade / year<select id="gradeLevelFilter" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="historyFiltersChanged()">
                    <option value="">All Grades</option>
                    <?php foreach ($grade_levels as $grade): ?>
                        <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                    <?php endforeach; ?>
                </select></label><label>From date<input type="date" id="dateFrom" onchange="historyFiltersChanged()"></label><label>Through date<input type="date" id="dateTo" onchange="historyFiltersChanged()"></label><label>Sort order<select id="sortFilter" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="historyFiltersChanged()">
                    <option value="latest">Latest First</option>
                    <option value="oldest">Oldest First</option>
                </select></label></div></div></details>
    <!-- Session directory: long purposes are summarized here and kept intact in details. -->
    <section class="ch-sessions" aria-labelledby="chSessionsTitle">
        <header class="ch-list-header"><div><p class="ch-eyebrow">SESSION DIRECTORY</p><h2 id="chSessionsTitle">Counseling sessions</h2><p id="recordsInfo" role="status" aria-live="polite">Loading sessions…</p></div><span class="ch-page-size">10 per page</span></header>
        <div class="ch-table-wrap"><table class="ch-table" data-cw-no-cards><caption class="ch-sr-only">Counseling sessions and appointment history</caption><thead><tr><th scope="col">Student</th><th scope="col">Session date</th><th scope="col">Purpose</th><th scope="col">Counselor</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead><tbody id="counselingBody"><tr><td colspan="6" class="ch-empty">Loading sessions…</td></tr></tbody></table></div>
        <footer class="ch-pagination"><span id="pageInfo" aria-live="polite"></span><nav aria-label="Counseling session pages"><button type="button" onclick="changePage(-1)" id="prevBtn" aria-label="Previous session page">‹ <span>Previous</span></button><span id="pageNums"></span><button type="button" onclick="changePage(1)" id="nextBtn" aria-label="Next session page"><span>Next</span> ›</button></nav></footer>
    </section>
</div>

<div id="appointmentModal" class="pd-overlay hidden ch-details-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="chDetailsTitle">
    <div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-comments"></i></span><div class="pd-heading-copy"><p class="ch-eyebrow">COUNSELING RECORD</p><h2 id="chDetailsTitle">Session details</h2><p>Review the appointment and its full purpose.</p></div><button type="button" class="pd-close" id="closeModalBtn" aria-label="Close session details" data-dialog-close="appointmentModal">×</button></header><div class="pd-body" id="modalContent" aria-live="polite"></div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="appointmentModal">Close</button></footer></div>
</div>

<script src="../assets/js/counseling-history.js?v=20261028-1"></script>
