<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "Counseling Appointments with Status 'confirmed':\n";
echo "=========================================\n";

$query = "SELECT c.id, c.user_id, c.assigned_advocate_id, c.status, 
                 u.first_name, u.last_name, 
                 adv.first_name as advocate_first_name, adv.last_name as advocate_last_name
          FROM counseling_appointments c
          JOIN users u ON c.user_id = u.id
          LEFT JOIN users adv ON c.assigned_advocate_id = adv.id
          WHERE c.status = 'confirmed'
          ORDER BY c.created_at DESC LIMIT 10";

$stmt = $db->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "ID: {$row['id']}, User: {$row['first_name']} {$row['last_name']}, ";
    echo "Assigned Advocate ID: " . ($row['assigned_advocate_id'] ?? 'NULL') . ", ";
    echo "Advocate Name: " . (($row['advocate_first_name'] ?? '') . ' ' . ($row['advocate_last_name'] ?? '')) . "\n";
}

if (empty($results)) {
    echo "No confirmed appointments found\n";
}
