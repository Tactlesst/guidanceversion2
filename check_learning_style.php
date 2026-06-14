<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "Multiple Intelligence Survey Table Structure:\n";
echo "=========================================\n";

$stmt = $db->query('DESCRIBE multiple_intelligence_survey');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n\nSample Data:\n";
echo "=========================================\n";

$stmt = $db->query('SELECT * FROM multiple_intelligence_survey LIMIT 1');
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($row as $key => $value) {
        echo "$key => $value\n";
    }
} else {
    echo "No data found\n";
}
