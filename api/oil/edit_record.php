<?php
// api/oil/edit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$tech_id = $input['tech_id'] ?? null;
$license_plate = trim($input['license_plate'] ?? '');
$date_recorded = $input['date_recorded'] ?? null;
$mileage = intval($input['mileage'] ?? 0);
$liters = floatval($input['liters'] ?? 0);
$price_per_liter = floatval($input['price_per_liter'] ?? 0);
$job_count = intval($input['job_count'] ?? 0);

if (!$id || !$tech_id || !$license_plate || !$date_recorded || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$date_recorded_mysql = date('Y-m-d H:i:s', strtotime($date_recorded));
$total_price = isset($input['total_price']) && $input['total_price'] !== '' ? round(floatval($input['total_price'])) : round($liters * $price_per_liter);

// คำนวณระยะทางจากรอบก่อนหน้า (ไม่รวมตัวเอง)
$distance = 0;
$stmtLastMile = $pdo->prepare("SELECT mileage FROM oil_records WHERE license_plate = ? AND date_recorded < ? AND id != ? ORDER BY date_recorded DESC LIMIT 1");
$stmtLastMile->execute([$license_plate, $date_recorded_mysql, $id]);
$lastMileage = $stmtLastMile->fetchColumn();
if ($lastMileage && $mileage >= $lastMileage) {
    $distance = $mileage - $lastMileage;
}

try {
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$tech_id]);
    $fullName = $stmtUser->fetchColumn();

    $stmt = $pdo->prepare("UPDATE oil_records SET 
        tech_id = ?, license_plate = ?, filler_name = ?, date_recorded = ?, mileage = ?,
        liters = ?, price_per_liter = ?, total_price = ?, distance = ?, job_count = ?
        WHERE id = ?");
    $stmt->execute([
        $tech_id, $license_plate, $fullName ?: null, $date_recorded_mysql, $mileage,
        $liters, $price_per_liter, $total_price, $distance, $job_count, $id
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>