<?php
// config/db.php

$date_default_timezone_set('Asia/Bangkok');

// Allow overriding connection via environment variables (recommended for dev/production)
$host = getenv('DB_HOST') ?: 'sql207.infinityfree.com';
$db   = getenv('DB_NAME') ?: 'if0_42036532_ro';
$user = getenv('DB_USER') ?: 'if0_42036532';
$pass = getenv('DB_PASS') ?: 'Wxv8bmb9Cak';
$charset = getenv('DB_CHARSET') ?: 'utf8';

// If running on a local development host, prefer XAMPP/MySQL defaults
// Detect by common localhost hostnames
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
if (stripos($httpHost, 'localhost') !== false || stripos($httpHost, '127.0.0.1') !== false) {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    // keep DB_NAME if provided; otherwise try a sensible default 'bonus'
    $db = getenv('DB_NAME') ?: $db;
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 🌟 ดักจับ Error และส่งกลับเป็น JSON เพื่อให้ Javascript ไม่พัง
    $isApiCall = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    
    if ($isApiCall) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Database Error: ' . $e->getMessage(),
            'hint' => 'ตรวจสอบ config/db.php หรือตั้งค่า DB_HOST/DB_USER/DB_PASS'
        ]);
    } else {
        die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage() . " — ตรวจสอบ config/db.php หรือตัวแปรแวดล้อม DB_HOST/DB_USER/DB_PASS");
    }
    exit;
}
?>