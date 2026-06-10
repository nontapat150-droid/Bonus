<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    echo "Successfully updated users table id column to AUTO_INCREMENT.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
