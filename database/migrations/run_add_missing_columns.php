<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "Adding missing columns to counseling_appointments...\n\n";

// Add concern_type if not exists
try {
    $stmt = $db->query("SHOW COLUMNS FROM counseling_appointments LIKE 'concern_type'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE counseling_appointments ADD COLUMN concern_type ENUM('academic', 'personal', 'behavioral', 'career', 'family', 'other') DEFAULT 'other' AFTER appointment_time");
        echo "[+] Added concern_type column\n";
    } else {
        echo "[OK] concern_type column already exists\n";
    }
} catch (Exception $e) {
    echo "[ERROR] concern_type: " . $e->getMessage() . "\n";
}

// Add urgency_level if not exists
try {
    $stmt = $db->query("SHOW COLUMNS FROM counseling_appointments LIKE 'urgency_level'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE counseling_appointments ADD COLUMN urgency_level ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER concern_type");
        echo "[+] Added urgency_level column\n";
    } else {
        echo "[OK] urgency_level column already exists\n";
    }
} catch (Exception $e) {
    echo "[ERROR] urgency_level: " . $e->getMessage() . "\n";
}

// Add 'pending' to status ENUM if not present
try {
    $stmt = $db->query("SHOW COLUMNS FROM counseling_appointments LIKE 'status'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_type = $row['Type'];
    if (strpos($current_type, 'pending') === false) {
        $db->exec("ALTER TABLE counseling_appointments MODIFY COLUMN status ENUM('pending','confirmed','in_progress','completed','cancelled','rescheduled','missed','follow_up_scheduled') DEFAULT 'pending'");
        echo "[+] Added 'pending' to status ENUM\n";
    } else {
        echo "[OK] 'pending' already in status ENUM\n";
    }
} catch (Exception $e) {
    echo "[ERROR] status ENUM: " . $e->getMessage() . "\n";
}

echo "\nDone! Verifying columns...\n";
$stmt = $db->query('SHOW COLUMNS FROM counseling_appointments');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
