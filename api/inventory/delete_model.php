<?php
// api/inventory/delete_model.php
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

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ Model ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ลบ Logs ที่เกี่ยวข้องกับ Items ของ Model นี้
    $stmt = $pdo->prepare("DELETE FROM inventory_logs WHERE item_id IN (SELECT id FROM inventory_items WHERE model_id = ?)");
    $stmt->execute([$id]);

    // ลบ Model (จะ Cascade ไปลบ Items ใน inventory_items อัตโนมัติ)
    $stmt = $pdo->prepare("DELETE FROM product_models WHERE id = ?");
    $stmt->execute([$id]);

    $count = $stmt->rowCount();
    $pdo->commit();

    if ($count > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบรุ่นสินค้าและรายการที่เกี่ยวข้องสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรุ่นสินค้าที่ต้องการลบ']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
