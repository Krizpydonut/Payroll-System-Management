USE payroll_db;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_compute_payroll$$
CREATE PROCEDURE sp_compute_payroll(
  IN  p_employee_id    INT UNSIGNED,
  IN  p_period_start   DATE,
  IN  p_period_end     DATE,
  IN  p_period_label   VARCHAR(20),
  OUT p_payroll_id     INT UNSIGNED,
  OUT p_net_salary     DECIMAL(12,2)
)
BEGIN
  DECLARE v_salary_rate    DECIMAL(12,2);
  DECLARE v_emp_type       VARCHAR(10);
  DECLARE v_total_days     DECIMAL(5,2);
  DECLARE v_total_hours    DECIMAL(7,2);
  DECLARE v_total_ot       DECIMAL(7,2);
  DECLARE v_basic_pay      DECIMAL(12,2);
  DECLARE v_ot_pay         DECIMAL(12,2);
  DECLARE v_hourly_rate    DECIMAL(10,4);
  DECLARE v_daily_rate     DECIMAL(10,4);
  DECLARE v_total_bonuses  DECIMAL(12,2);
  DECLARE v_total_ded      DECIMAL(12,2);
  DECLARE v_gross          DECIMAL(12,2);
  DECLARE v_net            DECIMAL(12,2);

  -- Fetch employee rate
  SELECT salary_rate, employment_type
    INTO v_salary_rate, v_emp_type
    FROM employees
   WHERE employee_id = p_employee_id;

  -- Summarise attendance for period
  SELECT
    COUNT(*),
    COALESCE(SUM(hours_worked),  0),
    COALESCE(SUM(overtime_hours),0)
  INTO v_total_days, v_total_hours, v_total_ot
  FROM attendance
  WHERE employee_id = p_employee_id
    AND work_date BETWEEN p_period_start AND p_period_end;

  -- Compute basic pay
  IF v_emp_type = 'monthly' THEN
    SET v_daily_rate  = v_salary_rate / 26;
    SET v_hourly_rate = v_daily_rate / 8;
    SET v_basic_pay   = v_daily_rate * v_total_days;
  ELSE
    SET v_daily_rate  = v_salary_rate;
    SET v_hourly_rate = v_daily_rate / 8;
    SET v_basic_pay   = v_daily_rate * v_total_days;
  END IF;

  SET v_ot_pay = v_total_ot * v_hourly_rate * 1.25;

  SELECT COALESCE(SUM(amount), 0) INTO v_total_bonuses
    FROM bonuses WHERE employee_id = p_employee_id AND payroll_period = p_period_label;

  SELECT COALESCE(SUM(amount), 0) INTO v_total_ded
    FROM deductions WHERE employee_id = p_employee_id AND payroll_period = p_period_label;

  SET v_gross = ROUND(v_basic_pay + v_ot_pay + v_total_bonuses, 2);
  SET v_net   = ROUND(v_gross - v_total_ded, 2);

  -- Upsert payroll row (Removed payroll_date, allowing processed_at to auto-fill)
  INSERT INTO payroll
    (employee_id, period_label, period_start, period_end,
     total_days, total_hours, total_ot_hours,
     basic_pay, ot_pay, total_bonuses, gross_salary,
     total_deductions, net_salary, status)
  VALUES
    (p_employee_id, p_period_label, p_period_start, p_period_end,
     v_total_days, v_total_hours, v_total_ot,
     v_basic_pay, v_ot_pay, v_total_bonuses, v_gross,
     v_total_ded, v_net, 'draft')
  ON DUPLICATE KEY UPDATE
    total_days       = v_total_days,
    total_hours      = v_total_hours,
    total_ot_hours   = v_total_ot,
    basic_pay        = v_basic_pay,
    ot_pay           = v_ot_pay,
    total_bonuses    = v_total_bonuses,
    gross_salary     = v_gross,
    total_deductions = v_total_ded,
    net_salary       = v_net,
    status           = 'draft';

  SET p_payroll_id = LAST_INSERT_ID();
  SET p_net_salary = v_net;
END$$

DROP PROCEDURE IF EXISTS sp_run_payroll_batch$$
CREATE PROCEDURE sp_run_payroll_batch(
  IN p_period_start DATE,
  IN p_period_end   DATE,
  IN p_period_label VARCHAR(20)
)
BEGIN
  DECLARE done        INT DEFAULT 0;
  DECLARE v_emp_id    INT UNSIGNED;
  DECLARE v_pay_id    INT UNSIGNED;
  DECLARE v_net       DECIMAL(12,2);

  DECLARE cur CURSOR FOR
    SELECT employee_id FROM employees WHERE status = 'active';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_emp_id;
    IF done THEN LEAVE read_loop; END IF;
    CALL sp_compute_payroll(v_emp_id, p_period_start, p_period_end,
                            p_period_label, v_pay_id, v_net);
  END LOOP;
  CLOSE cur;

  SELECT CONCAT('Batch payroll complete for period: ', p_period_label) AS result;
END$$

DROP PROCEDURE IF EXISTS sp_generate_payslip$$
CREATE PROCEDURE sp_generate_payslip(
  IN p_payroll_id INT UNSIGNED
)
BEGIN
  DECLARE v_employee_id   INT UNSIGNED;
  DECLARE v_emp_json      JSON;
  DECLARE v_pay_json      JSON;
  DECLARE v_bonus_json    JSON;
  DECLARE v_ded_json      JSON;
  DECLARE v_full_json     JSON;
  DECLARE v_period_label  VARCHAR(20);

  SELECT employee_id, period_label INTO v_employee_id, v_period_label
    FROM payroll WHERE payroll_id = p_payroll_id;

  SELECT JSON_OBJECT(
    'employee_id',   e.employee_id,
    'employee_code', e.employee_code,
    'full_name',     CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name,' '),''), e.last_name),
    'position',      p.position_name,
    'department',    d.department_name,
    'employment_type', e.employment_type,
    'salary_rate',   e.salary_rate
  ) INTO v_emp_json
  FROM employees e
  JOIN positions   p ON p.position_id   = e.position_id
  JOIN departments d ON d.department_id = e.department_id
  WHERE e.employee_id = v_employee_id;

  SELECT JSON_OBJECT(
    'period_label',    period_label,
    'period_start',    DATE_FORMAT(period_start,'%Y-%m-%d'),
    'period_end',      DATE_FORMAT(period_end,  '%Y-%m-%d'),
    'total_days',      total_days,
    'total_hours',     total_hours,
    'total_ot_hours',  total_ot_hours,
    'basic_pay',       basic_pay,
    'ot_pay',          ot_pay,
    'total_bonuses',   total_bonuses,
    'gross_salary',    gross_salary,
    'total_deductions',total_deductions,
    'net_salary',      net_salary
  ) INTO v_pay_json
  FROM payroll WHERE payroll_id = p_payroll_id;

  SELECT JSON_ARRAYAGG(JSON_OBJECT('type', bt.type_name, 'amount', b.amount)) INTO v_bonus_json
  FROM bonuses b JOIN bonus_types bt ON bt.bonus_type_id = b.bonus_type_id
  WHERE b.employee_id = v_employee_id AND b.payroll_period = v_period_label;

  SELECT JSON_ARRAYAGG(JSON_OBJECT('type', dt.type_name, 'amount', d.amount)) INTO v_ded_json
  FROM deductions d JOIN deduction_types dt ON dt.deduction_type_id = d.deduction_type_id
  WHERE d.employee_id = v_employee_id AND d.payroll_period = v_period_label;

  SET v_full_json = JSON_OBJECT(
    'employee',   v_emp_json,
    'payroll',    v_pay_json,
    'bonuses',    IFNULL(v_bonus_json, JSON_ARRAY()),
    'deductions', IFNULL(v_ded_json,   JSON_ARRAY())
  );

  -- Removed issue_date, allowing generated_at to auto-fill
  INSERT INTO payslips (payroll_id, employee_id, details_json)
  VALUES (p_payroll_id, v_employee_id, v_full_json)
  ON DUPLICATE KEY UPDATE
    details_json = v_full_json;

  SELECT v_full_json AS payslip_json;
END$$

DROP PROCEDURE IF EXISTS sp_add_employee$$
CREATE PROCEDURE sp_add_employee(
  IN p_first_name      VARCHAR(80),
  IN p_middle_name     VARCHAR(80),
  IN p_last_name       VARCHAR(80),
  IN p_gender          VARCHAR(10),
  IN p_birthdate       DATE,
  IN p_email           VARCHAR(150),
  IN p_phone           VARCHAR(20),
  IN p_address         TEXT,
  IN p_department_id   INT UNSIGNED,
  IN p_position_id     INT UNSIGNED,
  IN p_employment_type VARCHAR(10),
  IN p_salary_rate     DECIMAL(12,2),
  IN p_date_hired      DATE,
  OUT p_new_id         INT UNSIGNED,
  OUT p_emp_code       VARCHAR(20)
)
BEGIN
  INSERT INTO employees
    (employee_code, first_name, middle_name, last_name, gender,
     birthdate, email, phone, address,
     department_id, position_id, employment_type, salary_rate, date_hired)
  VALUES
    ('TEMP', p_first_name, p_middle_name, p_last_name, p_gender,
     p_birthdate, p_email, p_phone, p_address,
     p_department_id, p_position_id, p_employment_type, p_salary_rate, p_date_hired);

  SET p_new_id   = LAST_INSERT_ID();
  SET p_emp_code = CONCAT('EMP-', LPAD(p_new_id, 4, '0'));

  UPDATE employees SET employee_code = p_emp_code WHERE employee_id = p_new_id;
END$$

DELIMITER ;

-- VIEWS
CREATE OR REPLACE VIEW v_employee_list AS
SELECT
  e.employee_id, e.employee_code,
  CONCAT(e.first_name,' ',IFNULL(CONCAT(e.middle_name,' '),''),e.last_name) AS full_name,
  e.gender, e.birthdate, e.email, e.phone, d.department_name, p.position_name,
  e.employment_type, e.salary_rate, e.date_hired, e.status
FROM employees e
JOIN departments d ON d.department_id = e.department_id
JOIN positions   p ON p.position_id   = e.position_id;

CREATE OR REPLACE VIEW v_attendance_summary AS
SELECT
  a.attendance_id, e.employee_code,
  CONCAT(e.first_name,' ',e.last_name) AS full_name,
  d.department_name, a.work_date, a.time_in, a.time_out,
  a.hours_worked, a.overtime_hours, a.is_restday, a.remarks
FROM attendance a
JOIN employees   e ON e.employee_id   = a.employee_id
JOIN departments d ON d.department_id = e.department_id;

CREATE OR REPLACE VIEW v_payroll_summary AS
SELECT
  pr.payroll_id, e.employee_code,
  CONCAT(e.first_name,' ',e.last_name) AS full_name,
  d.department_name, p.position_name,
  pr.period_label, pr.period_start, pr.period_end,
  pr.total_days, pr.total_hours, pr.total_ot_hours,
  pr.basic_pay, pr.ot_pay, pr.total_bonuses, pr.gross_salary,
  pr.total_deductions, pr.net_salary, pr.processed_at, pr.status
FROM payroll pr
JOIN employees   e ON e.employee_id   = pr.employee_id
JOIN departments d ON d.department_id = e.department_id
JOIN positions   p ON p.position_id   = e.position_id;

CREATE OR REPLACE VIEW v_period_totals AS
SELECT
  period_label, COUNT(DISTINCT employee_id) AS employee_count,
  ROUND(SUM(gross_salary),2) AS total_gross,
  ROUND(SUM(total_deductions),2) AS total_deductions,
  ROUND(SUM(net_salary),2) AS total_net, status
FROM payroll GROUP BY period_label, status;

CREATE OR REPLACE VIEW v_deduction_breakdown AS
SELECT
  d.payroll_period, e.employee_code,
  CONCAT(e.first_name,' ',e.last_name) AS full_name,
  dt.type_name AS deduction_type, SUM(d.amount) AS total_amount
FROM deductions d
JOIN employees e ON e.employee_id = d.employee_id
JOIN deduction_types dt ON dt.deduction_type_id = d.deduction_type_id
GROUP BY d.payroll_period, d.employee_id, d.deduction_type_id;

CREATE OR REPLACE VIEW v_bonus_breakdown AS
SELECT
  b.payroll_period, e.employee_code,
  CONCAT(e.first_name,' ',e.last_name) AS full_name,
  bt.type_name AS bonus_type, SUM(b.amount) AS total_amount
FROM bonuses b
JOIN employees e ON e.employee_id = b.employee_id
JOIN bonus_types bt ON bt.bonus_type_id = b.bonus_type_id
GROUP BY b.payroll_period, b.employee_id, b.bonus_type_id;