<?php
// config/db.php

date_default_timezone_set('Asia/Bangkok');

$host = 'sql207.infinityfree.com';
$db   = 'if0_42036532_ro';
$user = 'if0_42036532'; // Adjust based on XAMPP default
$pass = 'Wxv8bmb9Cak';     // Adjust based on XAMPP default
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
    die("เชื่อมต่อฐานข้อมูลล้มเหลว กรุณาตรวจสอบว่ามีฐานข้อมูล '$db' อยู่จริง ข้อผิดพลาด: " . $e->getMessage());
}
?>