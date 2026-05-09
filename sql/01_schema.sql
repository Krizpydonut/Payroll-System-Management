-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE
);

-- 2. Positions Table
CREATE TABLE IF NOT EXISTS positions (
    position_id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) NOT NULL UNIQUE
);

-- 3. Employees Table
CREATE TABLE IF NOT EXISTS employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    birthdate DATE NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    department_id INT NOT NULL,
    position_id INT NOT NULL,
    employment_type ENUM('monthly', 'daily') NOT NULL DEFAULT 'monthly',
    salary_rate DECIMAL(10, 2) NOT NULL,
    date_hired DATE NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE RESTRICT,
    FOREIGN KEY (position_id) REFERENCES positions(position_id) ON DELETE RESTRICT
);

-- 4. Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    time_in TIME NOT NULL,
    time_out TIME NOT NULL,
    hours_worked DECIMAL(5, 2) DEFAULT 0.00,
    overtime_hours DECIMAL(5, 2) DEFAULT 0.00,
    is_restday TINYINT(1) DEFAULT 0,
    remarks VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, work_date)
);

-- 5. Bonus Types
CREATE TABLE IF NOT EXISTS bonus_types (
    bonus_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    is_taxable TINYINT(1) DEFAULT 0
);

-- 6. Deduction Types
CREATE TABLE IF NOT EXISTS deduction_types (
    deduction_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    is_mandatory TINYINT(1) DEFAULT 0
);

-- 7. Bonuses (Transactions)
CREATE TABLE IF NOT EXISTS bonuses (
    bonus_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bonus_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    date_given DATE NOT NULL,
    payroll_period VARCHAR(50) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (bonus_type_id) REFERENCES bonus_types(bonus_type_id) ON DELETE RESTRICT
);

-- 8. Deductions (Transactions)
CREATE TABLE IF NOT EXISTS deductions (
    deduction_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    deduction_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    date_applied DATE NOT NULL,
    payroll_period VARCHAR(50) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(deduction_type_id) ON DELETE RESTRICT
);

-- 9. Payroll Register 
CREATE TABLE IF NOT EXISTS payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    period_label VARCHAR(50) NOT NULL,
    total_days DECIMAL(5, 2) DEFAULT 0.00,
    total_hours DECIMAL(6, 2) DEFAULT 0.00,
    total_ot_hours DECIMAL(6, 2) DEFAULT 0.00,
    basic_pay DECIMAL(10, 2) DEFAULT 0.00,
    ot_pay DECIMAL(10, 2) DEFAULT 0.00,
    total_bonuses DECIMAL(10, 2) DEFAULT 0.00,
    total_deductions DECIMAL(10, 2) DEFAULT 0.00,
    gross_salary DECIMAL(10, 2) DEFAULT 0.00,
    net_salary DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll_period (employee_id, period_label)
);

-- 10. JSON Payslips 
CREATE TABLE IF NOT EXISTS payslips (
    payslip_id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    employee_id INT NOT NULL,
    details_json JSON NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_payslip (payroll_id)
);