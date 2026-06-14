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
        $type = $_GET['type'] ?? 'all';
        
        $w = [];
        $p_arr = [];
        
        if ($q) {
            $like = "%$q%";
            if ($type === 'all') {
                // For UNION queries, need params twice
                $p_arr = [$like, $like, $like, $like, $like, $like];
            } else {
                $p_arr = [$like, $like, $like];
            }
        }
        
        if ($type !== 'all') {
            if ($type === 'multiple_intelligence') {
                $w[] = "mis.id IS NOT NULL";
            } elseif ($type === 'learning_style') {
                $w[] = "lsi.id IS NOT NULL AND (lsi.level IS NULL OR lsi.level != 'intermediate')";
            } elseif ($type === 'learning_style_intermediate') {
                $w[] = "lsi.id IS NOT NULL AND lsi.level = 'intermediate'";
            }
        }
        
        $where = implode(' AND ', $w);
        
        // Combined query - MI has student_id, LS has user_id
        if ($type === 'all') {
            if ($q) {
                $c_query = "SELECT COUNT(DISTINCT user_id) as total FROM (
                            SELECT DISTINCT u.id as user_id FROM users u
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id
                            LEFT JOIN multiple_intelligence_survey mis ON sp.student_id = mis.student_id
                            WHERE mis.id IS NOT NULL AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
                            UNION
                            SELECT DISTINCT u.id as user_id FROM users u
                            LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id
                            WHERE lsi.id IS NOT NULL AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
                        ) combined";
            } else {
                $c_query = "SELECT COUNT(DISTINCT user_id) as total FROM (
                            SELECT DISTINCT u.id as user_id FROM users u
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id
                            LEFT JOIN multiple_intelligence_survey mis ON sp.student_id = mis.student_id
                            WHERE mis.id IS NOT NULL
                            UNION
                            SELECT DISTINCT u.id as user_id FROM users u
                            LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id
                            WHERE lsi.id IS NOT NULL
                        ) combined";
            }
        } elseif ($type === 'multiple_intelligence') {
            $c_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u
                    LEFT JOIN student_profiles sp ON u.id = sp.user_id
                    LEFT JOIN multiple_intelligence_survey mis ON sp.student_id = mis.student_id
                    WHERE mis.id IS NOT NULL";
            if ($q) {
                $c_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
        } elseif ($type === 'learning_style') {
            $c_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u
                    LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id
                    WHERE lsi.id IS NOT NULL AND (lsi.level IS NULL OR lsi.level != 'intermediate')";
            if ($q) {
                $c_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
        } elseif ($type === 'learning_style_intermediate') {
            $c_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u
                    LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id
                    WHERE lsi.id IS NOT NULL AND lsi.level = 'intermediate'";
            if ($q) {
                $c_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
        } else {
            // learning_style
            $c_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u
                    LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id
                    WHERE lsi.id IS NOT NULL";
            if ($q) {
                $c_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
        }
        
        $c_stmt = $db->prepare($c_query);
        $c_stmt->execute($p_arr);
        $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Fetch combined results - MI has student_id, LS has user_id
        // For simplicity, we'll get results from each type separately based on filter
        if ($type === 'multiple_intelligence') {
            $f_query = "SELECT 
                        u.id as user_id,
                        u.first_name, u.last_name, u.email, 
                        sp.student_id, sp.department, sp.grade_level,
                        mis.id as mi_id, mis.completed_at as mi_date,
                        NULL as ls_id, NULL as ls_date
                        FROM users u 
                        LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                        LEFT JOIN multiple_intelligence_survey mis ON sp.student_id = mis.student_id 
                        WHERE mis.id IS NOT NULL";
            
            if ($q) {
                $f_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
            
            $f_query .= " ORDER BY mis.completed_at DESC LIMIT $per OFFSET $off";
        } elseif ($type === 'learning_style') {
            $f_query = "SELECT 
                        u.id as user_id,
                        u.first_name, u.last_name, u.email, 
                        sp.student_id, sp.department, sp.grade_level,
                        NULL as mi_id, NULL as mi_date,
                        lsi.id as ls_id, lsi.created_at as ls_date,
                        lsi.level as ls_level
                        FROM users u 
                        LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id 
                        LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                        WHERE lsi.id IS NOT NULL AND (lsi.level IS NULL OR lsi.level != 'intermediate')";
            
            if ($q) {
                $f_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
            
            $f_query .= " ORDER BY lsi.created_at DESC LIMIT $per OFFSET $off";
        } elseif ($type === 'learning_style_intermediate') {
            $f_query = "SELECT 
                        u.id as user_id,
                        u.first_name, u.last_name, u.email, 
                        sp.student_id, sp.department, sp.grade_level,
                        NULL as mi_id, NULL as mi_date,
                        lsi.id as ls_id, lsi.created_at as ls_date,
                        lsi.level as ls_level
                        FROM users u 
                        LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id 
                        LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                        WHERE lsi.id IS NOT NULL AND lsi.level = 'intermediate'";
            
            if ($q) {
                $f_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
            
            $f_query .= " ORDER BY lsi.created_at DESC LIMIT $per OFFSET $off";
        } else {
            // All assessments - need to get both types
            // To keep it simple, we'll limit the search results properly
            $search_where = '';
            if ($q) {
                $search_where = " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            }
            
            $f_query = "SELECT * FROM (
                        SELECT 
                            u.id as user_id,
                            u.first_name, u.last_name, u.email, 
                            sp.student_id, sp.department, sp.grade_level,
                            mis.id as mi_id, mis.completed_at as mi_date,
                            NULL as ls_id, NULL as ls_date,
                            mis.completed_at as sort_date
                            FROM users u 
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                            LEFT JOIN multiple_intelligence_survey mis ON sp.student_id = mis.student_id 
                            WHERE mis.id IS NOT NULL" . $search_where . "
                        UNION ALL
                        SELECT 
                            u.id as user_id,
                            u.first_name, u.last_name, u.email, 
                            sp.student_id, sp.department, sp.grade_level,
                            NULL as mi_id, NULL as mi_date,
                            lsi.id as ls_id, lsi.created_at as ls_date,
                            lsi.created_at as sort_date
                            FROM users u 
                            LEFT JOIN learning_style_inventory lsi ON u.id = lsi.user_id 
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                            WHERE lsi.id IS NOT NULL" . $search_where . "
                    ) combined
                    ORDER BY sort_date DESC LIMIT $per OFFSET $off";
        }
        $f_stmt = $db->prepare($f_query);
        $f_stmt->execute($p_arr);
        $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => (int)$per, 'page' => (int)$pg]);
    } catch (Exception $e) {
        error_log('Survey Results Error: ' . $e->getMessage());
        error_log('Query: ' . $f_query);
        error_log('Params: ' . json_encode($p_arr));
        echo json_encode(['error' => $e->getMessage(), 'rows' => [], 'total' => 0, 'per_page' => 10, 'page' => 1, 'debug' => ['query' => $f_query, 'params' => $p_arr]]);
    }
    exit();
}

// AJAX endpoint for fetching detailed assessment data
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        
        if ($type === 'mi') {
            // Fetch multiple intelligence survey details
            $query = "SELECT mis.*, u.first_name, u.last_name, sp.student_id 
                      FROM multiple_intelligence_survey mis
                      JOIN student_profiles sp ON mis.student_id = sp.student_id
                      JOIN users u ON sp.user_id = u.id
                      WHERE mis.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $student = $result['first_name'] . ' ' . $result['last_name'];
                $submitted = date('F j, Y at g:i A', strtotime($result['completed_at']));
                
                // Map sections to intelligence types based on survey_thankyou.php
                $intelligence_types = [
                    1 => ['name' => 'Naturalist Intelligence', 'score' => $result['section1_score']],
                    2 => ['name' => 'Musical Intelligence', 'score' => $result['section2_score']],
                    3 => ['name' => 'Logical-Mathematical Intelligence', 'score' => $result['section3_score']],
                    4 => ['name' => 'Existential Intelligence', 'score' => $result['section4_score']],
                    5 => ['name' => 'Interpersonal Intelligence', 'score' => $result['section5_score']],
                    6 => ['name' => 'Bodily-Kinesthetic Intelligence', 'score' => $result['section6_score']],
                    7 => ['name' => 'Linguistic Intelligence', 'score' => $result['section7_score']],
                    8 => ['name' => 'Intrapersonal Intelligence', 'score' => $result['section8_score']],
                    9 => ['name' => 'Spatial Intelligence', 'score' => $result['section9_score']],
                ];
                
                // Calculate total score for percentage
                $total_score = 0;
                foreach ($intelligence_types as $type) {
                    $total_score += $type['score'];
                }
                
                // Calculate percentages and find dominant
                $scores = [];
                $max_score = 0;
                foreach ($intelligence_types as $type) {
                    $percentage = $total_score > 0 ? round(($type['score'] / $total_score) * 100) : 0;
                    $scores[] = [
                        'name' => $type['name'],
                        'score' => $percentage,
                        'dominant' => $type['score'] >= 2 // Dominant if score is 2 or more
                    ];
                    if ($type['score'] > $max_score) {
                        $max_score = $type['score'];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'student' => $student,
                    'student_id' => $result['student_id'],
                    'submitted' => $submitted,
                    'scores' => $scores
                ]);
            } else {
                echo json_encode(['error' => 'Assessment not found']);
            }
        } elseif ($type === 'ls') {
            // Fetch learning style inventory details
            $query = "SELECT lsi.*, u.first_name, u.last_name, sp.student_id 
                      FROM learning_style_inventory lsi
                      JOIN users u ON lsi.user_id = u.id
                      LEFT JOIN student_profiles sp ON u.id = sp.user_id
                      WHERE lsi.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $student = $result['first_name'] . ' ' . $result['last_name'];
                $submitted = date('F j, Y at g:i A', strtotime($result['created_at']));
                
                // Calculate percentages based on scores
                $total_score = $result['visual_score'] + $result['auditory_score'] + $result['kinesthetic_score'];
                $visual_pct = $total_score > 0 ? round(($result['visual_score'] / $total_score) * 100) : 0;
                $auditory_pct = $total_score > 0 ? round(($result['auditory_score'] / $total_score) * 100) : 0;
                $kinesthetic_pct = $total_score > 0 ? round(($result['kinesthetic_score'] / $total_score) * 100) : 0;
                
                $scores = [
                    ['name' => 'Visual', 'score' => $visual_pct, 'dominant' => $result['learning_style'] === 'Visual'],
                    ['name' => 'Auditory', 'score' => $auditory_pct, 'dominant' => $result['learning_style'] === 'Auditory'],
                    ['name' => 'Kinesthetic', 'score' => $kinesthetic_pct, 'dominant' => $result['learning_style'] === 'Kinesthetic'],
                ];
                
                echo json_encode([
                    'success' => true,
                    'student' => $student,
                    'student_id' => $result['student_id'] ?? 'N/A',
                    'submitted' => $submitted,
                    'level' => $result['level'] ?? null,
                    'scores' => $scores
                ]);
            } else {
                echo json_encode(['error' => 'Assessment not found']);
            }
        } else {
            echo json_encode(['error' => 'Invalid assessment type']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
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
        <div class="flex gap-2 mb-4">
            <button onclick="switchTab('all')" id="tab-all" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white">All Assessments</button>
            <button onclick="switchTab('multiple_intelligence')" id="tab-multiple_intelligence" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Multiple Intelligence</button>
            <button onclick="switchTab('learning_style')" id="tab-learning_style" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Learning Style</button>
            <button onclick="switchTab('learning_style_intermediate')" id="tab-learning_style_intermediate" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Learning Style (Intermediate)</button>
        </div>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <input type="text" id="searchInput" placeholder="Search by name or student ID..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none" onkeyup="debounceSearch()">
            </div>
            <div>
                <select id="perPageFilter" class="w-full px-3 py-2 border rounded-lg text-sm" onchange="onPerPageChange()">
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="30">30 per page</option>
                    <option value="50">50 per page</option>
                </select>
            </div>
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
                        <th class="px-4 py-3">Grade Level</th>
                        <th class="px-4 py-3">Assessments Completed</th>
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

<!-- Assessment Details Modal -->
<div id="assessmentModal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto relative z-[10000]" onclick="event.stopPropagation()">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center sticky top-0 z-50">
            <h3 class="text-lg font-bold" id="modalTitle"><i class="fas fa-chart-bar mr-2"></i>Assessment Details</h3>
            <button type="button" onclick="closeAssessmentModal()" class="flex items-center justify-center w-10 h-10 text-white hover:text-white hover:bg-white/20 rounded-lg transition-all duration-200 cursor-pointer hover:scale-110" title="Close modal">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="modalContent">
            <!-- Content will be loaded here -->
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-2 sticky bottom-0 bg-white">
            <button onclick="printAssessment()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 flex items-center gap-2">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="closeAssessmentModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Close
            </button>
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=survey_results';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;
let currentTab = 'all';

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function switchTab(tab) {
    currentTab = tab;
    
    // Update tab styles
    document.querySelectorAll('[id^="tab-"]').forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-700');
    });
    document.getElementById('tab-' + tab).classList.remove('bg-gray-100', 'text-gray-700');
    document.getElementById('tab-' + tab).classList.add('bg-primary', 'text-white');
    
    // Refresh data
    currentPage = 1;
    fetchSurveyResults();
}

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
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&type=${encodeURIComponent(currentTab)}`)
    .then(r => r.json()).then(data => {
        const tbody = document.getElementById('surveyBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No assessment results found</td></tr>';
        } else {
            data.rows.forEach(result => {
                const name = (result.last_name||'') + ', ' + (result.first_name||'');
                
                // Determine which assessments are completed
                const assessments = [];
                if (result.mi_id) {
                    assessments.push('<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Multiple Intelligence</span>');
                }
                if (result.ls_id) {
                    if (result.ls_level === 'intermediate') {
                        assessments.push('<span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">Learning Style (Intermediate)</span>');
                    } else {
                        assessments.push('<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Learning Style</span>');
                    }
                }
                
                // Get the latest submission date
                const miDate = result.mi_date ? new Date(result.mi_date) : null;
                const lsDate = result.ls_date ? new Date(result.ls_date) : null;
                let latestDate = null;
                if (miDate && lsDate) {
                    latestDate = miDate > lsDate ? miDate : lsDate;
                } else {
                    latestDate = miDate || lsDate;
                }
                
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.student_id||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.department||'—')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.grade_level||'—')}</td>
                        <td class="px-4 py-3">${assessments.length > 0 ? assessments.join(' ') : '<span class="text-gray-400">No assessments</span>'}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">${latestDate ? latestDate.toLocaleDateString() : '—'}</td>
                        <td class="px-4 py-3 text-right">
                            ${result.mi_id ? `<button onclick="viewResult('mi', ${result.mi_id})" class="px-2 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 mr-1">MI</button>` : ''}
                            ${result.ls_id ? `<button onclick="viewResult('ls', ${result.ls_id})" class="px-2 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200">LS</button>` : ''}
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    });
}

function viewResult(type, id) {
    // Fetch detailed assessment data and show in modal
    fetch(BASE + `&action=get_details&type=${type}&id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        
        const modal = document.getElementById('assessmentModal');
        const content = document.getElementById('modalContent');
        const title = document.getElementById('modalTitle');
        
        if (type === 'mi') {
            title.innerHTML = `<i class="fas fa-brain mr-2"></i>${data.student} - Intelligence Profile`;
            content.innerHTML = `
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Student ID: ${esc(data.student_id)}</p>
                    <p class="text-sm text-gray-500">Submitted: ${data.submitted}</p>
                </div>
                
                <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Intelligence Scores:</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    ${data.scores.map(score => `
                        <div class="flex justify-between items-center p-3 rounded-lg ${score.dominant ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50'}">
                            <span class="text-sm font-medium ${score.dominant ? 'text-blue-800' : 'text-gray-700'}">${score.name}</span>
                            <span class="text-sm font-bold ${score.dominant ? 'text-blue-800' : 'text-gray-600'}">${score.score}% ${score.dominant ? '<span class="text-xs ml-1">Dominant</span>' : ''}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        } else if (type === 'ls') {
            const levelClass = data.level === 'intermediate' ? 'text-purple-800' : 'text-green-800';
            const levelText = data.level === 'intermediate' ? 'Learning Style (Intermediate) Profile' : 'Learning Style Profile';
            title.innerHTML = `<i class="fas fa-graduation-cap mr-2"></i>${data.student} - ${levelText}`;
            content.innerHTML = `
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Student ID: ${esc(data.student_id)}</p>
                    <p class="text-sm text-gray-500">Submitted: ${data.submitted}</p>
                    ${data.level ? `<p class="text-sm text-gray-500">Level: ${esc(data.level)}</p>` : ''}
                </div>
                
                <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Learning Style Results:</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    ${data.scores.map(score => `
                        <div class="flex justify-between items-center p-3 rounded-lg ${score.dominant ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}">
                            <span class="text-sm font-medium ${score.dominant ? 'text-green-800' : 'text-gray-700'}">${score.name}</span>
                            <span class="text-sm font-bold ${score.dominant ? 'text-green-800' : 'text-gray-600'}">${score.score}% ${score.dominant ? '<span class="text-xs ml-1">Dominant</span>' : ''}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    })
    .catch(error => {
        console.error('Error fetching assessment details:', error);
        alert('Error loading assessment details');
    });
}

function closeAssessmentModal() {
    const modal = document.getElementById('assessmentModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function printAssessment() {
    const content = document.getElementById('modalContent').innerHTML;
    const title = document.getElementById('modalTitle').textContent;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                .p-3 { padding: 10px; }
                .rounded-lg { border-radius: 8px; }
                .bg-blue-50 { background: #eff6ff; }
                .bg-green-50 { background: #f0fdf4; }
                .bg-gray-50 { background: #f9fafb; }
                .border { border: 1px solid #e5e7eb; }
                .border-blue-200 { border-color: #bfdbfe; }
                .border-green-200 { border-color: #bbf7d0; }
                .text-sm { font-size: 14px; }
                .text-xs { font-size: 12px; }
                .font-medium { font-weight: 500; }
                .font-bold { font-weight: 700; }
                .text-blue-800 { color: #1e40af; }
                .text-green-800 { color: #166534; }
                .text-gray-700 { color: #374151; }
                .text-gray-600 { color: #4b5563; }
                .text-gray-500 { color: #6b7280; }
                .flex { display: flex; }
                .justify-between { justify-content: space-between; }
                .items-center { align-items: center; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <h2>${title}</h2>
            ${content}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
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
