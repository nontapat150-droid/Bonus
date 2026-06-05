<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE checkins ADD COLUMN lat VARCHAR(50) DEFAULT NULL, ADD COLUMN lng VARCHAR(50) DEFAULT NULL");
    echo "checkins altered. ";
} catch (Exception $e) {
    echo "checkins: " . $e->getMessage() . ". ";
}

try {
    $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN lat VARCHAR(50) DEFAULT NULL, ADD COLUMN lng VARCHAR(50) DEFAULT NULL");
    echo "ma_checkins altered.";
} catch (Exception $e) {
    echo "ma_checkins: " . $e->getMessage() . ".";
}
