<?php
// /api/attendance.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET: Fetch Data
// ==========================================
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    // Fetch Active Employees for the "Log Attendance" Modal
    if ($action === 'employees') {
        try {
            $sql = "SELECT employee_id as id, employee_code as code, first_name as firstName, last_name as lastName, employment_type as type FROM employees WHERE status = 'active'";
            $employees = $pdo->query($sql)->fetchAll();
            echo json_encode(["status" => "success", "data" => $employees]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Failed to load employees."]);
        }
        exit();
    }

    // Default: Fetch Attendance Logs (Filterable by Date)
    try {
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        
        $sql = "SELECT * FROM v_attendance_summary";
        $params = [];

        // If a date is passed from the frontend filter, apply it
        if ($date) {
            $sql .= " WHERE work_date = ?";
            $params[] = $date;
        }

        $sql .= " ORDER BY work_date DESC, full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendance = $stmt->fetchAll();
        
        echo json_encode(["status" => "success", "data" => $attendance]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Failed to fetch attendance: " . $e->getMessage()]);
    }
}

// ==========================================
// POST: Insert New Time Log
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        // Insert the record. We take the hours_worked and overtime calculated from your frontend
        $sql = "INSERT INTO attendance (employee_id, work_date, time_in, time_out, hours_worked, overtime_hours) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['employee_id'],
            $input['date'],
            $input['time_in'],
            $input['time_out'],
            $input['hours_worked'],
            $input['overtime']
        ]);

        echo json_encode(["status" => "success", "message" => "Attendance logged successfully!"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>