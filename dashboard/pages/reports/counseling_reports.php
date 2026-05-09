<?php
$status_stats = [];
$monthly_points = [];
$reason_stats = [];
$counselor_summary = [];
$totals = ['all' => 0, 'completed' => 0, 'pending' => 0, 'cancelled' => 0];

try {
    $status_stats = $db->query("SELECT status, COUNT(*) AS cnt FROM counseling_appointments GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($status_stats as $s) {
        $totals['all'] += (int)$s['cnt'];
        if (isset($totals[$s['status']])) $totals[$s['status']] = (int)$s['cnt'];
    }

    $monthly_points = $db->query("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS ym, COUNT(*) AS cnt FROM counseling_appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC")->fetchAll(PDO::FETCH_ASSOC);

    $reason_rows = $db->query("SELECT concern_description, COUNT(*) AS cnt FROM counseling_appointments WHERE concern_description IS NOT NULL AND concern_description != '' AND status='completed' GROUP BY concern_description ORDER BY cnt DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reason_rows as $r) {
        $reason_stats[] = ['label' => mb_strimwidth($r['concern_description'], 0, 45, '...'), 'count' => (int)$r['cnt']];
    }

    $rows = $db->query("SELECT CONCAT(u.first_name,' ',u.last_name) AS counselor_name, COUNT(*) AS cnt, SUM(CASE WHEN ca.status='completed' THEN 1 ELSE 0 END) AS completed_cnt FROM counseling_appointments ca LEFT JOIN users u ON ca.assigned_advocate_id=u.id WHERE ca.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY ca.assigned_advocate_id, counselor_name ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $name = $r['counselor_name'] ?: 'Unassigned';
        $counselor_summary[] = [
            'name' => $name,
            'total' => (int)$r['cnt'],
            'completed' => (int)$r['completed_cnt'],
            'rate' => ((int)$r['cnt']) > 0 ? round(((int)$r['completed_cnt'] / (int)$r['cnt']) * 100, 1) : 0
        ];
    }
} catch (Exception $e) {}

$status_labels = array_map(fn($x) => ucfirst((string)$x['status']), $status_stats);
$status_data = array_map(fn($x) => (int)$x['cnt'], $status_stats);
$status_colors = array_map(function($x) {
    $s = strtolower((string)($x['status'] ?? ''));
    return match ($s) {
        'completed' => '#22c55e',
        'pending' => '#f59e0b',
        'cancelled' => '#ef4444',
        'confirmed' => '#3b82f6',
        'rescheduled' => '#8b5cf6',
        'missed' => '#64748b',
        default => '#94a3b8',
    };
}, $status_stats);
$month_labels = array_map(fn($x) => date('M Y', strtotime($x['ym'] . '-01')), $monthly_points);
$month_data = array_map(fn($x) => (int)$x['cnt'], $monthly_points);
$reason_labels = array_map(fn($x) => $x['label'], $reason_stats);
$reason_data = array_map(fn($x) => (int)$x['count'], $reason_stats);
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-chart-bar mr-2 text-primary"></i>Counseling Reports</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><div class="text-xs text-gray-500">Total</div><div class="text-2xl font-bold text-gray-800"><?= $totals['all'] ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500"><div class="text-xs text-gray-500">Completed</div><div class="text-2xl font-bold text-gray-800"><?= $totals['completed'] ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500"><div class="text-xs text-gray-500">Pending</div><div class="text-2xl font-bold text-gray-800"><?= $totals['pending'] ?></div></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500"><div class="text-xs text-gray-500">Cancelled</div><div class="text-2xl font-bold text-gray-800"><?= $totals['cancelled'] ?></div></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Status Distribution</h3>
            <?php if (!empty($status_labels) && array_sum($status_data) > 0): ?>
                <div class="relative h-64">
                    <canvas id="statusChart"></canvas>
                </div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No status data yet.</div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Top Counseling Reasons</h3>
            <?php if (!empty($reason_labels) && array_sum($reason_data) > 0): ?>
                <div class="relative h-64">
                    <canvas id="reasonChart"></canvas>
                </div>
            <?php else: ?>
                <div class="h-64 flex items-center justify-center text-sm text-gray-400">No completed appointments with concerns yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold text-gray-800 mb-3">Monthly Trend (Last 6 Months)</h3>
        <?php if (!empty($month_labels) && array_sum($month_data) > 0): ?>
            <div class="relative h-56">
                <canvas id="monthChart"></canvas>
            </div>
        <?php else: ?>
            <div class="h-56 flex items-center justify-center text-sm text-gray-400">No activity in the last 6 months.</div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b"><h3 class="font-semibold text-gray-800">Counselor Workload (30 days)</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Counselor</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Completed</th><th class="px-4 py-3">Success Rate</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($counselor_summary)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No recent counselor activity.</td></tr>
                <?php else: foreach ($counselor_summary as $c): ?>
                    <tr class="hover:bg-gray-50"><td class="px-4 py-3 font-medium"><?= htmlspecialchars($c['name']) ?></td><td class="px-4 py-3"><?= $c['total'] ?></td><td class="px-4 py-3"><?= $c['completed'] ?></td><td class="px-4 py-3"><?= $c['rate'] ?>%</td></tr>
                <?php endforeach; endif; ?>
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
    initChart('statusChart', {
        type: 'pie',
        data: { labels: <?= json_encode($status_labels) ?>, datasets: [{ data: <?= json_encode($status_data) ?>, backgroundColor: <?= json_encode($status_colors) ?>, borderWidth: 1, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    initChart('reasonChart', {
        type: 'doughnut',
        data: { labels: <?= json_encode($reason_labels) ?>, datasets: [{ data: <?= json_encode($reason_data) ?>, backgroundColor: ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#14b8a6'], borderWidth: 1, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });

    initChart('monthChart', {
        type: 'line',
        data: { labels: <?= json_encode($month_labels) ?>, datasets: [{ label: 'Appointments', data: <?= json_encode($month_data) ?>, borderColor: '#163269', backgroundColor: 'rgba(22,50,105,0.12)', fill: true, tension: 0.35, pointRadius: 4, pointHoverRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
