<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Check for superadmin accounts
    $stmt = $db->prepare("SELECT id, email, first_name, last_name, role, is_active, archived FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $superadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Superadmin Accounts in Database</h2>";
    
    if (empty($superadmins)) {
        echo "<p style='color:red;'>No superadmin accounts found!</p>";
        echo "<p>You need to create one. Here's a SQL command to create one:</p>";
        echo "<pre>";
        echo "INSERT INTO users (email, password, role, first_name, last_name, is_active, archived, created_at) 
VALUES ('admin@srcb.edu.ph', '\$2y\$10\$YourHashedPasswordHere', 'super_admin', 'Super', 'Admin', 1, 0, NOW());";
        echo "</pre>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Active</th><th>Archived</th></tr>";
        foreach ($superadmins as $sa) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sa['id']) . "</td>";
            echo "<td>" . htmlspecialchars($sa['email']) . "</td>";
            echo "<td>" . htmlspecialchars($sa['first_name'] . ' ' . $sa['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($sa['role']) . "</td>";
            echo "<td>" . ($sa['is_active'] ? "Yes" : "No") . "</td>";
            echo "<td>" . ($sa['archived'] ? "Yes" : "No") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check all users
    echo "<h2>All Users in Database</h2>";
    $stmt = $db->prepare("SELECT id, email, role, first_name, last_name, is_active, archived FROM users ORDER BY role, email");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Name</th><th>Active</th><th>Archived</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($u['id']) . "</td>";
        echo "<td>" . htmlspecialchars($u['email']) . "</td>";
        echo "<td>" . htmlspecialchars($u['role']) . "</td>";
        echo "<td>" . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . "</td>";
        echo "<td>" . ($u['is_active'] ? "Yes" : "No") . "</td>";
        echo "<td>" . ($u['archived'] ? "Yes" : "No") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
