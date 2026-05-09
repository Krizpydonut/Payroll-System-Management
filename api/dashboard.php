<?php
// /api/dashboard.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Bring in the PDO connection
require_once 'db.php';

$period = isset($_GET['period']) ? $_GET['period'] : '';

if (!$period) {
    echo json_encode(["error" => "No period specified."]);
    exit();
}

try {
    // 1. Fetch the main table data using your view
    $stmtData = $pdo->prepare("SELECT * FROM v_payroll_summary WHERE period_label = ? ORDER BY department_name, full_name");
    $stmtData->execute([$period]);
    $payrollData = $stmtData->fetchAll();

    // 2. Fetch the KPI totals using your period totals view
    $stmtKPI = $pdo->prepare("SELECT employee_count, total_gross, total_deductions, total_net FROM v_period_totals WHERE period_label = ?");
    $stmtKPI->execute([$period]);
    $kpi = $stmtKPI->fetch();

    // 3. Fetch the total number of active employees for the subheading
    $stmtActive = $pdo->query("SELECT COUNT(*) as active_count FROM employees WHERE status = 'active'");
    $activeEmp = $stmtActive->fetch();

    // If no payroll has been generated for this period yet, default KPIs to 0
    if (!$kpi) {
        $kpi = [
            'employee_count' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0
        ];
    }

    // Assemble the exact JSON structure your dashboard.js is expecting
    echo json_encode([
        "kpis" => [
            "employees" => $kpi['employee_count'],
            "active_employees" => $activeEmp['active_count'],
            "gross" => $kpi['total_gross'],
            "deductions" => $kpi['total_deductions'],
            "net" => $kpi['total_net']
        ],
        "payrollData" => $payrollData
    ]);

} catch (PDOException $e) {
    // If something breaks, send the error back to the JS showToast() function
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>