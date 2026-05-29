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

if (!$id || !$tech_id || !$license_plate) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // ดึงชื่อผู้เติมจาก users และอัปเดต tech_id, license_plate และ filler_name
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$tech_id]);
    $fullName = $stmtUser->fetchColumn();

    $stmt = $pdo->prepare("UPDATE oil_records SET tech_id = ?, license_plate = ?, filler_name = ? WHERE id = ?");
    $stmt->execute([$tech_id, $license_plate, $fullName ?: null, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>