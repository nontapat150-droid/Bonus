<?php
// config/db.php

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
];

try {
    // We try to connect. If database doesn't exist, we might need a setup script or manual creation first.     
    // For local XAMPP, you might need to create the database `smart_business_suite` via phpMyAdmin.
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // For initial setup, if DB doesn't exist, we just fail gracefully with a message
    die("เชื่อมต่อฐานข้อมูลล้มเหลว กรุณาตรวจสอบว่ามีฐานข้อมูล '$db' อยู่จริง ข้อผิดพลาด: " . $e->getMessage());
}
