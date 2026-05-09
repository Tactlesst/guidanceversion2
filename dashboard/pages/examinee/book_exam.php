<?php
// Book Entrance Exam - Examinee page
// Loaded by layout.php - session/db already set up

if (!defined('IN_LAYOUT')) die('Direct access not allowed');

// Check if entrance exam is enabled
try {
    require_once __DIR__ . '/../../../classes/SystemSettings.php';
    $settings = new SystemSettings($db);
    if(!$settings->isEntranceExamEnabled()) {
        echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>Entrance exam booking is currently disabled.</div>';
        echo '<a href="layout.php?page=dashboard" class="btn btn-primary"><i class="fas fa-arrow-left mr-2"></i>Back to Dashboard</a>';
        return;
    }
} catch (Exception $e) {}

$success_message = '';
$error_message = '';

// Handle success message from redirect
if(isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Your entrance exam appointment has been booked successfully! Your selected slot is already confirmed.";
}

// Check if user already has completed entrance exam results
$completed_exam_query = "SELECT ea.id, ea.status, ea.preferred_date, ea.preferred_time,
                                ea.total_score, ea.qualified_grade, ea.updated_at
                         FROM entrance_exam_appointments ea
                         WHERE ea.user_id = ? 
                         AND ea.status = 'completed' 
                         AND (ea.total_score IS NOT NULL OR ea.qualified_grade IS NOT NULL)
                         ORDER BY ea.updated_at DESC
                         LIMIT 1";
$completed_exam_stmt = $db->prepare($completed_exam_query);
$completed_exam_stmt->execute([$uid]);
$completed_exam = $completed_exam_stmt->fetch(PDO::FETCH_ASSOC);

// If user has completed exam with results, redirect to results page
if($completed_exam) {
    echo '<script>window.location.href = "layout.php?page=view_exam_results&message=exam_already_completed";</script>';
    return;
}

// Check if user has an active appointment (confirmed or awaiting_results)
$active_exam_query = "SELECT ea.id, ea.status, ea.preferred_date, ea.preferred_time
                      FROM entrance_exam_appointments ea
                      WHERE ea.user_id = ? 
                      AND ea.status IN ('confirmed', 'awaiting_results')
                      ORDER BY ea.preferred_date ASC
                      LIMIT 1";
$active_exam_stmt = $db->prepare($active_exam_query);
$active_exam_stmt->execute([$uid]);
$active_exam = $active_exam_stmt->fetch(PDO::FETCH_ASSOC);
$has_active_exam = !empty($active_exam);

// Handle form submission
if($_POST && isset($_POST['book_appointment'])) {
    if($has_active_exam) {
        $error_message = "You already have an active entrance exam appointment. You cannot book a new appointment until your current appointment is completed or marked as missed.";
    } else {
        require_once __DIR__ . '/../../../classes/EntranceExam.php';
        $entrance_exam = new EntranceExam($db);
        
        $entrance_exam->user_id = $uid;
        $entrance_exam->preferred_date = $_POST['preferred_date'];
        $entrance_exam->preferred_time = $_POST['preferred_time'] ?? '';
        $entrance_exam->grade_level_applying = $_POST['grade_level_applying'];
        $entrance_exam->previous_school = $_POST['previous_school'];
        $entrance_exam->preferred_program = $_POST['preferred_program'] ?? null;

        // Validate date is not in the past
        if(strtotime($entrance_exam->preferred_date) < strtotime(date('Y-m-d'))) {
            $error_message = "Please select a future date for your appointment.";
        }

        if (empty($error_message) && empty($entrance_exam->preferred_time)) {
            $error_message = "Please select a time for your entrance exam appointment.";
        }

        // Check if user already has an appointment on this date
        if (empty($error_message)) {
            $existing_exam_check = "SELECT COUNT(*) as count FROM entrance_exam_appointments 
                                   WHERE user_id = ? AND preferred_date = ? 
                                   AND status IN ('confirmed', 'rescheduled', 'awaiting_results')";
            $existing_exam_stmt = $db->prepare($existing_exam_check);
            $existing_exam_stmt->execute([$uid, $entrance_exam->preferred_date]);
            $existing_exam_count = $existing_exam_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($existing_exam_count > 0) {
                $error_message = "You already have an entrance exam scheduled for " . date('F j, Y', strtotime($entrance_exam->preferred_date)) . ". You can only book one exam per day.";
            }
        }
        
        // If no errors, create the appointment
        if(empty($error_message)) {
            $appointment_id = $entrance_exam->create();
            if($appointment_id) {
                // Create notifications for staff
                require_once __DIR__ . '/../../../classes/Notification.php';
                require_once __DIR__ . '/../../../classes/NotificationService.php';
                $notification = new Notification($db);
                $notificationService = new NotificationService($db);
                
                $staff_query = "SELECT id FROM users WHERE role IN ('guidance_advocate', 'admin') AND is_active = 1";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->execute();
                
                while($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $applicant_name = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
                    $email_title = 'New Entrance Exam Application';
                    $email_message = "New entrance exam application from {$applicant_name}";
                    
                    $notificationService->notify(
                        (int)$staff['id'],
                        $email_title,
                        $email_message,
                        'info',
                        'entrance_exam_appointments',
                        (int)$appointment_id,
                        false,
                        null,
                        null,
                        'entrance_exam_application:' . (int)$appointment_id . ':staff:' . (int)$staff['id']
                    );
                }
                
                echo '<script>window.location.href = "layout.php?page=book_exam&success=1";</script>';
                return;
            } else {
                $error_message = "Failed to submit your application. Please try again.";
            }
        }
    }
}

// Get minimum date (today)
$min_date = date('Y-m-d');
?>

<h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-clipboard-list mr-2"></i>Book Entrance Exam</h1>

<?php if($success_message): ?>
<div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-5">
    <i class="fas fa-check-circle mr-2"></i><?= $success_message ?>
</div>
<?php endif; ?>

<?php if($error_message): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5">
    <i class="fas fa-exclamation-triangle mr-2"></i><?= $error_message ?>
</div>
<?php endif; ?>

<?php if($has_active_exam): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg mb-5">
    <i class="fas fa-info-circle mr-2"></i>You have an active entrance exam appointment scheduled for <strong><?= date('F j, Y', strtotime($active_exam['preferred_date'])) ?> at <?= date('g:i A', strtotime($active_exam['preferred_time'])) ?></strong>.
    <br><a href="layout.php?page=view_application" class="text-blue-600 underline mt-2 inline-block">View Application Status</a>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Entrance Exam Application Form</h2>
    
    <form method="POST" action="">
        <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Grade Level Applying <span class="text-red-500">*</span></label>
                <select name="grade_level_applying" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required>
                    <option value="">Select Grade Level</option>
                    <option value="Grade 7">Grade 7</option>
                    <option value="Grade 8">Grade 8</option>
                    <option value="Grade 9">Grade 9</option>
                    <option value="Grade 10">Grade 10</option>
                    <option value="Grade 11">Grade 11</option>
                    <option value="Grade 12">Grade 12</option>
                    <option value="1st Year College">1st Year College</option>
                    <option value="2nd Year College">2nd Year College</option>
                    <option value="3rd Year College">3rd Year College</option>
                    <option value="4th Year College">4th Year College</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Previous School <span class="text-red-500">*</span></label>
                <input type="text" name="previous_school" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Program (Optional)</label>
            <input type="text" name="preferred_program" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="e.g., BSIT, BSED, ABM">
        </div>
        
        <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Date <span class="text-red-500">*</span></label>
                <input type="date" name="preferred_date" min="<?= $min_date ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Time <span class="text-red-500">*</span></label>
                <select name="preferred_time" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required>
                    <option value="">Select Time</option>
                    <option value="08:00:00">8:00 AM</option>
                    <option value="09:00:00">9:00 AM</option>
                    <option value="10:00:00">10:00 AM</option>
                    <option value="11:00:00">11:00 AM</option>
                    <option value="13:00:00">1:00 PM</option>
                    <option value="14:00:00">2:00 PM</option>
                    <option value="15:00:00">3:00 PM</option>
                </select>
            </div>
        </div>
        
        <div class="flex gap-3 mt-6">
            <button type="submit" name="book_appointment" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-semibold" <?= $has_active_exam ? 'disabled' : '' ?>>
                <i class="fas fa-calendar-check mr-2"></i>Submit Application
            </button>
            <a href="layout.php?page=dashboard" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                <i class="fas fa-arrow-left mr-2"></i>Cancel
            </a>
        </div>
    </form>
</div>
