<?php
// api/leave/update_status.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$leaveId = (int)($body['leave_id'] ?? 0);
$status  = trim($body['status'] ?? '');

if (!$leaveId || !in_array($status, ['approved', 'rejected'], true)) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $leaveId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการที่ต้องการ']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => $status === 'approved' ? 'อนุมัติเรียบร้อยแล้ว' : 'ปฏิเสธเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
