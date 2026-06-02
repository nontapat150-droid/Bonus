<?php
// api/start_day/delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// ป้องกันขั้นสูงสุด: ให้เฉพาะ super_admin ทำรายการได้เท่านั้น
if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง: เฉพาะผู้ดูแลระบบสูงสุด (Super Admin) เท่านั้นที่สามารถลบข้อมูลนี้ได้']);
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
    // **หมายเหตุ: เปลี่ยนชื่อตาราง start_day_records เป็นชื่อตารางจริงในฐานข้อมูลของคุณ**
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