<?php
require 'config/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'days_off'");
    file_put_contents('schema_check.txt', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)));
} catch (Exception $e) {
    file_put_contents('schema_check.txt', $e->getMessage());
}
