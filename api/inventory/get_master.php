<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

try {
    $data = ['sn_products' => [], 'consumables' => []];
    
    // ดึงสินค้าแบบ SN
    $stmt = $pdo->query("SELECT p.name, pm.model_name FROM products p JOIN product_models pm ON p.id = pm.product_id ORDER BY p.name");
    while ($row = $stmt->fetch()) {
        if (!isset($data['sn_products'][$row['name']])) $data['sn_products'][$row['name']] = [];
        $data['sn_products'][$row['name']][] = $row['model_name'];
    }

    // ดึงสินค้าแบบนับจำนวน (วัสดุสิ้นเปลือง)
    $stmtCon = $pdo->query("SELECT product_name, unit FROM inventory_consumable ORDER BY product_name");
    while ($row = $stmtCon->fetch()) {
        $data['consumables'][] = ['name' => $row['product_name'], 'unit' => $row['unit']];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>