<?php
// api/oil/check_vehicle.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$user_id = $_SESSION['user_id'];

if (empty($plate)) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT last_tech_id FROM vehicles WHERE license_plate = ?");
$stmt->execute([$plate]);
$vehicle = $stmt->fetch();

if ($vehicle && $vehicle['last_tech_id'] == $user_id) {
    echo json_encode(['success' => true, 'locked_to_current_user' => true]);
} else {
    echo json_encode(['success' => true, 'locked_to_current_user' => false]);
}
