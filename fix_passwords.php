<?php
require_once 'config/db.php';

$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ?");
$stmt->execute([$hash]);

echo "All passwords updated to hash of 'password123'. Hash: " . $hash . "\n";
