<?php
// /api/archive.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch Archived Employees
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM v_archived_employees ORDER BY deleted_at DESC");
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
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
            // DANGER: This cascades and permanently deletes all related records (payslips, attendance, etc.)
            $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->execute([$input['employee_id']]);
            echo json_encode(["status" => "success", "message" => "Employee and all associated records permanently wiped."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Operation failed: " . $e->getMessage()]);
    }
}
?>