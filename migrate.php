<?php
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
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'technician', 'sales', 'intern') NOT NULL DEFAULT 'technician'");
    echo "Success\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
