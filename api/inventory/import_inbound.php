<?php
// api/inventory/import_inbound.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสินค้า']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$successCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    $stmtFindProd = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmtInstProd = $pdo->prepare("INSERT INTO products (product_code, name) VALUES (?, ?)");

    $stmtFindModel = $pdo->prepare("SELECT id FROM product_models WHERE product_id = ? AND model_name = ?");    
    $stmtInstModel = $pdo->prepare("INSERT INTO product_models (product_id, model_name) VALUES (?, ?)");        

    $stmtCheckSN = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmtInstItem = $pdo->prepare("INSERT INTO inventory_items (model_id, sn, status) VALUES (?, ?, 'in_stock')");
    $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'in', ?)");     

    foreach ($items as $index => $item) {
        $pName = trim($item['product_name'] ?? '');
        $mName = trim($item['model_name'] ?? '');
        $sn = trim($item['sn'] ?? '');

        if (empty($pName) || empty($mName)) {
            $errors[] = "แถวที่ " . ($index + 1) . ": จำเป็นต้องระบุชื่อสินค้าและรุ่น";
            continue;
        }

        if (empty($sn)) {
            $sn = 'SYS-' . strtoupper(uniqid());
        }

        $stmtCheckSN->execute([$sn]);
        if ($stmtCheckSN->fetch()) {
            $errors[] = "แถวที่ " . ($index + 1) . ": หมายเลขซีเรียล '$sn' มีอยู่ในระบบแล้ว";
            continue;
        }

        $stmtFindProd->execute([$pName]);
        $prodId = $stmtFindProd->fetchColumn();

        if (!$prodId) {
            $pCode = 'P-' . strtoupper(substr(md5($pName . time()), 0, 6));
            $stmtInstProd->execute([$pCode, $pName]);
            $prodId = $pdo->lastInsertId();
        }

        $stmtFindModel->execute([$prodId, $mName]);
        $modelId = $stmtFindModel->fetchColumn();

        if (!$modelId) {
            $stmtInstModel->execute([$prodId, $mName]);
            $modelId = $pdo->lastInsertId();
        }

        $stmtInstItem->execute([$modelId, $sn]);
        $itemId = $pdo->lastInsertId();

        $stmtLog->execute([$itemId, $admin_id]);

        $successCount++;
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'imported' => $successCount,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}