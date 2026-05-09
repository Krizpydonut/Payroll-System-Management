<?php
// /api/payslips.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET: Fetch Payslip Data
// ==========================================
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    // 1. Get available periods for the dropdown
    if ($action === 'periods') {
        try {
            $periods = $pdo->query("SELECT DISTINCT period_label FROM payroll ORDER BY period_label DESC")->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(["status" => "success", "data" => $periods]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Database error."]);
        }
        exit();
    }

    // 2. Get list of generated payslips for a specific period
    if ($action === 'list') {
        $period = isset($_GET['period']) ? $_GET['period'] : '';
        try {
            // Join payslips with payroll and employees to get the summary info for the sidebar list
            $sql = "
                SELECT 
                    ps.payslip_id, 
                    ps.details_json,
                    e.employee_code,
                    CONCAT(e.first_name, ' ', e.last_name) as full_name
                FROM payslips ps
                JOIN payroll pr ON ps.payroll_id = pr.payroll_id
                JOIN employees e ON ps.employee_id = e.employee_id
                WHERE pr.period_label = ?
                ORDER BY e.first_name ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$period]);
            $payslips = $stmt->fetchAll();

            // Decode the JSON string from MySQL into an actual PHP array before sending to JS
            foreach ($payslips as &$slip) {
                $slip['details'] = json_decode($slip['details_json'], true);
                unset($slip['details_json']); // Remove the raw string to keep the response clean
            }

            echo json_encode(["status" => "success", "data" => $payslips]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Failed to load payslips: " . $e->getMessage()]);
        }
    }
}

// ==========================================
// POST: Generate Payslips
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'generate_all') {
        $period = $input['period'];
        
        try {
            // Find all payroll records for this period
            $stmt = $pdo->prepare("SELECT payroll_id FROM payroll WHERE period_label = ?");
            $stmt->execute([$period]);
            $payroll_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($payroll_ids) === 0) {
                echo json_encode(["status" => "error", "message" => "No payroll records found to generate payslips for."]);
                exit();
            }

            // Loop through and call your awesome generation procedure for each one
            foreach ($payroll_ids as $p_id) {
                $genStmt = $pdo->prepare("CALL sp_generate_payslip(?)");
                $genStmt->execute([$p_id]);
            }

            echo json_encode(["status" => "success", "message" => "Payslips generated successfully!"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Generation failed: " . $e->getMessage()]);
        }
    }
}
?>