<?php
require_once __DIR__ . '/../../../classes/EntranceExam.php';

$exam = new EntranceExam($db);

// AJAX endpoint for fetching passed examinees
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $program = $_GET['program'] ?? '';
        
        $w = ["e.status = 'completed'", "e.exam_result = 'passed'"];
        $p_arr = [];
        
        if ($date_from) { $w[] = "e.preferred_date >= ?"; $p_arr[] = $date_from; }
        if ($date_to) { $w[] = "e.preferred_date <= ?"; $p_arr[] = $date_to; }
        if ($program) { $w[] = "e.preferred_program = ?"; $p_arr[] = $program; }
        
        $where = implode(' AND ', $w);
        
        $stmt = $db->prepare("
            SELECT e.*, u.first_name, u.last_name, u.middle_name, u.email, u.phone,
                   c.first_name as confirmed_by_name, c.last_name as confirmed_by_lastname,
                   a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
            FROM entrance_exam_appointments e 
            JOIN users u ON e.user_id = u.id
            LEFT JOIN users c ON e.confirmed_by = c.id 
            LEFT JOIN users a ON e.assisted_by = a.id
            WHERE $where 
            ORDER BY e.preferred_date DESC, e.preferred_time DESC, u.last_name, u.first_name
        ");
        $stmt->execute($p_arr);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Get programs for filter
$programs = [];
try {
    $programs = $db->query("SELECT DISTINCT preferred_program FROM entrance_exam_appointments WHERE preferred_program IS NOT NULL AND preferred_program != '' ORDER BY preferred_program")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-clipboard-check mr-2 text-primary"></i>Passed Examinees Report</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Date From</label>
                <input type="date" id="dateFromFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchPassedExaminees()">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Date To</label>
                <input type="date" id="dateToFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchPassedExaminees()">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Program</label>
                <select id="programFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchPassedExaminees()">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= htmlspecialchars($prog) ?>"><?= htmlspecialchars($prog) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="printPassedExamineesReport()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">
                    <i class="fas fa-print mr-1"></i>Print
                </button>
            </div>
        </div>
    </div>

    <!-- Passed Examinees Report -->
    <div class="bg-white rounded-xl shadow-sm p-6" id="reportContainer">
        <div class="text-center mb-6 border-b-2 border-primary pb-4">
            <h2 class="text-xl font-bold text-gray-800">St. Rita's College of Balingasag</h2>
            <p class="text-xs text-gray-500">Guidance and Counseling Office</p>
            <h3 class="text-lg font-semibold text-gray-700 mt-2">Passed Examinees Report</h3>
            <p class="text-sm text-gray-500" id="reportSubtitle">All Passed Examinees</p>
            <p class="text-xs text-gray-400 mt-1" id="reportDate"></p>
        </div>

        <!-- Examinees Table -->
        <div id="examineesTable"></div>

        <!-- Grand Total -->
        <div class="mt-6 text-right">
            <span class="text-lg font-semibold text-gray-700">Total Passed: </span>
            <span class="text-lg font-bold text-primary" id="grandTotal">0</span>
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=passed_examinees_report';

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function fetchPassedExaminees() {
    const dateFrom = document.getElementById('dateFromFilter').value;
    const dateTo = document.getElementById('dateToFilter').value;
    const program = document.getElementById('programFilter').value;
    
    fetch(BASE + `&action=fetch&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&program=${encodeURIComponent(program)}`)
    .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
    })
    .then(data => {
        if (data.error) {
            console.error('Server error:', data.error);
            document.getElementById('examineesTable').innerHTML = '<div class="text-center text-red-500 py-4">Error: ' + esc(data.error) + '</div>';
            return;
        }
        
        const examineesTable = document.getElementById('examineesTable');
        examineesTable.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            examineesTable.innerHTML = '<div class="text-center text-gray-500 py-4">No passed examinees found</div>';
            document.getElementById('grandTotal').textContent = '0';
            return;
        }
        
        let html = `
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left border">No.</th>
                        <th class="px-4 py-2 text-left border">Name</th>
                        <th class="px-4 py-2 text-left border">Email</th>
                        <th class="px-4 py-2 text-left border">Phone</th>
                        <th class="px-4 py-2 text-left border">Exam Date</th>
                        <th class="px-4 py-2 text-left border">Score</th>
                        <th class="px-4 py-2 text-left border">Program Applying</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
        `;
        
        data.rows.forEach((examinee, index) => {
            const name = (examinee.last_name||'') + ', ' + (examinee.first_name||'') + (examinee.middle_name ? ' ' + examinee.middle_name : '');
            html += `
                <tr>
                    <td class="px-4 py-2 border">${index + 1}</td>
                    <td class="px-4 py-2 border">${esc(name)}</td>
                    <td class="px-4 py-2 border">${esc(examinee.email || '—')}</td>
                    <td class="px-4 py-2 border">${esc(examinee.phone || '—')}</td>
                    <td class="px-4 py-2 border">${examinee.preferred_date ? date('M d, Y', strtotime(examinee.preferred_date)) : '—'}</td>
                    <td class="px-4 py-2 border">${esc(examinee.exam_score || '—')}</td>
                    <td class="px-4 py-2 border">${esc(examinee.preferred_program || '—')}</td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        examineesTable.innerHTML = html;
        document.getElementById('grandTotal').textContent = data.rows.length;
        
        // Update subtitle
        let subtitle = 'All Passed Examinees';
        const filters = [];
        if (dateFrom) filters.push('From ' + dateFrom);
        if (dateTo) filters.push('To ' + dateTo);
        if (program) filters.push(program);
        if (filters.length > 0) subtitle = filters.join(' - ');
        document.getElementById('reportSubtitle').textContent = subtitle;
    })
    .catch(error => {
        console.error('Error fetching passed examinees:', error);
        document.getElementById('examineesTable').innerHTML = '<div class="text-center text-red-500 py-4">Error loading examinees. Please try again.</div>';
    });
}

function printPassedExamineesReport() {
    const dateFrom = document.getElementById('dateFromFilter').value;
    const dateTo = document.getElementById('dateToFilter').value;
    const program = document.getElementById('programFilter').value;
    
    fetch(BASE + `&action=fetch&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&program=${encodeURIComponent(program)}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        if (!data.rows || data.rows.length === 0) {
            Swal.fire('No Data', 'No passed examinees found for the selected filters.', 'warning');
            return;
        }
        
        // Build subtitle
        let subtitle = 'All Passed Examinees';
        const filters = [];
        if (dateFrom) filters.push('From ' + dateFrom);
        if (dateTo) filters.push('To ' + dateTo);
        if (program) filters.push(program);
        if (filters.length > 0) subtitle = filters.join(' - ');
        
        // Generate print HTML
        let printHTML = `
            <html>
            <head>
                <title>Passed Examinees Report</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                    .header h1 { font-size: 18px; font-weight: bold; margin: 0 0 5px 0; }
                    .header h2 { font-size: 14px; font-weight: bold; margin: 5px 0; }
                    .header p { font-size: 10px; color: #666; margin: 2px 0; }
                    .info { margin-bottom: 15px; font-size: 11px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 11px; }
                    th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                    .total { text-align: right; margin-top: 15px; font-size: 12px; font-weight: bold; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>St. Rita's College of Balingasag</h1>
                    <p>Guidance and Counseling Office</p>
                    <h2>Passed Examinees Report</h2>
                    <p>${subtitle}</p>
                    <p>As of ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th width="25%">Name</th>
                            <th width="20%">Email</th>
                            <th width="15%">Phone</th>
                            <th width="15%">Exam Date</th>
                            <th width="10%">Score</th>
                            <th width="10%">Program Applying</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.rows.forEach((examinee, index) => {
            const name = (examinee.last_name||'') + ', ' + (examinee.first_name||'') + (examinee.middle_name ? ' ' + examinee.middle_name : '');
            const examDate = examinee.preferred_date ? new Date(examinee.preferred_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
            
            printHTML += `
                <tr>
                    <td style="text-align: center;">${index + 1}</td>
                    <td>${name}</td>
                    <td>${examinee.email || '—'}</td>
                    <td>${examinee.phone || '—'}</td>
                    <td style="text-align: center;">${examDate}</td>
                    <td style="text-align: center;">${examinee.exam_score || '—'}</td>
                    <td>${examinee.preferred_program || '—'}</td>
                </tr>
            `;
        });
        
        printHTML += `
                    </tbody>
                </table>
                <div class="total">
                    Total Passed: ${data.rows.length}
                </div>
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.print();
    })
    .catch(error => {
        console.error('Error generating print view:', error);
        Swal.fire('Error', 'Failed to generate print view', 'error');
    });
}

// Initial load - show empty state
document.getElementById('reportDate').textContent = 'As of ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
document.getElementById('examineesTable').innerHTML = '<div class="text-center text-gray-500 py-8">Please select filters to view passed examinees</div>';
document.getElementById('grandTotal').textContent = '0';
</script>
