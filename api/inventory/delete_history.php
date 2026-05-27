<?php
// api/inventory/delete_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$log_type = $data['log_type'] ?? 'sn';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ ID']);
    exit;
}

try {
    $table = ($log_type === 'consumable') ? 'inventory_consumable_logs' : 'inventory_logs';
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบประวัติสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการประวัติที่ต้องการลบ']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
