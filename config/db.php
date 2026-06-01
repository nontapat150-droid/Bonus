<?php
// config/db.php

date_default_timezone_set('Asia/Bangkok');

$host = 'sql207.infinityfree.com';
$db   = 'if0_42036532_ro';
$user = 'if0_42036532';
$pass = 'Wxv8bmb9Cak';
$charset = 'utf8';

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
            'error' => 'Database Error: ' . $e->getMessage()
        ]);
    } else {
        die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
    }
    exit;
}
?>