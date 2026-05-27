<?php
// api/inventory/get_stock.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    $sql = "SELECT
                p.product_code,
                p.name as product_name,
                pm.id as model_id,
                pm.model_name,
                COUNT(i.id) as qty,
                GROUP_CONCAT(CONCAT(i.id, ':', i.sn) SEPARATOR '|') as sn_list
            FROM products p
            JOIN product_models pm ON p.id = pm.product_id
            LEFT JOIN inventory_items i ON pm.id = i.model_id AND i.status = 'in_stock'
            GROUP BY p.id, pm.id
            ORDER BY p.name ASC, pm.model_name ASC";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlConsumables = "SELECT
                            id as consumable_id,
                            product_name,
                            qty,
                            unit
                       FROM inventory_consumable
                       ORDER BY product_name ASC";
    $stmtC = $pdo->query($sqlConsumables);
    $consumables = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine data for UI or send separately
    echo json_encode(['success' => true, 'data' => $data, 'consumables' => $consumables]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}