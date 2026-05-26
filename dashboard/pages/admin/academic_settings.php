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

// Handle form submissions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                
                switch ($type) {
                    case 'department':
                        $stmt = $db->prepare("INSERT INTO academic_departments (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $success_message = "Department added successfully!";
                        logAdminAction('add_department', "Added department: $name", null, $db);
                        break;
                    case 'program':
                        $stmt = $db->prepare("INSERT INTO academic_programs (name, description, department_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $department_id]);
                        $success_message = "Program added successfully!";
                        logAdminAction('add_program', "Added program: $name (Department ID: $department_id)", null, $db);
                        break;
                    case 'strand':
                        $stmt = $db->prepare("INSERT INTO academic_strands (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $success_message = "Strand added successfully!";
                        logAdminAction('add_strand', "Added strand: $name", null, $db);
                        break;
                    case 'grade':
                        $stmt = $db->prepare("INSERT INTO academic_grade_levels (department_id, name) VALUES (?, ?)");
                        $stmt->execute([$department_id, $name]);
                        $success_message = "Grade level added successfully!";
                        logAdminAction('add_grade_level', "Added grade level: $name (Department ID: $department_id)", null, $db);
                        break;
                }
                // Redirect to refresh data
                header("Location: layout.php?page=academic_settings&success=" . urlencode($success_message));
                exit();
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                
                switch ($type) {
                    case 'department':
                        $stmt = $db->prepare("UPDATE academic_departments SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $id]);
                        $success_message = "Department updated successfully!";
                        logAdminAction('edit_department', "Updated department ID $id: $name", null, $db);
                        break;
                    case 'program':
                        $stmt = $db->prepare("UPDATE academic_programs SET name = ?, description = ?, department_id = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $department_id, $id]);
                        $success_message = "Program updated successfully!";
                        logAdminAction('edit_program', "Updated program ID $id: $name (Department ID: $department_id)", null, $db);
                        break;
                    case 'strand':
                        $stmt = $db->prepare("UPDATE academic_strands SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $id]);
                        $success_message = "Strand updated successfully!";
                        logAdminAction('edit_strand', "Updated strand ID $id: $name", null, $db);
                        break;
                    case 'grade':
                        $stmt = $db->prepare("UPDATE academic_grade_levels SET department_id = ?, name = ? WHERE id = ?");
                        $stmt->execute([$department_id, $name, $id]);
                        $success_message = "Grade level updated successfully!";
                        logAdminAction('edit_grade_level', "Updated grade level ID $id: $name (Department ID: $department_id)", null, $db);
                        break;
                }
                // Redirect to refresh data
                header("Location: layout.php?page=academic_settings&success=" . urlencode($success_message));
                exit();
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
                    logAdminAction('toggle_academic_status', "Toggled status for $table ID $id to: " . ($status ? 'Active' : 'Inactive'), null, $db);
                }
                // Redirect to refresh data
                header("Location: layout.php?page=academic_settings&success=" . urlencode($success_message));
                exit();
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// Handle GET request for toggle status
if (isset($_GET['toggle_status'])) {
    $table = $_GET['table'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
    $status = (int)($_GET['status'] ?? 0);
    
    $allowed_tables = ['academic_departments', 'academic_programs', 'academic_strands', 'academic_grade_levels'];
    if (in_array($table, $allowed_tables) && $id > 0) {
        $stmt = $db->prepare("UPDATE {$table} SET is_active = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $success_message = "Status updated successfully!";
    }
    // Redirect to refresh data
    header("Location: layout.php?page=academic_settings&success=" . urlencode($success_message ?? ''));
    exit;
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

// Combine all data for dynamic rendering
$academic_data = [
    'department' => $departments,
    'program' => $programs,
    'strand' => $strands,
    'grade' => $grade_levels
];

// Define field configurations for each type
$field_configs = [
    'department' => [
        'name_label' => 'Department Name',
        'show_department' => false,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Status', 'Actions']
    ],
    'program' => [
        'name_label' => 'Program Name',
        'show_department' => true,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Status', 'Actions']
    ],
    'strand' => [
        'name_label' => 'Strand Name',
        'show_department' => false,
        'show_description' => true,
        'table_columns' => ['Name', 'Description', 'Status', 'Actions']
    ],
    'grade' => [
        'name_label' => 'Grade Level Name',
        'show_department' => true,
        'show_description' => false,
        'table_columns' => ['Grade Level', 'Department', 'Status', 'Actions']
    ]
];
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
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md bg-primary text-white" data-type="department">Departments</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-type="program">Programs</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-type="strand">Strands</button>
            <button type="button" class="tab-btn px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200" data-type="grade">Grade Levels</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800" id="tableTitle">Departments</h3>
            <button type="button" onclick="openAddModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">
                <i class="fas fa-plus mr-1"></i><span id="addButtonText">Add Department</span>
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr id="tableHeader">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="tableBody">
                    <!-- Dynamic content will be rendered here -->
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4 px-2">
                <div class="text-sm text-gray-500" id="paginationInfo">Showing 1-10 of 0</div>
                <div class="flex gap-2" id="paginationControls">
                    <!-- Pagination buttons will be rendered here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold" id="addModalTitle"><i class="fas fa-plus mr-2"></i>Add</h3>
            <button type="button" onclick="closeModal('addModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4" id="addForm">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="type" id="addType">
            
            <div id="addNameField">
                <label class="block text-sm text-gray-700 mb-1" id="addNameLabel">Name</label>
                <input type="text" name="name" id="addName" required class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            
            <div id="addDepartmentField" class="hidden">
                <label class="block text-sm text-gray-700 mb-1">Department</label>
                <select name="department_id" id="addDepartment" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select Department</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="addDescriptionField">
                <label class="block text-sm text-gray-700 mb-1">Description</label>
                <textarea name="description" id="addDescription" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold" id="editModalTitle"><i class="fas fa-edit mr-2"></i>Edit</h3>
            <button type="button" onclick="closeModal('editModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" id="editType">
            <input type="hidden" name="id" id="editId">
            
            <div id="editNameField">
                <label class="block text-sm text-gray-700 mb-1" id="editNameLabel">Name</label>
                <input type="text" name="name" id="editName" required class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            
            <div id="editDepartmentField" class="hidden">
                <label class="block text-sm text-gray-700 mb-1">Department</label>
                <select name="department_id" id="editDepartment" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select Department</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="editDescriptionField">
                <label class="block text-sm text-gray-700 mb-1">Description</label>
                <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const academicData = <?= json_encode($academic_data) ?>;
const fieldConfigs = <?= json_encode($field_configs) ?>;
const tableNames = {
    'department': 'academic_departments',
    'program': 'academic_programs',
    'strand': 'academic_strands',
    'grade': 'academic_grade_levels'
};

let currentType = 'department';
let currentPage = 1;
const itemsPerPage = 10;

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

function switchAcademicTab(type) {
    currentType = type;
    currentPage = 1; // Reset to page 1 when switching tabs
    const config = fieldConfigs[type];
    const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
    
    // Update tab styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.type === type) {
            btn.classList.add('bg-primary', 'text-white');
            btn.classList.remove('text-gray-600', 'hover:bg-gray-200');
        } else {
            btn.classList.remove('bg-primary', 'text-white');
            btn.classList.add('text-gray-600', 'hover:bg-gray-200');
        }
    });
    
    // Update table title and add button
    document.getElementById('tableTitle').textContent = typeLabel + 's';
    document.getElementById('addButtonText').textContent = 'Add ' + typeLabel;
    
    // Update table header
    const headerRow = document.getElementById('tableHeader');
    headerRow.innerHTML = config.table_columns.map(col => `<th class="px-4 py-3">${col}</th>`).join('');
    
    // Render table body
    renderTableBody(type);
}

function openAddModal() {
    const config = fieldConfigs[currentType];
    const typeLabel = currentType.charAt(0).toUpperCase() + currentType.slice(1);
    
    document.getElementById('addType').value = currentType;
    document.getElementById('addModalTitle').innerHTML = `<i class="fas fa-plus mr-2"></i>Add ${typeLabel}`;
    document.getElementById('addNameLabel').textContent = config.name_label;
    
    // Clear form fields
    document.getElementById('addName').value = '';
    document.getElementById('addDescription').value = '';
    document.getElementById('addDepartment').value = '';
    
    // Toggle fields
    document.getElementById('addDepartmentField').classList.toggle('hidden', !config.show_department);
    document.getElementById('addDescriptionField').classList.toggle('hidden', !config.show_description);
    
    openModal('addModal');
}

function renderTableBody(type) {
    const data = academicData[type];
    const tbody = document.getElementById('tableBody');
    const config = fieldConfigs[type];
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="' + config.table_columns.length + '" class="px-4 py-8 text-center text-gray-400">No records found</td></tr>';
        document.getElementById('paginationInfo').textContent = 'Showing 0 of 0';
        document.getElementById('paginationControls').innerHTML = '';
        return;
    }
    
    // Calculate pagination
    const totalPages = Math.ceil(data.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = data.slice(startIndex, endIndex);
    
    // Update pagination info
    const showingStart = data.length > 0 ? startIndex + 1 : 0;
    const showingEnd = Math.min(endIndex, data.length);
    document.getElementById('paginationInfo').textContent = `Showing ${showingStart}-${showingEnd} of ${data.length}`;
    
    // Render table rows
    tbody.innerHTML = paginatedData.map(item => {
        const statusClass = item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600';
        const statusText = item.is_active ? 'Active' : 'Inactive';
        const toggleBtnClass = item.is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200';
        const toggleIcon = item.is_active ? 'fa-pause' : 'fa-play';
        const toggleText = item.is_active ? 'Deactivate' : 'Activate';
        
        let cells = '';
        
        if (type === 'grade') {
            cells = `
                <td class="px-4 py-3 font-medium">${htmlspecialchars(item.name)}</td>
                <td class="px-4 py-3 text-gray-600">${htmlspecialchars(item.department_name || '')}</td>
            `;
        } else {
            cells = `
                <td class="px-4 py-3 font-medium">${htmlspecialchars(item.name)}</td>
                ${config.show_description ? `<td class="px-4 py-3 text-gray-600">${htmlspecialchars(item.description || 'No description')}</td>` : ''}
            `;
        }
        
        cells += `
            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusText}</span></td>
            <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                    <button type="button" class="px-3 py-1.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="editItem('${type}', ${item.id}, '${addslashes(item.name)}', '${addslashes(item.description || '')}', ${item.department_id || 'null'})"><i class="fas fa-edit mr-1"></i>Edit</button>
                    <button type="button" class="px-3 py-1.5 text-xs rounded ${toggleBtnClass}" onclick="toggleStatus('${tableNames[type]}', ${item.id}, ${item.is_active ? 0 : 1})"><i class="fas ${toggleIcon} mr-1"></i>${toggleText}</button>
                </div>
            </td>
        `;
        
        return `<tr class="hover:bg-gray-50">${cells}</tr>`;
    }).join('');
    
    // Render pagination controls
    renderPaginationControls(totalPages);
}

function renderPaginationControls(totalPages) {
    const controls = document.getElementById('paginationControls');
    
    if (totalPages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button type="button" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1 text-sm border rounded ${currentPage === 1 ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100'}"><i class="fas fa-chevron-left"></i></button>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<button type="button" onclick="goToPage(${i})" class="px-3 py-1 text-sm border rounded ${i === currentPage ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100'}">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<span class="px-2 text-gray-400">...</span>`;
        }
    }
    
    // Next button
    html += `<button type="button" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1 text-sm border rounded ${currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100'}"><i class="fas fa-chevron-right"></i></button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    const data = academicData[currentType];
    const totalPages = Math.ceil(data.length / itemsPerPage);
    
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderTableBody(currentType);
}

function toggleStatus(table, id, status) {
    window.location.href = `layout.php?page=academic_settings&toggle_status=1&table=${table}&id=${id}&status=${status}`;
}

function editItem(type, id, name, description, departmentId) {
    const config = fieldConfigs[type];
    
    document.getElementById('editType').value = type;
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDescription').value = description || '';
    document.getElementById('editDepartment').value = departmentId || '';
    
    document.getElementById('editModalTitle').innerHTML = `<i class="fas fa-edit mr-2"></i>Edit ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    document.getElementById('editNameLabel').textContent = config.name_label;
    
    document.getElementById('editDepartmentField').classList.toggle('hidden', !config.show_department);
    document.getElementById('editDescriptionField').classList.toggle('hidden', !config.show_description);
    
    openModal('editModal');
}

function htmlspecialchars(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
    });
}

function addslashes(str) {
    if (!str) return '';
    return str.replace(/[\\"']/g, function(m) {
        return {'\\': '\\\\', '"': '\\"', "'": "\\'"}[m];
    });
}

// Initialize
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchAcademicTab(btn.dataset.type));
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
        closeModal('addModal');
        closeModal('editModal');
    }
});

// Initial render
switchAcademicTab('department');
</script>
