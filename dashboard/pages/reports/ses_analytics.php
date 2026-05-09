<?php
$filter_ses = $_GET['filter_ses'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$allowed_filters = ['all', 'Low', 'Middle', 'High'];
if (!in_array($filter_ses, $allowed_filters, true)) {
    $filter_ses = 'all';
}

$where = [];
$params = [];

if ($filter_ses !== 'all') {
    $where[] = "spred.predicted_ses = ?";
    $params[] = $filter_ses;
}

if ($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR COALESCE(sp.student_id, '') LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stats = ['Low' => 0, 'Middle' => 0, 'High' => 0];
try {
    $stats_sql = "SELECT predicted_ses, COUNT(*) AS cnt FROM ses_predictions WHERE is_latest = 1 GROUP BY predicted_ses";
    $stats_stmt = $db->query($stats_sql);
    foreach ($stats_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ses = $row['predicted_ses'] ?? '';
        if (isset($stats[$ses])) {
            $stats[$ses] = (int)$row['cnt'];
        }
    }
} catch (Exception $e) {}

$total_students = array_sum($stats);

$students = [];
try {
    $sql = "SELECT 
                u.id AS user_id,
                CONCAT(u.last_name, ', ', u.first_name) AS full_name,
                u.email,
                COALESCE(sp.student_id, 'N/A') AS student_id,
                spred.predicted_ses,
                spred.confidence_score,
                spred.prediction_date
            FROM ses_predictions spred
            JOIN users u ON u.id = spred.user_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            $where_sql
            ORDER BY 
                CASE spred.predicted_ses
                    WHEN 'Low' THEN 1
                    WHEN 'Middle' THEN 2
                    WHEN 'High' THEN 3
                    ELSE 4
                END,
                spred.prediction_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$ses_labels = ['Low', 'Middle', 'High'];
$ses_counts = [(int)$stats['Low'], (int)$stats['Middle'], (int)$stats['High']];
$ses_colors = ['#ef4444', '#f59e0b', '#22c55e'];

// Confidence histogram buckets (percentage)
$confidence_labels = ['0-20%', '20-40%', '40-60%', '60-80%', '80-100%'];
$confidence_counts = [0, 0, 0, 0, 0];
foreach ($students as $s) {
    $c = isset($s['confidence_score']) ? (float)$s['confidence_score'] : 0.0;
    // confidence_score is stored as 0..1
    $pct = max(0.0, min(100.0, $c * 100.0));
    if ($pct < 20) $confidence_counts[0]++;
    elseif ($pct < 40) $confidence_counts[1]++;
    elseif ($pct < 60) $confidence_counts[2]++;
    elseif ($pct < 80) $confidence_counts[3]++;
    else $confidence_counts[4]++;
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-chart-line mr-2 text-primary"></i>SES Analytics
        </h1>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500">Total Predictions</div>
            <div class="text-2xl font-bold text-gray-800"><?= $total_students ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
            <div class="text-xs text-gray-500">Low SES</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['Low'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
            <div class="text-xs text-gray-500">Middle SES</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['Middle'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
            <div class="text-xs text-gray-500">High SES</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['High'] ?></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <form method="GET" action="layout.php" class="flex flex-wrap gap-3">
            <input type="hidden" name="page" value="ses_analytics">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, student ID..." class="flex-1 min-w-[220px] px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:outline-none">
            <select name="filter_ses" class="px-3 py-2 border rounded-lg text-sm">
                <option value="all" <?= $filter_ses === 'all' ? 'selected' : '' ?>>All SES</option>
                <option value="Low" <?= $filter_ses === 'Low' ? 'selected' : '' ?>>Low SES</option>
                <option value="Middle" <?= $filter_ses === 'Middle' ? 'selected' : '' ?>>Middle SES</option>
                <option value="High" <?= $filter_ses === 'High' ? 'selected' : '' ?>>High SES</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">
                <i class="fas fa-filter mr-1"></i>Apply
            </button>
            <a href="layout.php?page=ses_analytics" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">
                Reset
            </a>
        </form>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">SES Distribution</h3>
            <?php if ($total_students > 0): ?>
                <div class="relative h-64"><canvas id="sesDistChart"></canvas></div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No SES predictions yet.</div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Confidence Histogram</h3>
            <?php if (!empty($students)): ?>
                <div class="relative h-64"><canvas id="sesConfidenceChart"></canvas></div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No records to compute confidence.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">SES</th>
                        <th class="px-4 py-3">Confidence</th>
                        <th class="px-4 py-3">Predicted On</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No SES records found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $student): ?>
                    <?php
                        $ses = $student['predicted_ses'] ?? 'N/A';
                        $ses_class = 'bg-gray-100 text-gray-700';
                        if ($ses === 'Low') $ses_class = 'bg-red-100 text-red-700';
                        if ($ses === 'Middle') $ses_class = 'bg-yellow-100 text-yellow-700';
                        if ($ses === 'High') $ses_class = 'bg-green-100 text-green-700';
                        $confidence = isset($student['confidence_score']) ? (float)$student['confidence_score'] : 0;
                        $confidence_display = $confidence > 0 ? number_format($confidence * 100, 1) . '%' : 'N/A';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($student['full_name']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($student['email']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($student['student_id']) ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium <?= $ses_class ?>"><?= htmlspecialchars($ses) ?></span></td>
                        <td class="px-4 py-3 text-gray-600"><?= $confidence_display ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= !empty($student['prediction_date']) ? date('M d, Y h:i A', strtotime($student['prediction_date'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function initChart(id, config) {
    if (!window.Chart) return;
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, config);
}

document.addEventListener('DOMContentLoaded', function () {
    initChart('sesDistChart', {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($ses_labels) ?>,
            datasets: [{
                data: <?= json_encode($ses_counts) ?>,
                backgroundColor: <?= json_encode($ses_colors) ?>,
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '62%'
        }
    });

    initChart('sesConfidenceChart', {
        type: 'bar',
        data: {
            labels: <?= json_encode($confidence_labels) ?>,
            datasets: [{
                label: 'Students',
                data: <?= json_encode($confidence_counts) ?>,
                backgroundColor: 'rgba(22,50,105,0.18)',
                borderColor: '#163269',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>
