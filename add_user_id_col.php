<?php
require 'config/db.php';

try {
    // Add user_id to inventory_logs
    $stmt = $pdo->query("SHOW COLUMNS FROM inventory_logs LIKE 'user_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN user_id INT DEFAULT NULL AFTER target_user_id");
        echo "Added user_id to inventory_logs\n";
    } else {
        echo "user_id already exists in inventory_logs\n";
    }

    // Add user_id to inventory_consumable_logs
    $stmt2 = $pdo->query("SHOW COLUMNS FROM inventory_consumable_logs LIKE 'user_id'");
    if (!$stmt2->fetch()) {
        $pdo->exec("ALTER TABLE inventory_consumable_logs ADD COLUMN user_id INT DEFAULT NULL AFTER target_user_id");
        echo "Added user_id to inventory_consumable_logs\n";
    } else {
        echo "user_id already exists in inventory_consumable_logs\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
