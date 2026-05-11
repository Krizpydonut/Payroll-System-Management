<?php
// api/employees.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    try {
        // Fetch lists for the "Add Employee" Modal dropdowns
        if ($action === 'dropdowns') {
            $depts = $pdo->query("SELECT department_id as id, department_name as name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
            $positions = $pdo->query("SELECT position_id as id, position_name as name FROM positions")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "departments" => $depts, "positions" => $positions]);
            exit;
        }

        // Main Table Fetch
        $sql = "SELECT e.*, d.department_name, p.position_name 
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN positions p ON e.position_id = p.position_id
                WHERE e.is_deleted = 0 ORDER BY e.employee_id DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) { 
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]); 
    }
}

if ($method === 'POST') {
    $action = $input['action'] ?? '';

    if ($action === 'add') {
        $fn = trim($input['first_name']);
        $ln = trim($input['last_name']);
        $mn = trim($input['middle_name']);
        $phone = trim($input['phone']);
        $rate = $input['rate'];

        // 1. DUPLICATE CHECK: First + Middle + Last Name
        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE first_name=? AND last_name=? AND middle_name=? AND is_deleted=0 LIMIT 1");
        $check->execute([$fn, $ln, $mn]);
        if ($check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Security Alert: '$fn $mn $ln' is already in the system."]);
            exit;
        }

        // 2. PH PHONE VALIDATION (09XXXXXXXXX)
        if (strlen($phone) !== 11 || substr($phone, 0, 2) !== '09') {
            echo json_encode(["status" => "error", "message" => "Phone number must be 11 digits starting with 09."]);
            exit;
        }

        try {
            $code = "EMP-" . rand(1000, 9999);
            $sql = "INSERT INTO employees (first_name, last_name, middle_name, gender, birthdate, email, phone, date_hired, department_id, position_id, employment_type, rate, address, code, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $fn, $ln, $mn, $input['gender'], $input['birthdate'], $input['email'], $phone,
                $input['date_hired'], $input['department_id'], $input['position_id'], 
                $input['employment_type'], $rate, $input['address'], $code
            ]);
            echo json_encode(["status" => "success", "message" => "Successfully added employee: $code"]);
        } catch (PDOException $e) { 
            echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]); 
        }
    }

    if ($action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE employees SET status = (CASE WHEN status='active' THEN 'inactive' ELSE 'active' END) WHERE employee_id = ?");
            $stmt->execute([$input['employee_id']]);
            echo json_encode(["status" => "success"]);
        } catch (PDOException $e) { echo json_encode(["status" => "error"]); }
    }

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("UPDATE employees SET is_deleted = 1, deleted_at = NOW() WHERE employee_id = ?");
            $stmt->execute([$input['employee_id']]);
            echo json_encode(["status" => "success"]);
        } catch (PDOException $e) { echo json_encode(["status" => "error"]); }
    }
}
?>