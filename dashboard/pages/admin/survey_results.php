<?php
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$pds = new PersonalDataSheet($db);

// AJAX endpoint for fetching survey results
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $pg = max(1, intval($_GET['p'] ?? 1));
        $per = max(1, min(50, intval($_GET['per'] ?? 10)));
        $off = ($pg - 1) * $per;
        $q = trim($_GET['q'] ?? '');
        
        $w = ["(u.archived=0 OR u.archived IS NULL)"];
        $p_arr = [];
        
        if ($q) {
            $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_id LIKE ?)";
            $like = "%$q%";
            $p_arr = [$like, $like, $like];
        }
        
        $where = implode(' AND ', $w);
        
        // Count
        $c_query = "SELECT COUNT(*) as total FROM multiple_intelligence_survey mis JOIN users u ON mis.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE $where";
        $c_stmt = $db->prepare($c_query);
        $c_stmt->execute($p_arr);
        $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Fetch page
        $f_query = "SELECT mis.id, mis.user_id, mis.created_at, mis.verbal_linguistic, mis.logical_mathematical, mis.visual_spatial, 
                    mis.bodily_kinesthetic, mis.musical, mis.interpersonal, mis.intrapersonal, mis.naturalist,
                    u.first_name, u.last_name, u.email, sp.student_id, sp.department 
                    FROM multiple_intelligence_survey mis 
                    JOIN users u ON mis.user_id = u.id 
                    LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                    WHERE $where 
                    ORDER BY mis.created_at DESC 
                    LIMIT $per OFFSET $off";
        $f_stmt = $db->prepare($f_query);
        $f_stmt->execute($p_arr);
        $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure proper serialization
        foreach ($rows as &$row) {
            $row['verbal_linguistic'] = (float)($row['verbal_linguistic'] ?? 0);
            $row['logical_mathematical'] = (float)($row['logical_mathematical'] ?? 0);
            $row['visual_spatial'] = (float)($row['visual_spatial'] ?? 0);
            $row['bodily_kinesthetic'] = (float)($row['bodily_kinesthetic'] ?? 0);
            $row['musical'] = (float)($row['musical'] ?? 0);
            $row['interpersonal'] = (float)($row['interpersonal'] ?? 0);
            $row['intrapersonal'] = (float)($row['intrapersonal'] ?? 0);
            $row['naturalist'] = (float)($row['naturalist'] ?? 0);
        }
        unset($row);
        
        echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => (int)$per, 'page' => (int)$pg]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'rows' => [], 'total' => 0, 'per_page' => 10, 'page' => 1]);
    }
    exit();
}

// Get statistics
$stats = [
    'multiple_intelligence' => 0,
    'learning_style' => 0,
    'total' => 0,
    'completion_rate' => 0
];

try {
    $total_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND (archived = 0 OR archived IS NULL)")->fetchColumn();
    $stats['multiple_intelligence'] = $db->query("SELECT COUNT(*) FROM multiple_intelligence_survey")->fetchColumn();
    $stats['learning_style'] = $db->query("SELECT COUNT(*) FROM learning_style_inventory")->fetchColumn();
    $stats['total'] = $stats['multiple_intelligence'] + $stats['learning_style'];
    
    if ($total_students > 0) {
        $stats['completion_rate'] = round(($stats['total'] / ($total_students * 2)) * 100);
    }
} catch (Exception $e) {}

// Get assessment overview counts
$assessment_overview = [
    'multiple_intelligence_survey' => 0,
    'learning_style_inventory' => 0,
    'learning_style_intermediate' => 0
];

try {
    $assessment_overview['multiple_intelligence_survey'] = $db->query("SELECT COUNT(*) FROM multiple_intelligence_survey")->fetchColumn();
    $assessment_overview['learning_style_inventory'] = $db->query("SELECT COUNT(*) FROM learning_style_inventory")->fetchColumn();
    $assessment_overview['learning_style_intermediate'] = $db->query("SELECT COUNT(*) FROM learning_style_inventory WHERE level = 'intermediate'")->fetchColumn();
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Student Assessment Results</h1>
        <p class="text-sm text-gray-500">View and analyze student survey responses and learning assessments</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['multiple_intelligence'] ?></div>
            <div class="text-xs text-gray-500">Multiple Intelligence</div>
            <div class="text-[10px] text-gray-400">Completed surveys</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['learning_style'] ?></div>
            <div class="text-xs text-gray-500">Learning Style</div>
            <div class="text-[10px] text-gray-400">Completed inventories</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-purple-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
            <div class="text-xs text-gray-500">Total Assessments</div>
            <div class="text-[10px] text-gray-400">All completed</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-orange-500">
            <div class="text-2xl font-bold text-gray-800"><?= $stats['completion_rate'] ?>%</div>
            <div class="text-xs text-gray-500">Completion Rate</div>
            <div class="text-[10px] text-gray-400">Student participation</div>
        </div>
    </div>

    <!-- Assessment Results Overview -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Assessment Results Overview</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <div class="text-2xl font-bold text-blue-800"><?= $assessment_overview['multiple_intelligence_survey'] ?></div>
                <div class="text-xs text-blue-600">Multiple Intelligence Survey</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <div class="text-2xl font-bold text-green-800"><?= $assessment_overview['learning_style_inventory'] ?></div>
                <div class="text-xs text-green-600">Learning Style Inventory</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <div class="text-2xl font-bold text-purple-800"><?= $assessment_overview['learning_style_intermediate'] ?></div>
                <div class="text-xs text-purple-600">Learning Style (Intermediate)</div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Options -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex gap-3 items-center">
            <input type="text" id="searchInput" placeholder="Search by name or student ID..." class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none" onkeyup="debounceSearch()">
            <select id="perPageFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="onPerPageChange()">
                <option value="10">10 per page</option>
                <option value="20">20 per page</option>
                <option value="30">30 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>
    </div>

    <!-- Assessment Results Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Assessment Results</h3>
            <p class="text-xs text-gray-500" id="recordsInfo">Loading...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Dominant Intelligences</th>
                        <th class="px-4 py-3">Submission Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="surveyBody" class="divide-y divide-gray-100"></tbody>
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
const BASE = 'layout.php?page=survey_results';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchSurveyResults(); }, 300);
}

function onPerPageChange() {
    itemsPerPage = parseInt(document.getElementById('perPageFilter').value);
    currentPage = 1;
    fetchSurveyResults();
}

function fetchSurveyResults() {
    const q = document.getElementById('searchInput').value;
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('surveyBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No assessment results found</td></tr>';
        } else {
            data.rows.forEach(result => {
                const name = (result.last_name||'') + ', ' + (result.first_name||'');
                
                // Calculate dominant intelligence
                const intelligences = [
                    'verbal_linguistic', 'logical_mathematical', 'visual_spatial',
                    'bodily_kinesthetic', 'musical', 'interpersonal',
                    'intrapersonal', 'naturalist'
                ];
                
                let dominant = '';
                let maxScore = 0;
                intelligences.forEach(intel => {
                    if (result[intel] > maxScore) {
                        maxScore = result[intel];
                        const labels = {
                            'verbal_linguistic': 'Verbal-Linguistic',
                            'logical_mathematical': 'Logical-Mathematical',
                            'visual_spatial': 'Visual-Spatial',
                            'bodily_kinesthetic': 'Bodily-Kinesthetic',
                            'musical': 'Musical',
                            'interpersonal': 'Interpersonal',
                            'intrapersonal': 'Intrapersonal',
                            'naturalist': 'Naturalist'
                        };
                        dominant = labels[intel];
                    }
                });
                
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.student_id||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.department||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(dominant||'—')}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">${new Date(result.created_at).toLocaleDateString()}</td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="viewResult(${result.id})" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</button>
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    });
}

function viewResult(id) {
    // Open result view in modal or new page
    window.open(`../surveys/survey_thankyou.php?id=${id}`, '_blank');
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('recordsInfo').textContent = total === 0 ? 'No records' : `Showing ${start} to ${end} of ${total} results`;
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
        b.onclick = () => { currentPage = p; fetchSurveyResults(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchSurveyResults(); }

// Initial load
fetchSurveyResults();
</script>
