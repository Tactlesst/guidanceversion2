<?php
// AJAX Handler for Academic Settings
// This file handles AJAX requests independently of layout.php

// Suppress error output to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if user is super admin
if ($_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';

try {
    switch ($action) {
        case 'add':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            
            switch ($type) {
                case 'department':
                    $stmt = $db->prepare("INSERT INTO academic_departments (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    echo json_encode(['success' => true, 'message' => 'Department added successfully!']);
                    exit;
                case 'program':
                    $stmt = $db->prepare("INSERT INTO academic_programs (name, description, department_id) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, $department_id]);
                    echo json_encode(['success' => true, 'message' => 'Program added successfully!']);
                    exit;
                case 'strand':
                    $stmt = $db->prepare("INSERT INTO academic_strands (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    echo json_encode(['success' => true, 'message' => 'Strand added successfully!']);
                    exit;
                case 'grade':
                    $stmt = $db->prepare("INSERT INTO academic_grade_levels (department_id, name) VALUES (?, ?)");
                    $stmt->execute([$department_id, $name]);
                    echo json_encode(['success' => true, 'message' => 'Grade level added successfully!']);
                    exit;
            }
            break;
            
        case 'edit':
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            
            switch ($type) {
                case 'department':
                    $stmt = $db->prepare("UPDATE academic_departments SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    echo json_encode(['success' => true, 'message' => 'Department updated successfully!']);
                    exit;
                case 'program':
                    $stmt = $db->prepare("UPDATE academic_programs SET name = ?, description = ?, department_id = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $department_id, $id]);
                    echo json_encode(['success' => true, 'message' => 'Program updated successfully!']);
                    exit;
                case 'strand':
                    $stmt = $db->prepare("UPDATE academic_strands SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    echo json_encode(['success' => true, 'message' => 'Strand updated successfully!']);
                    exit;
                case 'grade':
                    $stmt = $db->prepare("UPDATE academic_grade_levels SET department_id = ?, name = ? WHERE id = ?");
                    $stmt->execute([$department_id, $name, $id]);
                    echo json_encode(['success' => true, 'message' => 'Grade level updated successfully!']);
                    exit;
            }
            break;
            
        case 'toggle_status':
            $table = $_POST['table'];
            $id = $_POST['id'];
            $status = $_POST['status'];
            
            $allowed_tables = ['academic_departments', 'academic_programs', 'academic_strands', 'academic_grade_levels'];
            if (in_array($table, $allowed_tables)) {
                $stmt = $db->prepare("UPDATE {$table} SET is_active = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success' => true, 'message' => 'Status updated successfully!']);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Invalid table']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
