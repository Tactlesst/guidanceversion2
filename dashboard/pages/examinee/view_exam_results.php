<?php
// View Exam Results - Examinee page
// Loaded by layout.php - session/db already set up

if (!defined('IN_LAYOUT')) die('Direct access not allowed');

require_once __DIR__ . '/../../classes/EntranceExamResults.php';
$exam_results = new EntranceExamResults($db);

// Get student's exam results
$results_stmt = $exam_results->getResultsByUserId($uid);
$results = $results_stmt->fetch(PDO::FETCH_ASSOC);

// Handle messages from URL parameters
$message = isset($_GET['message']) ? $_GET['message'] : null;
$info_message = '';

if($message == 'exam_already_completed') {
    $info_message = "You have already completed your entrance examination. You cannot book another appointment as this is a one-time exam. Your results are displayed below.";
}

if(!$results) {
    echo '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-5">';
    echo '<i class="fas fa-exclamation-triangle mr-2"></i>No entrance exam results found.';
    echo '</div>';
    echo '<a href="layout.php?page=dashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">';
    echo '<i class="fas fa-arrow-left"></i>Back to Dashboard</a>';
    return;
}

// Determine department based on grade level
$is_college_header = strpos($results['grade_level_applying'], 'College') !== false || 
                    strpos($results['grade_level_applying'], 'Year') !== false;
$is_senior_high = strpos($results['grade_level_applying'], 'Grade 11') !== false || 
                 strpos($results['grade_level_applying'], 'Grade 12') !== false;

if ($is_college_header) {
    $department_name = 'Higher Education Department';
} elseif ($is_senior_high) {
    $department_name = 'Senior High School Department';
} else {
    $department_name = 'Basic Education Department';
}

$is_college = strpos($results['grade_level_applying'], 'College') !== false || 
             strpos($results['grade_level_applying'], 'Year') !== false;

$is_basic_ed = false;
for ($i = 1; $i <= 10; $i++) {
    if (strpos($results['grade_level_applying'], 'Grade ' . $i) !== false) {
        $is_basic_ed = true;
        break;
    }
}
?>

<h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-certificate mr-2"></i>Entrance Exam Results</h1>

<?php if($info_message): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg mb-5">
    <i class="fas fa-info-circle mr-2"></i><?= $info_message ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <!-- Header -->
    <div class="bg-white border-b-2 border-gray-200 p-6 text-center">
        <div class="flex justify-center items-center gap-4 mb-3">
            <img src="../assets/images/srcblogo.png" alt="SRCB Logo" class="w-16 h-16">
            <div>
                <h3 class="text-lg font-bold text-gray-800">St. Rita's College of Balingasag</h3>
                <h5 class="text-sm font-semibold text-gray-600"><?= $department_name ?></h5>
                <h6 class="text-xs text-gray-500">GUIDANCE CENTER</h6>
            </div>
            <img src="../assets/images/logo.jpg" alt="Guidance Logo" class="w-16 h-16">
        </div>
        <h4 class="text-base font-bold text-gray-800">ENTRANCE EXAM RESULT</h4>
    </div>

    <div class="p-6">
        <!-- Basic Information -->
        <div class="bg-gray-50 rounded-lg p-4 mb-5">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm mb-2"><strong>Date:</strong> <?= date('m-d-Y', strtotime($results['exam_date'])) ?></p>
                    <p class="text-sm mb-2"><strong>Name:</strong> <?= htmlspecialchars($results['first_name'] . ' ' . $results['last_name']) ?></p>
                    <p class="text-sm mb-2"><strong>Previous School:</strong> <?= htmlspecialchars($results['previous_school']) ?></p>
                    <?php if($is_college): ?>
                    <p class="text-sm mb-2"><strong>Preferred Program:</strong> <?= htmlspecialchars(!empty($results['preferred_program']) ? $results['preferred_program'] : 'N/A') ?></p>
                    <p class="text-sm mb-2"><strong>Qualified Program:</strong> <?= htmlspecialchars(!empty($results['qualified_program']) ? $results['qualified_program'] : 'N/A') ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-sm mb-2"><strong>Date of Examination:</strong> <?= date('m-d-Y', strtotime($results['exam_date'])) ?></p>
                    <?php if(!$is_college && !$is_basic_ed): ?>
                    <p class="text-sm mb-2"><strong>Grade Level Applied:</strong> <?= htmlspecialchars($results['grade_level_applying']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Test Scores Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-5">
            <div class="grid grid-cols-4 gap-2 p-3 bg-primary text-white text-sm font-bold">
                <div>Test Taken</div>
                <div class="text-center">Test Score</div>
                <div class="text-center">Stanine</div>
                <div class="text-center">Interpretation</div>
            </div>
            
            <div class="grid grid-cols-4 gap-2 p-3 border-b border-gray-200">
                <div class="font-bold">OLSAT Level <?= $results['olsat_level'] ?> Form <?= $results['olsat_form'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-6 bg-gray-50 border-b border-gray-200 text-sm">
                <div>• <strong>Verbal Abilities</strong></div>
                <div class="text-center"><strong><?= $results['verbal_abilities_score'] ?></strong></div>
                <div class="text-center"><strong><?= $results['verbal_stanine'] ?? 'N/A' ?></strong></div>
                <div class="text-center"><strong><?= $results['verbal_interpretation'] ?? 'N/A' ?></strong></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-10 border-b border-gray-200 text-sm">
                <div>Verbal Comprehension</div>
                <div class="text-center"><?= $results['verbal_comprehension_score'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-10 border-b border-gray-200 text-sm">
                <div>Verbal Reasoning</div>
                <div class="text-center"><?= $results['verbal_reasoning_score'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-6 bg-gray-50 border-b border-gray-200 text-sm">
                <div>• <strong>Non-Verbal Abilities</strong></div>
                <div class="text-center"><strong><?= $results['nonverbal_abilities_score'] ?></strong></div>
                <div class="text-center"><strong><?= $results['nonverbal_stanine'] ?? 'N/A' ?></strong></div>
                <div class="text-center"><strong><?= $results['nonverbal_interpretation'] ?? 'N/A' ?></strong></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-10 border-b border-gray-200 text-sm">
                <div>Figural Reasoning</div>
                <div class="text-center"><?= $results['figural_reasoning_score'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-2 pl-10 border-b border-gray-200 text-sm">
                <div>Quantitative Reasoning</div>
                <div class="text-center"><?= $results['quantitative_reasoning_score'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-3 bg-yellow-100 border-b border-gray-200">
                <div class="font-bold">Total Score (out of <?= $results['total_items'] ?> items)</div>
                <div class="text-center font-bold"><?= $results['total_score'] ?></div>
                <div class="text-center"></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-3 bg-gray-200 border-b border-gray-200">
                <div class="font-bold">Overall Stanine</div>
                <div class="text-center"></div>
                <div class="text-center font-bold"><?= $results['stanine_score'] ?></div>
                <div class="text-center"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 p-3 bg-gray-200">
                <div class="font-bold">Overall Interpretation</div>
                <div class="text-center"></div>
                <div class="text-center"></div>
                <div class="text-center font-bold"><?= $results['interpretation'] ?></div>
            </div>
        </div>

        <!-- Qualification Results -->
        <div class="bg-gray-50 rounded-lg p-5 text-center mb-5">
            <h5 class="text-lg font-bold mb-3">Congratulations!</h5>
            <?php if(!empty($results['qualified_grade'])): ?>
                <div class="inline-block bg-green-500 text-white px-6 py-3 rounded-full font-bold mb-3">
                    <i class="fas fa-check-circle mr-2"></i>QUALIFIED
                </div>
                <?php 
                $is_college_qualified = strpos($results['qualified_grade'], 'College') !== false || 
                                       strpos($results['qualified_grade'], 'Year') !== false;
                ?>
                <?php if($is_college_qualified && !empty($results['qualified_program'])): ?>
                    <p class="text-sm">You are qualified for admission at SRCB for the SY <strong><?= $results['school_year'] ?></strong>.</p>
                <?php else: ?>
                    <?php
                    $is_basic_ed_qualified = false;
                    for ($i = 1; $i <= 10; $i++) {
                        if (strpos($results['qualified_grade'], 'Grade ' . $i) !== false) {
                            $is_basic_ed_qualified = true;
                            break;
                        }
                    }
                    ?>
                    <p class="text-sm">You are qualified for admission as a <strong><?= $results['qualified_grade'] ?></strong> Student<?php 
                        if(strpos($results['qualified_grade'], 'Grade 11') !== false || strpos($results['qualified_grade'], 'Grade 12') !== false) {
                            echo ' to the <strong>Academic Strand of ' . $results['academic_strand'] . '</strong>';
                        } elseif (!$is_basic_ed_qualified && $results['academic_strand']) {
                            echo ' to the <strong>Academic Level of ' . $results['academic_strand'] . '</strong>';
                        }
                    ?> at SRCB for the SY <strong><?= $results['school_year'] ?></strong>.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="inline-block bg-red-500 text-white px-6 py-3 rounded-full font-bold mb-3">
                    <i class="fas fa-times-circle mr-2"></i>NOT QUALIFIED
                </div>
                <p class="text-sm">Unfortunately, you did not meet the qualification requirements for admission at this time.</p>
            <?php endif; ?>
        </div>

        <!-- Testing Information -->
        <?php if(!empty($results['testing_in_charge'])): ?>
        <div class="text-center mt-6">
            <div class="inline-block">
                <div class="border-t-2 border-gray-800 pt-1" style="width: 200px;">
                    <p class="text-sm font-bold"><?= htmlspecialchars($results['testing_in_charge']) ?></p>
                    <p class="text-xs text-gray-500">Testing in Charge</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex gap-3 mt-6 justify-center">
            <button onclick="window.print()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-semibold">
                <i class="fas fa-print mr-2"></i>Print Results
            </button>
            <a href="layout.php?page=dashboard" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    button, a { display: none !important; }
}
</style>
