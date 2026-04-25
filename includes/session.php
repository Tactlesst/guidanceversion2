<?php
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true, 'samesite' => 'Strict'
    ]);
    session_start();

    // 30 min inactivity timeout
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
        session_unset(); session_destroy(); session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();

    // Session hijacking prevention
    if (isset($_SESSION['user_id'])) {
        if (!isset($_SESSION['HTTP_USER_AGENT'])) {
            $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($_SESSION['HTTP_USER_AGENT'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            // User-Agent can change when switching device emulation (mobile/desktop) in the browser.
            // Instead of destroying the session, refresh the stored UA to avoid logging out.
            $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        // Optional additional signal: bind session to IP (best-effort; may change on some networks)
        if (!isset($_SESSION['IP_ADDRESS'])) {
            $_SESSION['IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'] ?? '';
        } elseif (($_SESSION['IP_ADDRESS'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            // If IP changes, keep the session but update the stored value.
            // (Hard logout here can be disruptive on networks with changing IPs.)
            $_SESSION['IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit();
        }
        header("Location: ../auth/login.php"); exit();
    }
}

function checkRole($required_roles) {
    if (!isset($_SESSION['role'])) { header("Location: ../auth/login.php"); exit(); }
    if (!is_array($required_roles)) $required_roles = [$required_roles];
    if ($_SESSION['role'] == 'super_admin') return true;
    if (!in_array($_SESSION['role'], $required_roles)) {
        header("Location: ../unauthorized.php"); exit();
    }
}

function getUserInfo() {
    return [
        'id' => $_SESSION['user_id'] ?? null, 'role' => $_SESSION['role'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? null, 'last_name' => $_SESSION['last_name'] ?? null,
        'position' => $_SESSION['position'] ?? null, 'student_id' => $_SESSION['student_id'] ?? null,
        'grade_level_applying' => $_SESSION['grade_level_applying'] ?? null
    ];
}

function isLoggedIn() { return isset($_SESSION['user_id']); }

function logout() { session_destroy(); header("Location: ../auth/login.php?logout=success"); exit(); }
?>
