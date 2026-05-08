<?php
/**
 * Admin: Get Student PDS Data
 * 
 * AJAX endpoint to fetch student Personal Data Sheet information
 * Includes SES prediction data if available
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

// Prevent any output before JSON
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);
ob_start();

require_once '../config/database.php';
require_once '../includes/session.php';

ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check authentication and authorization
try {
    checkLogin();
    if (!in_array($_SESSION['role'], ['admin', 'counselor', 'guidance_advocate', 'super_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get and validate user_id
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Fetch college student PDS data with SES prediction
    $query = "SELECT 
                p.*,
                u.first_name, u.last_name, u.email as user_email,
                sp.student_id,
                ses.predicted_ses, ses.confidence_score, ses.prediction_date,
                CONCAT(p.first_name_he, ' ', 
                       CASE WHEN p.middle_name_he IS NOT NULL AND p.middle_name_he != '' 
                            THEN CONCAT(SUBSTRING(p.middle_name_he, 1, 1), '. ') 
                            ELSE '' 
                       END,
                       p.last_name_he) as full_name
              FROM pds_college p
              JOIN users u ON p.user_id = u.id
              LEFT JOIN student_profiles sp ON u.id = sp.user_id
              LEFT JOIN ses_predictions ses ON p.user_id = ses.user_id AND ses.is_latest = 1
              WHERE p.user_id = :user_id
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        // Calculate age if date of birth is available
        $age = null;
        if (!empty($student['date_of_birth_he'])) {
            $dob = new DateTime($student['date_of_birth_he']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        }
        
        // Format the response data
        $response = [
            'success' => true,
            'student' => [
                // Basic Information
                'user_id' => $student['user_id'],
                'student_id' => $student['student_id'],
                'full_name' => $student['full_name'],
                'first_name_he' => $student['first_name_he'],
                'middle_name_he' => $student['middle_name_he'],
                'last_name_he' => $student['last_name_he'],
                'nickname' => $student['nickname'],
                'student_photo' => $student['student_photo'],
                
                // Academic Information
                'course' => $student['course'],
                'year_level' => $student['year_level'],
                
                // Personal Information
                'sex_he' => $student['sex_he'],
                'date_of_birth_he' => $student['date_of_birth_he'],
                'age' => $age,
                'place_of_birth_he' => $student['place_of_birth_he'],
                'civil_status_he' => $student['civil_status_he'],
                'nationality' => $student['nationality'],
                'religion_he' => $student['religion_he'],
                
                // Contact Information
                'mobile_number' => $student['mobile_number'],
                'email_he' => $student['email_he'],
                'home_address_he' => $student['home_address_he'],
                
                // Family Information
                'father_given_name' => $student['father_given_name'],
                'father_surname' => $student['father_surname'],
                'father_occupation' => $student['father_occupation'],
                'mother_given_name' => $student['mother_given_name'],
                'mother_surname' => $student['mother_surname'],
                'mother_occupation' => $student['mother_occupation'],
                'guardian_name' => $student['guardian_name'],
                'guardian_occupation' => $student['guardian_occupation'] ?? null,
                'parents_marital' => $student['parents_marital'],
                
                // Socioeconomic Information
                'family_income' => $student['family_income'],
                'num_siblings' => $student['num_siblings'] ?? $student['sibling_type'] ?? null,
                
                // SES Prediction (if available)
                'predicted_ses' => $student['predicted_ses'] ?? 'N/A',
                'confidence_score' => $student['confidence_score'] ?? 0,
                'prediction_date' => $student['prediction_date'] ?? null
            ]
        ];
        
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching student PDS: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in get_student_pds: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred'
    ]);
}
