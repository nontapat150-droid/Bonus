<?php
// api/inventory/delete_item.php
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
$product_name = $data['product_name'] ?? null;
$model_name = $data['model_name'] ?? null;

if (!$product_name) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุข้อมูลสินค้า']);
    exit;
}

try {
    $pdo->beginTransaction();

    // เนื่องจาก DB สลับกัน: p.name เก็บ model_name (จาก UI), pm.model_name เก็บ product_name (จาก UI)
    $stmt = $pdo->prepare("
        SELECT pm.id as model_id 
        FROM product_models pm 
        JOIN products p ON pm.product_id = p.id 
        WHERE pm.model_name = ? AND p.name = ?
    ");
    // กรณีที่ส่งมาแต่ product_name แต่ไม่มี model_name (วัสดุสิ้นเปลือง) ให้ค้นหาเฉพาะที่ model_name ว่าง
    if ($model_name) {
        $stmt->execute([$product_name, $model_name]);
    } else {
        $stmt = $pdo->prepare("
            SELECT pm.id as model_id 
            FROM product_models pm 
            JOIN products p ON pm.product_id = p.id 
            WHERE pm.model_name = ? AND (p.name IS NULL OR p.name = '')
        ");
        $stmt->execute([$product_name]);
    }
    
    $model = $stmt->fetch();

    if ($model) {
        $modelId = $model['model_id'];
        // ลบ Logs ก่อน
        $stmtLogs = $pdo->prepare("DELETE FROM inventory_logs WHERE item_id IN (SELECT id FROM inventory_items WHERE model_id = ?)");
        $stmtLogs->execute([$modelId]);

        // ลบ Items
        $stmtItems = $pdo->prepare("DELETE FROM inventory_items WHERE model_id = ?");
        $stmtItems->execute([$modelId]);

        // ลบ Model
        $stmtModel = $pdo->prepare("DELETE FROM product_models WHERE id = ?");
        $stmtModel->execute([$modelId]);
        
        $count = $stmtModel->rowCount();
    } else {
        $count = 0;
    }

    $pdo->commit();

    if ($count > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบรายการสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการที่ต้องการลบ']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

