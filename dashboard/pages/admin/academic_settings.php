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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Settings - SRCB Guidance</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/srcblogo.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #4ade80;
            --warning-color: #facc15;
            --danger-color: #f87171;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --light-text: #64748b;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Main Content */
        #content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed);
            min-height: 100vh;
            padding-bottom: 20px;
        }
        
        .sidebar-collapsed #content {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border: none;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--light-text);
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 8px 8px 0 0;
            margin-right: 4px;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .nav-tabs .nav-link:hover {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        /* Section headers */
        .section-header {
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Icon circles */
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .icon-circle-primary {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .icon-circle-success {
            background: rgba(76, 222, 128, 0.1);
            color: var(--success-color);
        }
        
        .icon-circle-warning {
            background: rgba(250, 204, 21, 0.1);
            color: var(--warning-color);
        }
        
        .icon-circle-info {
            background: rgba(76, 201, 240, 0.1);
            color: var(--accent-color);
        }
        
        /* Badge colors */
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .bg-success {
            background-color: var(--success-color) !important;
        }
        
        .bg-warning {
            background-color: var(--warning-color) !important;
        }
        
        .bg-info {
            background-color: var(--accent-color) !important;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        /* Mobile responsive fixes */
        @media (max-width: 991.98px) {
            /* Content always takes full width on mobile */
            #content {
                margin-left: 0 !important;
            }
            
            .sidebar-collapsed #content {
                margin-left: 0 !important;
            }
            
            /* Mobile overlay styling */
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                z-index: 1049;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .sidebar-show .mobile-overlay {
                display: block;
                opacity: 1;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 575.98px) {
            .container-fluid {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .px-3 {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            .dashboard-card {
                margin-bottom: 1rem;
            }
        }
        
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Include Sidebar Component -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4 px-3">
            <div class="col-12">
                <h2 class="text-primary mb-0">
                    Academic Settings Management
                </h2>
                <p class="text-muted">Manage departments, programs, strands, and grade levels for the guidance system</p>
            </div>
        </div>

        <div class="row px-3">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header bg-light">
                        <h5 class="text-primary mb-0 section-header">
                            <i class="fas fa-graduation-cap me-2"></i>Academic Configuration
                        </h5>
                    </div>
                    
                    <?php if($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs" id="academicTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab">
                                <i class="fas fa-building me-2"></i>Departments
                                <span class="badge bg-light text-dark ms-2"><?php echo count($departments); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button" role="tab">
                                <i class="fas fa-book me-2"></i>Programs
                                <span class="badge bg-light text-dark ms-2"><?php echo count($programs); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="strands-tab" data-bs-toggle="tab" data-bs-target="#strands" type="button" role="tab">
                                <i class="fas fa-route me-2"></i>Strands
                                <span class="badge bg-light text-dark ms-2"><?php echo count($strands); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button" role="tab">
                                <i class="fas fa-layer-group me-2"></i>Grade Levels
                                <span class="badge bg-light text-dark ms-2"><?php echo count($grade_levels); ?></span>
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="academicTabContent">
                        <!-- Departments Tab -->
                        <div class="tab-pane fade show active" id="departments" role="tabpanel">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h5>Add New Department</h5>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_department">
                                            <div class="mb-3">
                                                <label class="form-label">Department Name</label>
                                                <input type="text" class="form-control" name="department_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="department_description" rows="3"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Department
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-8">
                                        <h5>Existing Departments</h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($departments as $dept): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($dept['description'] ?? 'No description'); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo $dept['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['name']); ?>', '<?php echo addslashes($dept['description'] ?? ''); ?>')">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="table" value="academic_departments">
                                                                <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $dept['is_active'] ? 0 : 1; ?>">
                                                                <button type="submit" class="btn btn-sm <?php echo $dept['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                    <i class="fas <?php echo $dept['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                                    <?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Programs Tab -->
                        <div class="tab-pane fade" id="programs" role="tabpanel">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h5>Add New Program</h5>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_program">
                                            <div class="mb-3">
                                                <label class="form-label">Program Name</label>
                                                <input type="text" class="form-control" name="program_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <select class="form-control" name="program_department_id" required>
                                                    <option value="">Select Department</option>
                                                    <?php foreach($departments as $dept): ?>
                                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="program_description" rows="3"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Program
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-8">
                                        <h5>Existing Programs</h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($programs as $program): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($program['name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($program['description'] ?? 'No description'); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo $program['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo $program['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" onclick="editProgram(<?php echo $program['id']; ?>, '<?php echo addslashes($program['name']); ?>', '<?php echo addslashes($program['description'] ?? ''); ?>', <?php echo $program['department_id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="table" value="academic_programs">
                                                                <input type="hidden" name="id" value="<?php echo $program['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $program['is_active'] ? 0 : 1; ?>">
                                                                <button type="submit" class="btn btn-sm <?php echo $program['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                    <i class="fas <?php echo $program['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                                    <?php echo $program['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Strands Tab -->
                        <div class="tab-pane fade" id="strands" role="tabpanel">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h5>Add New Strand</h5>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_strand">
                                            <div class="mb-3">
                                                <label class="form-label">Strand Name</label>
                                                <input type="text" class="form-control" name="strand_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="strand_description" rows="3"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Strand
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-8">
                                        <h5>Existing Strands</h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($strands as $strand): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($strand['name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($strand['description'] ?? 'No description'); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo $strand['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo $strand['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" onclick="editStrand(<?php echo $strand['id']; ?>, '<?php echo addslashes($strand['name']); ?>', '<?php echo addslashes($strand['description'] ?? ''); ?>')">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="table" value="academic_strands">
                                                                <input type="hidden" name="id" value="<?php echo $strand['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $strand['is_active'] ? 0 : 1; ?>">
                                                                <button type="submit" class="btn btn-sm <?php echo $strand['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                    <i class="fas <?php echo $strand['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                                    <?php echo $strand['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Grade Levels Tab -->
                        <div class="tab-pane fade" id="grades" role="tabpanel">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h5>Add New Grade Level</h5>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_grade_level">
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <select class="form-control" name="grade_department_id" required>
                                                    <option value="">Select Department</option>
                                                    <?php foreach($departments as $dept): ?>
                                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Grade Level Name</label>
                                                <input type="text" class="form-control" name="grade_name" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Grade Level
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-8">
                                        <h5>Existing Grade Levels</h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Grade Level</th>
                                                        <th>Department</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($grade_levels as $grade): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($grade['name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($grade['department_name']); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo $grade['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo $grade['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" onclick="editGrade(<?php echo $grade['id']; ?>, '<?php echo addslashes($grade['name']); ?>', <?php echo $grade['department_id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="table" value="academic_grade_levels">
                                                                <input type="hidden" name="id" value="<?php echo $grade['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $grade['is_active'] ? 0 : 1; ?>">
                                                                <button type="submit" class="btn btn-sm <?php echo $grade['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                    <i class="fas <?php echo $grade['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                                    <?php echo $grade['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared Edit Modals -->
    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_department">
                        <input type="hidden" name="department_id" id="edit_dept_id">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="department_name" id="edit_dept_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="department_description" id="edit_dept_desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Program Modal -->
    <div class="modal fade" id="editProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_program">
                        <input type="hidden" name="program_id" id="edit_prog_id">
                        <div class="mb-3">
                            <label class="form-label">Program Name</label>
                            <input type="text" class="form-control" name="program_name" id="edit_prog_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-control" name="program_department_id" id="edit_prog_dept" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="program_description" id="edit_prog_desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Program
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Strand Modal -->
    <div class="modal fade" id="editStrandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Strand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_strand">
                        <input type="hidden" name="strand_id" id="edit_strand_id">
                        <div class="mb-3">
                            <label class="form-label">Strand Name</label>
                            <input type="text" class="form-control" name="strand_name" id="edit_strand_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="strand_description" id="edit_strand_desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Strand
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Grade Level Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Grade Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_grade_level">
                        <input type="hidden" name="grade_id" id="edit_grade_id">
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-control" name="grade_department_id" id="edit_grade_dept" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level Name</label>
                            <input type="text" class="form-control" name="grade_name" id="edit_grade_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Grade Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal Management JavaScript -->
    <script>
        // Edit Department Function
        function editDepartment(id, name, description) {
            $('#edit_dept_id').val(id);
            $('#edit_dept_name').val(name);
            $('#edit_dept_desc').val(description);
            $('#editDepartmentModal').modal('show');
        }

        // Edit Program Function  
        function editProgram(id, name, description, departmentId) {
            $('#edit_prog_id').val(id);
            $('#edit_prog_name').val(name);
            $('#edit_prog_desc').val(description);
            $('#edit_prog_dept').val(departmentId);
            $('#editProgramModal').modal('show');
        }

        // Edit Strand Function
        function editStrand(id, name, description) {
            $('#edit_strand_id').val(id);
            $('#edit_strand_name').val(name);
            $('#edit_strand_desc').val(description);
            $('#editStrandModal').modal('show');
        }

        // Edit Grade Level Function
        function editGrade(id, name, departmentId) {
            $('#edit_grade_id').val(id);
            $('#edit_grade_name').val(name);
            $('#edit_grade_dept').val(departmentId);
            $('#editGradeModal').modal('show');
        }
    </script>
</div> <!-- End Content -->
</body>
</html>
