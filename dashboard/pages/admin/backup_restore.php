<?php
/**
 * Backup & Restore Page
 * Super Admin only - Database backup and restore functionality
 */

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Backup directory
$backup_dir = __DIR__ . '/../../../backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup/restore actions
if ($_POST) {
    if (isset($_POST['create_backup'])) {
        try {
            // Get database connection (Database class already loaded)
            $database = new Database();
            $conn = $database->getConnection();
            
            // Get database name from connection
            $db_name = $conn->query("SELECT DATABASE()")->fetchColumn();
            
            // Create backup filename with timestamp
            $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Use mysqldump to create backup
            $host = 'localhost';
            $username = 'root';
            $password = '';
            
            $command = "mysqldump --host={$host} --user={$username} --password={$password} {$db_name} > {$backup_file} 2>&1";
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($backup_file)) {
                $_SESSION['success_message'] = "Backup created successfully: " . basename($backup_file);
                logAdminAction('create_backup', "Created backup: " . basename($backup_file), null, $conn);
            } else {
                // Fallback to PHP-based backup if mysqldump fails
                $backup_file = createPHPBackup($conn, $backup_dir);
                if ($backup_file) {
                    $_SESSION['success_message'] = "Backup created successfully: " . basename($backup_file);
                    logAdminAction('create_backup', "Created backup: " . basename($backup_file), null, $conn);
                } else {
                    $_SESSION['error_message'] = "Failed to create backup. Please check server permissions.";
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Backup failed: " . $e->getMessage();
        }
        header("Location: layout.php?page=backup_restore");
        exit();
    }
    
    if (isset($_POST['restore_backup'])) {
        try {
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error_message'] = "Please select a valid backup file.";
                header("Location: layout.php?page=backup_restore");
                exit();
            }
            
            $uploaded_file = $_FILES['backup_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_ext !== 'sql') {
                $_SESSION['error_message'] = "Only .sql files are allowed.";
                header("Location: layout.php?page=backup_restore");
                exit();
            }
            
            // Get database connection
            $database = new Database();
            $conn = $database->getConnection();
            
            // Read and execute SQL file
            $sql = file_get_contents($uploaded_file);
            if ($sql === false) {
                throw new Exception("Failed to read backup file.");
            }
            
            // Split SQL into individual statements
            $statements = explode(';', $sql);
            $conn->beginTransaction();
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $conn->exec($statement);
                    } catch (PDOException $e) {
                        // Log error but continue with other statements
                        error_log("SQL statement error: " . $e->getMessage());
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Database restored successfully!";
            logAdminAction('restore_backup', "Restored: " . $_FILES['backup_file']['name'], null, $conn);
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Restore failed: " . $e->getMessage();
        }
        header("Location: layout.php?page=backup_restore");
        exit();
    }
    
    if (isset($_POST['delete_backup'])) {
        $backup_file = $_POST['backup_file'];
        $file_path = $backup_dir . '/' . basename($backup_file);
        
        if (file_exists($file_path) && unlink($file_path)) {
            $_SESSION['success_message'] = "Backup deleted successfully.";
            $database = new Database();
            $conn = $database->getConnection();
            logAdminAction('delete_backup', "Deleted backup: " . basename($backup_file), null, $conn);
        } else {
            $_SESSION['error_message'] = "Failed to delete backup file.";
        }
        header("Location: layout.php?page=backup_restore");
        exit();
    }
}

// Function to create backup using PHP (fallback)
function createPHPBackup($conn, $backup_dir) {
    try {
        $db_name = $conn->query("SELECT DATABASE()")->fetchColumn();
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $fp = fopen($backup_file, 'w');
        
        // Get all tables
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            fwrite($fp, "-- Table: $table\n");
            fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
            
            // Get create table statement
            $create_table = $conn->query("SHOW CREATE TABLE `$table`")->fetch();
            fwrite($fp, $create_table['Create Table'] . ";\n\n");
            
            // Get table data
            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columns_str = '`' . implode('`,`', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = array_map(function($val) {
                        if ($val === null) return 'NULL';
                        if (is_numeric($val)) return $val;
                        return "'" . addslashes($val) . "'";
                    }, $row);
                    $values_str = implode(',', $values);
                    fwrite($fp, "INSERT INTO `$table` ($columns_str) VALUES ($values_str);\n");
                }
                fwrite($fp, "\n");
            }
        }
        
        fclose($fp);
        return $backup_file;
    } catch (Exception $e) {
        error_log("PHP backup failed: " . $e->getMessage());
        return false;
    }
}

// Get backup files
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . '/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }
    // Sort by date descending
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-database mr-2 text-primary"></i>Backup & Restore
        </h1>
    </div>

    <!-- Alerts -->
    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Info Alert -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
            <div>
                <h3 class="font-semibold text-blue-900 mb-1">Database Backup & Restore</h3>
                <p class="text-sm text-blue-700">Create backups of your database to protect your data. Restore from previous backups if needed.</p>
            </div>
        </div>
    </div>

    <!-- Backup Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fas fa-save mr-2 text-green-500"></i>Create Backup
            </h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-4">Create a complete backup of the database. This includes all users, appointments, schedules, and system data.</p>
            <form method="POST">
                <input type="hidden" name="create_backup" value="1">
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
                    <i class="fas fa-download mr-2"></i>Create Backup Now
                </button>
            </form>
        </div>
    </div>

    <!-- Restore Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fas fa-upload mr-2 text-yellow-500"></i>Restore from Backup
            </h3>
        </div>
        <div class="p-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                    <div>
                        <h4 class="font-semibold text-yellow-900 mb-1">Warning</h4>
                        <p class="text-sm text-yellow-700">Restoring from a backup will replace all current data. This action cannot be undone. Make sure to create a backup of the current state before restoring.</p>
                    </div>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="restore_backup" value="1">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Backup File</label>
                    <input type="file" name="backup_file" accept=".sql,.zip" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: .sql, .zip</p>
                </div>
                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600 transition-colors" onclick="return confirm('Are you sure you want to restore from this backup? This will replace all current data.')">
                    <i class="fas fa-upload mr-2"></i>Restore from Backup
                </button>
            </form>
        </div>
    </div>

    <!-- Backup History -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fas fa-history mr-2 text-blue-500"></i>Backup History
            </h3>
        </div>
        <div class="p-6">
            <?php if (empty($backup_files)): ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-database fa-3x mb-3 opacity-30"></i>
                    <p class="text-sm">No backup history available yet</p>
                    <p class="text-xs mt-1">Backups will appear here once created</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-3 font-medium text-gray-700">File Name</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-700">Size</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-700">Date Created</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $backup): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-2 px-3 text-gray-800"><?= htmlspecialchars($backup['name']) ?></td>
                                    <td class="py-2 px-3 text-gray-600"><?= formatFileSize($backup['size']) ?></td>
                                    <td class="py-2 px-3 text-gray-600"><?= $backup['date'] ?></td>
                                    <td class="py-2 px-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="backups/<?= htmlspecialchars($backup['name']) ?>" download class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                <i class="fas fa-download mr-1"></i>Download
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" name="delete_backup" value="1" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}
?>
