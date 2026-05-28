<?php
require_once '../config/db.php';
require_once '../config/auth.php';

// Only allow super_admin to run this
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die('Unauthorized access.');
}

echo "<h2>Database Update Tool</h2>";

$queries = [
    "ALTER TABLE `inventory_items` ADD UNIQUE KEY IF NOT EXISTS `unique_sn` (`sn`)",
    "ALTER TABLE `products` ADD UNIQUE KEY IF NOT EXISTS `unique_product_name` (`name`)",
    "ALTER TABLE `inventory_logs` MODIFY `target_user_id` int(11) DEFAULT NULL",
    "ALTER TABLE `inventory_logs` MODIFY `receiver_id` int(11) DEFAULT NULL",
    "ALTER TABLE `inventory_items` ADD COLUMN IF NOT EXISTS `remark` TEXT DEFAULT NULL AFTER `status`"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div style='color: green;'>SUCCESS: $sql</div>";
    } catch (PDOException $e) {
        echo "<div style='color: orange;'>SKIPPED/ERROR: $sql (Error: " . $e->getMessage() . ")</div>";
    }
}

echo "<br><a href='../index.php'>กลับหน้าหลัก</a>";
?>