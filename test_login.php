<?php
require_once 'config/db.php';

echo "1. DB Connected successfully.\n";

$username = 'superadmin';
$password = 'password123';

$stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user) {
    echo "2. User found: " . $user['username'] . "\n";
    echo "Hash in DB: " . $user['password_hash'] . "\n";
    
    if (password_verify($password, $user['password_hash'])) {
        echo "3. Password verification: SUCCESS\n";
    } else {
        echo "3. Password verification: FAILED\n";
        echo "Let's generate a new hash to see what it should be: " . password_hash($password, PASSWORD_DEFAULT) . "\n";
    }
} else {
    echo "2. User NOT found.\n";
}
