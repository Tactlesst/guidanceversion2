<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session.php';
checkLogin();
$user_info = getUserInfo();
if (!in_array($user_info['role'], ['admin'])) { header("Location: layout.php"); exit(); }

$pds_id = $_GET['id'] ?? 0;
if (!$pds_id) { header("Location: layout.php?page=view_pds"); exit(); }

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

$pds_data = null;
try { 
    $stmt = $db->prepare("SELECT p.*, u.first_name, u.last_name, u.email FROM pds p JOIN users u ON p.user_id = u.id WHERE p.id = ? LIMIT 1"); 
    $stmt->execute([$pds_id]); 
    $pds_data = $stmt->fetch(PDO::FETCH_ASSOC); 
} catch (Exception $e) {}

if (!$pds_data) { 
    if ($is_ajax) {
        echo '<div class="p-4 text-center text-red-500">PDS not found</div>';
    } else {
        echo "<script>alert('PDS not found'); window.close();</script>"; 
    }
    exit(); 
}

$siblings = []; 
$organizations = [];
try { 
    $sib = $db->prepare("SELECT * FROM pds_siblings WHERE user_id = ? ORDER BY id"); 
    $sib->execute([$pds_data['user_id']]); 
    $siblings = $sib->fetchAll(PDO::FETCH_ASSOC); 
} catch (Exception $e) {}
try { 
    $org = $db->prepare("SELECT * FROM pds_organizations WHERE user_id = ? ORDER BY id"); 
    $org->execute([$pds_data['user_id']]); 
    $organizations = $org->fetchAll(PDO::FETCH_ASSOC); 
} catch (Exception $e) {}

$el = $pds_data['education_level'] ?? 'highschool';
$elLabel = ucfirst(str_replace('ed',' Education',$el));

// Declare the row function once
function row($label,$val){echo '<div class="flex border-b border-gray-200"><div class="w-1/3 py-2 px-3 bg-gray-50 font-medium text-sm text-gray-700">'.$label.'</div><div class="w-2/3 py-2 px-3 text-sm text-gray-800">'.$val.'</div></div>';}

// If AJAX request, return only the content
if ($is_ajax) {
    ?>
    <div class="max-w-4xl mx-auto p-6 bg-white border border-gray-300">
        <div class="text-center mb-6 border-b-2 border-gray-800 pb-4">
            <h1 class="text-2xl font-bold text-gray-800 uppercase tracking-wide">Personal Data Sheet</h1>
            <p class="text-sm text-gray-600 mt-1">Education Level: <span class="font-semibold"><?= $elLabel ?></span></p>
        </div>

        <?php
        $ri=0;
        ?>

        <!-- Personal -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Personal Information</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('Full Name',trim(($pds_data['first_name']??'').' '.($pds_data['middle_name']??'').' '.($pds_data['last_name']??'').(($pds_data['suffix']??'')?', '.$pds_data['suffix']:'')));
            row('Nickname',$pds_data['nickname']??'—');
            row('Gender',$pds_data['gender']??'—');
            row('Date of Birth',$pds_data['birth_date']??'—');
            row('Place of Birth',$pds_data['birth_place']??'—');
            row('Age',$pds_data['age']??'—');
            if($el==='highered') row('Civil Status',$pds_data['civil_status']??'—');
            row('Nationality',$pds_data['nationality']??'—');
            row('Religion',$pds_data['religion']??'—');
            row('Citizenship',$pds_data['citizenship']??'—');
            ?>
            </div>
        </div>

        <!-- Academic & Contact -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Academic & Contact Information</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('School Year',$pds_data['school_year']??'—');
            row('Grade Level',$pds_data['grade_level']??'—');
            if(in_array($el,['seniorhigh'])) row('Strand',$pds_data['strand']??'—');
            if($el==='highered'){row('Course',$pds_data['course']??'—');row('Year Level',$pds_data['year_level']??'—');row('Semester',$pds_data['semester']??'—');}
            row('Student Type',$pds_data['student_type']??'—');
            row('Home Address',$pds_data['home_address']??'—');
            if($el==='highered'){row('Street/Purok/Barangay',trim(($pds_data['city_street']??'').', '.($pds_data['city_purok']??'').', '.($pds_data['city_barangay']??'')));}
            row('Contact Number',$pds_data['contact_number']??'—');
            if($el==='highered') row('Email',$pds_data['email']??'—');
            ?>
            </div>
        </div>

        <!-- Family -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Family Background</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('Father',trim(($pds_data['father_surname']??'').', '.($pds_data['father_given_name']??'').', '.($pds_data['father_middle_name']??'')));
            row('Father Contact/Occupation',trim(($pds_data['father_contact']??'').' / '.($pds_data['father_occupation']??'')));
            row('Father Type/Status/Education',trim(($pds_data['father_type']??'').' / '.($pds_data['father_status']??'').' / '.($pds_data['father_education']??'')));
            row('Mother',trim(($pds_data['mother_surname']??'').', '.($pds_data['mother_given_name']??'').', '.($pds_data['mother_middle_name']??'')));
            row('Mother Contact/Occupation',trim(($pds_data['mother_contact']??'').' / '.($pds_data['mother_occupation']??'')));
            row('Mother Type/Status/Education',trim(($pds_data['mother_type']??'').' / '.($pds_data['mother_status']??'').' / '.($pds_data['mother_education']??'')));
            row('Guardian',trim(($pds_data['guardian_name']??'').' ('.($pds_data['guardian_relationship']??'').')'));
            row('Guardian Contact',$pds_data['guardian_contact']??'—');
            row('Parents Marital',$pds_data['parents_marital']??'—');
            row('Child Residing With',$pds_data['child_residing']??'—');
            row('Birth Order',$pds_data['birth_order']??'—');
            row('Family Income',$pds_data['family_income']??'—');
            row('Residence Type',$pds_data['residence_type']??'—');
            row('Languages',$pds_data['languages_spoken']??'—');
            ?>
            </div>
        </div>

        <!-- Siblings -->
        <?php if(!empty($siblings)):?>
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Siblings</div>
            <div class="border border-gray-300 border-t-0">
            <?php foreach($siblings as $s): $ri=0;?>
            <div class="flex border-b border-gray-200"><div class="w-1/3 py-2 px-3 bg-gray-50 font-medium text-sm text-gray-700">Name</div><div class="w-2/3 py-2 px-3 text-sm text-gray-800"><?= htmlspecialchars($s['sibling_name'])?></div></div>
            <div class="flex border-b border-gray-200"><div class="w-1/3 py-2 px-3 bg-gray-50 font-medium text-sm text-gray-700">Age / School / Status</div><div class="w-2/3 py-2 px-3 text-sm text-gray-800"><?= ($s['sibling_age']??'—').' / '.($s['sibling_school']??'—').' / '.($s['sibling_status']??'—')?></div></div>
            <?php endforeach;?>
            </div>
        </div>
        <?php endif;?>

        <!-- Health -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Health Information</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('Height / Weight',($pds_data['height']??'—').' cm / '.($pds_data['weight']??'—').' kg');
            row('Physical Condition',$pds_data['physical_condition']??'—');
            row('Health Problem',$pds_data['health_problem']??'—');
            row('Details',$pds_data['health_problem_details']??'—');
            ?>
            </div>
        </div>

        <!-- Emergency -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Emergency Contact</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('Name',$pds_data['emergency_contact_name']??'—');
            row('Relationship',$pds_data['emergency_relationship']??'—');
            row('Contact Number',$pds_data['emergency_contact_number']??'—');
            ?>
            </div>
        </div>

        <!-- Privacy -->
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Privacy Agreement</div>
            <div class="border border-gray-300 border-t-0">
            <?php $ri=0;
            row('Agreed',($pds_data['privacy_agreement']??0)==1?'<span class="text-green-600 font-semibold">Yes</span>':'<span class="text-red-500">No</span>');
            row('Date Agreed',$pds_data['privacy_agreement_date']??'—');
            ?>
            </div>
        </div>

        <div class="text-center mt-6 no-print">
            <button onclick="window.print()" class="bg-gray-800 text-white px-6 py-2 rounded text-sm font-semibold hover:bg-gray-700 transition-colors mr-2"><i class="fas fa-print mr-1"></i>Print</button>
        </div>
    </div>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PDS - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
    <style>
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .bg-gray-50 { background: white !important; }
            .shadow-sm { box-shadow: none !important; }
            .rounded-xl { border-radius: 0 !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
<div class="max-w-4xl mx-auto p-6 bg-white border border-gray-300 my-8">
    <div class="text-center mb-6 border-b-2 border-gray-800 pb-4 no-print">
        <h1 class="text-2xl font-bold text-gray-800 uppercase tracking-wide">Personal Data Sheet</h1>
        <p class="text-sm text-gray-600 mt-1">Education Level: <span class="font-semibold"><?= $elLabel ?></span></p>
    </div>

    <?php
    $ri=0;
    ?>

    <!-- Personal -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Personal Information</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('Full Name',trim(($pds_data['first_name']??'').' '.($pds_data['middle_name']??'').' '.($pds_data['last_name']??'').(($pds_data['suffix']??'')?', '.$pds_data['suffix']:'')));
        row('Nickname',$pds_data['nickname']??'—');
        row('Gender',$pds_data['gender']??'—');
        row('Date of Birth',$pds_data['birth_date']??'—');
        row('Place of Birth',$pds_data['birth_place']??'—');
        row('Age',$pds_data['age']??'—');
        if($el==='highered') row('Civil Status',$pds_data['civil_status']??'—');
        row('Nationality',$pds_data['nationality']??'—');
        row('Religion',$pds_data['religion']??'—');
        row('Citizenship',$pds_data['citizenship']??'—');
        ?>
        </div>
    </div>

    <!-- Academic & Contact -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Academic & Contact Information</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('School Year',$pds_data['school_year']??'—');
        row('Grade Level',$pds_data['grade_level']??'—');
        if(in_array($el,['seniorhigh'])) row('Strand',$pds_data['strand']??'—');
        if($el==='highered'){row('Course',$pds_data['course']??'—');row('Year Level',$pds_data['year_level']??'—');row('Semester',$pds_data['semester']??'—');}
        row('Student Type',$pds_data['student_type']??'—');
        row('Home Address',$pds_data['home_address']??'—');
        if($el==='highered'){row('Street/Purok/Barangay',trim(($pds_data['city_street']??'').', '.($pds_data['city_purok']??'').', '.($pds_data['city_barangay']??'')));}
        row('Contact Number',$pds_data['contact_number']??'—');
        if($el==='highered') row('Email',$pds_data['email']??'—');
        ?>
        </div>
    </div>

    <!-- Family -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Family Background</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('Father',trim(($pds_data['father_surname']??'').', '.($pds_data['father_given_name']??'').', '.($pds_data['father_middle_name']??'')));
        row('Father Contact/Occupation',trim(($pds_data['father_contact']??'').' / '.($pds_data['father_occupation']??'')));
        row('Father Type/Status/Education',trim(($pds_data['father_type']??'').' / '.($pds_data['father_status']??'').' / '.($pds_data['father_education']??'')));
        row('Mother',trim(($pds_data['mother_surname']??'').', '.($pds_data['mother_given_name']??'').', '.($pds_data['mother_middle_name']??'')));
        row('Mother Contact/Occupation',trim(($pds_data['mother_contact']??'').' / '.($pds_data['mother_occupation']??'')));
        row('Mother Type/Status/Education',trim(($pds_data['mother_type']??'').' / '.($pds_data['mother_status']??'').' / '.($pds_data['mother_education']??'')));
        row('Guardian',trim(($pds_data['guardian_name']??'').' ('.($pds_data['guardian_relationship']??'').')'));
        row('Guardian Contact',$pds_data['guardian_contact']??'—');
        row('Parents Marital',$pds_data['parents_marital']??'—');
        row('Child Residing With',$pds_data['child_residing']??'—');
        row('Birth Order',$pds_data['birth_order']??'—');
        row('Family Income',$pds_data['family_income']??'—');
        row('Residence Type',$pds_data['residence_type']??'—');
        row('Languages',$pds_data['languages_spoken']??'—');
        ?>
        </div>
    </div>

    <!-- Siblings -->
    <?php if(!empty($siblings)):?>
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Siblings</div>
        <div class="border border-gray-300 border-t-0">
        <?php foreach($siblings as $s): $ri=0;?>
        <div class="flex border-b border-gray-200"><div class="w-1/3 py-2 px-3 bg-gray-50 font-medium text-sm text-gray-700">Name</div><div class="w-2/3 py-2 px-3 text-sm text-gray-800"><?= htmlspecialchars($s['sibling_name'])?></div></div>
        <div class="flex border-b border-gray-200"><div class="w-1/3 py-2 px-3 bg-gray-50 font-medium text-sm text-gray-700">Age / School / Status</div><div class="w-2/3 py-2 px-3 text-sm text-gray-800"><?= ($s['sibling_age']??'—').' / '.($s['sibling_school']??'—').' / '.($s['sibling_status']??'—')?></div></div>
        <?php endforeach;?>
        </div>
    </div>
    <?php endif;?>

    <!-- Health -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Health Information</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('Height / Weight',($pds_data['height']??'—').' cm / '.($pds_data['weight']??'—').' kg');
        row('Physical Condition',$pds_data['physical_condition']??'—');
        row('Health Problem',$pds_data['health_problem']??'—');
        row('Details',$pds_data['health_problem_details']??'—');
        ?>
        </div>
    </div>

    <!-- Emergency -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Emergency Contact</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('Name',$pds_data['emergency_contact_name']??'—');
        row('Relationship',$pds_data['emergency_relationship']??'—');
        row('Contact Number',$pds_data['emergency_contact_number']??'—');
        ?>
        </div>
    </div>

    <!-- Privacy -->
    <div class="mb-6">
        <div class="bg-gray-100 px-4 py-2 border-t-2 border-l-2 border-r-2 border-gray-800 font-bold text-gray-800 text-sm uppercase">Privacy Agreement</div>
        <div class="border border-gray-300 border-t-0">
        <?php $ri=0;
        row('Agreed',($pds_data['privacy_agreement']??0)==1?'<span class="text-green-600 font-semibold">Yes</span>':'<span class="text-red-500">No</span>');
        row('Date Agreed',$pds_data['privacy_agreement_date']??'—');
        ?>
        </div>
    </div>

    <div class="text-center mt-6 no-print">
        <button onclick="window.print()" class="bg-gray-800 text-white px-6 py-2 rounded text-sm font-semibold hover:bg-gray-700 transition-colors mr-2"><i class="fas fa-print mr-1"></i>Print</button>
        <button onclick="window.close()" class="border-2 border-gray-300 text-gray-600 px-6 py-2 rounded text-sm font-semibold hover:bg-gray-50 transition-colors"><i class="fas fa-times mr-1"></i>Close</button>
    </div>
</div>
</body>
</html>
