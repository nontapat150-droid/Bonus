<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN days_off JSON DEFAULT NULL");
    echo 'Success';
} catch(Exception $e) {
    if ($e->getCode() == '42S21') {
        echo 'Column already exists';
    } else {
        echo $e->getMessage();
    }
}
