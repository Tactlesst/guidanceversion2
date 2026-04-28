<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/SystemLogger.php';
require_once '../includes/session.php';

if (isLoggedIn()) { header("Location: ../dashboard/layout.php"); exit(); }

// --- Lockout helper ---
$MAX_ATTEMPTS = 3; $LOCKOUT_SEC = 60;

function getLockout() {
    global $MAX_ATTEMPTS, $LOCKOUT_SEC;
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $last = $_SESSION['last_attempt_time'] ?? 0;
    $expired = $attempts >= $MAX_ATTEMPTS && (time() - $last) >= $LOCKOUT_SEC;
    if ($expired) { $_SESSION['login_attempts'] = 0; $_SESSION['last_attempt_time'] = 0; $attempts = 0; }
    $remaining = max(0, $MAX_ATTEMPTS - $attempts);
    $lockout = ($attempts >= $MAX_ATTEMPTS && !$expired) ? max(1, $LOCKOUT_SEC - (time() - $last)) : 0;
    return [$attempts, $remaining, $lockout, $attempts < $MAX_ATTEMPTS || $expired];
}

list($attempts, $remaining_attempts, $lockout_time, $can_attempt) = getLockout();
$error_message = $_SESSION['login_error'] ?? '';
if (isset($_SESSION['login_error'])) unset($_SESSION['login_error']);

// --- Handle POST ---
if ($_POST && $can_attempt) {
    try {
        $db = (new Database())->getConnection();
        if (!$db) throw new Exception("DB connection failed");
        $user = new User($db);
        $logger = new SystemLogger($db);
    } catch (Exception $e) {
        $_SESSION['login_error'] = "Server error: Unable to connect to the database.";
        header("Location: login.php?error=1"); exit();
    }

    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($user->login($identifier, $password)) {
        // Fetch full user data
        $stmt = $db->prepare("SELECT u.id, u.email, u.role, u.first_name, u.last_name, u.position, u.grade_level_applying, sp.student_id, sp.grade_level
                              FROM users u LEFT JOIN student_profiles sp ON u.id = sp.user_id
                              WHERE (u.email = ? OR sp.student_id = ?) AND u.is_active = 1 AND (u.archived = 0 OR u.archived IS NULL)");
        $stmt->execute([$identifier, $identifier]);
        $ud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ud) {
            $_SESSION['login_error'] = "Login failed. Please try again.";
            header("Location: login.php?error=1"); exit();
        }

        session_regenerate_id(true);
        $_SESSION = array_merge($_SESSION, [
            'user_id' => $ud['id'], 'user_email' => $ud['email'], 'user_role' => $ud['role'],
            'role' => $ud['role'], 'user_name' => $ud['first_name'] . ' ' . $ud['last_name'],
            'first_name' => $ud['first_name'], 'last_name' => $ud['last_name'],
            'position' => $ud['position'], 'student_id' => $ud['student_id'],
            'grade_level_applying' => $ud['grade_level_applying'],
            'login_attempts' => 0, 'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_success' => true, 'welcome_name' => $ud['first_name'],
            'user_role_display' => ucfirst($ud['role'])
        ]);
        $logger->login($ud['id'], ['email' => $ud['email'], 'role' => $ud['role'], 'login_method' => 'web']);
        $show_success = true;
    } else {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt_time'] = time();
        $rem = max(0, $MAX_ATTEMPTS - $_SESSION['login_attempts']);

        try {
            // Check if account exists but is archived/inactive
            $chk = $db->prepare("SELECT u.is_active, u.archived FROM users u LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE (u.email = ? OR sp.student_id = ?)");
            $chk->execute([$identifier, $identifier]);
            if ($chk->rowCount() > 0) {
                $s = $chk->fetch(PDO::FETCH_ASSOC);
                $error_message = ($s['archived'] ?? 0) == 1 ? "Account archived. Contact admin." :
                                ($s['is_active'] == 0 ? "Account inactive. Contact admin." :
                                ($rem > 0 ? "Invalid password. {$rem} attempt(s) remaining." : "Too many attempts. Wait {$LOCKOUT_SEC}s."));
            } else {
                $error_message = $rem > 0 ? "Account not found. {$rem} attempt(s) remaining." : "Too many attempts. Wait {$LOCKOUT_SEC}s.";
            }
        } catch (Exception $e) {
            $error_message = $rem > 0 ? "Invalid credentials. {$rem} attempt(s) remaining." : "Too many attempts. Wait {$LOCKOUT_SEC}s.";
        }
        $_SESSION['login_error'] = $error_message;
        $logger->warning("Failed login for: {$identifier}", null, ['identifier' => $identifier, 'attempt' => $_SESSION['login_attempts']]);
        header("Location: login.php?error=1"); exit();
    }
} elseif ($_POST && !$can_attempt) {
    $_SESSION['login_error'] = "Too many attempts. Wait {$lockout_time}s.";
    header("Location: login.php?error=1"); exit();
}

list($attempts, $remaining_attempts, $lockout_time, $can_attempt) = getLockout();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SRCB Guidance Management System</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/srcblogo.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#163269', 'primary-dark': '#3a56c4', secondary: '#002fff', accent: '#2630be'
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-50 p-5 relative">
    <!-- Background decoration -->
    <div class="fixed inset-0 pointer-events-none" style="background: radial-gradient(circle at 20% 80%, rgba(120,119,198,0.3) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%)"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl p-10 border border-white/20 hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-gradient-to-br from-primary to-secondary rounded-full opacity-[0.08] animate-pulse"></div>
            <div class="absolute -bottom-14 -left-14 w-40 h-40 bg-gradient-to-br from-secondary to-accent rounded-full opacity-[0.06] animate-pulse"></div>

            <!-- Logo -->
            <div class="text-center mb-6 relative z-10">
                <div class="relative inline-block mb-4">
                    <div class="absolute w-20 h-20 bg-gradient-to-br from-primary to-secondary rounded-full -top-1 left-1/2 -translate-x-1/2 opacity-10"></div>
                    <img src="../assets/images/srcblogo.png" alt="Logo" class="w-[70px] h-[70px] rounded-full shadow-md p-[3px] bg-white border border-gray-100 relative z-10 object-cover"
                         onload="document.getElementById('temp-logo').style.display='none';"
                         onerror="this.style.display='none'; document.getElementById('temp-logo').style.display='flex';">
                    <div id="temp-logo" class="hidden w-[70px] h-[70px] bg-gradient-to-br from-primary to-secondary rounded-full flex-col items-center justify-center text-white font-bold text-xs leading-tight p-1.5 mx-auto shadow-lg relative z-10">
                        <div>St. Rita's</div><div>College</div>
                    </div>
                </div>
                <h1 class="text-2xl font-extrabold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent tracking-tight">St. Rita's College of Balingasag</h1>
                <p class="text-gray-400 text-sm font-medium">Guidance Management System</p>
            </div>

            <!-- Error alert -->
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-600 rounded-xl px-4 py-3 mb-4 flex items-center justify-between text-sm">
                    <span><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="POST" id="loginForm" <?= $lockout_time > 0 ? 'data-locked="true"' : '' ?> data-lockout="<?= $lockout_time ?>">
                <div class="mb-4 relative">
                    <input type="text" id="identifier" name="identifier" placeholder=" " required maxlength="100" minlength="3"
                           class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                    <label for="identifier" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Email or Student ID</label>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"><i class="fas fa-user"></i></span>
                </div>

                <div class="mb-4 relative">
                    <input type="password" id="password" name="password" placeholder=" " required maxlength="128" minlength="8"
                           class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                    <label for="password" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Password</label>
                    <span id="passwordToggle" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer hover:text-primary transition-colors z-10"><i class="fas fa-eye"></i></span>
                </div>

                <button type="submit" id="loginBtn" class="w-full py-4 rounded-xl font-bold text-white uppercase tracking-wider transition-all duration-300 relative overflow-hidden shadow-[0_4px_15px_rgba(74,107,223,0.3)] hover:-translate-y-1 hover:shadow-[0_8px_25px_rgba(74,107,223,0.4)] bg-gradient-to-r from-primary to-secondary hover:from-primary-dark hover:to-primary disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed disabled:opacity-60 disabled:translate-y-0 disabled:shadow-none" <?= $lockout_time > 0 ? 'disabled' : '' ?>>
                    <?php if ($lockout_time > 0): ?>
                        <span id="loginBtnText">Locked (<?= $lockout_time ?>s)</span>
                    <?php else: ?>
                        <i class="fas fa-sign-in-alt mr-2"></i><span id="loginBtnText">Login to Account</span>
                    <?php endif; ?>
                </button>

                <?php if ($remaining_attempts < 3 && $lockout_time == 0): ?>
                    <div class="bg-yellow-50 text-yellow-700 rounded-lg mt-2 p-2 text-xs flex items-center">
                        <i class="fas fa-exclamation-triangle mr-1"></i><strong>Warning:</strong>&nbsp;You have <?= $remaining_attempts ?> login attempt(s) remaining.
                    </div>
                <?php endif; ?>
            </form>

            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-gray-400 hover:text-primary hover:bg-primary/5 transition-all text-sm px-3 py-1.5 rounded-md"><i class="fas fa-key mr-1"></i>Forgot Password?</a>
            </div>

            <div class="flex items-center my-5 text-gray-400 text-xs"><span class="flex-1 h-px bg-gray-200 mx-2"></span>OR<span class="flex-1 h-px bg-gray-200 mx-2"></span></div>

            <div class="text-center space-y-2 mb-3">
                <a href="../homepage.php" class="block text-primary font-semibold hover:text-accent transition-colors text-xs"><i class="fas fa-house mr-1"></i>View Announcements Homepage</a>
                <a href="register.php" class="block text-primary font-semibold hover:text-accent transition-colors text-xs"><i class="fas fa-user-plus mr-1"></i>Register as Examinee (New Student / Transferee)</a>
            </div>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3.5 border-l-3 border-primary">
                <p class="text-xs text-gray-700 leading-relaxed mb-0"><i class="fas fa-info-circle mr-1.5"></i>For SRCB students, use your student credentials. For entrance exam applicants, please register first.</p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('loginBtnText');

        // Logout success alert
        <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
            Swal.fire({ icon: 'success', title: 'Logged Out Successfully', confirmButtonColor: '#163269', timer: 3000, timerProgressBar: true })
                .then(() => { history.replaceState({}, document.title, location.pathname); });
        <?php endif; ?>

        // Login success alert
        <?php if (isset($show_success)): ?>
            <?php $wn = htmlspecialchars($_SESSION['welcome_name'] ?? 'User'); $ur = htmlspecialchars($_SESSION['user_role_display'] ?? 'User'); unset($_SESSION['login_success'], $_SESSION['welcome_name'], $_SESSION['user_role_display']); ?>
            setTimeout(() => {
                Swal.fire({
                    icon: 'success', title: 'Welcome Back!',
                    html: `<div style="text-align:center;margin:15px 0"><h4 style="color:#163269;margin-bottom:10px;font-weight:600">Hello, <?= $wn ?>!</h4><p style="color:#718096;font-size:15px;margin:0">Logged in as <?= $ur ?></p><p style="color:#4ade80;font-size:13px;margin-top:8px;font-weight:500">Redirecting to dashboard...</p></div>`,
                    showConfirmButton: true, confirmButtonText: 'Continue to Dashboard', confirmButtonColor: '#163269',
                    timer: 3000, timerProgressBar: true,
                    willClose: () => { location.href = '../dashboard/layout.php'; }
                }).then((result) => {
                    if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                        location.href = '../dashboard/layout.php';
                    }
                });
            }, 300);
            return;
        <?php endif; ?>

        // Password toggle
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const pw = document.getElementById('password');
            const icon = this.querySelector('i');
            pw.type = pw.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye'); icon.classList.toggle('fa-eye-slash');
        });

        // Lockout countdown
        const lockout = parseInt(form.dataset.lockout || 0);
        if (lockout > 0) {
            let sec = lockout;
            const errAlert = document.querySelector('.bg-red-50');
            const cd = setInterval(() => {
                sec--;
                if (sec > 0) { btnText.textContent = `Locked (${sec}s)`; }
                else { clearInterval(cd); loginBtn.disabled = false; btnText.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Login to Account'; if (errAlert) errAlert.style.display = 'none'; setTimeout(() => location.reload(), 500); }
            }, 1000);
        }

        // Form submit
        form.addEventListener('submit', function(e) {
            if (form.dataset.locked || loginBtn.disabled) { e.preventDefault(); return; }
            if (!document.getElementById('identifier').value.trim() || !document.getElementById('password').value.trim()) { e.preventDefault(); alert('Please fill in all fields'); return; }
            loginBtn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging in...';
            Swal.fire({ title: 'Authenticating...', html: 'Please wait while we verify your credentials', allowEscapeKey: false, allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        });
    });
    </script>
</body>
</html>
