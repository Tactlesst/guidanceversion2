<?php
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$user_obj = new User($db);
$pds = new PersonalDataSheet($db);

// AJAX endpoint for fetching student list
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $department = $_GET['department'] ?? '';
        $strand = $_GET['strand'] ?? '';
        $program = $_GET['program'] ?? '';
        $grade = $_GET['grade'] ?? '';
        
        $w = ["u.role = 'student'", "(u.archived=0 OR u.archived IS NULL)"];
        $p_arr = [];
        
        if ($department) { $w[] = "sp.department = ?"; $p_arr[] = $department; }
        if ($strand) { $w[] = "sp.strand = ?"; $p_arr[] = $strand; }
        if ($program) { $w[] = "sp.program = ?"; $p_arr[] = $program; }
        if ($grade) { $w[] = "sp.grade_level = ?"; $p_arr[] = $grade; }
        
        $where = implode(' AND ', $w);
        
        // Fetch all students ordered by department, strand, program, last name, first name
        $stmt = $db->prepare("SELECT u.*, sp.student_id, sp.department, sp.strand, sp.program, sp.grade_level, p.gender
            FROM users u 
            LEFT JOIN student_profiles sp ON u.id=sp.user_id
            LEFT JOIN pds p ON u.id=p.user_id
            WHERE $where ORDER BY sp.department, sp.strand, sp.program, u.last_name, u.first_name");
        $stmt->execute($p_arr);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Get departments, strands, grades, programs for filters
$departments = [];
$strands = [];
$grades = [];
$programs = [];

try {
    $departments = $db->query("SELECT DISTINCT department FROM student_profiles WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    
    // Fetch strands with their department relationships
    $strands_query = $db->query("
        SELECT DISTINCT sp.strand, s.department_id, d.name as department_name
        FROM student_profiles sp
        LEFT JOIN academic_strands s ON sp.strand = s.name
        LEFT JOIN academic_departments d ON s.department_id = d.id
        WHERE sp.strand IS NOT NULL AND sp.strand != ''
        ORDER BY sp.strand
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $strands = [];
    $strand_departments = [];
    foreach ($strands_query as $row) {
        $strands[] = $row['strand'];
        $strand_departments[$row['strand']] = $row['department_name'];
    }
    
    // Fetch programs with their department relationships
    $programs_query = $db->query("
        SELECT DISTINCT sp.program, p.department_id, d.name as department_name
        FROM student_profiles sp
        LEFT JOIN academic_programs p ON sp.program = p.name
        LEFT JOIN academic_departments d ON p.department_id = d.id
        WHERE sp.program IS NOT NULL AND sp.program != ''
        ORDER BY sp.program
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $programs = [];
    $program_departments = [];
    foreach ($programs_query as $row) {
        $programs[] = $row['program'];
        $program_departments[$row['program']] = $row['department_name'];
    }
    
    $grades = $db->query("SELECT DISTINCT grade_level FROM student_profiles WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-list mr-2 text-primary"></i>Student List Report</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <select id="departmentFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="updateFilters()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="strandFilter" class="px-3 py-2 border rounded-lg text-sm hidden" onchange="fetchStudentList()">
                <option value="">All Strands</option>
                <?php foreach ($strands as $strand): ?>
                    <option value="<?= htmlspecialchars($strand) ?>" data-department="<?= htmlspecialchars($strand_departments[$strand] ?? '') ?>"><?= htmlspecialchars($strand) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="programFilter" class="px-3 py-2 border rounded-lg text-sm hidden" onchange="fetchStudentList()">
                <option value="">All Programs</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= htmlspecialchars($program) ?>" data-department="<?= htmlspecialchars($program_departments[$program] ?? '') ?>"><?= htmlspecialchars($program) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="gradeFilter" class="px-3 py-2 border rounded-lg text-sm" onchange="fetchStudentList()">
                <option value="">All Grade Levels</option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Student List Report -->
    <div class="bg-white rounded-xl shadow-sm p-6" id="reportContainer">
        <div class="text-center mb-6 border-b-2 border-primary pb-4">
            <h2 class="text-xl font-bold text-gray-800">St. Rita's College of Balingasag</h2>
            <p class="text-xs text-gray-500">Guidance and Counseling Office</p>
            <h3 class="text-lg font-semibold text-gray-700 mt-2">Student List Report</h3>
            <p class="text-sm text-gray-500" id="reportSubtitle">All Students</p>
            <p class="text-xs text-gray-400 mt-1" id="reportDate"></p>
        </div>

        <!-- Student Groups by Department and Strand -->
        <div id="studentGroups"></div>

        <!-- Grand Total -->
        <div class="bg-gray-100 p-4 rounded-lg mb-6">
            <div class="flex justify-between items-center">
                <span class="font-semibold text-gray-700">Grand Total:</span>
                <span class="font-bold text-gray-800 text-lg" id="grandTotal">0</span>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=student_list_report';

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function updateFilters() {
    const department = document.getElementById('departmentFilter').value;
    const strandFilter = document.getElementById('strandFilter');
    const programFilter = document.getElementById('programFilter');
    
    // Reset strand and program filters when department changes
    strandFilter.value = '';
    programFilter.value = '';
    
    // Show/hide filters based on department
    if (department === 'Senior High School') {
        strandFilter.classList.remove('hidden');
        programFilter.classList.add('hidden');
    } else if (department === 'Higher Education') {
        strandFilter.classList.add('hidden');
        programFilter.classList.remove('hidden');
    } else {
        strandFilter.classList.add('hidden');
        programFilter.classList.add('hidden');
    }
    
    // Filter strand options based on selected department
    const strandOptions = strandFilter.querySelectorAll('option');
    strandOptions.forEach(option => {
        if (option.value === '') return;
        const strandDept = option.getAttribute('data-department');
        if (department && strandDept && strandDept !== department) {
            option.style.display = 'none';
        } else {
            option.style.display = '';
        }
    });
    
    // Filter program options based on selected department
    const programOptions = programFilter.querySelectorAll('option');
    programOptions.forEach(option => {
        if (option.value === '') return;
        const programDept = option.getAttribute('data-department');
        if (department && programDept && programDept !== department) {
            option.style.display = 'none';
        } else {
            option.style.display = '';
        }
    });
    
    fetchStudentList();
}

function fetchStudentList() {
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const program = document.getElementById('programFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    fetch(BASE + `&action=fetch&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&program=${encodeURIComponent(program)}&grade=${encodeURIComponent(grade)}`)
    .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
    })
    .then(data => {
        if (data.error) {
            console.error('Server error:', data.error);
            document.getElementById('studentGroups').innerHTML = '<div class="text-center text-red-500 py-4">Error: ' + esc(data.error) + '</div>';
            return;
        }
        
        const studentGroups = document.getElementById('studentGroups');
        studentGroups.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            studentGroups.innerHTML = '<div class="text-center text-gray-500 py-4">No students found</div>';
            document.getElementById('grandTotal').textContent = '0';
            return;
        }
        
        // Group students hierarchically: Department -> Strand/Program -> Grade Level -> Students
        const hierarchy = {};
        data.rows.forEach(student => {
            const dept = student.department || 'No Department';
            const strand = student.strand || 'No Strand';
            const program = student.program || 'No Program';
            const grade = student.grade_level || 'No Grade Level';
            
            // Determine subcategory (strand or program) based on department
            let subcategory;
            if (dept === 'Senior High School') {
                subcategory = strand !== 'No Strand' ? strand : 'All Strands';
            } else if (dept === 'Higher Education') {
                subcategory = program !== 'No Program' ? program : 'All Programs';
            } else {
                subcategory = 'General';
            }
            
            if (!hierarchy[dept]) hierarchy[dept] = {};
            if (!hierarchy[dept][subcategory]) hierarchy[dept][subcategory] = {};
            if (!hierarchy[dept][subcategory][grade]) hierarchy[dept][subcategory][grade] = [];
            
            hierarchy[dept][subcategory][grade].push(student);
        });
        
        let totalCount = 0;
        
        // Build accordion HTML
        let html = '';
        Object.keys(hierarchy).sort().forEach(dept => {
            const deptCount = Object.values(hierarchy[dept]).reduce((sum, sub) => 
                sum + Object.values(sub).reduce((s, students) => s + students.length, 0), 0);
            totalCount += deptCount;
            
            html += `
                <div class="mb-4 border rounded-lg overflow-hidden">
                    <div class="bg-gray-100 px-4 py-3 cursor-pointer flex justify-between items-center" onclick="toggleAccordion(this)">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-chevron-right transition-transform duration-200"></i>
                            <span class="font-semibold text-gray-800">${esc(dept)}</span>
                            <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded-full">${deptCount} students</span>
                        </div>
                        <button onclick="event.stopPropagation(); printDepartment('${esc(dept)}')" class="px-2 py-1 bg-primary text-white text-xs rounded hover:bg-primary-dark">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                    <div class="hidden">
            `;
            
            Object.keys(hierarchy[dept]).sort().forEach(subcategory => {
                const subCount = Object.values(hierarchy[dept][subcategory]).reduce((sum, students) => sum + students.length, 0);
                
                html += `
                    <div class="ml-4 border-l-2 border-gray-200">
                        <div class="bg-gray-50 px-4 py-2 cursor-pointer flex justify-between items-center" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-chevron-right transition-transform duration-200 text-sm"></i>
                                <span class="font-medium text-gray-700 text-sm">${esc(subcategory)}</span>
                                <span class="text-xs text-gray-400 bg-white px-2 py-0.5 rounded-full">${subCount} students</span>
                            </div>
                            <button onclick="event.stopPropagation(); printSubcategory('${esc(dept)}', '${esc(subcategory)}')" class="px-2 py-1 bg-primary text-white text-xs rounded hover:bg-primary-dark">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                        <div class="hidden ml-4">
                `;
                
                Object.keys(hierarchy[dept][subcategory]).sort().forEach(grade => {
                    const students = hierarchy[dept][subcategory][grade];
                    const gradeCount = students.length;
                    
                    html += `
                        <div class="mb-3">
                            <div class="bg-white px-4 py-2 border-b flex justify-between items-center">
                                <span class="font-medium text-gray-600 text-sm">${esc(grade)}</span>
                                <span class="text-xs text-gray-400">${gradeCount} students</span>
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left border">No.</th>
                                        <th class="px-4 py-2 text-left border">ID </th>
                                        <th class="px-4 py-2 text-left border">Name</th>
                                        <th class="px-4 py-2 text-left border">Gender</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                    `;
                    
                    students.forEach((student, index) => {
                        const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
                        html += `
                            <tr>
                                <td class="px-4 py-2 border">${index + 1}</td>
                                <td class="px-4 py-2 border">${esc(student.student_id || '—')}</td>
                                <td class="px-4 py-2 border">${esc(name)}</td>
                                <td class="px-4 py-2 border">${esc(student.gender || '—')}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        studentGroups.innerHTML = html;
        
        document.getElementById('grandTotal').textContent = totalCount;
        
        // Update subtitle
        let subtitle = 'All Students';
        if (department || strand || program || grade) {
            const filters = [];
            if (department) filters.push(department);
            if (strand) filters.push(strand);
            if (program) filters.push(program);
            if (grade) filters.push(grade);
            subtitle = filters.join(' - ');
        }
        document.getElementById('reportSubtitle').textContent = subtitle;
    })
    .catch(error => {
        console.error('Error fetching student list:', error);
        const maleBody = document.getElementById('maleBody');
        const femaleBody = document.getElementById('femaleBody');
        maleBody.innerHTML = '<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Error loading students. Please try again.</td></tr>';
        femaleBody.innerHTML = '<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Error loading students. Please try again.</td></tr>';
    });
}

function toggleAccordion(element) {
    const content = element.nextElementSibling;
    const icon = element.querySelector('i');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.add('rotate-90');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('rotate-90');
    }
}

function printDepartment(targetDepartment) {
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const program = document.getElementById('programFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    fetch(BASE + `&action=fetch&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&program=${encodeURIComponent(program)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        if (!data.rows || data.rows.length === 0) {
            Swal.fire('No Data', 'No students found for the selected filters.', 'warning');
            return;
        }
        
        // Filter students for the specific department
        const deptStudents = data.rows.filter(s => (s.department || 'No Department') === targetDepartment);
        
        if (deptStudents.length === 0) {
            Swal.fire('No Data', `No students found for ${targetDepartment}`, 'warning');
            return;
        }
        
        // Group students hierarchically for print: Department -> Strand/Program -> Grade Level -> Students
        const groups = {};
        deptStudents.forEach(student => {
            const dept = student.department || 'No Department';
            const strand = student.strand || 'No Strand';
            const program = student.program || 'No Program';
            const gradeLevel = student.grade_level || 'No Grade Level';
            
            let subcategory;
            if (dept === 'Senior High School') {
                subcategory = strand !== 'No Strand' ? strand : 'All Strands';
            } else if (dept === 'Higher Education') {
                subcategory = program !== 'No Program' ? program : 'All Programs';
            } else {
                subcategory = 'General';
            }
            
            if (!groups[dept]) groups[dept] = {};
            if (!groups[dept][subcategory]) groups[dept][subcategory] = {};
            if (!groups[dept][subcategory][gradeLevel]) groups[dept][subcategory][gradeLevel] = [];
            
            groups[dept][subcategory][gradeLevel].push(student);
        });
        
        // Generate print HTML
        let printHTML = `
            <html>
            <head>
                <title>Student List Report - ${targetDepartment}</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 30px; padding: 20px; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                    .header h1 { font-size: 36px; font-weight: bold; margin: 0 0 5px 0; }
                    .header h2 { font-size: 32px; font-weight: bold; margin: 5px 0; }
                    .header p { font-size: 24px; color: #666; margin: 2px 0; }
                    .group-header { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 28px; font-weight: bold; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #000; padding: 12px; text-align: left; font-size: 26px; }
                    th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                    .group-total { text-align: right; margin-top: 5px; font-size: 26px; font-weight: bold; }
                    .grand-total { text-align: right; margin-top: 20px; font-size: 32px; font-weight: bold; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>St. Rita's College of Balingasag</h1>
                    <p>Guidance and Counseling Office</p>
                    <h2>Student List Report - ${targetDepartment}</h2>
                    <p>As of ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
        `;
        
        let totalCount = 0;
        
        // Display each subcategory (strand/program)
        Object.keys(groups[targetDepartment]).sort().forEach(subcategory => {
            const subCount = Object.values(groups[targetDepartment][subcategory]).reduce((sum, students) => sum + students.length, 0);
            
            printHTML += `
                <div class="group-header">${subcategory} (${subCount} students)</div>
            `;
            
            // Display each grade level
            Object.keys(groups[targetDepartment][subcategory]).sort().forEach(grade => {
                const students = groups[targetDepartment][subcategory][grade];
                const gradeCount = students.length;
                
                printHTML += `
                    <div style="margin-left: 20px; margin-top: 10px; margin-bottom: 5px; font-weight: bold; font-size: 11px;">${grade} (${gradeCount} students)</div>
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No.</th>
                                <th width="25%">ID</th>
                                <th width="50%">Name</th>
                                <th width="20%">Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                students.forEach((student, index) => {
                    const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
                    printHTML += `
                        <tr>
                            <td style="text-align: center;">${index + 1}</td>
                            <td>${student.student_id || '—'}</td>
                            <td>${name}</td>
                            <td style="text-align: center;">${student.gender || '—'}</td>
                        </tr>
                    `;
                });
                
                printHTML += `
                        </tbody>
                    </table>
                `;
            });
        });
        
        printHTML += `
                <div class="grand-total">
                    Total: ${deptStudents.length}
                </div>
                
                <!-- Signature Section -->
                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 48px;">
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;"><?= htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['middle_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')) ?></p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($role))) ?></p>
                        <p style="font-size: 22px; color: #9ca3af; margin: 2px 0 0 0;">Guidance and Counseling Office</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;">_____________________</p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;">School Administrator</p>
                    </div>
                </div>
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.print();
    })
    .catch(error => {
        console.error('Error generating print view:', error);
        Swal.fire('Error', 'Failed to generate print view', 'error');
    });
}

function printSubcategory(department, subcategory) {
    const departmentFilter = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const program = document.getElementById('programFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    fetch(BASE + `&action=fetch&department=${encodeURIComponent(departmentFilter)}&strand=${encodeURIComponent(strand)}&program=${encodeURIComponent(program)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        if (!data.rows || data.rows.length === 0) {
            Swal.fire('No Data', 'No students found for the selected filters.', 'warning');
            return;
        }
        
        // Filter students for the specific department and subcategory
        const subStudents = data.rows.filter(s => {
            const dept = s.department || 'No Department';
            let sub;
            if (dept === 'Senior High School') {
                sub = s.strand !== 'No Strand' ? s.strand : 'All Strands';
            } else if (dept === 'Higher Education') {
                sub = s.program !== 'No Program' ? s.program : 'All Programs';
            } else {
                sub = 'General';
            }
            return dept === department && sub === subcategory;
        });
        
        if (subStudents.length === 0) {
            Swal.fire('No Data', `No students found for ${subcategory}`, 'warning');
            return;
        }
        
        // Group students by grade level
        const groups = {};
        subStudents.forEach(student => {
            const gradeLevel = student.grade_level || 'No Grade Level';
            if (!groups[gradeLevel]) groups[gradeLevel] = [];
            groups[gradeLevel].push(student);
        });
        
        // Generate print HTML
        let printHTML = `
            <html>
            <head>
                <title>Student List Report - ${subcategory}</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 30px; padding: 20px; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                    .header h1 { font-size: 36px; font-weight: bold; margin: 0 0 5px 0; }
                    .header h2 { font-size: 32px; font-weight: bold; margin: 5px 0; }
                    .header p { font-size: 24px; color: #666; margin: 2px 0; }
                    .group-header { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 28px; font-weight: bold; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #000; padding: 12px; text-align: left; font-size: 26px; }
                    th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                    .grand-total { text-align: right; margin-top: 20px; font-size: 32px; font-weight: bold; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>St. Rita's College of Balingasag</h1>
                    <p>Guidance and Counseling Office</p>
                    <h2>Student List Report - ${department} - ${subcategory}</h2>
                    <p>As of ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
        `;
        
        // Display each grade level
        Object.keys(groups).sort().forEach(grade => {
            const students = groups[grade];
            const gradeCount = students.length;
            
            printHTML += `
                <div class="group-header">${grade} (${gradeCount} students)</div>
                <table>
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th width="25%">ID </th>
                            <th width="50%">Name</th>
                            <th width="20%">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            students.forEach((student, index) => {
                const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
                printHTML += `
                    <tr>
                        <td style="text-align: center;">${index + 1}</td>
                        <td>${student.student_id || '—'}</td>
                        <td>${name}</td>
                        <td style="text-align: center;">${student.gender || '—'}</td>
                    </tr>
                `;
            });
            
            printHTML += `
                    </tbody>
                </table>
            `;
        });
        
        printHTML += `
                <div class="grand-total">
                    Total: ${subStudents.length}
                </div>
                
                <!-- Signature Section -->
                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 48px;">
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;"><?= htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['middle_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')) ?></p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($role))) ?></p>
                        <p style="font-size: 22px; color: #9ca3af; margin: 2px 0 0 0;">Guidance and Counseling Office</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;">_____________________</p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;">School Administrator</p>
                    </div>
                </div>
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.print();
    })
    .catch(error => {
        console.error('Error generating print view:', error);
        Swal.fire('Error', 'Failed to generate print view', 'error');
    });
}

function printStudentListReport() {
    const department = document.getElementById('departmentFilter').value;
    const strand = document.getElementById('strandFilter').value;
    const program = document.getElementById('programFilter').value;
    const grade = document.getElementById('gradeFilter').value;
    
    fetch(BASE + `&action=fetch&department=${encodeURIComponent(department)}&strand=${encodeURIComponent(strand)}&program=${encodeURIComponent(program)}&grade=${encodeURIComponent(grade)}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        if (!data.rows || data.rows.length === 0) {
            Swal.fire('No Data', 'No students found for the selected filters.', 'warning');
            return;
        }
        
        // Group students hierarchically for print: Department -> Strand/Program -> Grade Level -> Students
        const groups = {};
        data.rows.forEach(student => {
            const dept = student.department || 'No Department';
            const strand = student.strand || 'No Strand';
            const program = student.program || 'No Program';
            const grade = student.grade_level || 'No Grade Level';
            
            // Determine subcategory (strand or program) based on department
            let subcategory;
            if (dept === 'Senior High School') {
                subcategory = strand !== 'No Strand' ? strand : 'All Strands';
            } else if (dept === 'Higher Education') {
                subcategory = program !== 'No Program' ? program : 'All Programs';
            } else {
                subcategory = 'General';
            }
            
            if (!groups[dept]) groups[dept] = {};
            if (!groups[dept][subcategory]) groups[dept][subcategory] = {};
            if (!groups[dept][subcategory][grade]) groups[dept][subcategory][grade] = [];
            
            groups[dept][subcategory][grade].push(student);
        });
        
        // Build subtitle
        let subtitle = 'All Students';
        const filters = [];
        if (department) filters.push(department);
        if (strand) filters.push(strand);
        if (program) filters.push(program);
        if (grade) filters.push(grade);
        if (filters.length > 0) subtitle = filters.join(' - ');
        
        // Generate print HTML
        let printHTML = `
            <html>
            <head>
                <title>Student List Report</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 30px; padding: 20px; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                    .header h1 { font-size: 36px; font-weight: bold; margin: 0 0 5px 0; }
                    .header h2 { font-size: 32px; font-weight: bold; margin: 5px 0; }
                    .header p { font-size: 24px; color: #666; margin: 2px 0; }
                    .group-header { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 28px; font-weight: bold; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #000; padding: 12px; text-align: left; font-size: 26px; }
                    th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                    .group-total { text-align: right; margin-top: 5px; font-size: 26px; font-weight: bold; }
                    .grand-total { text-align: right; margin-top: 20px; font-size: 32px; font-weight: bold; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>St. Rita's College of Balingasag</h1>
                    <p>Guidance and Counseling Office</p>
                    <h2>Student List Report</h2>
                    <p>${subtitle}</p>
                    <p>As of ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
        `;
        
        let totalCount = 0;
        
        // Display each department
        Object.keys(groups).sort().forEach(dept => {
            const deptCount = Object.values(groups[dept]).reduce((sum, sub) => 
                sum + Object.values(sub).reduce((s, students) => s + students.length, 0), 0);
            totalCount += deptCount;
            
            printHTML += `
                <div class="group-header">${dept} (${deptCount} students)</div>
            `;
            
            // Display each subcategory (strand/program)
            Object.keys(groups[dept]).sort().forEach(subcategory => {
                const subCount = Object.values(groups[dept][subcategory]).reduce((sum, students) => sum + students.length, 0);
                
                printHTML += `
                    <div style="margin-left: 20px; margin-top: 15px; margin-bottom: 10px; font-weight: bold; font-size: 12px;">${subcategory} (${subCount} students)</div>
                `;
                
                // Display each grade level
                Object.keys(groups[dept][subcategory]).sort().forEach(grade => {
                    const students = groups[dept][subcategory][grade];
                    const gradeCount = students.length;
                    
                    printHTML += `
                        <div style="margin-left: 40px; margin-top: 10px; margin-bottom: 5px; font-weight: bold; font-size: 11px;">${grade} (${gradeCount} students)</div>
                        <table>
                            <thead>
                                <tr>
                                    <th width="5%">No.</th>
                                    <th width="25%">ID </th>
                                    <th width="50%">Name</th>
                                    <th width="20%">Gender</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    students.forEach((student, index) => {
                        const name = (student.last_name||'') + ', ' + (student.first_name||'') + (student.middle_name ? ' ' + student.middle_name : '');
                        printHTML += `
                            <tr>
                                <td style="text-align: center;">${index + 1}</td>
                                <td>${student.student_id || '—'}</td>
                                <td>${name}</td>
                                <td style="text-align: center;">${student.gender || '—'}</td>
                            </tr>
                        `;
                    });
                    
                    printHTML += `
                            </tbody>
                        </table>
                    `;
                });
            });
        });
        
        printHTML += `
                <div class="grand-total">
                    Grand Total: ${totalCount}
                </div>
                
                <!-- Signature Section -->
                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 48px;">
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;"><?= htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['middle_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')) ?></p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($role))) ?></p>
                        <p style="font-size: 22px; color: #9ca3af; margin: 2px 0 0 0;">Guidance and Counseling Office</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="height: 48px;"></div>
                        <div style="border-bottom: 1px solid #9ca3af; margin-bottom: 4px; width: 75%; margin-left: auto; margin-right: auto;"></div>
                        <p style="font-size: 26px; font-weight: 600; color: #1f2937; margin: 0;">_____________________</p>
                        <p style="font-size: 24px; color: #6b7280; margin: 0;">School Administrator</p>
                    </div>
                </div>
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.print();
    })
    .catch(error => {
        console.error('Error generating print view:', error);
        Swal.fire('Error', 'Failed to generate print view', 'error');
    });
}

// Initial load - fetch all students
document.getElementById('reportDate').textContent = 'As of ' + new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
fetchStudentList();
</script>
