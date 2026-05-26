<?php
// Shared PHP helpers — include once per page via layout.php

function sanitize($value) {
    if ($value === null || $value === '') return null;
    return htmlspecialchars(strip_tags(trim($value)));
}

function fetchSessionMessages() {
    $success = ''; $error = '';
    $keys = ['success_message','error_message','assign_success','complete_success',
              'cancel_success','missed_success','followup_success','form_success','settings_success'];
    foreach ($keys as $k) {
        if (isset($_SESSION[$k])) { $success = $_SESSION[$k]; unset($_SESSION[$k]); }
        if (isset($_SESSION[$k.'_message'])) { $success = $_SESSION[$k.'_message']; unset($_SESSION[$k.'_message']); }
    }
    if (isset($_SESSION['error_message'])) { $error = $_SESSION['error_message']; unset($_SESSION['error_message']); }
    return ['success' => $success, 'error' => $error];
}

function renderAlerts($success, $error) {
    $html = '';
    if ($success) $html .= '<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-check-circle mr-2"></i>'.htmlspecialchars($success).'</div>';
    if ($error)   $html .= '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center"><i class="fas fa-exclamation-circle mr-2"></i>'.htmlspecialchars($error).'</div>';
    return $html;
}

function logAdminAction($action, $details = '', $user_id = null, $db = null) {
    try {
        if (!$db) {
            $database = new Database();
            $db = $database->getConnection();
        }
        
        if (!$user_id) {
            $user_id = $_SESSION['user_id'] ?? null;
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Get user info
        $stmt = $db->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        $user_name = $user['first_name'] . ' ' . $user['last_name'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Log the action with the correct table structure
        $stmt = $db->prepare("INSERT INTO system_logs (user_id, log_type, message, ip_address, created_at) VALUES (?, 'admin_action', ?, ?, NOW())");
        $message = "$action: $details (User: $user_name)";
        $stmt->execute([$user_id, $message, $ip_address]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}
?>
