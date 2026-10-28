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
        header("Location: " . portalSaveRedirect("layout.php?page=backup_restore"));
        exit();
    }
    
    if (isset($_POST['restore_backup'])) {
        try {
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error_message'] = "Please select a valid backup file.";
                header("Location: " . portalSaveRedirect("layout.php?page=backup_restore"));
                exit();
            }
            
            $uploaded_file = $_FILES['backup_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_ext !== 'sql') {
                $_SESSION['error_message'] = "Only .sql files are allowed.";
                header("Location: " . portalSaveRedirect("layout.php?page=backup_restore"));
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
        header("Location: " . portalSaveRedirect("layout.php?page=backup_restore"));
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
        header("Location: " . portalSaveRedirect("layout.php?page=backup_restore"));
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

<section class="sa-page" aria-labelledby="sa-backup-title">
    <div class="sa-page-heading"><div><p class="sa-eyebrow">Protect your data</p><h1 id="sa-backup-title">Backup & restore</h1><p class="sa-subtitle">Save a copy of your records, download an existing backup, or restore an earlier copy.</p></div><span class="sa-role-label">Superadmin access</span></div>
    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <div class="sa-task-grid">
        <section class="sa-panel sa-backup-create"><div class="sa-panel-title"><div class="sa-task-title"><span class="sa-choice-icon"><i class="fas fa-database" aria-hidden="true"></i></span><h2>Create a backup</h2></div></div><div class="sa-panel-body"><p class="sa-task-description">Save the current database to a backup file on this computer. Your users, appointments, and school settings stay in place.</p><form data-portal-save method="post"><input type="hidden" name="create_backup" value="1"><button class="sa-button primary" type="submit"><i class="fas fa-plus" aria-hidden="true"></i>Create backup file</button></form><p class="sa-backup-detail">After creating a backup, use Download in the list below to keep a copy.</p></div></section>
        <section class="sa-panel sa-backup-restore"><div class="sa-panel-title"><div class="sa-task-title"><span class="sa-choice-icon"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i></span><h2>Restore an earlier backup</h2></div></div><div class="sa-panel-body"><p class="sa-task-description">Use a saved SQL backup when you need to recover older records.</p><details class="sa-restore-options"><summary>Show restore options</summary><form data-portal-save method="post" enctype="multipart/form-data" data-confirm-title="Restore this backup?" data-confirm-message="This can replace current records. Create and download a current backup before restoring an earlier one." data-confirm-label="Restore selected backup"><input type="hidden" name="restore_backup" value="1"><div class="sa-notice"><strong>This can replace current records</strong>Create and download a current backup before restoring an older one.</div><label class="sa-field">Choose a backup file<input type="file" name="backup_file" accept=".sql" required><small>SQL files only (.sql).</small></label><button class="sa-button amber" type="submit"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Restore selected backup</button></form></details></div></section>
    </div>
    <section class="sa-panel" style="margin-top:24px"><div class="sa-panel-title"><div><h2>Saved backup files</h2><p><?=count($backup_files)?> <?=count($backup_files)===1?'backup available':'backups available'?> · Most recent first</p></div></div><div class="sa-table-wrap"><table class="sa-table"><caption class="um-sr-only" style="position:absolute;clip:rect(0,0,0,0)">Saved backups</caption><thead><tr><th>Backup file</th><th>File size</th><th>Created</th><th style="text-align:right">Actions</th></tr></thead><tbody>
    <?php foreach($backup_files as $backup):?><tr><td class="sa-record-name" data-label="Backup file"><strong><?=htmlspecialchars($backup['name'])?></strong><small>SQL database backup</small></td><td data-label="File size"><?=formatFileSize($backup['size'])?></td><td data-label="Created"><?=date('M j, Y · g:i A',strtotime($backup['date']))?></td><td class="sa-actions"><div class="sa-row-actions"><a class="sa-button" href="../backups/<?=rawurlencode($backup['name'])?>" download><i class="fas fa-download" aria-hidden="true"></i>Download</a><form data-portal-save method="post" data-confirm-title="Delete this backup file?" data-confirm-message="This permanently removes the saved file. Current database records stay in place." data-confirm-label="Delete backup file"><input type="hidden" name="backup_file" value="<?=htmlspecialchars($backup['name'],ENT_QUOTES)?>"><button class="sa-button danger" type="submit" name="delete_backup" value="1"><i class="far fa-trash-can" aria-hidden="true"></i>Delete file</button></form></div></td></tr><?php endforeach;?>
    <?php if(!$backup_files):?><tr><td colspan="4" class="sa-empty"><strong>No backup files yet</strong><p>Create your first backup using the button above.</p></td></tr><?php endif;?>
    </tbody></table></div></section>
</section>

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
