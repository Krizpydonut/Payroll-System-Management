<?php
// /api/payroll.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET: Fetch Data
// ==========================================
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // Fetch initial data (Employees, Bonus Types, Deduction Types, Periods)
    if ($action === 'init') {
        try {
            $employees = $pdo->query("SELECT employee_id as id, employee_code as code, first_name as firstName, last_name as lastName FROM employees WHERE status = 'active'")->fetchAll();
            $bonusTypes = $pdo->query("SELECT bonus_type_id as id, type_name as name FROM bonus_types")->fetchAll();
            $deductionTypes = $pdo->query("SELECT deduction_type_id as id, type_name as name FROM deduction_types")->fetchAll();
            // CHANGED: Querying month_year instead of period_label
            $periods = $pdo->query("SELECT DISTINCT month_year FROM payroll ORDER BY month_year DESC")->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                "status" => "success", 
                "employees" => $employees, 
                "bonusTypes" => $bonusTypes, 
                "deductionTypes" => $deductionTypes,
                "periods" => $periods
            ]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Init failed: " . $e->getMessage()]);
        }
        exit();
    }

    // Fetch specific period data (Table, KPIs, Logs)
    // We are still catching 'period' from the JS, but it will now be a month_year string (e.g. '2026-10')
    $period = isset($_GET['period']) ? $_GET['period'] : '';
    if ($period) {
        try {
            // 1. Payroll Register Table (CHANGED: using month_year)
            $stmt = $pdo->prepare("SELECT * FROM v_payroll_summary WHERE month_year = ? ORDER BY department_name, full_name");
            $stmt->execute([$period]);
            $payroll = $stmt->fetchAll();

            // 2. KPIs (CHANGED: using month_year)
            $stmtKPI = $pdo->prepare("SELECT * FROM v_period_totals WHERE month_year = ?");
            $stmtKPI->execute([$period]);
            $kpis = $stmtKPI->fetch() ?: ['total_gross' => 0, 'total_deductions' => 0, 'total_net' => 0];

            // 3. Bonus Logs (CHANGED: using month_year)
            $stmtBonuses = $pdo->prepare("
                SELECT b.amount, b.date_given as date, bt.type_name as type, CONCAT(e.first_name, ' ', e.last_name) as name 
                FROM bonuses b JOIN bonus_types bt ON b.bonus_type_id = bt.bonus_type_id JOIN employees e ON b.employee_id = e.employee_id 
                WHERE b.month_year = ? ORDER BY b.bonus_id DESC
            ");
            $stmtBonuses->execute([$period]);
            $bonuses = $stmtBonuses->fetchAll();

            // 4. Deduction Logs (CHANGED: using month_year)
            $stmtDeductions = $pdo->prepare("
                SELECT d.amount, d.date_applied as date, dt.type_name as type, CONCAT(e.first_name, ' ', e.last_name) as name 
                FROM deductions d JOIN deduction_types dt ON d.deduction_type_id = dt.deduction_type_id JOIN employees e ON d.employee_id = e.employee_id 
                WHERE d.month_year = ? ORDER BY d.deduction_id DESC
            ");
            $stmtDeductions->execute([$period]);
            $deductions = $stmtDeductions->fetchAll();

            echo json_encode([
                "status" => "success",
                "payroll" => $payroll,
                "kpis" => $kpis,
                "bonuses" => $bonuses,
                "deductions" => $deductions
            ]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Period load failed: " . $e->getMessage()]);
        }
    }
}

// ==========================================
// POST: Insert / Update Data
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    try {
        if ($action === 'add_bonus') {
            // CHANGED: Using month_year
            $stmt = $pdo->prepare("INSERT INTO bonuses (employee_id, bonus_type_id, amount, date_given, month_year) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['emp_id'], $input['type_id'], $input['amount'], $input['date'], $input['period']]);
            
            // CHANGED: Re-run the batch procedure. Adjusted subqueries to look for month_year.
            $stmtRe = $pdo->prepare("CALL sp_compute_payroll(?, (SELECT period_start FROM payroll WHERE month_year = ? LIMIT 1), (SELECT period_end FROM payroll WHERE month_year = ? LIMIT 1), ?, @pid, @pnet)");
            $stmtRe->execute([$input['emp_id'], $input['period'], $input['period'], $input['period']]);

            echo json_encode(["status" => "success", "message" => "Bonus added and payroll recalculated."]);
        } 
        
        elseif ($action === 'add_deduction') {
            // CHANGED: Using month_year
            $stmt = $pdo->prepare("INSERT INTO deductions (employee_id, deduction_type_id, amount, date_applied, month_year) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['emp_id'], $input['type_id'], $input['amount'], $input['date'], $input['period']]);
            
            // CHANGED: Re-run the batch procedure. Adjusted subqueries to look for month_year.
            $stmtRe = $pdo->prepare("CALL sp_compute_payroll(?, (SELECT period_start FROM payroll WHERE month_year = ? LIMIT 1), (SELECT period_end FROM payroll WHERE month_year = ? LIMIT 1), ?, @pid, @pnet)");
            $stmtRe->execute([$input['emp_id'], $input['period'], $input['period'], $input['period']]);

            echo json_encode(["status" => "success", "message" => "Deduction added and payroll recalculated."]);
        } 
        
        elseif ($action === 'update_status') {
            // CHANGED: Using month_year
            $stmt = $pdo->prepare("UPDATE payroll SET status = ? WHERE month_year = ?");
            $stmt->execute([$input['status'], $input['period']]);
            echo json_encode(["status" => "success", "message" => "Payroll marked as " . strtoupper($input['status'])]);
        }

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Update failed: " . $e->getMessage()]);
    }
}
?>