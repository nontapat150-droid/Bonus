<?php
require_once 'config/db.php';

try {
    echo "Starting migration...\n";
    
    // 1. Alter inventory_items status
    $pdo->exec("ALTER TABLE inventory_items MODIFY COLUMN `status` enum('in_stock','outbound','used') NOT NULL DEFAULT 'in_stock'");
    echo "Updated inventory_items status ENUM.\n";

    // 2. Alter inventory_logs action
    $pdo->exec("ALTER TABLE inventory_logs MODIFY COLUMN `action` enum('in','out','transfer','used') NOT NULL");
    echo "Updated inventory_logs action ENUM.\n";
    
    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
