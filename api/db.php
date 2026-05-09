<?php
// /api/db.php - ONLY for connection logic

$host = 'localhost';
$dbname = 'payroll_db';
$username = 'root'; 
$password = '';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed: " . $e->getMessage()
    ]));
}
?>