<?php
// api/inventory/check_outbound.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sn = trim($_GET['sn'] ?? '');

if (empty($sn)) {
    echo json_encode(['success' => false, 'error' => 'SN is required']);
    exit;
}

try {
    $sql = "SELECT i.sn, p.name as product_name, pm.model_name 
            FROM inventory_items i
            JOIN product_models pm ON i.model_id = pm.id
            JOIN products p ON pm.product_id = p.id
            WHERE i.sn = ? AND i.status = 'in_stock'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sn]);
    $item = $stmt->fetch();

    if ($item) {
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบสินค้า (SN นี้อาจถูกเบิกไปแล้ว หรือไม่มีในระบบ)']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
