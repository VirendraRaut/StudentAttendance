<?php

/**
 * Admin AJAX API Endpoints
 * Dynamic Dependent Dropdowns & Lookup Data
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is authenticated as admin
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_sections':
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        if ($departmentId <= 0) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id, section_name 
                 FROM sections 
                 WHERE department_id = :dept_id AND status = 'Active' 
                 ORDER BY section_name ASC"
            );
            $stmt->execute([':dept_id' => $departmentId]);
            $sections = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $sections]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'get_subjects':
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        if ($departmentId <= 0) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id, subject_code, subject_name 
                 FROM subjects 
                 WHERE department_id = :dept_id AND status = 'Active' 
                 ORDER BY subject_code ASC, subject_name ASC"
            );
            $stmt->execute([':dept_id' => $departmentId]);
            $subjects = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $subjects]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'get_teachers':
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        try {
            $sql = "SELECT id, teacher_code, CONCAT(first_name, ' ', last_name) AS full_name 
                    FROM teachers 
                    WHERE status = 'Active'";
            $params = [];
            if ($departmentId > 0) {
                $sql .= " AND department_id = :dept_id";
                $params[':dept_id'] = $departmentId;
            }
            $sql .= " ORDER BY first_name ASC, last_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $teachers = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $teachers]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
