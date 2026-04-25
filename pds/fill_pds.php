<?php
require_once '../config/database.php';
require_once '../includes/session.php';
checkLogin();
$user_info = getUserInfo();
if (!in_array($user_info['role'], ['student', 'examinee'])) { header("Location: ../dashboard/index.php"); exit(); }
try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }

$student_profile = null;
try { $sp = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?"); $sp->execute([$user_info['id']]); $student_profile = $sp->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
if (!$student_profile || empty($student_profile['department']) || empty($student_profile['grade_level'])) { header("Location: ../profile/complete_profile.php?redirect=pds"); exit(); }

$user_data = null;
try { $ud = $db->prepare("SELECT * FROM users WHERE id = ?"); $ud->execute([$user_info['id']]); $user_data = $ud->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}

$dept = $student_profile['department'] ?? '';
$education_level = 'highschool';
if (strpos($dept, 'Higher') !== false || strpos($dept, 'College') !== false) $education_level = 'highered';
elseif (strpos($dept, 'Senior') !== false) $education_level = 'seniorhigh';
elseif (strpos($dept, 'Elementary') !== false) $education_level = 'elementary';

$pds_data = null;
try { $pds_stmt = $db->prepare("SELECT * FROM pds WHERE user_id = ? LIMIT 1"); $pds_stmt->execute([$user_info['id']]); $pds_data = $pds_stmt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}

$siblings = []; $organizations = [];
try { $sib = $db->prepare("SELECT * FROM pds_siblings WHERE user_id = ? ORDER BY id"); $sib->execute([$user_info['id']]); $siblings = $sib->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
try { $org = $db->prepare("SELECT * FROM pds_organizations WHERE user_id = ? ORDER BY id"); $org->execute([$user_info['id']]); $organizations = $org->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

function pdsVal($f, $fb = '') {
    global $pds_data, $user_info, $student_profile, $user_data;
    if (isset($pds_data[$f]) && $pds_data[$f] !== '' && $pds_data[$f] !== null) return $pds_data[$f];
    $auto = ['first_name'=>$user_info['first_name']??'','middle_name'=>$user_data['middle_name']??'','last_name'=>$user_info['last_name']??'','email'=>$user_info['email']??'','contact_number'=>$student_profile['contact_number']??'','nationality'=>'Filipino','grade_level'=>$student_profile['grade_level']??'','strand'=>$student_profile['strand']??'','course'=>$student_profile['program']??'','home_address'=>$student_profile['home_address']??''];
    return $auto[$f] ?? $fb;
}

$success_message = ''; $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        $fields = ['school_year','first_name','middle_name','last_name','suffix','nickname','gender','birth_date','birth_place','age','civil_status','religion','nationality','citizenship','citizenship_others','grade_level','strand','course','year_level','semester','student_type','home_address','city_street','city_purok','city_barangay','contact_number','email','emergency_contact_name','emergency_relationship','emergency_contact_number','father_surname','father_given_name','father_middle_name','father_contact','father_occupation','father_location','father_type','father_status','father_education','father_postgrad','father_specialization','mother_surname','mother_given_name','mother_middle_name','mother_contact','mother_occupation','mother_location','mother_type','mother_status','mother_education','mother_postgrad','mother_specialization','guardian_name','guardian_address','guardian_contact','guardian_relationship','guardian_relation','guardian_relation_others','parents_marital','child_residing','child_residing_others','birth_order','birth_order_others','sibling_type','relatives_at_home','relatives_others','total_relatives_at_home','family_income','residence_type','languages_spoken','financial_support','financial_support_others','leisure_activities','leisure_activities_others','special_talents','preschool_school','preschool_awards','preschool_year','gradeschool_school','gradeschool_awards','gradeschool_year','highschool_school','highschool_awards','highschool_year','seniorhigh_school','seniorhigh_awards','seniorhigh_year','height','weight','physical_condition','health_problem','health_problem_details','last_doctor_visit','doctor_visit_reason','general_condition','baptism_status','baptism_date','baptism_church','communion_status','communion_date','communion_church','confirmation_status','confirmation_date','confirmation_church','privacy_agreement'];

        $values = ['education_level' => $education_level];
        foreach ($fields as $f) {
            $val = $_POST[$f] ?? null; if ($val === null) continue;
            if ($f === 'nationality' && $val === 'Other' && !empty($_POST['nationality_other'])) $val = $_POST['nationality_other'];
            if ($f === 'religion' && $val === 'Other' && !empty($_POST['religion_other'])) $val = $_POST['religion_other'];
            if ($f === 'guardian_relation' && $val === 'Other' && !empty($_POST['guardian_relation_other'])) $val = $_POST['guardian_relation_other'];
            if ($f === 'child_residing' && $val === 'Other' && !empty($_POST['child_residing_other'])) $val = $_POST['child_residing_other'];
            $values[$f] = is_string($val) ? strip_tags(trim($val)) : $val;
        }
        if (!empty($values['privacy_agreement']) && $values['privacy_agreement'] == 1) $values['privacy_agreement_date'] = date('Y-m-d H:i:s');
        foreach ($values as $k => $v) { if ($v === '') $values[$k] = null; }

        if ($pds_data) {
            $set = []; $params = [];
            foreach ($values as $col => $val) { $set[] = "{$col} = ?"; $params[] = $val; }
            $params[] = $user_info['id'];
            $db->prepare("UPDATE pds SET " . implode(', ', $set) . " WHERE user_id = ?")->execute($params);
        } else {
            $values['user_id'] = $user_info['id'];
            $cols = array_keys($values); $ph = array_fill(0, count($values), '?');
            $db->prepare("INSERT INTO pds (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $ph) . ")")->execute(array_values($values));
        }

        try {
            $db->prepare("DELETE FROM pds_siblings WHERE user_id = ?")->execute([$user_info['id']]);
            $sn = $_POST['sibling_name'] ?? []; $sa = $_POST['sibling_age'] ?? []; $ss = $_POST['sibling_school'] ?? []; $sst = $_POST['sibling_status'] ?? []; $so = $_POST['sibling_occupation'] ?? [];
            $sib_ins = $db->prepare("INSERT INTO pds_siblings (user_id,sibling_name,sibling_age,sibling_school,sibling_status,sibling_occupation) VALUES (?,?,?,?,?,?)");
            foreach ($sn as $i => $n) { if (empty(trim($n))) continue; $sib_ins->execute([$user_info['id'],strip_tags(trim($n)),$sa[$i]??null,$ss[$i]??null,$sst[$i]??null,$so[$i]??null]); }
        } catch (Exception $e) {}

        try {
            $db->prepare("DELETE FROM pds_organizations WHERE user_id = ?")->execute([$user_info['id']]);
            $on = $_POST['org_name'] ?? []; $od = $_POST['org_designation'] ?? []; $oy = $_POST['org_year'] ?? [];
            $org_ins = $db->prepare("INSERT INTO pds_organizations (user_id,organization_name,designation,school_year) VALUES (?,?,?,?)");
            foreach ($on as $i => $n) { if (empty(trim($n))) continue; $org_ins->execute([$user_info['id'],strip_tags(trim($n)),$od[$i]??null,$oy[$i]??null]); }
        } catch (Exception $e) {}

        $db->commit(); $success_message = "PDS saved successfully!";
        $pds_stmt = $db->prepare("SELECT * FROM pds WHERE user_id = ? LIMIT 1"); $pds_stmt->execute([$user_info['id']]); $pds_data = $pds_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $db->rollBack(); $error_message = "Failed to save: " . $e->getMessage(); }
}

$nat_list = ['Filipino','American','Chinese','Japanese','Korean','Indian','British','Canadian','Australian','German','French','Spanish','Italian','Brazilian','Mexican','Singaporean','Malaysian','Indonesian','Thai','Vietnamese','Other'];
$rel_list = ['Roman Catholic','Iglesia ni Cristo','Baptist','Methodist','Seventh Day Adventist','Protestant','Muslim','Buddhist','Hindu','Jehovah\'s Witness','Lutheran','Anglican','Orthodox','Atheist/Agnostic','Other'];
$nv = pdsVal('nationality','Filipino'); $nio = !in_array($nv,$nat_list);
$rv = pdsVal('religion'); $rio = !in_array($rv,$rel_list);
function sel($field,$val) { return pdsVal($field)===$val ? 'selected' : ''; }
function inp($field) { return htmlspecialchars(pdsVal($field)); }

// Determine if PDS is considered "complete" (so we can show success modal from sidebar)
$required_fields = ['first_name','last_name','gender','grade_level','home_address','contact_number'];
if ($education_level === 'seniorhigh') $required_fields[] = 'strand';
if ($education_level === 'highered') {
    $required_fields[] = 'course';
    $required_fields[] = 'year_level';
    $required_fields[] = 'semester';
    $required_fields[] = 'email';
}
$pds_is_complete = true;
foreach ($required_fields as $rf) {
    $val = $pds_data[$rf] ?? '';
    if ($val === null || trim((string)$val) === '') { $pds_is_complete = false; break; }
}
if (($pds_data['privacy_agreement'] ?? 0) != 1) $pds_is_complete = false;

// Detect if loaded inside layout.php or standalone
$in_layout = defined('IN_LAYOUT');
$base_url = $in_layout ? 'layout.php' : '../dashboard/layout.php';
$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
$self_fill_url = $in_layout ? 'layout.php?page=fill_pds' : 'fill_pds.php';

if (!$in_layout && !isset($db)) {
    // Standalone mode — include session/db if not already loaded
    require_once '../config/database.php';
    require_once '../includes/session.php';
    checkLogin();
    $user_info = getUserInfo();
    if (!in_array($user_info['role'], ['student', 'examinee'])) { header("Location: ../dashboard/index.php"); exit(); }
    try { $db = (new Database())->getConnection(); } catch (Exception $e) { die("Database connection failed."); }
    $student_profile = null;
    try { $sp = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?"); $sp->execute([$user_info['id']]); $student_profile = $sp->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    if (!$student_profile || empty($student_profile['department']) || empty($student_profile['grade_level'])) { header("Location: ../profile/complete_profile.php?redirect=pds"); exit(); }
    $user_data = null;
    try { $ud = $db->prepare("SELECT * FROM users WHERE id = ?"); $ud->execute([$user_info['id']]); $user_data = $ud->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    $dept = $student_profile['department'] ?? '';
    $education_level = 'highschool';
    if (strpos($dept, 'Higher') !== false || strpos($dept, 'College') !== false) $education_level = 'highered';
    elseif (strpos($dept, 'Senior') !== false) $education_level = 'seniorhigh';
    elseif (strpos($dept, 'Elementary') !== false) $education_level = 'elementary';
    $pds_data = null;
    try { $pds_stmt = $db->prepare("SELECT * FROM pds WHERE user_id = ? LIMIT 1"); $pds_stmt->execute([$user_info['id']]); $pds_data = $pds_stmt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    $siblings = []; $organizations = [];
    try { $sib = $db->prepare("SELECT * FROM pds_siblings WHERE user_id = ? ORDER BY id"); $sib->execute([$user_info['id']]); $siblings = $sib->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    try { $org = $db->prepare("SELECT * FROM pds_organizations WHERE user_id = ? ORDER BY id"); $org->execute([$user_info['id']]); $organizations = $org->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    function pdsVal($f, $fb = '') {
        global $pds_data, $user_info, $student_profile, $user_data;
        if (isset($pds_data[$f]) && $pds_data[$f] !== '' && $pds_data[$f] !== null) return $pds_data[$f];
        $auto = ['first_name'=>$user_info['first_name']??'','middle_name'=>$user_data['middle_name']??'','last_name'=>$user_info['last_name']??'','email'=>$user_info['email']??'','contact_number'=>$student_profile['contact_number']??'','nationality'=>'Filipino','grade_level'=>$student_profile['grade_level']??'','strand'=>$student_profile['strand']??'','course'=>$student_profile['program']??'','home_address'=>$student_profile['home_address']??''];
        return $auto[$f] ?? $fb;
    }
    $success_message = ''; $error_message = '';
    // POST handling is below — but in standalone mode, we need to handle it here
    // Since the POST logic is at the top of this file, it's already handled
}

if (!$in_layout) {
    // Output full HTML wrapper for standalone mode
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDS - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="<?= $in_layout ? '../pds/wizard.js' : 'wizard.js' ?>" defer></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
<style>.step-panel{display:none}.step-panel.active{display:block}</style>
<?php if(!$in_layout): ?>
<style>
 .pds-swal{border-radius:18px;padding:28px 22px !important;}
 .pds-swal .swal2-title{margin-top:8px !important;font-size:20px !important;color:#111827 !important;}
 .pds-swal .swal2-html-container{margin:10px 0 0 0 !important;}
 .pds-swal-confirm{background:#0ea5e9 !important;border-radius:10px !important;padding:10px 18px !important;font-weight:700 !important;}
 .pds-swal-cancel{background:#4f46e5 !important;border-radius:10px !important;padding:10px 18px !important;font-weight:700 !important;}
 .pds-swal-actions{gap:10px !important;margin-top:18px !important;}
 .pds-intro-overlay{position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.35);display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none;}
 .pds-intro-card{width:380px;max-width:92vw;background:#fff;border-radius:16px;box-shadow:0 30px 80px rgba(2,6,23,.25);padding:22px;pointer-events:auto;}
 .pds-intro-icon{width:56px;height:56px;border-radius:9999px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;margin:0 auto;}
 .pds-intro-icon-inner{width:40px;height:40px;border-radius:9999px;background:#2563eb;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 25px rgba(37,99,235,.35)}
 .pds-intro-btn{border-radius:10px;padding:10px 14px;font-weight:700;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:8px;}
</style>
<?php endif; ?>
</head>
<body class="min-h-screen bg-gray-50">
<?php include '../dashboard/sidebar.php'; ?>
<div class="lg:ml-64 pt-14 lg:pt-0">
<?php } ?>
<?php $show_intro = !$success_message && !$pds_is_complete && empty($_GET['skip_intro']); ?>
<?php if($show_intro): ?>
<div class="pds-intro-overlay" id="pdsIntro">
    <div class="pds-intro-card">
        <div class="pds-intro-icon">
            <div class="pds-intro-icon-inner"><i class="fas fa-file-alt" style="color:#fff;font-size:16px"></i></div>
        </div>
        <h2 class="text-center text-base font-bold text-gray-900 mt-3">Personal Data Sheet</h2>
        <p class="text-center text-xs text-gray-500 mt-1">Your PDS is not complete yet. Continue filling up to submit.</p>
        <div class="grid grid-cols-2 gap-2 mt-5">
            <a href="<?= $dashboard_url ?>" class="pds-intro-btn border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"><i class="fas fa-arrow-left"></i>Back</a>
            <a href="<?= $self_fill_url . (strpos($self_fill_url,'?')!==false?'&':'?') ?>skip_intro=1" class="pds-intro-btn bg-primary text-white hover:bg-primary-dark transition-colors"><i class="fas fa-play"></i>Continue</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="max-w-4xl mx-auto p-5" id="pdsPage" style="<?= $show_intro ? 'visibility:hidden' : '' ?>">
    <div class="flex items-center justify-between mb-4">
        <div><h1 class="text-xl font-bold text-primary"><i class="fas fa-file-alt mr-2"></i>Personal Data Sheet</h1><p class="text-sm text-gray-400">Level: <span class="font-semibold text-primary"><?= ucfirst(str_replace('ed',' Education',$education_level)) ?></span></p></div>
        <?php if($pds_data):?><span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold"><i class="fas fa-check mr-1"></i>Saved</span><?php endif;?>
    </div>
    <?php if($success_message):?><script>
    Swal.fire({
        title: 'PDS Successfully Submitted',
        html: `
            <div style="display:flex;justify-content:center;margin-top:2px">
                <div style="width:74px;height:74px;border-radius:9999px;background:rgba(59,130,246,.12);display:flex;align-items:center;justify-content:center">
                    <div style="width:56px;height:56px;border-radius:9999px;background:#2563eb;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 25px rgba(37,99,235,.35)">
                        <i class="fas fa-check" style="color:white;font-size:22px"></i>
                    </div>
                </div>
            </div>
            <p style="color:#6b7280;font-size:13px;line-height:1.35;margin-top:12px">
                Great job! Your Personal Data Sheet has been submitted and is ready for review. You can view your information or make updates anytime.
            </p>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-eye"></i> View Details',
        cancelButtonText: '<i class="fas fa-pen-to-square"></i> Update Information',
        reverseButtons: true,
        allowOutsideClick: false,
        buttonsStyling: true,
        customClass: {
            popup: 'pds-swal',
            actions: 'pds-swal-actions',
            confirmButton: 'pds-swal-confirm',
            cancelButton: 'pds-swal-cancel'
        },
        backdrop: 'rgba(15,23,42,.35)'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= $in_layout ? "layout.php?page=view_pds" : "view_pds.php" ?>';
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    </script><?php endif;?>
    <?php if(!$success_message && $pds_data && $pds_is_complete && empty($_GET['skip_modal'])):?><script>
    // Hide page contents so the background looks blank while the modal is open
    const pdsPage = document.getElementById('pdsPage');
    if (pdsPage) pdsPage.style.visibility = 'hidden';
    // Add style to allow sidebar clicks through modal backdrop
    const style = document.createElement('style');
    style.textContent = '.swal2-container { pointer-events: none !important; } .swal2-popup { pointer-events: auto !important; }';
    document.head.appendChild(style);
    Swal.fire({
        title: 'PDS Successfully Submitted',
        html: `
            <div style="display:flex;justify-content:center;margin-top:2px">
                <div style="width:74px;height:74px;border-radius:9999px;background:rgba(59,130,246,.12);display:flex;align-items:center;justify-content:center">
                    <div style="width:56px;height:56px;border-radius:9999px;background:#2563eb;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 25px rgba(37,99,235,.35)">
                        <i class="fas fa-check" style="color:white;font-size:22px"></i>
                    </div>
                </div>
            </div>
            <p style="color:#6b7280;font-size:13px;line-height:1.35;margin-top:12px">
                Great job! Your Personal Data Sheet has been submitted and is ready for review. You can view your information or make updates anytime.
            </p>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-eye"></i> View Details',
        cancelButtonText: '<i class="fas fa-pen-to-square"></i> Update Information',
        reverseButtons: true,
        allowOutsideClick: false,
        buttonsStyling: true,
        customClass: {
            popup: 'pds-swal',
            actions: 'pds-swal-actions',
            confirmButton: 'pds-swal-confirm',
            cancelButton: 'pds-swal-cancel'
        },
        backdrop: 'rgba(15,23,42,.35)'
    }).then((result) => {
        style.remove();
        if (result.isConfirmed) {
            window.location.href = '<?= $in_layout ? "layout.php?page=view_pds" : "view_pds.php" ?>';
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            const sep = '<?= strpos($self_fill_url,'?') !== false ? '&' : '?' ?>';
            window.location.href = '<?= $self_fill_url ?>' + sep + 'skip_modal=1';
        } else {
            // If user closed modal, restore page
            if (pdsPage) pdsPage.style.visibility = 'visible';
        }
    });
    </script><?php endif;?>
    <?php if($error_message):?><div class="bg-red-50 text-red-600 rounded-lg px-4 py-3 mb-4 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message)?></div><?php endif;?>
    <!-- STEPPER -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
        <div class="flex items-center justify-between">
            <?php $sl=[1=>'Personal',2=>'Academic',3=>'Family',4=>'Siblings & Ed',5=>'Health',6=>'Review']; for($si=1;$si<=6;$si++):?>
            <div class="flex flex-col items-center flex-1 cursor-pointer" onclick="goStep(<?=$si?>)">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold mb-1 sc<?=$si?> <?= $si===1?'bg-primary text-white':'bg-gray-200 text-gray-400' ?>"><?=$si?></div>
                <span class="text-[10px] font-medium sl<?=$si?> <?= $si===1?'text-primary':'text-gray-400' ?> hidden md:block"><?=$sl[$si]?></span>
            </div>
            <?php if($si<6):?><div class="flex-1 h-0.5 bg-gray-200 mt-[-16px] sline<?=$si?>"></div><?php endif; endfor;?>
        </div>
    </div>
    <form method="POST" id="pdsForm">

    <!-- STEP 1: PERSONAL -->
    <div class="step-panel active" data-step="1">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-user mr-2"></i>Personal Information</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">First Name *</label><input type="text" name="first_name" value="<?= inp('first_name')?>" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Middle Name</label><input type="text" name="middle_name" value="<?= inp('middle_name')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Last Name *</label><input type="text" name="last_name" value="<?= inp('last_name')?>" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Suffix</label><input type="text" name="suffix" value="<?= inp('suffix')?>" placeholder="Jr., Sr." class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nickname</label><input type="text" name="nickname" value="<?= inp('nickname')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Gender *</label><select name="gender" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Male" <?= sel('gender','Male')?>>Male</option><option value="Female" <?= sel('gender','Female')?>>Female</option></select></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Date of Birth</label><input type="date" name="birth_date" value="<?= inp('birth_date')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Place of Birth</label><input type="text" name="birth_place" value="<?= inp('birth_place')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Age</label><input type="number" name="age" value="<?= inp('age')?>" min="5" max="100" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <?php if($education_level==='highered'):?><div><label class="block text-xs font-medium text-gray-500 mb-1">Civil Status</label><select name="civil_status" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Single" <?= sel('civil_status','Single')?>>Single</option><option value="Married" <?= sel('civil_status','Married')?>>Married</option><option value="Separated" <?= sel('civil_status','Separated')?>>Separated</option><option value="Widowed" <?= sel('civil_status','Widowed')?>>Widowed</option></select></div><?php endif;?>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nationality</label><select name="nationality" id="natSel" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><?php foreach($nat_list as $n):?><option value="<?=$n?>" <?=(!$nio&&$nv===$n)?'selected':''?>><?=$n?></option><?php endforeach;?></select><input type="text" id="natOther" name="nationality_other" value="<?=$nio?htmlspecialchars($nv):''?>" placeholder="Specify" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none mt-2 <?=$nio?'':'hidden'?>"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Religion</label><select name="religion" id="relSel" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><?php foreach($rel_list as $r):?><option value="<?=$r?>" <?=(!$rio&&$rv===$r)?'selected':''?>><?=$r?></option><?php endforeach;?></select><input type="text" id="relOther" name="religion_other" value="<?=$rio?htmlspecialchars($rv):''?>" placeholder="Specify" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none mt-2 <?=$rio?'':'hidden'?>"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Citizenship</label><select name="citizenship" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Filipino" <?= sel('citizenship','Filipino')?>>Filipino</option><option value="Dual Citizen" <?= sel('citizenship','Dual Citizen')?>>Dual Citizen</option><option value="Foreign" <?= sel('citizenship','Foreign')?>>Foreign</option></select></div>
            </div>
        </div>
    </div>

    <!-- STEP 2: ACADEMIC & CONTACT -->
    <div class="step-panel" data-step="2">
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-graduation-cap mr-2"></i>Academic Information</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Student ID</label><input type="text" value="<?= htmlspecialchars($student_profile['student_id']??'')?>" readonly class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm bg-gray-50 outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">School Year</label><input type="text" name="school_year" value="<?= inp('school_year')?>" placeholder="2025-2026" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Grade Level</label><input type="text" name="grade_level" value="<?= inp('grade_level')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <?php if($education_level==='seniorhigh'):?><div><label class="block text-xs font-medium text-gray-500 mb-1">Strand</label><input type="text" name="strand" value="<?= inp('strand')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div><?php endif;?>
                <?php if($education_level==='highered'):?>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Course</label><input type="text" name="course" value="<?= inp('course')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Year Level</label><input type="text" name="year_level" value="<?= inp('year_level')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Semester</label><select name="semester" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="1st Semester" <?= sel('semester','1st Semester')?>>1st</option><option value="2nd Semester" <?= sel('semester','2nd Semester')?>>2nd</option></select></div>
                <?php endif;?>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Student Type</label><select name="student_type" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Old Student" <?= sel('student_type','Old Student')?>>Old</option><option value="New Student" <?= sel('student_type','New Student')?>>New</option><option value="Transferee" <?= sel('student_type','Transferee')?>>Transferee</option><option value="Returnee" <?= sel('student_type','Returnee')?>>Returnee</option></select></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-phone mr-2"></i>Contact</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Home Address</label><input type="text" name="home_address" value="<?= inp('home_address')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Contact Number</label><input type="tel" name="contact_number" value="<?= inp('contact_number')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <?php if($education_level==='highered'):?>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Street</label><input type="text" name="city_street" value="<?= inp('city_street')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Purok</label><input type="text" name="city_purok" value="<?= inp('city_purok')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Barangay</label><input type="text" name="city_barangay" value="<?= inp('city_barangay')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Email</label><input type="email" name="email" value="<?= inp('email')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <?php endif;?>
            </div>
        </div>
    </div>

    <!-- STEP 3: FAMILY -->
    <div class="step-panel" data-step="3">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-users mr-2"></i>Family Information</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-blue-50/50 rounded-lg p-4"><p class="text-xs font-semibold text-primary mb-3">Father</p><div class="space-y-2">
                    <div class="grid grid-cols-3 gap-2"><input type="text" name="father_surname" value="<?= inp('father_surname')?>" placeholder="Surname" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="father_given_name" value="<?= inp('father_given_name')?>" placeholder="Given" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="father_middle_name" value="<?= inp('father_middle_name')?>" placeholder="Middle" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div>
                    <div class="grid grid-cols-2 gap-2"><input type="tel" name="father_contact" value="<?= inp('father_contact')?>" placeholder="Contact" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="father_occupation" value="<?= inp('father_occupation')?>" placeholder="Occupation" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div>
                    <div class="grid grid-cols-3 gap-2"><select name="father_type" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Type</option><option value="Living" <?= sel('father_type','Living')?>>Living</option><option value="Deceased" <?= sel('father_type','Deceased')?>>Deceased</option></select><select name="father_status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Status</option><option value="Living Together" <?= sel('father_status','Living Together')?>>Together</option><option value="Separated" <?= sel('father_status','Separated')?>>Separated</option><option value="OFW" <?= sel('father_status','OFW')?>>OFW</option></select><select name="father_education" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Education</option><option value="Elementary" <?= sel('father_education','Elementary')?>>Elem</option><option value="High School" <?= sel('father_education','High School')?>>HS</option><option value="College" <?= sel('father_education','College')?>>College</option><option value="Post Graduate" <?= sel('father_education','Post Graduate')?>>PostGrad</option></select></div>
                    <input type="text" name="father_location" value="<?= inp('father_location')?>" placeholder="Location (if OFW)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                </div></div>
                <div class="bg-pink-50/50 rounded-lg p-4"><p class="text-xs font-semibold text-primary mb-3">Mother</p><div class="space-y-2">
                    <div class="grid grid-cols-3 gap-2"><input type="text" name="mother_surname" value="<?= inp('mother_surname')?>" placeholder="Surname (Maiden)" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="mother_given_name" value="<?= inp('mother_given_name')?>" placeholder="Given" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="mother_middle_name" value="<?= inp('mother_middle_name')?>" placeholder="Middle" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div>
                    <div class="grid grid-cols-2 gap-2"><input type="tel" name="mother_contact" value="<?= inp('mother_contact')?>" placeholder="Contact" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="mother_occupation" value="<?= inp('mother_occupation')?>" placeholder="Occupation" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div>
                    <div class="grid grid-cols-3 gap-2"><select name="mother_type" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Type</option><option value="Living" <?= sel('mother_type','Living')?>>Living</option><option value="Deceased" <?= sel('mother_type','Deceased')?>>Deceased</option></select><select name="mother_status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Status</option><option value="Living Together" <?= sel('mother_status','Living Together')?>>Together</option><option value="Separated" <?= sel('mother_status','Separated')?>>Separated</option><option value="OFW" <?= sel('mother_status','OFW')?>>OFW</option></select><select name="mother_education" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Education</option><option value="Elementary" <?= sel('mother_education','Elementary')?>>Elem</option><option value="High School" <?= sel('mother_education','High School')?>>HS</option><option value="College" <?= sel('mother_education','College')?>>College</option><option value="Post Graduate" <?= sel('mother_education','Post Graduate')?>>PostGrad</option></select></div>
                    <input type="text" name="mother_location" value="<?= inp('mother_location')?>" placeholder="Location (if OFW)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                </div></div>
            </div>
            <div class="mt-4 bg-amber-50/50 rounded-lg p-4"><p class="text-xs font-semibold text-primary mb-3">Guardian</p><div class="grid md:grid-cols-4 gap-3">
                <input type="text" name="guardian_name" value="<?= inp('guardian_name')?>" placeholder="Name" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                <input type="text" name="guardian_relationship" value="<?= inp('guardian_relationship')?>" placeholder="Relationship" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                <input type="tel" name="guardian_contact" value="<?= inp('guardian_contact')?>" placeholder="Contact" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
                <input type="text" name="guardian_address" value="<?= inp('guardian_address')?>" placeholder="Address" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none">
            </div></div>
            <div class="grid md:grid-cols-3 gap-4 mt-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Parents Marital</label><select name="parents_marital" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Married" <?= sel('parents_marital','Married')?>>Married</option><option value="Legally Separated" <?= sel('parents_marital','Legally Separated')?>>Separated</option><option value="Widowed" <?= sel('parents_marital','Widowed')?>>Widowed</option><option value="Living Together" <?= sel('parents_marital','Living Together')?>>Together</option></select></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Child Residing With</label><select name="child_residing" id="crSel" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Both Parents" <?= sel('child_residing','Both Parents')?>>Both</option><option value="Father Only" <?= sel('child_residing','Father Only')?>>Father</option><option value="Mother Only" <?= sel('child_residing','Mother Only')?>>Mother</option><option value="Guardian" <?= sel('child_residing','Guardian')?>>Guardian</option><option value="Relatives" <?= sel('child_residing','Relatives')?>>Relatives</option><option value="Other" <?= sel('child_residing','Other')?>>Other</option></select><input type="text" id="crOther" name="child_residing_other" placeholder="Specify" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none mt-2 hidden"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Birth Order</label><input type="number" name="birth_order" value="<?= inp('birth_order')?>" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Relatives at Home</label><input type="number" name="total_relatives_at_home" value="<?= inp('total_relatives_at_home')?>" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Family Income</label><select name="family_income" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Below P10,000" <?= sel('family_income','Below P10,000')?>>Below P10k</option><option value="P 10,001 - P15,000" <?= sel('family_income','P 10,001 - P15,000')?>>P10k-15k</option><option value="P 15,001 - P20,000" <?= sel('family_income','P 15,001 - P20,000')?>>P15k-20k</option><option value="P 20,001 - P25,000" <?= sel('family_income','P 20,001 - P25,000')?>>P20k-25k</option><option value="Above P30,000" <?= sel('family_income','Above P30,000')?>>Above P30k</option></select></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Residence Type</label><select name="residence_type" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="owned" <?= sel('residence_type','owned')?>>Owned</option><option value="rented" <?= sel('residence_type','rented')?>>Rented</option><option value="living with relatives" <?= sel('residence_type','living with relatives')?>>With Relatives</option></select></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Languages</label><input type="text" name="languages_spoken" value="<?= inp('languages_spoken')?>" placeholder="Filipino, English" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
            </div>
        </div>
    </div>
    <!-- STEP 4: SIBLINGS & ED HISTORY -->
    <div class="step-panel" data-step="4">
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
            <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-primary"><i class="fas fa-people-arrows mr-2"></i>Siblings</h3><button type="button" onclick="addSib()" class="text-primary text-xs font-semibold hover:underline"><i class="fas fa-plus mr-1"></i>Add</button></div>
            <div id="sibC" class="space-y-3"><?php if(!empty($siblings)): foreach($siblings as $s):?><div class="sib-row grid grid-cols-6 gap-2 items-end bg-gray-50 rounded-lg p-3"><input type="text" name="sibling_name[]" value="<?= htmlspecialchars($s['sibling_name'])?>" placeholder="Name" class="col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="number" name="sibling_age[]" value="<?= $s['sibling_age']??''?>" placeholder="Age" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="sibling_school[]" value="<?= htmlspecialchars($s['sibling_school']??'')?>" placeholder="School" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><select name="sibling_status[]" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Status</option><option value="Studying" <?= ($s['sibling_status']??'')==='Studying'?'selected':''?>>Studying</option><option value="Working" <?= ($s['sibling_status']??'')==='Working'?'selected':''?>>Working</option><option value="Married" <?= ($s['sibling_status']??'')==='Married'?'selected':''?>>Married</option></select><button type="button" onclick="this.closest('.sib-row').remove()" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button></div><?php endforeach; endif;?></div>
            <div id="noSib" class="<?= !empty($siblings)?'hidden':''?> text-center text-gray-400 text-sm py-4">No siblings added</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-school mr-2"></i>Educational History</h3>
            <div class="space-y-3">
                <?php foreach(['preschool'=>'Pre-School','gradeschool'=>'Grade School','highschool'=>'High School'] as $p=>$l):?><div class="bg-gray-50 rounded-lg p-3"><p class="text-xs font-semibold text-gray-500 mb-2"><?=$l?></p><div class="grid grid-cols-3 gap-2"><input type="text" name="<?=$p?>_school" value="<?= inp($p.'_school')?>" placeholder="School" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="<?=$p?>_awards" value="<?= inp($p.'_awards')?>" placeholder="Awards" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="<?=$p?>_year" value="<?= inp($p.'_year')?>" placeholder="Year" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div></div><?php endforeach;?>
                <?php if(in_array($education_level,['seniorhigh','highered'])):?><div class="bg-gray-50 rounded-lg p-3"><p class="text-xs font-semibold text-gray-500 mb-2">Senior High</p><div class="grid grid-cols-3 gap-2"><input type="text" name="seniorhigh_school" value="<?= inp('seniorhigh_school')?>" placeholder="School" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="seniorhigh_awards" value="<?= inp('seniorhigh_awards')?>" placeholder="Awards" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="seniorhigh_year" value="<?= inp('seniorhigh_year')?>" placeholder="Year" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div></div><?php endif;?>
            </div>
        </div>
    </div>

    <!-- STEP 5: HEALTH & SACRAMENTS -->
    <div class="step-panel" data-step="5">
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-heartbeat mr-2"></i>Health</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Height (cm)</label><input type="text" name="height" value="<?= inp('height')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Weight (kg)</label><input type="text" name="weight" value="<?= inp('weight')?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Condition</label><select name="physical_condition" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Normal" <?= sel('physical_condition','Normal')?>>Normal</option><option value="With Disability" <?= sel('physical_condition','With Disability')?>>Disability</option><option value="With Health Condition" <?= sel('physical_condition','With Health Condition')?>>Condition</option></select></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Health Problem?</label><select name="health_problem" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="No" <?= sel('health_problem','No')?>>No</option><option value="Yes" <?= sel('health_problem','Yes')?>>Yes</option></select></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium text-gray-500 mb-1">Details</label><textarea name="health_problem_details" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none resize-none"><?= inp('health_problem_details')?></textarea></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-church mr-2"></i>Sacraments (Optional)</h3>
            <div class="space-y-3">
                <?php foreach(['baptism'=>'Baptism','communion'=>'First Communion','confirmation'=>'Confirmation'] as $k=>$l):?><div class="bg-gray-50 rounded-lg p-3"><p class="text-xs font-semibold text-gray-500 mb-2"><?=$l?></p><div class="grid grid-cols-3 gap-2"><select name="<?=$k?>_status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><option value="">Status</option><option value="Yes" <?= sel($k.'_status','Yes')?>>Yes</option><option value="No" <?= sel($k.'_status','No')?>>No</option></select><input type="date" name="<?=$k?>_date" value="<?= inp($k.'_date')?>" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="<?=$k?>_church" value="<?= inp($k.'_church')?>" placeholder="Church" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"></div></div><?php endforeach;?>
            </div>
        </div>
    </div>

    <!-- STEP 6: ORGS, EMERGENCY, PRIVACY -->
    <div class="step-panel" data-step="6">
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
            <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-primary"><i class="fas fa-users-cog mr-2"></i>Organizations</h3><button type="button" onclick="addOrg()" class="text-primary text-xs font-semibold hover:underline"><i class="fas fa-plus mr-1"></i>Add</button></div>
            <div id="orgC" class="space-y-3"><?php if(!empty($organizations)): foreach($organizations as $o):?><div class="org-row grid grid-cols-4 gap-2 items-end bg-gray-50 rounded-lg p-3"><input type="text" name="org_name[]" value="<?= htmlspecialchars($o['organization_name'])?>" placeholder="Organization" class="col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><input type="text" name="org_designation[]" value="<?= htmlspecialchars($o['designation']??'')?>" placeholder="Role" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none"><div class="flex gap-2"><input type="text" name="org_year[]" value="<?= htmlspecialchars($o['school_year']??'')?>" placeholder="Year" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary outline-none flex-1"><button type="button" onclick="this.closest('.org-row').remove()" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button></div></div><?php endforeach; endif;?></div>
            <div id="noOrg" class="<?= !empty($organizations)?'hidden':''?> text-center text-gray-400 text-sm py-4">No organizations added</div>
            <div class="grid md:grid-cols-2 gap-4 mt-4">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Leisure Activities</label><textarea name="leisure_activities" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none resize-none"><?= inp('leisure_activities')?></textarea></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Special Talents</label><textarea name="special_talents" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none resize-none"><?= inp('special_talents')?></textarea></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Financial Support</label><select name="financial_support" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none"><option value="">Select</option><option value="Parents" <?= sel('financial_support','Parents')?>>Parents</option><option value="Scholarship" <?= sel('financial_support','Scholarship')?>>Scholarship</option><option value="Self-supporting" <?= sel('financial_support','Self-supporting')?>>Self</option><option value="Relatives" <?= sel('financial_support','Relatives')?>>Relatives</option></select></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
            <h3 class="font-bold text-primary mb-4"><i class="fas fa-exclamation-circle mr-2"></i>Emergency Contact</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <input type="text" name="emergency_contact_name" value="<?= inp('emergency_contact_name')?>" placeholder="Name" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none">
                <input type="text" name="emergency_relationship" value="<?= inp('emergency_relationship')?>" placeholder="Relationship" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none">
                <input type="tel" name="emergency_contact_number" value="<?= inp('emergency_contact_number')?>" placeholder="Contact #" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-primary outline-none">
            </div>
        </div>
        <div class="bg-blue-50 rounded-xl p-4 flex items-start gap-3">
            <input type="checkbox" name="privacy_agreement" value="1" <?= (pdsVal('privacy_agreement')==1)?'checked':''?> id="privChk" class="mt-1 w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
            <label for="privChk" class="text-sm text-gray-700">I certify the information is true and authorize SRCB Guidance Office to process this data per the Data Privacy Act of 2012.</label>
        </div>
    </div>

    <!-- NAVIGATION -->
    <div class="flex gap-3 mt-4">
        <a href="<?= $dashboard_url ?>" id="backBtn" class="flex-1 text-center py-3 rounded-lg border-2 border-gray-200 text-gray-500 font-semibold text-sm hover:bg-gray-50 transition-colors"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
        <button type="button" id="prevBtn" onclick="prevStep()" class="hidden flex-1 py-3 rounded-lg border-2 border-primary text-primary font-semibold text-sm hover:bg-primary hover:text-white transition-colors"><i class="fas fa-chevron-left mr-1"></i>Previous</button>
        <button type="button" id="nextBtn" onclick="nextStep()" class="flex-1 py-3 rounded-lg bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-colors">Next <i class="fas fa-chevron-right ml-1"></i></button>
        <button type="submit" id="submitBtn" class="hidden flex-1 py-3 rounded-lg bg-green-600 text-white font-semibold text-sm hover:bg-green-700 transition-colors"><i class="fas fa-save mr-1"></i>Save PDS</button>
    </div>
    </form>
</div>
<?php if (!$in_layout): ?>
</div>
</body>
</html>
<?php endif; ?>
