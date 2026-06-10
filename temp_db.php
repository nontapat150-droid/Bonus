<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT id, image_path, checkout_image FROM checkins LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query('SELECT id, image_path, checkout_image FROM ma_checkins LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
