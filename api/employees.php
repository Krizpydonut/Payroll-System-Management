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
        if ($action === 'dropdowns') {
            $depts = $pdo->query("SELECT department_id as id, department_name as name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
            $positions = $pdo->query("SELECT position_id as id, position_name as name FROM positions")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "departments" => $depts, "positions" => $positions]);
            exit;
        }

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

    // ========================================================
    // CREATE NEW EMPLOYEE
    // ========================================================
    if ($action === 'add') {
        $fn = trim($input['first_name']);
        $ln = trim($input['last_name']);
        $mn = trim($input['middle_name']);
        $phone = trim($input['phone']);

        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE first_name=? AND last_name=? AND middle_name=? AND is_deleted=0 LIMIT 1");
        $check->execute([$fn, $ln, $mn]);
        if ($check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Security Alert: '$fn $mn $ln' is already in the system."]);
            exit;
        }

        if (strlen($phone) !== 11 || substr($phone, 0, 2) !== '09') {
            echo json_encode(["status" => "error", "message" => "Phone number must be 11 digits starting with 09."]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("CALL sp_add_employee(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @new_id, @emp_code)");
            $stmt->execute([
                $fn, $mn, $ln, $input['gender'], $input['birthdate'], $input['email'], $phone, 
                $input['address'], $input['department_id'], $input['position_id'], 
                $input['employment_type'], $input['rate'], $input['date_hired']
            ]);
            $res = $pdo->query("SELECT @emp_code AS code")->fetch();
            echo json_encode(["status" => "success", "message" => "Successfully added employee: " . $res['code']]);
        } catch (PDOException $e) { 
            echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]); 
        }
    }

    // ========================================================
    // UPDATE EXISTING EMPLOYEE
    // ========================================================
    if ($action === 'edit') {
        $id = $input['employee_id'];
        $fn = trim($input['first_name']);
        $ln = trim($input['last_name']);
        $mn = trim($input['middle_name']);
        $phone = trim($input['phone']);

        // Check for duplicate names, excluding the CURRENT employee being edited
        $check = $pdo->prepare("SELECT employee_id FROM employees WHERE first_name=? AND last_name=? AND middle_name=? AND employee_id != ? AND is_deleted=0 LIMIT 1");
        $check->execute([$fn, $ln, $mn, $id]);
        if ($check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Update Failed: Another employee named '$fn $mn $ln' already exists."]);
            exit;
        }

        if (strlen($phone) !== 11 || substr($phone, 0, 2) !== '09') {
            echo json_encode(["status" => "error", "message" => "Phone number must be 11 digits starting with 09."]);
            exit;
        }

        try {
            $sql = "UPDATE employees SET 
                    first_name=?, middle_name=?, last_name=?, gender=?, birthdate=?, email=?, phone=?, 
                    address=?, department_id=?, position_id=?, employment_type=?, rate=?, date_hired=? 
                    WHERE employee_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $fn, $mn, $ln, $input['gender'], $input['birthdate'], $input['email'], $phone,
                $input['address'], $input['department_id'], $input['position_id'], 
                $input['employment_type'], $input['rate'], $input['date_hired'], $id
            ]);
            echo json_encode(["status" => "success", "message" => "Successfully updated employee details."]);
        } catch (PDOException $e) { 
            echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]); 
        }
    }

    // ========================================================
    // OTHER ACTIONS
    // ========================================================
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