<?php
// api/attendance.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET: Fetch Data (Untouched)
// ==========================================
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    if ($action === 'employees') {
        try {
            $sql = "SELECT employee_id as id, employee_code as code, first_name as firstName, last_name as lastName, employment_type as type FROM employees WHERE status = 'active'";
            $employees = $pdo->query($sql)->fetchAll();
            echo json_encode(["status" => "success", "data" => $employees]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Failed to load employees."]);
        }
        exit();
    }

    try {
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $sql = "SELECT * FROM v_attendance_summary";
        $params = [];
        if ($date) {
            $sql .= " WHERE work_date = ?";
            $params[] = $date;
        }
        $sql .= " ORDER BY work_date DESC, full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendance = $stmt->fetchAll();
        
        echo json_encode(["status" => "success", "data" => $attendance]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Failed to fetch attendance: " . $e->getMessage()]);
    }
}

// ==========================================
// POST: ADVANCED TIMEKEEPING ENGINE
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $empId = $input['employee_id'];
        $date = $input['date'];
        $time_in = $input['time_in'];
        $time_out = $input['time_out'];

        // --- Configuration: Standard Shift ---
        $shift_start = "08:00:00";
        $shift_end = "17:00:00";
        
        // --- Initialization ---
        $status = 'Absent';
        $is_late = 0;
        $is_undertime = 0;
        $needs_review = 0;
        $late_mins = 0;
        $undertime_mins = 0;
        $reg_hours = 0;
        $ot_hours = 0;

        // 1. Status Triggers & Missing Logs
        if (empty($time_in) && empty($time_out)) {
            $status = 'Absent';
        } elseif (empty($time_in) || empty($time_out)) {
            // The Forgetful Employee Edge Case
            $status = 'Incomplete';
            $needs_review = 1;
        } else {
            // Both logs exist
            $status = 'Present';
            
            // Convert to robust DateTime timestamps
            $tIn = strtotime("$date $time_in");
            $tOut = strtotime("$date $time_out");
            $sStart = strtotime("$date $shift_start");
            $sEnd = strtotime("$date $shift_end");

            // 2. The Midnight Trap (Cross-day shift fix)
            if ($tOut < $tIn) {
                $tOut += 86400; // Add 24 hours
            }

            // 3. Late & Grace Period Logic (15 mins = 900 seconds)
            $grace_limit = $sStart + 900;
            if ($tIn > $grace_limit) {
                $is_late = 1;
                $late_mins = floor(($tIn - $sStart) / 60);
            }

            // 4. Undertime Logic
            if ($tOut < $sEnd) {
                $is_undertime = 1;
                $undertime_mins = floor(($sEnd - $tOut) / 60);
            }

            // 5. The Calculation Engine
            $gross_hours = ($tOut - $tIn) / 3600;
            
            if ($gross_hours <= 4.0) {
                $status = 'Half-day';
                $net_hours = $gross_hours; // No break deducted for half days
            } else {
                $net_hours = max(0, $gross_hours - 1); // Deduct 1 hour unpaid break
            }

            // Separate Regular from Overtime
            $reg_hours = min(8, $net_hours);
            $ot_hours = max(0, $net_hours - 8);
        }

        // 6. Secure Database Execution (Upsert)
        $sql = "INSERT INTO attendance 
                (employee_id, work_date, time_in, time_out, hours_worked, overtime_hours, status, is_late, is_undertime, needs_review, late_minutes, undertime_minutes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                time_in = VALUES(time_in), 
                time_out = VALUES(time_out), 
                hours_worked = VALUES(hours_worked), 
                overtime_hours = VALUES(overtime_hours),
                status = VALUES(status),
                is_late = VALUES(is_late),
                is_undertime = VALUES(is_undertime),
                needs_review = VALUES(needs_review),
                late_minutes = VALUES(late_minutes),
                undertime_minutes = VALUES(undertime_minutes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $empId, $date, 
            empty($time_in) ? null : $time_in, 
            empty($time_out) ? null : $time_out, 
            $reg_hours, $ot_hours, 
            $status, $is_late, $is_undertime, $needs_review, $late_mins, $undertime_mins
        ]);

        // Provide a smart feedback message based on the evaluation
        $msg = "Logged successfully!";
        if ($needs_review) $msg = "Warning: Incomplete log detected. Flagged for review.";
        if ($is_late) $msg = "Logged. Marked as Late ($late_mins mins).";

        echo json_encode([
            "status" => "success", 
            "message" => $msg
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "System Error: " . $e->getMessage()]);
    }
}
?>