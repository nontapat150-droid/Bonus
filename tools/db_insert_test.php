<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Test insert into oil_records with safe sample data
    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'TEST123', 10.5, 12345, 35.5, 372.75, date('Y-m-d H:i:s')]);
    echo "Inserted ID: " . $pdo->lastInsertId() . PHP_EOL;
} catch (PDOException $e) {
    echo "PDOException: " . $e->getMessage() . PHP_EOL;
    echo "SQLSTATE: " . $e->getCode() . PHP_EOL;
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
}
