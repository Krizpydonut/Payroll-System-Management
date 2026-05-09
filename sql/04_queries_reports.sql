-- ============================================================
-- PAYROLL MANAGEMENT SYSTEM — DYNAMIC QUERIES & REPORTS
-- (MODIFIED FOR PHPMYADMIN TESTING - ALL '?' REPLACED WITH SAMPLE DATA)
-- ============================================================

USE payroll_db;

-- ============================================================
-- 1. EMPLOYEE QUERIES
-- ============================================================

-- All employees by status
SELECT * FROM v_employee_list 
WHERE status = 'active' 
ORDER BY department_name, full_name;

-- Employees per department count
SELECT
  d.department_name,
  COUNT(e.employee_id)                             AS headcount,
  SUM(CASE WHEN e.status='active' THEN 1 ELSE 0 END) AS active_count,
  ROUND(AVG(e.salary_rate),2)                      AS avg_salary
FROM employees e
JOIN departments d ON d.department_id = e.department_id
GROUP BY d.department_name
ORDER BY headcount DESC;

-- Employees hired in a specific year
SELECT employee_code, full_name, date_hired, department_name, position_name
FROM v_employee_list
WHERE YEAR(date_hired) = 2021
ORDER BY date_hired DESC;

-- ============================================================
-- 2. ATTENDANCE QUERIES
-- ============================================================

-- Attendance for a specific date range 
SELECT * FROM v_attendance_summary
WHERE work_date BETWEEN '2025-06-01' AND '2025-06-15'
ORDER BY work_date, department_name, full_name;

-- Employees with overtime in a specific period
SELECT
  employee_code,
  full_name,
  department_name,
  work_date,
  time_in,
  time_out,
  hours_worked,
  overtime_hours
FROM v_attendance_summary
WHERE overtime_hours > 0
  AND work_date BETWEEN '2025-06-01' AND '2025-06-15'
ORDER BY overtime_hours DESC;

-- Lates for a specific date range, past a specific grace period
SELECT
  employee_code,
  full_name,
  department_name,
  work_date,
  time_in,
  TIMEDIFF(time_in, '08:00:00') AS minutes_late
FROM v_attendance_summary
WHERE time_in > '08:05:00'
  AND work_date BETWEEN '2025-06-01' AND '2025-06-15'
ORDER BY work_date, minutes_late DESC;

-- ============================================================
-- 3. PAYROLL COMPUTATION — MANUAL RUN
-- ============================================================

-- Step 1: Run batch payroll (Generates the payroll for 2025-06-A)
CALL sp_run_payroll_batch('2025-06-01', '2025-06-15', '2025-06-A');

-- Step 2: Review the computed payroll
SELECT * FROM v_payroll_summary
WHERE period_label = '2025-06-A'
ORDER BY department_name, full_name;

-- ============================================================
-- 4. PAYROLL REPORT QUERIES
-- ============================================================

-- Department payroll cost for a selected period
SELECT
  d.department_name,
  COUNT(pr.employee_id) AS headcount,
  ROUND(SUM(pr.basic_pay),2)        AS basic_pay_total,
  ROUND(SUM(pr.ot_pay),2)           AS ot_pay_total,
  ROUND(SUM(pr.total_bonuses),2)    AS bonuses_total,
  ROUND(SUM(pr.gross_salary),2)     AS gross_total,
  ROUND(SUM(pr.total_deductions),2) AS deductions_total,
  ROUND(SUM(pr.net_salary),2)       AS net_total
FROM payroll pr
JOIN employees   e ON e.employee_id   = pr.employee_id
JOIN departments d ON d.department_id = e.department_id
WHERE pr.period_label = '2025-06-A'
GROUP BY d.department_name
ORDER BY net_total DESC;

-- Top earners for a selected period
SELECT
  employee_code,
  full_name,
  department_name,
  position_name,
  gross_salary,
  total_deductions,
  net_salary
FROM v_payroll_summary
WHERE period_label = '2025-06-A'
ORDER BY net_salary DESC
LIMIT 5;

-- Government contributions summary for a selected period
SELECT
  d.payroll_period,
  dt.type_name,
  COUNT(DISTINCT d.employee_id) AS contributors,
  ROUND(SUM(d.amount),2)        AS total_contribution
FROM deductions d
JOIN deduction_types dt ON dt.deduction_type_id = d.deduction_type_id
WHERE dt.is_mandatory = 1 AND d.payroll_period = '2025-06-A'
GROUP BY d.payroll_period, d.deduction_type_id
ORDER BY d.payroll_period, dt.type_name;

-- ============================================================
-- 5. SEARCH / FILTER HELPERS
-- ============================================================

-- Search employee by name 
SELECT * FROM v_employee_list
WHERE full_name LIKE CONCAT('%', 'Cruz', '%');

-- Employee payroll history 
SELECT
  period_label,
  gross_salary,
  total_deductions,
  net_salary,
  status
FROM v_payroll_summary
WHERE employee_code = 'EMP-0002'
ORDER BY period_start DESC;