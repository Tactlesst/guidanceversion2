<?php
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page_no = max(1, (int)($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($page_no - 1) * $per_page;

$where = ["ea.status = 'completed'"];
$params = [];
if ($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status === 'qualified') $where[] = "ea.qualified_grade IS NOT NULL AND ea.qualified_grade != ''";
if ($status === 'examinee') $where[] = "u.role = 'examinee'";
if ($status === 'student') $where[] = "u.role = 'student'";
$where_sql = implode(' AND ', $where);

$stats = ['total' => 0, 'qualified' => 0, 'avg' => 0, 'max' => 0];
$results = [];
$total_records = 0;
$distribution_labels = [];
$distribution_data = [];
$interpretation_labels = [];
$interpretation_data = [];

try {
    $stats['total'] = (int)$db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='completed'")->fetchColumn();
    $stats['qualified'] = (int)$db->query("SELECT COUNT(*) FROM entrance_exam_appointments WHERE status='completed' AND qualified_grade IS NOT NULL AND qualified_grade != ''")->fetchColumn();
    $avg = $db->query("SELECT AVG(ROUND((total_score/NULLIF(total_items,0))*100,1)) FROM entrance_exam_appointments WHERE status='completed' AND total_score IS NOT NULL AND total_items > 0")->fetchColumn();
    $max = $db->query("SELECT MAX(ROUND((total_score/NULLIF(total_items,0))*100,1)) FROM entrance_exam_appointments WHERE status='completed' AND total_score IS NOT NULL AND total_items > 0")->fetchColumn();
    $stats['avg'] = $avg ? round((float)$avg, 1) : 0;
    $stats['max'] = $max ? round((float)$max, 1) : 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM entrance_exam_appointments ea JOIN users u ON ea.user_id=u.id WHERE $where_sql");
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();

    $sql = "SELECT ea.*, u.first_name, u.last_name, u.role, sp.student_id
            FROM entrance_exam_appointments ea
            JOIN users u ON ea.user_id = u.id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE $where_sql
            ORDER BY ea.preferred_date DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $p) $stmt->bindValue($i + 1, $p);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = $db->query("SELECT CASE WHEN total_score >= 61 THEN '61-72' WHEN total_score >= 51 THEN '51-60' WHEN total_score >= 41 THEN '41-50' WHEN total_score >= 31 THEN '31-40' WHEN total_score >= 21 THEN '21-30' WHEN total_score >= 11 THEN '11-20' ELSE '0-10' END AS bucket, COUNT(*) AS cnt FROM entrance_exam_appointments WHERE status='completed' AND total_score IS NOT NULL GROUP BY bucket ORDER BY MIN(total_score)");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $distribution_labels[] = $r['bucket'];
        $distribution_data[] = (int)$r['cnt'];
    }

    $rows = $db->query("SELECT COALESCE(interpretation,'N/A') AS label, COUNT(*) AS cnt FROM entrance_exam_appointments WHERE status='completed' GROUP BY interpretation");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $interpretation_labels[] = $r['label'];
        $interpretation_data[] = (int)$r['cnt'];
    }
} catch (Exception $e) {}

$total_pages = max(1, (int)ceil($total_records / $per_page));
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-clipboard-list mr-2 text-primary"></i>OLSAT Exam Reports</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><div class="text-xs text-gray-500">Total Completed</div><div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500"><div class="text-xs text-gray-500">Qualified</div><div class="text-2xl font-bold text-gray-800"><?= $stats['qualified'] ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-cyan-500"><div class="text-xs text-gray-500">Average Score</div><div class="text-2xl font-bold text-gray-800"><?= $stats['avg'] ?>%</div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500"><div class="text-xs text-gray-500">Highest Score</div><div class="text-2xl font-bold text-gray-800"><?= $stats['max'] ?>%</div></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <form class="grid md:grid-cols-4 gap-3" method="GET" action="layout.php">
            <input type="hidden" name="page" value="olsat_reports">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student..." class="px-3 py-2 border rounded-lg text-sm">
            <select name="status" class="px-3 py-2 border rounded-lg text-sm">
                <option value="">All</option>
                <option value="qualified" <?= $status === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                <option value="student" <?= $status === 'student' ? 'selected' : '' ?>>Converted Student</option>
                <option value="examinee" <?= $status === 'examinee' ? 'selected' : '' ?>>Examinee</option>
            </select>
            <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Apply</button>
            <a href="layout.php?page=olsat_reports" class="px-4 py-2 border rounded-lg text-sm text-center">Reset</a>
        </form>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Score Distribution</h3>
            <?php if (!empty($distribution_labels) && array_sum($distribution_data) > 0): ?>
                <div class="relative h-64"><canvas id="scoreChart"></canvas></div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No scored exams yet.</div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Interpretation Mix</h3>
            <?php if (!empty($interpretation_labels) && array_sum($interpretation_data) > 0): ?>
                <div class="relative h-64"><canvas id="interpChart"></canvas></div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No interpretation data yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Stanine</th><th class="px-4 py-3">Interpretation</th><th class="px-4 py-3">Date</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($results)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No OLSAT results found.</td></tr>
                    <?php else: foreach ($results as $r): ?>
                    <?php $percent = (!empty($r['total_score']) && !empty($r['total_items'])) ? round(((float)$r['total_score'] / (float)$r['total_items']) * 100, 1) : null; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?><?php if (!empty($r['student_id'])): ?><div class="text-xs text-gray-500"><?= htmlspecialchars($r['student_id']) ?></div><?php endif; ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $r['role'] === 'student' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>"><?= ucfirst($r['role']) ?></span></td>
                        <td class="px-4 py-3"><?= $percent !== null ? $percent . '%' : 'N/A' ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($r['stanine_score'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($r['interpretation'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-xs text-gray-600"><?= !empty($r['preferred_date']) ? date('M d, Y', strtotime($r['preferred_date'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t text-xs text-gray-500">Page <?= $page_no ?> of <?= $total_pages ?> (<?= $total_records ?> records)</div>
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
    initChart('scoreChart', {
        type: 'bar',
        data: { labels: <?= json_encode($distribution_labels) ?>, datasets: [{ label: 'Examinees', data: <?= json_encode($distribution_data) ?>, backgroundColor: 'rgba(22,50,105,0.18)', borderColor: '#163269', borderWidth: 2, borderRadius: 8 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    initChart('interpChart', {
        type: 'doughnut',
        data: { labels: <?= json_encode($interpretation_labels) ?>, datasets: [{ data: <?= json_encode($interpretation_data) ?>, backgroundColor: ['#f59e0b','#3b82f6','#22c55e','#ef4444','#a855f7','#14b8a6'], borderWidth: 1, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });
});
</script>
