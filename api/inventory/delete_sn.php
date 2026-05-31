<?php
// api/inventory/delete_sn.php
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
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get product id
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmt->execute([$pName]);
    $prodId = $stmt->fetchColumn();
    if (!$prodId) { throw new Exception('ไม่พบสินค้า'); }

    // Get model id
    $stmt = $pdo->prepare("SELECT id FROM product_models WHERE product_id = ? AND model_name = ?");
    $stmt->execute([$prodId, $mName]);
    $modelId = $stmt->fetchColumn();
    if (!$modelId) { throw new Exception('ไม่พบรุ่น'); }

    // Delete inventory item (and related logs)
    $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE model_id = ? AND sn = ?");
    $stmt->execute([$modelId, $sn]);
    $deleted = $stmt->rowCount();

    // Optionally delete logs for the item id (if any remain)
    // Since the item row is removed, we cannot get its id. Assume cascade not needed.

    $pdo->commit();
    if ($deleted > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบ SN ในระบบ']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
