<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/SystemSettings.php';
require_once '../includes/session.php';

if (isLoggedIn()) { header("Location: ../dashboard/layout.php"); exit(); }

$success_message = '';
$error_message = '';

try {
    $db = (new Database())->getConnection();
    if (!$db) throw new Exception('DB connection failed');
    $settings = new SystemSettings($db);
} catch (Exception $e) {
    $db = null;
    $settings = null;
    $error_message = 'Server error: Unable to connect to the database.';
}

if ($settings && !$settings->isEntranceExamEnabled()) {
    $error_message = 'Entrance exam registration is currently disabled. Please contact the guidance office.';
}

$normalize_person_name = function($value) {
    $value = preg_replace('/\s+/', ' ', trim((string)$value));
    if ($value === '') return '';
    if (function_exists('mb_convert_case')) return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    return ucwords(strtolower($value));
};

if ($_POST && $db && $settings && $settings->isEntranceExamEnabled()) {
    try {
        $first_name = $normalize_person_name($_POST['first_name'] ?? '');
        $middle_name = $normalize_person_name($_POST['middle_name'] ?? '');
        $last_name = $normalize_person_name($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $grade_level_applying = trim($_POST['grade_level_applying'] ?? '');
        $student_type = $_POST['student_type'] ?? '';

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm === '' || $grade_level_applying === '' || $student_type === '') {
            throw new Exception('Please fill in all required fields.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email.');
        }
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            throw new Exception('Passwords do not match.');
        }

        $examinee_type = $student_type === 'new' ? 'New Student' : ($student_type === 'transfer' ? 'Transferee' : null);
        if (!$examinee_type) throw new Exception('Invalid student type.');

        // Prevent duplicate examinee/student by name (best-effort)
        $name_check = $db->prepare("SELECT id FROM users WHERE role IN ('examinee','student') AND LOWER(TRIM(first_name)) = LOWER(TRIM(?)) AND LOWER(TRIM(last_name)) = LOWER(TRIM(?)) AND (archived = 0 OR archived IS NULL) LIMIT 1");
        $name_check->execute([$first_name, $last_name]);
        if ($name_check->rowCount() > 0) {
            throw new Exception('A user with the same name already exists (student/examinee). Please use your existing account or contact the guidance office.');
        }

        $user = new User($db);
        $user->password = $password;
        $user->email = $email;
        $user->role = 'examinee';
        $user->first_name = $first_name;
        $user->middle_name = $middle_name === '' ? null : $middle_name;
        $user->last_name = $last_name;
        $user->position = null;
        $user->student_id = null;
        $user->grade_level_applying = $grade_level_applying;
        $user->examinee_type = $examinee_type;

        if ($user->register()) {
            $success_message = 'Registration successful! You may now log in.';
        } else {
            throw new Exception('Registration failed. Please try again.');
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register (Examinee) - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-5 relative overflow-hidden">
<!-- Background decoration -->
<div class="fixed inset-0 pointer-events-none" style="background: radial-gradient(circle at 20% 80%, rgba(120,119,198,0.3) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%)"></div>

<div class="w-full max-w-md relative z-10">
    <div class="bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl p-10 border border-white/20 hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
        <!-- Decorative circles -->
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-gradient-to-br from-primary to-blue-500 rounded-full opacity-[0.08] animate-pulse"></div>
        <div class="absolute -bottom-14 -left-14 w-40 h-40 bg-gradient-to-br from-blue-500 to-primary rounded-full opacity-[0.06] animate-pulse"></div>

        <!-- Logo -->
        <div class="text-center mb-6 relative z-10">
            <div class="relative inline-block mb-4">
                <div class="absolute w-20 h-20 bg-gradient-to-br from-primary to-blue-500 rounded-full -top-1 left-1/2 -translate-x-1/2 opacity-10"></div>
                <img src="../assets/images/srcblogo.png" alt="Logo" class="w-[70px] h-[70px] rounded-full shadow-md p-[3px] bg-white border border-gray-100 relative z-10 object-cover"
                     onload="document.getElementById('temp-logo').style.display='none';"
                     onerror="this.style.display='none'; document.getElementById('temp-logo').style.display='flex';">
                <div id="temp-logo" class="hidden w-[70px] h-[70px] bg-gradient-to-br from-primary to-blue-500 rounded-full flex-col items-center justify-center text-white font-bold text-xs leading-tight p-1.5 mx-auto shadow-lg relative z-10">
                    <div>St. Rita's</div><div>College</div>
                </div>
            </div>
            <h1 class="text-2xl font-extrabold bg-gradient-to-r from-primary to-blue-600 bg-clip-text text-transparent tracking-tight">St. Rita's College of Balingasag</h1>
            <p class="text-gray-400 text-sm font-medium">Guidance Management System</p>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center mb-4">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
            </div>
            <div class="text-center">
                <a href="login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary to-blue-600 text-white rounded-xl font-bold uppercase tracking-wider hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <i class="fas fa-sign-in-alt"></i> Login Here
                </a>
            </div>
        <?php else: ?>
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-600 rounded-xl px-4 py-3 mb-4 flex items-center justify-between text-sm">
                    <span><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 relative z-10">
                <!-- First / Last Name -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="relative">
                        <input type="text" name="first_name" id="first_name" placeholder=" " required
                               class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none"
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        <label for="first_name" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">First Name</label>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"><i class="fas fa-user"></i></span>
                    </div>
                    <div class="relative">
                        <input type="text" name="last_name" id="last_name" placeholder=" " required
                               class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none"
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        <label for="last_name" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Last Name</label>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"><i class="fas fa-user"></i></span>
                    </div>
                </div>

                <!-- Email -->
                <div class="relative">
                    <input type="email" name="email" id="email" placeholder=" " required
                           class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="email" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Email Address</label>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"><i class="fas fa-envelope"></i></span>
                </div>

                <!-- Password / Confirm -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="relative">
                        <input type="password" name="password" id="password" placeholder=" " required minlength="8"
                               class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                        <label for="password" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Password</label>
                        <span id="passwordToggle" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer hover:text-primary transition-colors z-10"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder=" " required minlength="8"
                               class="peer w-full h-[52px] rounded-xl border-2 border-gray-200 pt-5 pb-2 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                        <label for="confirm_password" class="absolute left-3 top-3.5 text-gray-400 text-sm transition-all peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-focus:font-semibold peer-focus:bg-white peer-focus:px-1 peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 pointer-events-none z-10">Confirm</label>
                        <span id="confirmToggle" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer hover:text-primary transition-colors z-10"><i class="fas fa-eye"></i></span>
                    </div>
                </div>

                <!-- Student Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">I am a:</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="student_type" value="new" required <?= (($_POST['student_type'] ?? '') === 'new') ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">New Student</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="student_type" value="transfer" required <?= (($_POST['student_type'] ?? '') === 'transfer') ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">Transferee</span>
                        </label>
                    </div>
                </div>

                <!-- Grade Level - Dynamic based on student type -->
                <div id="grade-level-container" class="hidden">
                    <!-- Hidden input to store the actual value -->
                    <input type="hidden" name="grade_level_applying" id="grade_level_applying" value="<?= htmlspecialchars($_POST['grade_level_applying'] ?? '') ?>">

                    <!-- New Student Entry Levels -->
                    <div id="new-student-grades" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Entry Level</label>
                        <select id="grade_new_select" class="w-full h-[52px] rounded-xl border-2 border-gray-200 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                            <option value="">Select Entry Level</option>
                            <option value="Grade 1">Grade 1 (Elementary)</option>
                            <option value="Grade 7">Grade 7 (Junior High)</option>
                            <option value="Grade 11">Grade 11 (Senior High)</option>
                            <option value="1st Year College">1st Year College</option>
                        </select>
                    </div>

                    <!-- Transfer Student Current Grade Levels -->
                    <div id="transfer-student-grades" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Grade Level</label>
                        <select id="grade_transfer_select" class="w-full h-[52px] rounded-xl border-2 border-gray-200 px-4 text-sm bg-white/80 backdrop-blur-sm focus:border-primary focus:shadow-[0_0_0_4px_rgba(74,107,223,0.1)] focus:-translate-y-0.5 transition-all outline-none">
                            <option value="">Select Current Grade Level</option>
                            <optgroup label="Elementary">
                                <option value="Grade 1">Grade 1</option>
                                <option value="Grade 2">Grade 2</option>
                                <option value="Grade 3">Grade 3</option>
                                <option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option>
                                <option value="Grade 6">Grade 6</option>
                            </optgroup>
                            <optgroup label="Junior High School">
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 10">Grade 10</option>
                            </optgroup>
                            <optgroup label="Senior High School">
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                            </optgroup>
                            <optgroup label="College">
                                <option value="1st Year College">1st Year College</option>
                                <option value="2nd Year College">2nd Year College</option>
                                <option value="3rd Year College">3rd Year College</option>
                                <option value="4th Year College">4th Year College</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-4 rounded-xl font-bold text-white uppercase tracking-wider transition-all duration-300 relative overflow-hidden shadow-[0_4px_15px_rgba(22,50,105,0.3)] hover:-translate-y-1 hover:shadow-[0_8px_25px_rgba(22,50,105,0.4)] bg-gradient-to-r from-primary to-blue-600 hover:from-primary-dark hover:to-primary">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>

                <!-- Login Link -->
                <div class="text-center">
                    <p class="text-gray-500 text-sm mb-1">Already have an account?</p>
                    <a href="login.php" class="inline-flex items-center gap-2 text-primary font-bold hover:text-blue-700 transition-colors">
                        <i class="fas fa-arrow-right"></i> Login Here
                    </a>
                </div>
            </form>

            <!-- Info Box -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3.5 border-l-4 border-primary mt-4">
                <p class="text-xs text-gray-700 leading-relaxed"><i class="fas fa-info-circle mr-1.5 text-primary"></i>After registration, you can log in to apply for the entrance examination.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    // Password toggles
    document.getElementById('passwordToggle')?.addEventListener('click', function() {
        const pw = document.getElementById('password');
        const icon = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye'); icon.classList.toggle('fa-eye-slash');
    });
    document.getElementById('confirmToggle')?.addEventListener('click', function() {
        const pw = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye'); icon.classList.toggle('fa-eye-slash');
    });

    // Grade level dropdown toggle based on student type
    const newRadio = document.querySelector('input[name="student_type"][value="new"]');
    const transferRadio = document.querySelector('input[name="student_type"][value="transfer"]');
    const gradeContainer = document.getElementById('grade-level-container');
    const newGrades = document.getElementById('new-student-grades');
    const transferGrades = document.getElementById('transfer-student-grades');
    const gradeNewSelect = document.getElementById('grade_new_select');
    const gradeTransferSelect = document.getElementById('grade_transfer_select');
    const gradeLevelHidden = document.getElementById('grade_level_applying');

    function showGradeLevel(type) {
        if (!gradeContainer) return;
        
        gradeContainer.classList.remove('hidden');
        gradeContainer.style.display = 'block';
        
        if (type === 'new') {
            if (newGrades) {
                newGrades.classList.remove('hidden');
                newGrades.style.display = 'block';
            }
            if (transferGrades) {
                transferGrades.classList.add('hidden');
                transferGrades.style.display = 'none';
            }
            if (gradeTransferSelect) gradeTransferSelect.value = '';
            // Sync value from new select to hidden input
            if (gradeNewSelect && gradeLevelHidden) {
                gradeLevelHidden.value = gradeNewSelect.value;
            }
        } else if (type === 'transfer') {
            if (newGrades) {
                newGrades.classList.add('hidden');
                newGrades.style.display = 'none';
            }
            if (transferGrades) {
                transferGrades.classList.remove('hidden');
                transferGrades.style.display = 'block';
            }
            if (gradeNewSelect) gradeNewSelect.value = '';
            // Sync value from transfer select to hidden input
            if (gradeTransferSelect && gradeLevelHidden) {
                gradeLevelHidden.value = gradeTransferSelect.value;
            }
        }
    }

    // Sync select values to hidden input when changed
    gradeNewSelect?.addEventListener('change', function() {
        if (gradeLevelHidden) gradeLevelHidden.value = this.value;
    });
    gradeTransferSelect?.addEventListener('change', function() {
        if (gradeLevelHidden) gradeLevelHidden.value = this.value;
    });

    function handleToggle() {
        if (newRadio && newRadio.checked) {
            showGradeLevel('new');
        } else if (transferRadio && transferRadio.checked) {
            showGradeLevel('transfer');
        }
    }

    // Add both change and click listeners for better browser compatibility
    if (newRadio) {
        newRadio.addEventListener('change', handleToggle);
        newRadio.addEventListener('click', handleToggle);
    }
    if (transferRadio) {
        transferRadio.addEventListener('change', handleToggle);
        transferRadio.addEventListener('click', handleToggle);
    }

    // Check on load (for form re-submission with errors)
    setTimeout(handleToggle, 0);
})();
</script>
</body>
</html>
