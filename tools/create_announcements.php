<?php
require_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        title VARCHAR(255) DEFAULT NULL, 
        message TEXT NOT NULL, 
        image_url VARCHAR(255) DEFAULT NULL, 
        expires_at DATETIME DEFAULT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    echo 'Success';
} catch(Exception $e) {
    echo $e->getMessage();
}
