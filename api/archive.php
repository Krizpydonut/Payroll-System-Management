<?php
// /api/archive.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch Archived Employees
if ($method === 'GET') {
    try {
        // We use a direct JOIN query here to avoid the "View Not Found" or "Permission" error
        $sql = "
            SELECT 
                e.employee_id, 
                e.employee_code, 
                CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                d.department_name,
                p.position_name,
                e.deleted_at,
                e.status
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN positions p ON e.position_id = p.position_id
            WHERE e.is_deleted = 1
            ORDER BY e.deleted_at DESC
        ";
        
        $stmt = $pdo->query($sql);
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}

// POST: Restore or Hard Delete
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    try {
        if ($action === 'restore') {
            $stmt = $pdo->prepare("UPDATE employees SET is_deleted = 0, deleted_at = NULL, status = 'active' WHERE employee_id = ?");
            $stmt->execute([$input['employee_id']]);
            echo json_encode(["status" => "success", "message" => "Employee restored to active list."]);
        } 
        elseif ($action === 'hard_delete') {
            // DANGER: This permanently deletes the record
            $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->execute([$input['employee_id']]);
            echo json_encode(["status" => "success", "message" => "Employee and all associated records permanently wiped."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Operation failed: " . $e->getMessage()]);
    }
}
?>