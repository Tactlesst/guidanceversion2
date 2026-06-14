<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "Testing Counseling History AJAX Endpoint\n";
echo str_repeat("=", 80) . "\n";

// Simulate AJAX request parameters
$pg = 1;
$per = 10;
$off = ($pg - 1) * $per;
$q = '';
$status = '';
$grade_level = '';
$date_from = '';
$date_to = '';
$sort = 'latest';

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
echo "Total records: $total\n";

// Fetch page
$order_by = $sort === 'latest' ? 'ca.appointment_date DESC, ca.appointment_time DESC' : 'ca.appointment_date ASC, ca.appointment_time ASC';
$f_query = "SELECT ca.id, ca.user_id, ca.appointment_date, ca.appointment_time, ca.status, ca.appointment_type, ca.concern, ca.remarks, ca.assigned_advocate_id,
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

echo "Fetched rows: " . count($rows) . "\n";
echo "\nSample data:\n";
foreach ($rows as $row) {
    echo "ID: {$row['id']}, Student: {$row['first_name']} {$row['last_name']}, Status: {$row['status']}\n";
}

echo "\nJSON output:\n";
echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => (int)$per, 'page' => (int)$pg]);
?>
