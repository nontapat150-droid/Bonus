<?php
// api/work_records/delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('intern')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ ID ของรายงาน']);
    exit;
}

try {
    // ตรวจสอบว่ารายงานเป็นของผู้ใช้ปัจจุบัน
    $stmt = $pdo->prepare("DELETE FROM work_records WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบรายงานสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายงานนี้']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
