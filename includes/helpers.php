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
?>
