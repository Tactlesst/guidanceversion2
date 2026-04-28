<?php
require_once __DIR__ . '/../classes/SystemLogger.php';

$logger = new SystemLogger($db);

// POST: clear logs
if ($_POST) {
    if (isset($_POST['clear_old_logs'])) {
        $days = (int)($_POST['days'] ?? 30);
        $logger->cleanOldLogs($days);
        $_SESSION['success_message'] = "Logs older than $days days cleared!";
        header("Location: layout.php?page=system_logs"); exit();
    }
    if (isset($_POST['clear_all_logs'])) {
        $db->prepare("DELETE FROM system_logs")->execute();
        $_SESSION['success_message'] = "All logs cleared!";
        header("Location: layout.php?page=system_logs"); exit();
    }
}

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Filters
$filter_type = $_GET['type'] ?? 'all';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_type !== 'all') { $where[] = "sl.log_type = ?"; $params[] = $filter_type; }
if ($filter_date) { $where[] = "DATE(sl.created_at) = ?"; $params[] = $filter_date; }
if ($search) { $where[] = "(sl.message LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $p = "%$search%"; $params[] = $p; $params[] = $p; $params[] = $p; }
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count
$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $where_clause");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $per_page);

// Get logs
$stmt = $db->prepare("SELECT sl.*, u.first_name, u.last_name, u.email FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $where_clause ORDER BY sl.created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary
$summary = $logger->getLogStats();

$log_colors = ['login'=>'blue','logout'=>'gray','warning'=>'yellow','error'=>'red','info'=>'cyan','success'=>'green','admin_action'=>'purple'];
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-file-alt mr-2 text-primary"></i>System Logs</h1>
        <div class="flex gap-2">
            <button onclick="openModal('clearLogsModal')" class="px-3 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600"><i class="fas fa-trash mr-1"></i>Clear Logs</button>
        </div>
    </div>

    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <?php if ($summary): foreach ($summary as $s): $c = $log_colors[$s['log_type']]??'gray'; ?>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center border-l-4 border-<?= $c ?>-500">
            <div class="text-xl font-bold text-gray-800"><?= $s['count'] ?></div>
            <div class="text-xs text-gray-500 capitalize"><?= str_replace('_',' ',$s['log_type']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <input type="hidden" name="page" value="system_logs">
            <select name="type" class="px-3 py-2 border rounded-lg text-sm">
                <option value="all">All Types</option>
                <?php foreach (['login','logout','warning','error','info','success','admin_action'] as $t): ?>
                <option value="<?= $t ?>" <?= $filter_type===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="px-3 py-2 border rounded-lg text-sm">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search logs..." class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Filter</button>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Message</th><th class="px-4 py-3">IP</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach ($logs as $l): $c = $log_colors[$l['log_type']]??'gray'; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap"><?= date('M d, Y g:i A', strtotime($l['created_at'])) ?></td>
                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars(($l['first_name']??'System').' '.($l['last_name']??'')) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-<?= $c ?>-100 text-<?= $c ?>-700 capitalize"><?= str_replace('_',' ',$l['log_type']) ?></span></td>
                    <td class="px-4 py-3 text-gray-600 max-w-[400px] truncate"><?= htmlspecialchars($l['message']) ?></td>
                    <td class="px-4 py-3 text-gray-400 text-xs"><?= htmlspecialchars($l['ip_address']??'—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No logs found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t flex justify-center gap-2">
            <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
            <a href="layout.php?page=system_logs&type=<?= $filter_type ?>&date=<?= $filter_date ?>&search=<?= urlencode($search) ?>&p=<?= $i ?>" class="px-3 py-1 rounded text-sm <?= $i==$page?'bg-primary text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Clear Logs Modal -->
<div id="clearLogsModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-red-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-trash mr-2"></i>Clear Logs</h3>
            <button onclick="closeModal('clearLogsModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <form method="POST" class="space-y-3">
                <input type="hidden" name="clear_old_logs" value="1">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Clear logs older than</label>
                    <select name="days" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option><option value="180">180 days</option>
                    </select>
                </div>
                <button type="submit" class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Clear Old Logs</button>
            </form>
            <hr>
            <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL logs? This cannot be undone.')">
                <input type="hidden" name="clear_all_logs" value="1">
                <button type="submit" class="w-full px-4 py-2 bg-red-500 text-white rounded-lg text-sm">Clear ALL Logs</button>
            </form>
        </div>
    </div>
</div>

<script>
// openModal/closeModal provided by shared.js
</script>
