<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "Debugging Counseling History\n";
echo str_repeat("=", 80) . "\n";

// Check total records in counseling_appointments
try {
    $stmt = $db->query("SELECT COUNT(*) FROM counseling_appointments");
    $total = $stmt->fetchColumn();
    echo "Total counseling_appointments: $total\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check with user join and archived filter
try {
    $stmt = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE (u.archived=0 OR u.archived IS NULL)");
    $total = $stmt->fetchColumn();
    echo "Total with archived filter: $total\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check sample data
try {
    $stmt = $db->query("SELECT ca.id, ca.user_id, ca.status, ca.appointment_date, u.first_name, u.last_name, u.archived FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSample data:\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']}, User: {$row['first_name']} {$row['last_name']}, Status: {$row['status']}, Archived: " . ($row['archived'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if there are any archived users with appointments
try {
    $stmt = $db->query("SELECT COUNT(*) FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id WHERE u.archived = 1");
    $archived_count = $stmt->fetchColumn();
    echo "\nAppointments from archived users: $archived_count\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check the actual AJAX query
try {
    $w = ["(u.archived=0 OR u.archived IS NULL)"];
    $where = implode(' AND ', $w);
    $c_query = "SELECT COUNT(*) as total FROM counseling_appointments ca JOIN users u ON ca.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE $where";
    $c_stmt = $db->prepare($c_query);
    $c_stmt->execute([]);
    $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nAJAX query count: $total\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
