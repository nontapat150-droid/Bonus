<?php
// api/users/approve.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

// อนุญาตให้ super_admin (และ admin ถ้าต้องการให้ admin อนุมัติได้ด้วย สามารถแก้เป็น hasRole(['admin', 'super_admin']) ได้)
if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if ($id && in_array($status, ['approved', 'rejected'])) {
    try {
        if ($status === 'approved') {
            // กรณี "อนุมัติ" -> ให้อัปเดตสถานะเพื่อให้เข้าใช้งานได้
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        } else if ($status === 'rejected') {
            // กรณี "ปฏิเสธ" -> ลบข้อมูลทิ้งไปเลย จะได้ไม่ถูกนำเข้าระบบและไม่รกฐานข้อมูล
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
}
?>