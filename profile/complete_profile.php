<?php
$in_layout = defined('IN_LAYOUT');
if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    checkLogin();
}
$user_info = getUserInfo();
if (!in_array($user_info['role'], ['student', 'examinee'])) { header("Location: " . ($in_layout ? "layout.php" : "../dashboard/index.php")); exit(); }

try {
    $db = (new Database())->getConnection();
} catch (Exception $e) { die("Database connection failed."); }

// Get current profile
$profile = null;
try {
    $stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$user_info['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get academic data (fallback to hardcoded if tables don't exist)
$departments = []; $grade_levels = []; $programs = [];
try {
    $departments = $db->query("SELECT * FROM academic_departments WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
    $grade_levels = $db->query("SELECT * FROM academic_grade_levels WHERE is_active = 1 ORDER BY department_id, sort_order")->fetchAll(PDO::FETCH_ASSOC);
    $programs = $db->query("SELECT * FROM academic_programs WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback hardcoded values
    $departments = [
        ['id' => 1, 'name' => 'Elementary'],
        ['id' => 2, 'name' => 'Junior High School'],
        ['id' => 3, 'name' => 'Senior High School'],
        ['id' => 4, 'name' => 'Higher Education']
    ];
    $grade_levels = [
        ['id'=>1,'department_id'=>1,'name'=>'Grade 1'], ['id'=>2,'department_id'=>1,'name'=>'Grade 2'],
        ['id'=>3,'department_id'=>1,'name'=>'Grade 3'], ['id'=>4,'department_id'=>1,'name'=>'Grade 4'],
        ['id'=>5,'department_id'=>1,'name'=>'Grade 5'], ['id'=>6,'department_id'=>1,'name'=>'Grade 6'],
        ['id'=>7,'department_id'=>2,'name'=>'Grade 7'], ['id'=>8,'department_id'=>2,'name'=>'Grade 8'],
        ['id'=>9,'department_id'=>2,'name'=>'Grade 9'], ['id'=>10,'department_id'=>2,'name'=>'Grade 10'],
        ['id'=>11,'department_id'=>3,'name'=>'Grade 11'], ['id'=>12,'department_id'=>3,'name'=>'Grade 12'],
        ['id'=>13,'department_id'=>4,'name'=>'1st Year'], ['id'=>14,'department_id'=>4,'name'=>'2nd Year'],
        ['id'=>15,'department_id'=>4,'name'=>'3rd Year'], ['id'=>16,'department_id'=>4,'name'=>'4th Year'],
    ];
    $programs = [
        ['name'=>'BSIT'],['name'=>'BSCRIM'],['name'=>'BSED'],['name'=>'BEED'],['name'=>'BSBA'],
        ['name'=>'BSPsych'],['name'=>'BSHM'],['name'=>'AB English'],['name'=>'BSN']
    ];
}

// Handle POST
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $department = $_POST['department'] ?? '';
        $grade_level = $_POST['grade_level'] ?? '';
        $program = $_POST['program'] ?? '';
        $strand = $_POST['strand'] ?? '';

        if (empty($department) || empty($grade_level)) {
            $error_message = "Department and Grade Level are required.";
        } else {
            if ($profile) {
                $stmt = $db->prepare("UPDATE student_profiles SET department=?, grade_level=?, program=?, strand=?, updated_at=NOW() WHERE user_id=?");
                $stmt->execute([$department, $grade_level, $program ?: null, $strand ?: null, $user_info['id']]);
            } else {
                $student_id = date('Y') . '-' . str_pad($user_info['id'], 3, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO student_profiles (user_id, student_id, department, grade_level, program, strand) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_info['id'], $student_id, $department, $grade_level, $program ?: null, $strand ?: null]);
            }
            // Update session
            $_SESSION['grade_level_applying'] = $grade_level;

            $redirect = $_GET['redirect'] ?? 'dashboard';
            header("Location: " . ($redirect === 'pds' ? '../pds/fill_pds.php' : '../dashboard/index.php'));
            exit();
        }
    } catch (Exception $e) {
        $error_message = "Failed to update profile. Please try again.";
    }
}

$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';

if (!$in_layout)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#163269', 'primary-dark': '#3a56c4' } } } }</script>
</head>
<body class="min-h-screen bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center p-5">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mx-auto mb-3 text-primary text-2xl"><i class="fas fa-user-graduate"></i></div>
            <h1 class="text-xl font-bold text-primary">Complete Your Profile</h1>
            <p class="text-sm text-gray-400">Please complete your academic profile to access all features</p>
        </div>

        <?php if ($error_message): ?>
        <div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Department -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                <select name="department" id="departmentSelect" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d['name']) ?>" <?= ($profile && $profile['department'] === $d['name']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grade Level -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Grade Level <span class="text-red-500">*</span></label>
                <select name="grade_level" id="gradeLevelSelect" required class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all">
                    <option value="">Select Grade Level</option>
                </select>
            </div>

            <!-- Program (Higher Education) -->
            <div class="mb-4 hidden" id="programGroup">
                <label class="block text-sm font-medium text-gray-700 mb-1">Program <span class="text-red-500">*</span></label>
                <select name="program" id="programSelect" class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all">
                    <option value="">Select Program</option>
                    <?php foreach ($programs as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?>" <?= ($profile && ($profile['program'] ?? '') === $p['name']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Strand (Senior High) -->
            <div class="mb-4 hidden" id="strandGroup">
                <label class="block text-sm font-medium text-gray-700 mb-1">Strand <span class="text-red-500">*</span></label>
                <select name="strand" id="strandSelect" class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all">
                    <option value="">Select Strand</option>
                    <option value="STEM" <?= ($profile && ($profile['strand'] ?? '') === 'STEM') ? 'selected' : '' ?>>STEM</option>
                    <option value="ABM" <?= ($profile && ($profile['strand'] ?? '') === 'ABM') ? 'selected' : '' ?>>ABM</option>
                    <option value="HUMSS" <?= ($profile && ($profile['strand'] ?? '') === 'HUMSS') ? 'selected' : '' ?>>HUMSS</option>
                    <option value="GAS" <?= ($profile && ($profile['strand'] ?? '') === 'GAS') ? 'selected' : '' ?>>GAS</option>
                    <option value="TVL-ICT" <?= ($profile && ($profile['strand'] ?? '') === 'TVL-ICT') ? 'selected' : '' ?>>TVL-ICT</option>
                    <option value="TVL-HE" <?= ($profile && ($profile['strand'] ?? '') === 'TVL-HE') ? 'selected' : '' ?>>TVL-HE</option>
                    <option value="TVL-IA" <?= ($profile && ($profile['strand'] ?? '') === 'TVL-IA') ? 'selected' : '' ?>>TVL-IA</option>
                    <option value="Arts and Design" <?= ($profile && ($profile['strand'] ?? '') === 'Arts and Design') ? 'selected' : '' ?>>Arts and Design</option>
                    <option value="Sports" <?= ($profile && ($profile['strand'] ?? '') === 'Sports') ? 'selected' : '' ?>>Sports</option>
                </select>
            </div>

            <div class="flex gap-3 mt-6">
                <a href="../dashboard/index.php" class="flex-1 text-center py-3 rounded-lg border-2 border-gray-200 text-gray-500 font-semibold text-sm hover:bg-gray-50 transition-colors"><i class="fas fa-arrow-left mr-1"></i>Back</a>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-colors"><i class="fas fa-save mr-1"></i>Save Profile</button>
            </div>
        </form>
    </div>

    <script>
    const gradeLevels = <?= json_encode($grade_levels) ?>;
    const currentProfile = <?= json_encode($profile) ?>;
    const deptMap = { 'Elementary': 1, 'Junior High School': 2, 'Senior High School': 3, 'Higher Education': 4 };

    document.getElementById('departmentSelect').addEventListener('change', function() {
        const dept = this.value;
        const glSelect = document.getElementById('gradeLevelSelect');
        const progGroup = document.getElementById('programGroup');
        const strandGroup = document.getElementById('strandGroup');
        const progSel = document.getElementById('programSelect');
        const strandSel = document.getElementById('strandSelect');

        glSelect.innerHTML = '<option value="">Select Grade Level</option>';

        if (dept) {
            const deptId = deptMap[dept];
            gradeLevels.filter(gl => gl.department_id == deptId).forEach(gl => {
                const opt = document.createElement('option');
                opt.value = gl.name; opt.textContent = gl.name;
                if (currentProfile && currentProfile.grade_level === gl.name) opt.selected = true;
                glSelect.appendChild(opt);
            });

            if (dept === 'Higher Education') { progGroup.classList.remove('hidden'); strandGroup.classList.add('hidden'); progSel.required = true; strandSel.required = false; }
            else if (dept === 'Senior High School') { progGroup.classList.add('hidden'); strandGroup.classList.remove('hidden'); progSel.required = false; strandSel.required = true; }
            else { progGroup.classList.add('hidden'); strandGroup.classList.add('hidden'); progSel.required = false; strandSel.required = false; }
        } else {
            progGroup.classList.add('hidden'); strandGroup.classList.add('hidden');
        }
    });

    // Trigger on load if department already selected
    if (document.getElementById('departmentSelect').value) {
        document.getElementById('departmentSelect').dispatchEvent(new Event('change'));
    }
    </script>
<?php if (!$in_layout): ?>
</body>
</html>
<?php endif; ?>
