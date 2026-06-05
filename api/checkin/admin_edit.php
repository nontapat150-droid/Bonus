<?php
// api/checkin/admin_edit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// ทำได้เฉพาะ Super Admin ตามที่ผู้ใช้แจ้ง
if (!hasRole(['super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์ในการจัดการข้อมูลนี้']);
    exit;
}

$id = $_POST['id'] ?? null;
$type = $_POST['type'] ?? 'regular';
$checkin_time = $_POST['checkin_time'] ?? null;
$checkout_time = $_POST['checkout_time'] ?? null;
$admin_status = $_POST['admin_status'] ?? null;

if (!$id || !$checkin_time) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน (ต้องระบุเวลาเข้างาน)']);
    exit;
}

if ($checkout_time === '') {
    $checkout_time = null;
}
if ($admin_status === '') {
    $admin_status = null;
}

$table = ($type === 'ma') ? 'ma_checkins' : 'checkins';

try {
    $sql = "UPDATE $table SET checkin_time = ?, checkout_time = ?, admin_status = ?, admin_edited = 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$checkin_time, $checkout_time, $admin_status, $id]);

    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
