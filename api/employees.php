<?php
// /api/employees.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET: Fetch Data
// ==========================================
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    // Fetch Departments and Positions for the Add Modal dropdowns
    if ($action === 'dropdowns') {
        try {
            $depts = $pdo->query("SELECT department_id as id, department_name as name FROM departments")->fetchAll();
            $positions = $pdo->query("SELECT position_id as id, position_name as name FROM positions")->fetchAll();
            echo json_encode(["status" => "success", "departments" => $depts, "positions" => $positions]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Failed to load dropdowns."]);
        }
        exit();
    }

    // Default: Fetch all active employees for the main table
    try {
        $sql = "
            SELECT 
                e.employee_id AS id, 
                e.employee_code AS code, 
                e.first_name AS firstName, 
                e.last_name AS lastName, 
                d.department_name, 
                p.position_name, 
                e.employment_type AS type, 
                e.salary_rate AS rate, 
                e.date_hired AS hired, 
                e.status,
                e.department_id AS dept_id
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN positions p ON e.position_id = p.position_id
            WHERE e.is_deleted = 0
            ORDER BY e.employee_id DESC
        ";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["error" => "Failed to fetch employees: " . $e->getMessage()]);
    }
}

// ==========================================
// POST: Create / Update / Delete Data
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    try {
        // Create a new employee 
        if ($action === 'add') {
            $sql = "CALL sp_add_employee(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @new_id, @new_code)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                $input['first_name'],
                $input['middle_name'] ?? null,
                $input['last_name'],
                $input['gender'],
                $input['birthdate'],
                $input['email'],
                $input['phone'] ?? null,
                $input['address'] ?? null,
                $input['department_id'],
                $input['position_id'],
                $input['employment_type'],
                $input['salary_rate'],
                $input['date_hired']
            ]);

            $result = $pdo->query("SELECT @new_id AS id, @new_code AS code")->fetch();

            echo json_encode(["status" => "success", "message" => "Employee created!", "data" => $result]);
        } 
        
        // Toggle Active/Inactive status
        elseif ($action === 'toggle_status') {
            $id = $input['employee_id'];
            
            $stmt = $pdo->prepare("SELECT status FROM employees WHERE employee_id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetchColumn();
            
            $newStatus = ($current === 'active') ? 'inactive' : 'active';
            $update = $pdo->prepare("UPDATE employees SET status = ? WHERE employee_id = ?");
            $update->execute([$newStatus, $id]);
            
            echo json_encode(["status" => "success", "message" => "Status updated to $newStatus"]);
        }

        // Soft Delete (Archive) an employee
        elseif ($action === 'delete') {
            $id = $input['employee_id'];
            
            $stmt = $pdo->prepare("UPDATE employees SET is_deleted = 1, deleted_at = NOW(), status = 'terminated' WHERE employee_id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(["status" => "success", "message" => "Employee moved to Archive."]);
        }
        
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>