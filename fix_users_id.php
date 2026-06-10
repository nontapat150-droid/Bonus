<?php
require 'config/db.php';
try {
    echo "<h3>Attempting to add PRIMARY KEY and AUTO_INCREMENT...</h3>";
    
    // Step 1: Try to add PRIMARY KEY (ถ้ายังไม่มี)
    try {
        $pdo->exec("ALTER TABLE users ADD PRIMARY KEY (id)");
        echo "<p>Added PRIMARY KEY to 'id'.</p>";
    } catch (Exception $e) {
        echo "<p>PRIMARY KEY might already exist or error: " . $e->getMessage() . "</p>";
    }

    // Step 2: Try to add AUTO_INCREMENT
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
        echo "<p style='color:green;'>Successfully updated users table id column to AUTO_INCREMENT.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>AUTO_INCREMENT Error: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
