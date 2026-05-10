<?php
// /api/dashboard.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$period = isset($_GET['period']) ? $_GET['period'] : '';

if (!$period) {
    echo json_encode(["error" => "No period specified."]);
    exit();
}

try {
    // 1. Fetch the main table data
    $stmtData = $pdo->prepare("SELECT * FROM v_payroll_summary WHERE month_year = ? ORDER BY department_name, full_name");
    $stmtData->execute([$period]);
    $payrollData = $stmtData->fetchAll();

    // 2. Fetch the KPI totals
    $stmtKPI = $pdo->prepare("SELECT employee_count, total_gross, total_deductions, total_net FROM v_period_totals WHERE month_year = ?");
    $stmtKPI->execute([$period]);
    $kpi = $stmtKPI->fetch();

    // 3. Fetch active employee count
    $stmtActive = $pdo->query("SELECT COUNT(*) as active_count FROM employees WHERE status = 'active'");
    $activeEmp = $stmtActive->fetch();

    // 4. NEW: Fetch Department-specific totals for the Pie Chart
    // This ensures the chart has accurate slices even if the main table is filtered
    $stmtDept = $pdo->prepare("
        SELECT 
            department_name, 
            SUM(net_salary) as total_net 
        FROM v_payroll_summary 
        WHERE month_year = ? 
        GROUP BY department_name
    ");
    $stmtDept->execute([$period]);
    $deptData = $stmtDept->fetchAll();

    if (!$kpi) {
        $kpi = [
            'employee_count' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0
        ];
    }

    // Assemble final JSON
    echo json_encode([
        "kpis" => [
            "employees" => $kpi['employee_count'],
            "active_employees" => $activeEmp['active_count'],
            "gross" => $kpi['total_gross'],
            "deductions" => $kpi['total_deductions'],
            "net" => $kpi['total_net']
        ],
        "payrollData" => $payrollData,
        "deptData" => $deptData // This will be used by renderDeptPieChart
    ]);

} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>