<?php
// api/archive.php
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $sql = "SELECT e.employee_id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS full_name, 
                d.department_name, p.position_name, e.deleted_at 
                FROM employees e 
                LEFT JOIN departments d ON e.department_id = d.department_id 
                LEFT JOIN positions p ON e.position_id = p.position_id 
                WHERE e.is_deleted = 1 ORDER BY e.deleted_at DESC";
        $stmt = $pdo->query($sql);
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
    } catch (PDOException $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $ids = $input['employee_ids'] ?? [];

    if (empty($ids)) { echo json_encode(["status" => "error", "message" => "No selection."]); exit; }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';

    try {
        if ($action === 'restore') {
            $stmt = $pdo->prepare("UPDATE employees SET is_deleted = 0, deleted_at = NULL, status = 'active' WHERE employee_id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(["status" => "success", "message" => count($ids) . " records restored."]);
        } 
        elseif ($action === 'hard_delete') {
            $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(["status" => "success", "message" => count($ids) . " records permanently wiped."]);
        }
    } catch (PDOException $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
}
?>