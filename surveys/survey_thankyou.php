<?php
// Survey Thank You / Results - Content partial for layout.php
// Session and database are already initialized in layout.php

// Only execute if included in layout
if (!defined('IN_LAYOUT')) {
    header('Location: ../dashboard/layout.php?page=survey_thankyou');
    exit();
}

$user_id = $uid;
$student_id = $_SESSION['student_id'] ?? null;

if (!$student_id) {
    echo '<div class="alert alert-danger">Student ID not found.</div>';
    return;
}

// Get student's survey results
$stmt = $db->prepare("SELECT * FROM multiple_intelligence_survey WHERE student_id = ?");
$stmt->execute([$student_id]);
$survey_results = $stmt->fetch();

// Define intelligence types with descriptions and icons
$intelligence_types = [
    1 => [
        'name' => 'Naturalist Intelligence',
        'description' => 'You have a strong connection with nature and excel at understanding patterns in the natural world.',
        'icon' => 'fas fa-leaf',
        'color' => '#22c55e',
        'traits' => ['Environmental awareness', 'Pattern recognition', 'Love for outdoors', 'Animal/plant knowledge']
    ],
    2 => [
        'name' => 'Musical Intelligence', 
        'description' => 'You have exceptional ability to understand and create music, rhythm, and sound.',
        'icon' => 'fas fa-music',
        'color' => '#8b5cf6',
        'traits' => ['Rhythm sensitivity', 'Musical composition', 'Sound patterns', 'Auditory processing']
    ],
    3 => [
        'name' => 'Logical-Mathematical Intelligence',
        'description' => 'You excel at logical reasoning, mathematics, and systematic problem-solving.',
        'icon' => 'fas fa-calculator',
        'color' => '#3b82f6',
        'traits' => ['Logical reasoning', 'Mathematical skills', 'Problem solving', 'Pattern analysis']
    ],
    4 => [
        'name' => 'Existential Intelligence',
        'description' => 'You think deeply about life\'s big questions and philosophical concepts.',
        'icon' => 'fas fa-infinity',
        'color' => '#6366f1',
        'traits' => ['Deep thinking', 'Philosophical inquiry', 'Life questions', 'Abstract concepts']
    ],
    5 => [
        'name' => 'Interpersonal Intelligence',
        'description' => 'You understand and work well with others, showing strong social skills.',
        'icon' => 'fas fa-users',
        'color' => '#f59e0b',
        'traits' => ['Social skills', 'Empathy', 'Communication', 'Leadership abilities']
    ],
    6 => [
        'name' => 'Bodily-Kinesthetic Intelligence',
        'description' => 'You learn through movement and have excellent body coordination and physical skills.',
        'icon' => 'fas fa-running',
        'color' => '#ef4444',
        'traits' => ['Physical coordination', 'Body awareness', 'Hands-on learning', 'Athletic abilities']
    ],
    7 => [
        'name' => 'Linguistic Intelligence',
        'description' => 'You have strong language skills and excel at reading, writing, and verbal communication.',
        'icon' => 'fas fa-book',
        'color' => '#10b981',
        'traits' => ['Language skills', 'Writing ability', 'Reading comprehension', 'Verbal communication']
    ],
    8 => [
        'name' => 'Intrapersonal Intelligence',
        'description' => 'You have strong self-awareness and understand your own emotions and motivations.',
        'icon' => 'fas fa-user-circle',
        'color' => '#8b5cf6',
        'traits' => ['Self-awareness', 'Emotional intelligence', 'Self-reflection', 'Personal insight']
    ],
    9 => [
        'name' => 'Spatial Intelligence',
        'description' => 'You think in images and have strong visual-spatial skills and artistic abilities.',
        'icon' => 'fas fa-palette',
        'color' => '#f97316',
        'traits' => ['Visual thinking', 'Spatial awareness', 'Artistic skills', 'Design abilities']
    ]
];

// Calculate top intelligence type if survey exists (only show the highest one)
$top_intelligence = null;
$top_score = 0;
if ($survey_results) {
    $scores = [];
    for ($i = 1; $i <= 9; $i++) {
        $score_key = "section{$i}_score";
        $scores[$i] = $survey_results[$score_key] ?? 0;
    }
    
    // Find the highest score
    arsort($scores);
    $top_intelligence_id = array_key_first($scores);
    $top_score = $scores[$top_intelligence_id];
    
    // Only set if there's a valid score
    if ($top_score > 0) {
        $top_intelligence = [
            'id' => $top_intelligence_id,
            'score' => $top_score
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey Completed - SRCB Guidance</title>
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
            overflow-y: auto;
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
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        /* Modern Thank You Page Styles */
        .thank-you-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }
        
        .success-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            text-align: center;
            position: relative;
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            animation: slideUp 0.6s ease-out;
        }
        
        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color), var(--success-color));
            border-radius: 16px 16px 0 0;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #4361ee, #3a86ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: bounceIn 0.8s ease-out 0.2s both;
            box-shadow: 0 0 0 15px rgba(67, 97, 238, 0.1);
        }
        
        .success-icon::before {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 2px solid rgba(67, 97, 238, 0.2);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .success-icon i {
            font-size: 2.5rem;
            color: white;
            z-index: 1;
        }
        
        .thank-you-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 0.75rem;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }
        
        .thank-you-subtitle {
            font-size: 1rem;
            color: var(--light-text);
            margin-bottom: 1.25rem;
            line-height: 1.6;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }
        
        .info-card-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .info-card-icon i {
            font-size: 1.25rem;
            color: white;
        }
        
        .info-card h4 {
            color: var(--dark-text);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }
        
        .info-card p {
            color: var(--light-text);
            margin: 0;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        
        .update-info {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            animation: fadeInUp 0.6s ease-out 0.55s both;
        }
        
        .update-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--warning-color), #f59e0b);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .update-icon i {
            font-size: 1.125rem;
            color: white;
        }
        
        .update-content h5 {
            color: var(--dark-text);
            font-weight: 600;
            margin-bottom: 0.375rem;
            font-size: 0.9375rem;
        }
        
        .update-content p {
            color: var(--light-text);
            margin: 0;
            line-height: 1.4;
            font-size: 0.875rem;
        }
        
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            justify-content: center;
            animation: fadeInUp 0.6s ease-out 0.6s both;
            margin-top: 1rem;
        }
        
        .btn-modern {
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 3px 12px rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(67, 97, 238, 0.3);
            color: white;
        }
        
        .btn-secondary-modern {
            background: linear-gradient(135deg, var(--accent-color), #0ea5e9);
            color: white;
            box-shadow: 0 3px 12px rgba(76, 201, 240, 0.25);
        }
        
        .btn-secondary-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(76, 201, 240, 0.3);
            color: white;
        }
        
        .btn-outline-modern {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.1);
        }
        
        .btn-outline-modern:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }
        
        /* Optimized Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            60% {
                opacity: 1;
                transform: scale(1.05);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }
        
        /* Large tablets and small desktops */
        @media (max-width: 1199.98px) {
            .success-card {
                max-width: 550px;
                padding: 2.25rem 1.75rem;
            }
            
            .thank-you-title {
                font-size: 2.125rem;
            }
            
            .action-buttons {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 0.875rem;
            }
        }
        
        /* Mobile responsive fixes */
        @media (max-width: 991.98px) {
            #content {
                margin-left: 0 !important;
            }
            
            .sidebar-collapsed #content {
                margin-left: 0 !important;
            }
            
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
            
            /* Menu adjustments for mobile */
            .sidebar-menu li a {
                padding: 15px 20px;
                font-size: 0.95rem;
            }
            
            /* Topbar mobile adjustments */
            .topbar {
                padding: 12px 20px;
            }
            
            .toggle-sidebar {
                width: 44px;
                height: 44px;
                font-size: 1.3rem;
            }
            
            /* Thank you page tablet adjustments */
            .thank-you-container {
                padding: 1.5rem 1rem;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .success-card {
                padding: 1.75rem 1.5rem;
                margin: 0 auto;
                border-radius: 12px;
                max-width: 500px;
                width: 100%;
            }
            
            .thank-you-title {
                font-size: 1.75rem;
            }
            
            .success-icon {
                width: 90px;
                height: 90px;
            }
            
            .success-icon::before {
                width: 110px;
                height: 110px;
            }
            
            .success-icon i {
                font-size: 2.25rem;
            }
            
            .intelligence-card {
                padding: 1rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .btn-modern {
                width: 100%;
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
            }
        }
        
        /* Standard tablets */
        @media (max-width: 768px) {
            .thank-you-container {
                padding: 1rem 0.75rem;
            }
            
            .success-card {
                padding: 1.5rem 1.25rem;
                max-width: 100%;
            }
            
            .thank-you-title {
                font-size: 1.625rem;
                margin-bottom: 0.625rem;
            }
            
            .thank-you-subtitle {
                font-size: 0.9375rem;
                margin-bottom: 1rem;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 0.875rem;
            }
            
            .success-icon::before {
                width: 100px;
                height: 100px;
            }
            
            .success-icon i {
                font-size: 2rem;
            }
            
            .intelligence-card {
                padding: 1rem;
                margin-bottom: 0.875rem;
            }
            
            .intelligence-icon {
                width: 40px;
                height: 40px;
                font-size: 1.125rem;
            }
            
            .intelligence-info h3 {
                font-size: 1rem;
            }
            
            .intelligence-description {
                font-size: 0.875rem;
                margin-bottom: 0;
            }
            
            .update-info {
                padding: 0.875rem;
                gap: 0.75rem;
                margin: 0.875rem 0;
            }
            
            .update-icon {
                width: 32px;
                height: 32px;
            }
            
            .update-content h5 {
                font-size: 0.875rem;
            }
            
            .update-content p {
                font-size: 0.8125rem;
            }
            
            .action-buttons {
                max-width: 320px;
                gap: 0.75rem;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 575.98px) {
            .container-fluid {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            .topbar {
                padding: 10px 15px;
            }
            
            .thank-you-container {
                padding: 0.75rem 0.5rem;
            }
            
            .success-card {
                padding: 1.25rem 1rem;
                max-width: 100%;
            }
            
            .thank-you-title {
                font-size: 1.5rem;
                line-height: 1.3;
                margin-bottom: 0.5rem;
            }
            
            .thank-you-subtitle {
                font-size: 0.875rem;
                line-height: 1.5;
                margin-bottom: 0.875rem;
            }
            
            .success-icon {
                width: 70px;
                height: 70px;
                margin-bottom: 0.75rem;
                box-shadow: 0 0 0 12px rgba(67, 97, 238, 0.1);
            }
            
            .success-icon::before {
                width: 90px;
                height: 90px;
            }
            
            .success-icon i {
                font-size: 1.75rem;
            }
            
            .intelligence-card {
                padding: 0.875rem;
                margin-bottom: 0.75rem;
                border-radius: 10px;
            }
            
            .intelligence-header {
                margin-bottom: 0.625rem;
                gap: 0.75rem;
            }
            
            .intelligence-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .intelligence-info h3 {
                font-size: 0.9375rem;
            }
            
            .intelligence-score {
                font-size: 0.8125rem;
            }
            
            .intelligence-description {
                font-size: 0.8125rem;
                line-height: 1.5;
                margin-bottom: 0;
            }
            
            .update-info {
                padding: 0.75rem;
                margin: 0.75rem 0;
                gap: 0.625rem;
                border-radius: 8px;
            }
            
            .update-icon {
                width: 30px;
                height: 30px;
            }
            
            .update-icon i {
                font-size: 0.875rem;
            }
            
            .update-content h5 {
                font-size: 0.8125rem;
                margin-bottom: 0.25rem;
            }
            
            .update-content p {
                font-size: 0.75rem;
                line-height: 1.4;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 0.625rem;
                max-width: 280px;
                margin: 1rem auto 0;
            }
            
            .btn-modern {
                padding: 0.75rem 1.25rem;
                font-size: 0.875rem;
                border-radius: 8px;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 479.98px) {
            .thank-you-container {
                padding: 0.5rem 0.375rem;
            }
            
            .success-card {
                padding: 1rem 0.875rem;
                max-width: 100%;
                border-radius: 10px;
            }
            
            .thank-you-title {
                font-size: 1.375rem;
                margin-bottom: 0.5rem;
            }
            
            .thank-you-subtitle {
                font-size: 0.8125rem;
                margin-bottom: 0.75rem;
            }
            
            .success-icon {
                width: 65px;
                height: 65px;
                margin-bottom: 0.625rem;
                box-shadow: 0 0 0 10px rgba(67, 97, 238, 0.1);
            }
            
            .success-icon::before {
                width: 85px;
                height: 85px;
            }
            
            .success-icon i {
                font-size: 1.625rem;
            }
            
            .intelligence-card {
                padding: 0.75rem;
                margin-bottom: 0.625rem;
            }
            
            .intelligence-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9375rem;
            }
            
            .intelligence-info h3 {
                font-size: 0.875rem;
            }
            
            .intelligence-score {
                font-size: 0.75rem;
            }
            
            .intelligence-description {
                font-size: 0.75rem;
                margin-bottom: 0;
            }
            
            .update-info {
                padding: 0.625rem;
                margin: 0.625rem 0;
                gap: 0.5rem;
            }
            
            .update-icon {
                width: 28px;
                height: 28px;
            }
            
            .update-content h5 {
                font-size: 0.75rem;
            }
            
            .update-content p {
                font-size: 0.6875rem;
            }
            
            .action-buttons {
                max-width: 250px;
                gap: 0.5rem;
                margin: 0.875rem auto 0;
            }
            
            .btn-modern {
                padding: 0.625rem 1rem;
                font-size: 0.8125rem;
            }
        }
        
        /* Ultra small devices */
        @media (max-width: 359.98px) {
            .thank-you-container {
                padding: 0.125rem;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            
            .success-card {
                padding: 1rem 0.5rem;
                max-width: 300px;
                width: calc(100% - 0.25rem);
                margin: 0 auto;
            }
            
            .thank-you-title {
                font-size: 1.25rem;
            }
            
            .thank-you-subtitle {
                font-size: 0.875rem;
            }
            
            .success-icon {
                width: 55px;
                height: 55px;
            }
            
            .success-icon i {
                font-size: 1.375rem;
            }
            
            .action-buttons {
                max-width: 220px;
            }
            
            .btn-modern {
                padding: 0.5rem 0.875rem;
                font-size: 0.75rem;
            }
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-menu::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        /* Intelligence Results Styles */
        .results-section {
            margin: 2rem 0;
        }
        
        .results-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 1.5rem;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .intelligence-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .intelligence-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }
        
        .intelligence-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .intelligence-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .intelligence-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .intelligence-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-text);
            margin: 0 0 0.25rem 0;
        }
        
        .intelligence-score {
            font-size: 0.875rem;
            color: var(--light-text);
            margin: 0;
        }
        
        .intelligence-description {
            color: var(--light-text);
            line-height: 1.5;
            margin-bottom: 0;
            font-size: 0.9375rem;
        }
        
        .rank-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
        }
        
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #92400e; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e5e5e5); color: #374151; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #d97706); color: white; }
        
        .no-results-message {
            text-align: center;
            color: var(--light-text);
            font-style: italic;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Include Modular Navigation Component -->
    <?php include '../dashboard/sidebar.php'; ?>

    <!-- Main Content (Topbar included in sidebar.php) -->
    
    <div class="thank-you-container">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="success-card">
                        <!-- Success Icon with Animation -->
                        <div class="success-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        
                        <!-- Main Title -->
                        <h1 class="thank-you-title">
                            <?php echo $top_intelligence ? 'Your Dominant Intelligence!' : 'Survey Completed!'; ?>
                        </h1>
                        
                        <!-- Subtitle -->
                        <p class="thank-you-subtitle">
                            <?php if ($top_intelligence): ?>
                                Discover your strongest intelligence type based on your survey responses.
                            <?php else: ?>
                                Thank you for completing the Multiple Intelligence Survey. Your responses have been successfully recorded and will be reviewed by our guidance counselors.
                            <?php endif; ?>
                        </p>
                        
                        <!-- Intelligence Results Section -->
                        <?php if ($top_intelligence): ?>
                        <div class="results-section">
                            <?php 
                            $intelligence_id = $top_intelligence['id'];
                            $score = $top_intelligence['score'];
                            $intelligence = $intelligence_types[$intelligence_id];
                            ?>
                            <div class="intelligence-card" style="border-top: 4px solid <?= $intelligence['color'] ?>">
                                <div class="intelligence-header">
                                    <div class="intelligence-icon" style="background: <?= $intelligence['color'] ?>">
                                        <i class="<?= $intelligence['icon'] ?>"></i>
                                    </div>
                                    <div class="intelligence-info">
                                        <h3><?= $intelligence['name'] ?></h3>
                                        <p class="intelligence-score">Score: <?= $score ?>/10 - 
                                            <?php 
                                            $percentage = ($score / 10) * 100;
                                            if ($percentage >= 80) echo "Very Strong";
                                            elseif ($percentage >= 60) echo "Strong"; 
                                            elseif ($percentage >= 40) echo "Moderate";
                                            else echo "Developing";
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <p class="intelligence-description">
                                    <?= $intelligence['description'] ?>
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="no-results-message">
                            <i class="fas fa-brain mb-2" style="font-size: 2rem; color: var(--primary-color);"></i>
                            <p>Complete the Multiple Intelligence Survey to see your personalized results!</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Survey Update Info -->
                        <div class="update-info">
                            <div class="update-icon">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="update-content">
                                <h5>Want to Update Your Responses?</h5>
                                <p>You can retake the survey anytime to update your results.</p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="../dashboard/" class="btn-modern btn-primary-modern">
                                <i class="fas fa-tachometer-alt me-2"></i>Back to Dashboard
                            </a>
                            <a href="multiple_intelligence_survey.php?update=1" class="btn-modern btn-secondary-modern">
                                <i class="fas fa-edit me-2"></i>Update Survey
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar functionality now handled by universal sidebar.js -->
</body>
</html>
