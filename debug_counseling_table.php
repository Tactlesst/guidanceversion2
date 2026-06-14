<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "Debugging Counseling Appointments Table Structure\n";
echo str_repeat("=", 80) . "\n";

// Check table structure
try {
    $stmt = $db->query("DESCRIBE counseling_appointments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTable columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check sample data
try {
    $stmt = $db->query("SELECT * FROM counseling_appointments LIMIT 3");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSample data:\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']}, User ID: {$row['user_id']}, Status: {$row['status']}, Date: {$row['appointment_date']}\n";
        echo "  Time: " . (isset($row['appointment_time']) ? $row['appointment_time'] : 'N/A') . "\n";
        echo "  Counselor ID: " . (isset($row['counselor_id']) ? $row['counselor_id'] : 'N/A') . "\n";
        echo "  Assigned Advocate ID: " . (isset($row['assigned_advocate_id']) ? $row['assigned_advocate_id'] : 'N/A') . "\n";
        echo "  Preferred Time: " . (isset($row['preferred_time']) ? $row['preferred_time'] : 'N/A') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
