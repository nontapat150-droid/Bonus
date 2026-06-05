<?php
// api/inventory/confirm_inbound_session.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$admin_id = $_SESSION['user_id'];

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสำหรับนำเข้า']);
    exit;
}

$processedSN = 0;
$processedQty = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    // Prepare statements for SN
    $stmtFindProdByName = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmtInstProd = $pdo->prepare("INSERT INTO products (product_code, name) VALUES (?, ?)");
    
    $stmtFindModel = $pdo->prepare("SELECT id FROM product_models WHERE product_id = ? AND model_name = ?");
    $stmtInstModel = $pdo->prepare("INSERT INTO product_models (product_id, model_name) VALUES (?, ?)");
    
    $stmtCheckSN = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmtInstItem = $pdo->prepare("INSERT INTO inventory_items (model_id, sn, status) VALUES (?, ?, 'in_stock')");
    $stmtLogSN = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'in', ?)");

    // Prepare statements for QTY
    $stmtFindConsumable = $pdo->prepare("SELECT id FROM inventory_consumable WHERE product_name = ?");
    $stmtUpdateConsumable = $pdo->prepare("UPDATE inventory_consumable SET qty = qty + ? WHERE id = ?");
    $stmtInstConsumable = $pdo->prepare("INSERT INTO inventory_consumable (id, product_name, qty, unit) VALUES (?, ?, ?, ?)");
    $stmtLogConsumable = $pdo->prepare("INSERT INTO inventory_consumable_logs (consumable_id, action, qty, admin_id) VALUES (?, 'in', ?, ?)");

    foreach ($items as $index => $item) {
        if ($item['type'] === 'SN') {
            $pName = trim($item['productName'] ?? '');
            $mName = trim($item['modelName'] ?? '');
            $sn = trim($item['sn'] ?? '');

            if (!$pName || !$mName || !$sn) {
                $errors[] = "แถวที่ " . ($index + 1) . ": ข้อมูลไม่ครบถ้วน (SN)";
                continue;
            }

            // Find or create Product
            $stmtFindProdByName->execute([$pName]);
            $prodId = $stmtFindProdByName->fetchColumn();
            if (!$prodId) {
                $pCode = 'P-' . strtoupper(substr(md5($pName . time()), 0, 6));
                $stmtInstProd->execute([$pCode, $pName]);
                $prodId = $pdo->lastInsertId();
            }

            // Find or create Model
            $stmtFindModel->execute([$prodId, $mName]);
            $modelId = $stmtFindModel->fetchColumn();
            if (!$modelId) {
                $stmtInstModel->execute([$prodId, $mName]);
                $modelId = $pdo->lastInsertId();
            }

            // Check duplicate SN
            $stmtCheckSN->execute([$sn]);
            if ($stmtCheckSN->fetch()) {
                $errors[] = "แถวที่ " . ($index + 1) . ": SN '$sn' ซ้ำในระบบ";
                continue;
            }

            // Insert Item
            $stmtInstItem->execute([$modelId, $sn]);
            $itemId = $pdo->lastInsertId();

            // Log
            $stmtLogSN->execute([$itemId, $admin_id]);

            $processedSN++;

        } elseif ($item['type'] === 'QTY') {
            $name = trim($item['productName'] ?? '');
            $qty = floatval($item['qty'] ?? 0);
            $unit = trim($item['unit'] ?? 'ชิ้น');

            if (!$name || $qty <= 0) {
                $errors[] = "แถวที่ " . ($index + 1) . ": ข้อมูลไม่ครบถ้วน (QTY)";
                continue;
            }

            $stmtFindConsumable->execute([$name]);
            $consumable_id = $stmtFindConsumable->fetchColumn();

            if ($consumable_id) {
                $stmtUpdateConsumable->execute([$qty, $consumable_id]);
            } else {
                $consumable_id = 'CON-' . uniqid();
                $stmtInstConsumable->execute([$consumable_id, $name, $qty, $unit]);
            }

            $stmtLogConsumable->execute([$consumable_id, $qty, $admin_id]);

            $processedQty++;
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'processed_sn' => $processedSN,
        'processed_qty' => $processedQty,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
