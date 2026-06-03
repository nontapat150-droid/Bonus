<?php
// api/dispatch/delete_reschedule_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ในการลบประวัติการเลื่อนนัด']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรายการ']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM job_reschedules WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลถูกลบไปแล้ว']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()]);
}
