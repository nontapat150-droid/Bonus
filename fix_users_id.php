<?php
require 'config/db.php';
try {
    echo "<h3>Current Table Schema:</h3><pre>";
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($result);
    echo "</pre>";

    echo "<h3>Attempting ALTER TABLE...</h3>";
    $pdo->exec("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    echo "<p style='color:green;'>Successfully updated users table id column to AUTO_INCREMENT.</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
