<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

try {
    $data = ['sn_products' => [], 'consumables' => []];
    
    // ดึงสินค้าแบบ SN
    $stmt = $pdo->query("SELECT p.name, pm.model_name FROM products p JOIN product_models pm ON p.id = pm.product_id ORDER BY pm.model_name");
    while ($row = $stmt->fetch()) {
        // สลับเอา model_name มาเป็น key (Product) และ name มาเป็น value (Model)
        $pName = $row['model_name']; 
        $mName = $row['name'];
        if (!isset($data['sn_products'][$pName])) $data['sn_products'][$pName] = [];
        $data['sn_products'][$pName][] = $mName;
    }

    // ดึงสินค้าแบบนับจำนวน (วัสดุสิ้นเปลือง)
    $stmtCon = $pdo->query("SELECT id, product_name, qty, unit FROM inventory_consumable ORDER BY product_name");
    while ($row = $stmtCon->fetch()) {
        $data['consumables'][] = [
            'consumable_id' => $row['id'], 
            'name' => $row['product_name'], 
            'qty' => $row['qty'],
            'unit' => $row['unit']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>