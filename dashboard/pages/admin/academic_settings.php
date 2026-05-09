<?php
// Academic Settings Management
// Included by layout.php - $db variable is already available

// Role-based access control - Only super admins can manage academic settings
if ($role != 'super_admin') {
    header('Location: layout.php?page=dashboard');
    exit();
}

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_department':
                $name = trim($_POST['department_name']);
                $description = trim($_POST['department_description']);
                
                $stmt = $db->prepare("INSERT INTO academic_departments (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success_message = "Department added successfully!";
                break;
                
            case 'add_program':
                $name = trim($_POST['program_name']);
                $description = trim($_POST['program_description']);
                $department_id = $_POST['program_department_id'];
                
                $stmt = $db->prepare("INSERT INTO academic_programs (name, description, department_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $department_id]);
                $success_message = "Program added successfully!";
                break;
                
            case 'add_strand':
                $name = trim($_POST['strand_name']);
                $description = trim($_POST['strand_description']);
                
                $stmt = $db->prepare("INSERT INTO academic_strands (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success_message = "Strand added successfully!";
                break;
                
            case 'add_grade_level':
                $department_id = $_POST['grade_department_id'];
                $name = trim($_POST['grade_name']);
                
                $stmt = $db->prepare("INSERT INTO academic_grade_levels (department_id, name) VALUES (?, ?)");
                $stmt->execute([$department_id, $name]);
                $success_message = "Grade level added successfully!";
                break;
                
            case 'edit_department':
                $id = $_POST['department_id'];
                $name = trim($_POST['department_name']);
                $description = trim($_POST['department_description']);
                
                $stmt = $db->prepare("UPDATE academic_departments SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $success_message = "Department updated successfully!";
                break;
                
            case 'edit_program':
                $id = $_POST['program_id'];
                $name = trim($_POST['program_name']);
                $description = trim($_POST['program_description']);
                $department_id = $_POST['program_department_id'];
                
                $stmt = $db->prepare("UPDATE academic_programs SET name = ?, description = ?, department_id = ? WHERE id = ?");
                $stmt->execute([$name, $description, $department_id, $id]);
                $success_message = "Program updated successfully!";
                break;
                
            case 'edit_strand':
                $id = $_POST['strand_id'];
                $name = trim($_POST['strand_name']);
                $description = trim($_POST['strand_description']);
                
                $stmt = $db->prepare("UPDATE academic_strands SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $success_message = "Strand updated successfully!";
                break;
                
            case 'edit_grade_level':
                $id = $_POST['grade_id'];
                $department_id = $_POST['grade_department_id'];
                $name = trim($_POST['grade_name']);
                
                $stmt = $db->prepare("UPDATE academic_grade_levels SET department_id = ?, name = ? WHERE id = ?");
                $stmt->execute([$department_id, $name, $id]);
                $success_message = "Grade level updated successfully!";
                break;
                
            case 'toggle_status':
                $table = $_POST['table'];
                $id = $_POST['id'];
                $status = $_POST['status'];
                
                $allowed_tables = ['academic_departments', 'academic_programs', 'academic_strands', 'academic_grade_levels'];
                if (in_array($table, $allowed_tables)) {
                    $stmt = $db->prepare("UPDATE {$table} SET is_active = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $success_message = "Status updated successfully!";
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch all academic data
$departments = $db->query("SELECT * FROM academic_departments ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$programs = $db->query("
    SELECT p.*, d.name as department_name 
    FROM academic_programs p 
    LEFT JOIN academic_departments d ON p.department_id = d.id 
    ORDER BY p.sort_order, p.name
")->fetchAll(PDO::FETCH_ASSOC);
$strands = $db->query("SELECT * FROM academic_strands ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$grade_levels = $db->query("
    SELECT gl.*, d.name as department_name 
    FROM academic_grade_levels gl 
    JOIN academic_departments d ON gl.department_id = d.id 
    ORDER BY d.sort_order, gl.sort_order
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-graduation-cap mr-2 text-primary"></i>Academic Settings
        </h1>
    </div>

    <?php if ($success_message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?></span>
        <button type="button" class="text-green-700/70 hover:text-green-700" data-dismiss-alert><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?></span>
        <button type="button" class="text-red-700/70 hover:text-red-700" data-dismiss-alert><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500">Departments</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($departments) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500">
            <div class="text-xs text-gray-500">Programs</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($programs) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-cyan-500">
            <div class="text-xs text-gray-500">Strands</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($strands) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
            <div class="text-xs text-gray-500">Grade Levels</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($grade_levels) ?></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex bg-gray-100 rounded-lg p-1 flex-wrap gap-1" id="academicTabs">
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md bg-primary text-white" data-tab="departments">Departments</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-tab="programs">Programs</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-tab="strands">Strands</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-tab="grades">Grade Levels</button>
        </div>
    </div>

    <div id="departments" class="tab-panel bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Add Department</h3>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="add_department">
                    <div><label class="block text-sm text-gray-700 mb-1">Department Name</label><input type="text" name="department_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="department_description" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Department</button>
                </form>
            </div>
            <div class="md:col-span-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach($departments as $dept): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($dept['name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($dept['description'] ?? 'No description') ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $dept['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>"><?= $dept['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="px-3 py-1.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="editDepartment(<?= $dept['id'] ?>, '<?= addslashes($dept['name']) ?>', '<?= addslashes($dept['description'] ?? '') ?>')"><i class="fas fa-edit mr-1"></i>Edit</button>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="table" value="academic_departments">
                                        <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $dept['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs rounded <?= $dept['is_active'] ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>"><i class="fas <?= $dept['is_active'] ? 'fa-pause' : 'fa-play' ?> mr-1"></i><?= $dept['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="programs" class="tab-panel hidden bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Add Program</h3>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="add_program">
                    <div><label class="block text-sm text-gray-700 mb-1">Program Name</label><input type="text" name="program_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm text-gray-700 mb-1">Department</label><select name="program_department_id" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="program_description" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Program</button>
                </form>
            </div>
            <div class="md:col-span-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach($programs as $program): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($program['name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($program['description'] ?? 'No description') ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $program['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>"><?= $program['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="px-3 py-1.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="editProgram(<?= $program['id'] ?>, '<?= addslashes($program['name']) ?>', '<?= addslashes($program['description'] ?? '') ?>', <?= (int)$program['department_id'] ?>)"><i class="fas fa-edit mr-1"></i>Edit</button>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="table" value="academic_programs"><input type="hidden" name="id" value="<?= $program['id'] ?>"><input type="hidden" name="status" value="<?= $program['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs rounded <?= $program['is_active'] ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>"><i class="fas <?= $program['is_active'] ? 'fa-pause' : 'fa-play' ?> mr-1"></i><?= $program['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="strands" class="tab-panel hidden bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Add Strand</h3>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="add_strand">
                    <div><label class="block text-sm text-gray-700 mb-1">Strand Name</label><input type="text" name="strand_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="strand_description" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Strand</button>
                </form>
            </div>
            <div class="md:col-span-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach($strands as $strand): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($strand['name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($strand['description'] ?? 'No description') ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $strand['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>"><?= $strand['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="px-3 py-1.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="editStrand(<?= $strand['id'] ?>, '<?= addslashes($strand['name']) ?>', '<?= addslashes($strand['description'] ?? '') ?>')"><i class="fas fa-edit mr-1"></i>Edit</button>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="table" value="academic_strands"><input type="hidden" name="id" value="<?= $strand['id'] ?>"><input type="hidden" name="status" value="<?= $strand['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs rounded <?= $strand['is_active'] ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>"><i class="fas <?= $strand['is_active'] ? 'fa-pause' : 'fa-play' ?> mr-1"></i><?= $strand['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="grades" class="tab-panel hidden bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Add Grade Level</h3>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="add_grade_level">
                    <div><label class="block text-sm text-gray-700 mb-1">Department</label><select name="grade_department_id" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-sm text-gray-700 mb-1">Grade Level Name</label><input type="text" name="grade_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Grade Level</button>
                </form>
            </div>
            <div class="md:col-span-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Grade Level</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach($grade_levels as $grade): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($grade['name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($grade['department_name']) ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $grade['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>"><?= $grade['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="px-3 py-1.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="editGrade(<?= $grade['id'] ?>, '<?= addslashes($grade['name']) ?>', <?= (int)$grade['department_id'] ?>)"><i class="fas fa-edit mr-1"></i>Edit</button>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="table" value="academic_grade_levels"><input type="hidden" name="id" value="<?= $grade['id'] ?>"><input type="hidden" name="status" value="<?= $grade['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs rounded <?= $grade['is_active'] ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>"><i class="fas <?= $grade['is_active'] ? 'fa-pause' : 'fa-play' ?> mr-1"></i><?= $grade['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDepartmentModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Department</h3>
            <button type="button" onclick="closeModal('editDepartmentModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_department"><input type="hidden" name="department_id" id="edit_dept_id">
            <div><label class="block text-sm text-gray-700 mb-1">Department Name</label><input type="text" name="department_name" id="edit_dept_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="department_description" id="edit_dept_desc" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('editDepartmentModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- Edit Program Modal -->
<div id="editProgramModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="bg-indigo-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Program</h3>
            <button type="button" onclick="closeModal('editProgramModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_program"><input type="hidden" name="program_id" id="edit_prog_id">
            <div><label class="block text-sm text-gray-700 mb-1">Program Name</label><input type="text" name="program_name" id="edit_prog_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm text-gray-700 mb-1">Department</label><select name="program_department_id" id="edit_prog_dept" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="program_description" id="edit_prog_desc" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('editProgramModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button><button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- Edit Strand Modal -->
<div id="editStrandModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="bg-cyan-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Strand</h3>
            <button type="button" onclick="closeModal('editStrandModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_strand"><input type="hidden" name="strand_id" id="edit_strand_id">
            <div><label class="block text-sm text-gray-700 mb-1">Strand Name</label><input type="text" name="strand_name" id="edit_strand_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm text-gray-700 mb-1">Description</label><textarea name="strand_description" id="edit_strand_desc" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('editStrandModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button><button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm hover:bg-cyan-700">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- Edit Grade Modal -->
<div id="editGradeModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="bg-purple-600 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Grade Level</h3>
            <button type="button" onclick="closeModal('editGradeModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_grade_level"><input type="hidden" name="grade_id" id="edit_grade_id">
            <div><label class="block text-sm text-gray-700 mb-1">Department</label><select name="grade_department_id" id="edit_grade_dept" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm text-gray-700 mb-1">Grade Level Name</label><input type="text" name="grade_name" id="edit_grade_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="closeModal('editGradeModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button><button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById(tab)?.classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tab) {
            btn.classList.add('bg-primary', 'text-white');
            btn.classList.remove('text-gray-600', 'hover:bg-gray-200');
        } else {
            btn.classList.remove('bg-primary', 'text-white');
            btn.classList.add('text-gray-600', 'hover:bg-gray-200');
        }
    });
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

document.querySelectorAll('[data-dismiss-alert]').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('div')?.remove());
});

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('fixed') && e.target.id && e.target.id.endsWith('Modal')) {
        closeModal(e.target.id);
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        ['editDepartmentModal', 'editProgramModal', 'editStrandModal', 'editGradeModal'].forEach(closeModal);
    }
});

function editDepartment(id, name, description) {
    setValue('edit_dept_id', id);
    setValue('edit_dept_name', name);
    setValue('edit_dept_desc', description);
    openModal('editDepartmentModal');
}

function editProgram(id, name, description, departmentId) {
    setValue('edit_prog_id', id);
    setValue('edit_prog_name', name);
    setValue('edit_prog_desc', description);
    setValue('edit_prog_dept', departmentId);
    openModal('editProgramModal');
}

function editStrand(id, name, description) {
    setValue('edit_strand_id', id);
    setValue('edit_strand_name', name);
    setValue('edit_strand_desc', description);
    openModal('editStrandModal');
}

function editGrade(id, name, departmentId) {
    setValue('edit_grade_id', id);
    setValue('edit_grade_name', name);
    setValue('edit_grade_dept', departmentId);
    openModal('editGradeModal');
}
</script>
