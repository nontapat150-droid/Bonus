<?php
// api/notifications/delete_notification.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

// ให้สิทธิ์เฉพาะแอดมินในการลบ
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลที่ต้องการลบ']);
    exit;
}

try {
    // ลบการแจ้งเตือนทิ้ง (ระบบฐานข้อมูลจะลบประวัติการอ่านของทุกคนที่เชื่อมอยู่ออกให้อัตโนมัติ)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>