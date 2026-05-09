<?php
// /api/run_payroll.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $start_date = $input['start_date'];
        $end_date = $input['end_date'];
        $label = $input['label'];

        // Call your powerful stored procedure
        $stmt = $pdo->prepare("CALL sp_run_payroll_batch(?, ?, ?)");
        $stmt->execute([$start_date, $end_date, $label]);

        echo json_encode([
            "status" => "success", 
            "message" => "Payroll batch '$label' generated successfully!"
        ]);
    } catch (PDOException $e) {
        // If it throws an error (like trying to run the same period twice)
        echo json_encode([
            "status" => "error", 
            "message" => "Batch failed: " . $e->getMessage()
        ]);
    }
}
?>