<?php
$in_layout = defined('IN_LAYOUT');

if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    checkLogin();
    $user_info = getUserInfo();
    if (!in_array($user_info['role'], ['student', 'examinee'])) { header("Location: ../dashboard/layout.php"); exit(); }
    try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }
}

$uid = $user_info['id'];
$pds_data = null;
try { $stmt = $db->prepare("SELECT * FROM pds WHERE user_id = ? LIMIT 1"); $stmt->execute([$uid]); $pds_data = $stmt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
if (!$pds_data) { header("Location: " . ($in_layout ? "layout.php?page=fill_pds" : "fill_pds.php")); exit(); }

$siblings = []; $organizations = [];
try { $sib = $db->prepare("SELECT * FROM pds_siblings WHERE user_id = ? ORDER BY id"); $sib->execute([$uid]); $siblings = $sib->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
try { $org = $db->prepare("SELECT * FROM pds_organizations WHERE user_id = ? ORDER BY id"); $org->execute([$uid]); $organizations = $org->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

$el = $pds_data['education_level'] ?? 'highschool';
$elLabel = ucfirst(str_replace('ed',' Education',$el));

$fill_pds_url = $in_layout ? 'layout.php?page=fill_pds' : 'fill_pds.php';
$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';

if (!$in_layout) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PDS - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
</head>
<body class="min-h-screen bg-gray-50">
<?php include '../dashboard/sidebar.php'; ?>
<div class="lg:ml-64 pt-14 lg:pt-0">
<?php } ?>
<div class="max-w-4xl mx-auto p-5">
    <div class="flex items-center justify-between mb-5">
        <div><h1 class="text-xl font-bold text-primary"><i class="fas fa-file-alt mr-2"></i>My Personal Data Sheet</h1><p class="text-sm text-gray-400">Education Level: <span class="font-semibold text-primary"><?= $elLabel ?></span></p></div>
        <div class="flex gap-2">
            <a href="<?= $fill_pds_url ?>" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-edit mr-1"></i>Update</a>
            <a href="<?= $dashboard_url ?>" class="border-2 border-gray-200 text-gray-500 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors"><i class="fas fa-arrow-left mr-1"></i>Dashboard</a>
        </div>
    </div>

    <?php
    function row($label,$val){echo '<div class="py-2 px-4 '.($GLOBALS['ri']++%2===0?'bg-gray-50':'bg-white').' flex justify-between gap-4"><span class="text-xs font-medium text-gray-500">'.$label.'</span><span class="text-sm text-gray-800 text-right">'.$val.'</span></div>';}
    $ri=0;
    ?>

    <!-- Personal -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-user mr-2"></i>Personal Information</h3></div>
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

    <!-- Academic & Contact -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-graduation-cap mr-2"></i>Academic & Contact</h3></div>
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

    <!-- Family -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-users mr-2"></i>Family</h3></div>
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

    <!-- Siblings -->
    <?php if(!empty($siblings)):?>
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-people-arrows mr-2"></i>Siblings</h3></div>
        <?php foreach($siblings as $s): $ri=0;?>
        <div class="py-2 px-4 bg-gray-50 flex justify-between gap-4"><span class="text-xs font-medium text-gray-500">Name</span><span class="text-sm text-gray-800"><?= htmlspecialchars($s['sibling_name'])?></span></div>
        <div class="py-2 px-4 bg-white flex justify-between gap-4"><span class="text-xs font-medium text-gray-500">Age / School / Status</span><span class="text-sm text-gray-800"><?= ($s['sibling_age']??'—').' / '.($s['sibling_school']??'—').' / '.($s['sibling_status']??'—')?></span></div>
        <?php endforeach;?>
    </div>
    <?php endif;?>

    <!-- Health -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-heartbeat mr-2"></i>Health</h3></div>
        <?php $ri=0;
        row('Height / Weight',($pds_data['height']??'—').' cm / '.($pds_data['weight']??'—').' kg');
        row('Physical Condition',$pds_data['physical_condition']??'—');
        row('Health Problem',$pds_data['health_problem']??'—');
        row('Details',$pds_data['health_problem_details']??'—');
        ?>
    </div>

    <!-- Emergency -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Emergency Contact</h3></div>
        <?php $ri=0;
        row('Name',$pds_data['emergency_contact_name']??'—');
        row('Relationship',$pds_data['emergency_relationship']??'—');
        row('Contact Number',$pds_data['emergency_contact_number']??'—');
        ?>
    </div>

    <!-- Privacy -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
        <div class="bg-primary/5 px-4 py-3 border-b"><h3 class="font-bold text-primary text-sm"><i class="fas fa-shield-alt mr-2"></i>Privacy Agreement</h3></div>
        <?php $ri=0;
        row('Agreed',($pds_data['privacy_agreement']??0)==1?'<span class="text-green-600 font-semibold">Yes</span>':'<span class="text-red-500">No</span>');
        row('Date Agreed',$pds_data['privacy_agreement_date']??'—');
        ?>
    </div>

    <div class="text-center mt-5">
        <a href="<?= $fill_pds_url ?>" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors mr-2"><i class="fas fa-edit mr-1"></i>Update Information</a>
        <a href="<?= $dashboard_url ?>" class="border-2 border-gray-200 text-gray-500 px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
    </div>
</div>
<?php if (!$in_layout): ?>
</div>
</body>
</html>
<?php endif; ?>
