<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$pName = trim($input['product_name'] ?? '');
$mName = trim($input['model_name'] ?? '');
$sn = trim($input['sn'] ?? '');
$admin_id = $_SESSION['user_id'];

if (!$pName || !$mName || !$sn) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']); exit;
}

try {
    $pdo->beginTransaction();

    // เช็ค SN ซ้ำ
    $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmt->execute([$sn]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'status' => 'duplicate']); exit;
    }

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

    // เพิ่ม Item และ Log
    $pdo->prepare("INSERT INTO inventory_items (model_id, sn, status) VALUES (?, ?, 'in_stock')")->execute([$modelId, $sn]);
    $itemId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'in', ?)")->execute([$itemId, $admin_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'status' => 'added']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>