<?php
// api/inventory/add_sn_fast.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
// สลับค่าที่รับมาเพื่อให้บันทึกลง Table ถูกต้องตามความต้องการ (Product -> pm.model_name, Model -> p.name)
$pName = trim($input['model_name'] ?? ''); 
$mName = trim($input['product_name'] ?? '');
$sn = trim($input['sn'] ?? '');
$admin_id = $_SESSION['user_id'];

if (!$pName || !$mName || !$sn) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']); exit;
}

try {
    $pdo->beginTransaction();

    // หาหรือสร้าง Product
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmt->execute([$pName]);
    $prodId = $stmt->fetchColumn();
    if (!$prodId) {
        $pCode = 'P-' . strtoupper(substr(md5($pName . time()), 0, 6));
        $pdo->prepare("INSERT INTO products (product_code, name) VALUES (?, ?)")->execute([$pCode, $pName]);
        $prodId = $pdo->lastInsertId();
    }

    // หาหรือสร้าง Model
    $stmt = $pdo->prepare("SELECT id FROM product_models WHERE product_id = ? AND model_name = ?");
    $stmt->execute([$prodId, $mName]);
    $modelId = $stmt->fetchColumn();
    if (!$modelId) {
        $pdo->prepare("INSERT INTO product_models (product_id, model_name) VALUES (?, ?)")->execute([$prodId, $mName]);
        $modelId = $pdo->lastInsertId();
    }

    // เช็ค SN ซ้ำ
    $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmt->execute([$sn]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'status' => 'duplicate']); exit;
    }

    // บันทึกรับเข้าสต็อก
    $pdo->prepare("INSERT INTO inventory_items (model_id, sn, status) VALUES (?, ?, 'in_stock')")->execute([$modelId, $sn]);
    $itemId = $pdo->lastInsertId();
    
    // บันทึกประวัติ (Log)
    $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'in', ?)")->execute([$itemId, $admin_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'status' => 'added']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}