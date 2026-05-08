    <?php
// Multiple Intelligence Survey - Content partial for layout.php
// Session and database are already initialized in layout.php

// Only execute if included in layout
if (!defined('IN_LAYOUT')) {
    header('Location: ../dashboard/layout.php?page=multiple_intelligence_survey');
    exit();
}

$user_id = $uid;

// Get student's profile including student_id and grade level
$profile_stmt = $db->prepare("SELECT student_id, grade_level FROM student_profiles WHERE user_id = ?");
$profile_stmt->execute([$user_id]);
$student_profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_profile || empty($student_profile['student_id'])) {
    echo '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-5">';
    echo '<i class="fas fa-exclamation-triangle mr-2"></i>Student profile not found. Please complete your Personal Data Sheet first.';
    echo '</div>';
    echo '<a href="layout.php?page=fill_pds" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">';
    echo '<i class="fas fa-file-alt mr-2"></i>Complete Personal Data Sheet</a>';
    return;
}

$student_id = $student_profile['student_id'];
$grade_level = $student_profile['grade_level'];

// Define eligible grade levels for Multiple Intelligence Survey (Senior High School and Higher Education)
$eligible_grades = ['Grade 11', 'Grade 12', '1st Year College', '2nd Year College', '3rd Year College', '4th Year College'];

// Check if student is eligible based on grade level
if (!in_array($grade_level, $eligible_grades)) {
    echo '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-5">';
    echo '<i class="fas fa-exclamation-triangle mr-2"></i>This survey is only available for Senior High School (Grade 11-12) and College students.';
    echo '<br><small>Your current grade level: <strong>' . htmlspecialchars($grade_level) . '</strong></small>';
    echo '</div>';
    echo '<a href="layout.php?page=dashboard" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">';
    echo '<i class="fas fa-arrow-left mr-2"></i>Back to Dashboard</a>';
    return;
}

// Check if this is an update request
$is_update = isset($_GET['update']) && $_GET['update'] == '1';

// Check if survey already completed
$stmt = $db->prepare("SELECT * FROM multiple_intelligence_survey WHERE student_id = ?");
$stmt->execute([$student_id]);
$existing_survey = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_survey && !$is_update) {
    // Redirect to thank you page if already completed and not updating
    echo '<script>window.location.href = "layout.php?page=survey_thankyou";</script>';
    return;
}

// Handle form submission
if ($_POST && isset($_POST['submit_survey'])) {
    $scores = [];
    $responses = [];
    for ($i = 1; $i <= 9; $i++) {
        $section_key = "section{$i}";
        $scores[$i] = isset($_POST[$section_key]) ? count($_POST[$section_key]) : 0;
        // Store individual responses as comma-separated values
        $responses[$i] = isset($_POST[$section_key]) ? implode(',', $_POST[$section_key]) : '';
    }
    
    try {
        if ($existing_survey) {
            // Update existing survey
            $stmt = $db->prepare("UPDATE multiple_intelligence_survey SET 
                section1_score = ?, section2_score = ?, section3_score = ?, section4_score = ?, 
                section5_score = ?, section6_score = ?, section7_score = ?, section8_score = ?, 
                section9_score = ?, completed_at = NOW() 
                WHERE student_id = ?");
            
            $stmt->execute([
                $scores[1], $scores[2], $scores[3], $scores[4], 
                $scores[5], $scores[6], $scores[7], $scores[8], $scores[9], $student_id
            ]);
        } else {
            // Insert new survey
            $stmt = $db->prepare("INSERT INTO multiple_intelligence_survey 
                (student_id, section1_score, section2_score, section3_score, section4_score, 
                 section5_score, section6_score, section7_score, section8_score, section9_score) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $student_id, $scores[1], $scores[2], $scores[3], $scores[4], 
                $scores[5], $scores[6], $scores[7], $scores[8], $scores[9]
            ]);
        }
        
        // Redirect to thank you page after successful submission
        echo '<script>window.location.href = "layout.php?page=survey_thankyou";</script>';
        exit();
    } catch (PDOException $e) {
        $error_message = "Error saving survey: " . $e->getMessage();
    }
}

// Prepare existing data for form population if updating
$existing_responses = [];
if ($existing_survey && $is_update) {
    // Debug: Show what data we have
    echo "<!-- DEBUG: Existing survey data: " . print_r($existing_survey, true) . " -->";
    
    // Get individual responses from database
    for ($i = 1; $i <= 9; $i++) {
        $response_key = "section{$i}_responses";
        if (!empty($existing_survey[$response_key])) {
            $existing_responses[$i] = explode(',', $existing_survey[$response_key]);
        } else {
            // If no individual responses stored, try to simulate from scores
            $score_key = "section{$i}_score";
            $score = $existing_survey[$score_key] ?? 0;
            if ($score > 0) {
                // Create a simple array with sequential values based on score
                $existing_responses[$i] = range(1, $score);
            } else {
                $existing_responses[$i] = [];
            }
        }
    }
    
    // Debug: Show what responses we prepared
    echo "<!-- DEBUG: Prepared responses: " . print_r($existing_responses, true) . " -->";
}

$survey_sections = require __DIR__ . '/config/mi_questions.php';
?>
<link rel="stylesheet" href="../assets/css/multiple_intelligence_survey.css">

    <div class="w-full px-4">
        <div class="survey-container">
            <!-- Survey Header Section -->
            <div class="survey-header" id="surveyHeader" <?php echo (!$existing_survey && !$is_update) ? 'style="display: none;"' : ''; ?>>
                <h1 class="survey-title">
                    <?php echo $is_update ? 'Update Multiple Intelligence Survey' : 'Multiple Intelligence Survey'; ?>
                </h1>
                <p class="survey-subtitle">
                    <?php echo $is_update ? 'Update your responses by checking statements that accurately describe you.' : 'Complete each section by checking statements that accurately describe you.'; ?>
                </p>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$existing_survey && !$is_update): ?>
            <!-- No Data Card - Show when no survey data exists -->
            <div class="no-data-card">
                <div class="no-data-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h2 class="no-data-title">Ready to Discover Your Intelligence Profile?</h2>
                <p class="no-data-description">
                    Take our comprehensive Multiple Intelligence Survey to discover your unique learning style and cognitive strengths. This assessment will help our guidance counselors provide you with personalized recommendations for your academic and career development.
                </p>
                <button type="button" class="fill-survey-btn" onclick="startSurvey()">
                    <i class="fas fa-play-circle"></i>
                    Start Survey
                </button>
            </div>
            <?php endif; ?>
                        
            <form id="surveyForm" method="POST" <?php echo (!$existing_survey && !$is_update) ? 'style="display: none;"' : ''; ?>>
                <?php foreach ($survey_sections as $sectionNumber => $section): ?>
                    <?php include __DIR__ . '/partials/mi_section_card.php'; ?>
                <?php endforeach; ?>
            </form>
            
            <!-- Submit Section - Optimized Sticky Note (Outside form so always visible) -->
            <div class="submit-section" id="stickySubmit" <?php echo (!$existing_survey && !$is_update) ? 'style="display: none;"' : ''; ?>>
                <div class="sticky-content">
                    <button type="button" class="sticky-close-btn" onclick="hideStickyNote(event)" title="Hide sticky note">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="sticky-info">
                        <h4 style="margin: 0 0 0.25rem 0; color: var(--primary-color); font-size: 1.1rem; font-weight: 600;">
                            <i class="fas fa-clipboard-check" style="margin-right: 0.5rem; color: var(--success-color);"></i>
                            Complete Your Survey
                        </h4>
                        <p style="margin: 0; color: var(--light-text); font-size: 0.875rem; line-height: 1.4;">
                            Review your responses and submit when ready
                        </p>
                    </div>
                    <div class="sticky-actions">
                        <button type="button" onclick="goBack()" style="padding: 0.75rem 1.25rem; border: 2px solid #d1d5db; color: #6b7280; background: white; border-radius: 12px; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; display: inline-flex; align-items: center; white-space: nowrap; cursor: pointer;">
                            <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                            Back
                        </button>
                        <button type="button" onclick="submitSurvey()" class="submit-button" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; display: inline-flex; align-items: center; white-space: nowrap;">
                            <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>
                            Submit Survey
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Floating Action Button -->
            <button type="button" class="floating-action-btn" id="floatingActionBtn" onclick="showStickyNote()" title="Show submit options">
                <i class="fas fa-chevron-up"></i>
            </button>
        </div> <!-- Close survey-container -->
    </div> <!-- Close container-fluid -->

    </div> <!-- End content -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.MISurveyData = {
            hasExistingData: <?php echo $existing_survey ? 'true' : 'false'; ?>,
            surveyStarted: <?php echo ($existing_survey || $is_update) ? 'true' : 'false'; ?>,
            existingResponses: <?php echo json_encode($existing_responses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        };
    </script>
    <script src="../assets/js/multiple_intelligence_survey.js"></script>
