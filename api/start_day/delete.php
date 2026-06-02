<?php
// api/start_day/delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// 🌟 จุดที่แก้: ปลดล็อกให้ทั้ง Admin และ Super Admin สามารถกดลบประวัติได้
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง: เฉพาะ Admin และ Super Admin เท่านั้น']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรายการที่ต้องการลบ']);
    exit;
}

try {
    // ลบข้อมูลประวัติค่าแรกเข้า
    $stmt = $pdo->prepare("DELETE FROM start_day_records WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลถูกลบไปแล้ว']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()]);
}
?>