<?php
require 'config/db.php';
$stmt = $pdo->query('DESCRIBE inventory_logs');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('DESCRIBE inventory_consumable_logs');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
