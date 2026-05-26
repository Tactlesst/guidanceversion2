<?php
require_once __DIR__ . '/../../../classes/User.php';

$user_obj = new User($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// POST handlers
if ($_POST) {
    if (isset($_POST['assign_student_ids'])) {
        try {
            $db->beginTransaction();
            $assigned_count = 0;
            
            // Handle student IDs
            foreach ($_POST['student_ids'] as $user_id => $student_id) {
                if (!empty(trim($student_id))) {
                    $sid = trim($student_id);
                    // Check if student_id already exists
                    $chk = $db->prepare("SELECT id FROM student_profiles WHERE student_id=?");
                    $chk->execute([$sid]);
                    if ($chk->rowCount() === 0) {
                        // Check if user already has a student profile
                        $pc = $db->prepare("SELECT id FROM student_profiles WHERE user_id=?");
                        $pc->execute([$user_id]);
                        if ($pc->rowCount() === 0) {
                            $db->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?,?)")->execute([$user_id, $sid]);
                        } else {
                            $db->prepare("UPDATE student_profiles SET student_id=? WHERE user_id=?")->execute([$sid, $user_id]);
                        }
                        $assigned_count++;
                    } else {
                        throw new Exception("Student ID '$sid' already exists.");
                    }
                }
            }
            
            // Handle emails
            foreach ($_POST['emails'] as $user_id => $email) {
                if (!empty(trim($email))) {
                    $email = trim($email);
                    // Check if email already exists
                    $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
                    $chk->execute([$email, $user_id]);
                    if ($chk->rowCount() === 0) {
                        $db->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $user_id]);
                        $assigned_count++;
                    } else {
                        throw new Exception("Email '$email' already exists.");
                    }
                }
            }
            
            $db->commit();
            $_SESSION['success_message'] = "Successfully updated $assigned_count record(s).";
            header("Location: layout.php?page=missing_student_ids");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// AJAX actions
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'fetch_missing_ids':
                $pg = max(1, intval($_GET['p'] ?? 1));
                $per = 20;
                $off = ($pg - 1) * $per;
                $q = trim($_GET['q'] ?? '');
                $w = ["u.role='student' AND (u.archived=0 OR u.archived IS NULL)"];
                $p_arr = [];
                if ($q) {
                    $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
                    $like = "%$q%";
                    array_push($p_arr, $like, $like, $like, $like);
                }
                $where = implode(' AND ', $w);
                $c_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where");
                $c_stmt->execute($p_arr);
                $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $stmt = $db->prepare("SELECT u.*, sp.student_id FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE $where ORDER BY u.created_at DESC LIMIT $per OFFSET $off");
                $stmt->execute($p_arr);
                echo json_encode(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'per_page' => $per, 'page' => $pg]);
                break;
            default:
                echo json_encode(['error' => 'Invalid']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Get initial counts
$missing_student_id_count = 0;
$missing_email_count = 0;
$total_students = 0;

try {
    $missing_student_id_count = $db->query("SELECT COUNT(*) FROM users u LEFT JOIN student_profiles sp ON u.id=sp.user_id WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) AND (sp.student_id IS NULL OR sp.student_id='')")->fetchColumn();
    $missing_email_count = $db->query("SELECT COUNT(*) FROM users u WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL) AND (u.email IS NULL OR u.email='')")->fetchColumn();
    $total_students = $db->query("SELECT COUNT(*) FROM users u WHERE u.role='student' AND (u.archived=0 OR u.archived IS NULL)")->fetchColumn();
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>Student Information</h1>
            <p class="text-sm text-gray-500 mt-1">Manage student IDs and email addresses</p>
        </div>
        <div class="flex gap-2">
            <a href="layout.php?page=user_management" class="px-3 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Back to User Management</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500">Missing Student ID</div>
                    <div class="text-2xl font-bold text-gray-800"><?= $missing_student_id_count ?></div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center">
                    <i class="fas fa-id-badge"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500">Missing Email</div>
                    <div class="text-2xl font-bold text-gray-800"><?= $missing_email_count ?></div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-gray-500">Total Students</div>
                    <div class="text-2xl font-bold text-gray-800"><?= $total_students ?></div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Search & Actions -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div class="flex gap-3 items-center flex-1">
                <input type="text" id="searchInput" placeholder="Search by name or email..." class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-yellow-500 focus:outline-none" oninput="debounceSearch()">
            </div>
            <form method="POST" id="assignForm">
                <input type="hidden" name="assign_student_ids" value="1">
                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-save mr-1"></i>Save Student IDs</button>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup><col class="w-[25%]"><col class="w-[25%]"><col class="w-[20%]"><col class="w-[20%]"><col class="w-[10%]"></colgroup>
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Assign Email</th>
                        <th class="px-4 py-3">Assign Student ID</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
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
const BASE = 'layout.php?page=missing_student_ids';
let searchTimer;
let currentPage = 1;

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { 
        currentPage = 1; 
        fetchStudents(); 
    }, 300);
}

function fetchStudents() {
    const q = document.getElementById('searchInput').value;
    const url = BASE + `&action=fetch_missing_ids&p=${currentPage}&q=${encodeURIComponent(q)}`;
    fetch(url).then(r=>r.json()).then(data => {
        const tbody = document.getElementById('studentsBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No students found</td></tr>';
        } else {
            let html = '';
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const missingEmail = !u.email || u.email === '';
                const missingStudentId = !u.student_id || u.student_id === '';
                const emailInput = missingEmail ? 
                    `<input type="email" name="emails[${u.id}]" placeholder="Enter email" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none bg-orange-50 border-orange-200">` : 
                    `<span class="text-gray-500">${esc(u.email||'—')}</span>`;
                const studentIdInput = missingStudentId ? 
                    `<input type="text" name="student_ids[${u.id}]" placeholder="Enter student ID" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-yellow-500 focus:outline-none bg-yellow-50 border-yellow-200">` : 
                    `<span class="text-gray-500">${esc(u.student_id||'—')}</span>`;
                
                html += `<tr class="hover:bg-gray-50 ${missingEmail || missingStudentId ? 'bg-red-50/50' : ''}">
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3">${emailInput}</td>
                    <td class="px-4 py-3">${studentIdInput}</td>
                    <td class="px-4 py-3 text-gray-400 text-xs">${new Date(u.created_at).toLocaleDateString()}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="layout.php?page=user_management" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="View in User Management"><i class="fas fa-external-link-alt"></i></a>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
        renderPagination(data.total, data.per_page, data.page);
    }).catch(err => {
        console.error('Error fetching students:', err);
        document.getElementById('studentsBody').innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-400">Error loading students</td></tr>';
    });
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    
    const pageInfoEl = document.getElementById('pageInfo');
    const prevBtnEl = document.getElementById('prevBtn');
    const nextBtnEl = document.getElementById('nextBtn');
    const pageNumsEl = document.getElementById('pageNums');
    
    if (pageInfoEl) pageInfoEl.textContent = total === 0 ? 'No records' : `Showing ${start}-${end} of ${total}`;
    if (prevBtnEl) prevBtnEl.disabled = page <= 1;
    if (nextBtnEl) nextBtnEl.disabled = page >= totalPages;
    
    if (pageNumsEl) {
        pageNumsEl.innerHTML = '';
        const maxBtns = 5;
        let sp = Math.max(1, page - Math.floor(maxBtns/2));
        let ep = Math.min(totalPages, sp + maxBtns - 1);
        if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);
        
        for (let p = sp; p <= ep; p++) {
            const b = document.createElement('button');
            b.textContent = p;
            b.className = p === page ? 'px-2.5 py-1 text-sm rounded-lg bg-yellow-500 text-white' : 'px-2.5 py-1 text-sm rounded-lg border hover:bg-gray-50';
            b.onclick = () => { currentPage = p; fetchStudents(); };
            pageNumsEl.appendChild(b);
        }
    }
}

function changePage(delta) { currentPage += delta; fetchStudents(); }

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// Init
fetchStudents();
</script>
