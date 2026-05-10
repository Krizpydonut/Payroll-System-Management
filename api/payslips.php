<?php
// /api/payslips.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // 1. Fetch available periods
    if ($action === 'periods') {
        try {
            // CHANGED: Query month_year from payroll
            $stmt = $pdo->query("SELECT DISTINCT month_year FROM payroll ORDER BY month_year DESC");
            $periods = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(["status" => "success", "data" => $periods]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // 2. Fetch payslips for a specific period
    if ($action === 'list') {
        $period = isset($_GET['period']) ? $_GET['period'] : '';
        try {
            // CHANGED: Joined with payroll to filter by month_year
            $stmt = $pdo->prepare("
                SELECT ps.payslip_id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as full_name, ps.details_json
                FROM payslips ps
                JOIN payroll pr ON ps.payroll_id = pr.payroll_id
                JOIN employees e ON ps.employee_id = e.employee_id
                WHERE pr.month_year = ?
                ORDER BY e.first_name
            ");
            $stmt->execute([$period]);
            $slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON for the frontend
            $parsedSlips = array_map(function($s) {
                $s['details'] = json_decode($s['details_json'], true);
                unset($s['details_json']);
                return $s;
            }, $slips);

            echo json_encode(["status" => "success", "data" => $parsedSlips]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    if ($action === 'generate_all') {
        $period = isset($input['period']) ? $input['period'] : '';
        try {
            // CHANGED: Filter by month_year
            $stmt = $pdo->prepare("SELECT payroll_id FROM payroll WHERE month_year = ?");
            $stmt->execute([$period]);
            $payrolls = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($payrolls)) {
                echo json_encode(["status" => "error", "message" => "No payroll records found for this period. Run batch processing first."]);
                exit();
            }

            foreach ($payrolls as $pid) {
                $pdo->exec("CALL sp_generate_payslip($pid)");
            }

            echo json_encode(["status" => "success", "message" => "Payslips successfully generated."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
}
?>