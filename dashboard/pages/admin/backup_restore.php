<?php
/**
 * Backup & Restore Page
 * Super Admin only - Database backup and restore functionality
 */

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Handle backup/restore actions
if ($_POST) {
    if (isset($_POST['create_backup'])) {
        // TODO: Implement backup functionality
        $_SESSION['success_message'] = "Backup feature coming soon!";
        header("Location: layout.php?page=backup_restore");
        exit();
    }
    if (isset($_POST['restore_backup'])) {
        // TODO: Implement restore functionality
        $_SESSION['success_message'] = "Restore feature coming soon!";
        header("Location: layout.php?page=backup_restore");
        exit();
    }
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

    <!-- Backup History (Placeholder) -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fas fa-history mr-2 text-blue-500"></i>Backup History
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-database fa-3x mb-3 opacity-30"></i>
                <p class="text-sm">No backup history available yet</p>
                <p class="text-xs mt-1">Backups will appear here once created</p>
            </div>
        </div>
    </div>

    <!-- Coming Soon Notice -->
    <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-xl p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-rocket text-purple-600 text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-800 mb-2">Feature Under Development</h3>
                <p class="text-sm text-gray-600 mb-3">The backup and restore functionality is currently being developed. This feature will include:</p>
                <ul class="text-sm text-gray-600 space-y-1 ml-4">
                    <li><i class="fas fa-check text-green-500 mr-2"></i>Automated daily backups</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i>One-click manual backups</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i>Backup history and management</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i>Selective restore options</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i>Cloud backup integration</li>
                </ul>
            </div>
        </div>
    </div>
</div>
