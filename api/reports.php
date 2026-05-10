<?php
// /api/reports.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // 1. Get available periods for the dropdown
    if ($action === 'periods') {
        try {
            // CHANGED: Querying month_year instead of period_label
            $periods = $pdo->query("SELECT DISTINCT month_year FROM payroll ORDER BY month_year DESC")->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(["status" => "success", "data" => $periods]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Database error."]);
        }
        exit();
    }

    // 2. Fetch all report data for a specific period
    if ($action === 'load') {
        $period = isset($_GET['period']) ? $_GET['period'] : '';
        
        try {
            // KPIs (CHANGED: using month_year)
            $stmtKPI = $pdo->prepare("SELECT total_gross, total_deductions, total_net FROM v_period_totals WHERE month_year = ?");
            $stmtKPI->execute([$period]);
            $kpis = $stmtKPI->fetch(PDO::FETCH_ASSOC) ?: ['total_gross' => 0, 'total_deductions' => 0, 'total_net' => 0];

            // Total OT Pay (CHANGED: using month_year)
            $stmtOT = $pdo->prepare("SELECT SUM(ot_pay) as total_ot FROM payroll WHERE month_year = ?");
            $stmtOT->execute([$period]);
            $ot_total = $stmtOT->fetchColumn() ?: 0;
            $kpis['total_ot'] = $ot_total;

            // Department Cost Breakdown (CHANGED: using month_year)
            $stmtDept = $pdo->prepare("
                SELECT d.department_name, COUNT(pr.employee_id) AS headcount, 
                       SUM(pr.gross_salary) AS gross_total, SUM(pr.total_deductions) AS deductions_total, SUM(pr.net_salary) AS net_total
                FROM payroll pr
                JOIN employees e ON e.employee_id = pr.employee_id
                JOIN departments d ON d.department_id = e.department_id
                WHERE pr.month_year = ? GROUP BY d.department_name ORDER BY net_total DESC
            ");
            $stmtDept->execute([$period]);
            $deptCosts = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

            // Government Contributions (CHANGED: using month_year)
            $stmtGovt = $pdo->prepare("
                SELECT dt.type_name, COUNT(DISTINCT d.employee_id) AS contributors, SUM(d.amount) AS total_amount
                FROM deductions d
                JOIN deduction_types dt ON dt.deduction_type_id = d.deduction_type_id
                WHERE dt.is_mandatory = 1 AND d.month_year = ?
                GROUP BY d.deduction_type_id ORDER BY dt.type_name
            ");
            $stmtGovt->execute([$period]);
            $govt = $stmtGovt->fetchAll(PDO::FETCH_ASSOC);

            // Top 5 Earners (CHANGED: using month_year)
            $stmtTop = $pdo->prepare("
                SELECT employee_code, full_name, department_name, gross_salary, net_salary 
                FROM v_payroll_summary WHERE month_year = ? ORDER BY net_salary DESC LIMIT 5
            ");
            $stmtTop->execute([$period]);
            $topEarners = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            // Overtime Report (CHANGED: using month_year)
            $stmtOTRep = $pdo->prepare("
                SELECT full_name, total_ot_hours, ot_pay 
                FROM v_payroll_summary WHERE month_year = ? AND ot_pay > 0 ORDER BY ot_pay DESC
            ");
            $stmtOTRep->execute([$period]);
            $overtime = $stmtOTRep->fetchAll(PDO::FETCH_ASSOC);

            // Bonus Breakdown (CHANGED: using month_year)
            $stmtBonus = $pdo->prepare("
                SELECT v.employee_code, v.full_name, d.department_name, v.bonus_type, v.total_amount
                FROM v_bonus_breakdown v
                JOIN employees e ON e.employee_code = v.employee_code
                JOIN departments d ON d.department_id = e.department_id
                WHERE v.month_year = ?
            ");
            $stmtBonus->execute([$period]);
            $bonusRaw = $stmtBonus->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "data" => [
                    "kpis" => $kpis,
                    "deptCosts" => $deptCosts,
                    "govt" => $govt,
                    "topEarners" => $topEarners,
                    "overtime" => $overtime,
                    "bonuses" => $bonusRaw
                ]
            ]);

        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Failed to load reports: " . $e->getMessage()]);
        }
    }
}
?>