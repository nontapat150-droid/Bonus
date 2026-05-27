<?php
// api/inventory/get_products.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['super_admin', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    // Get all products and models
    $stmt = $pdo->query("
        SELECT 
            p.id as product_id,
            p.product_code,
            p.name as product_name,
            m.id as model_id,
            m.model_name
        FROM products p
        LEFT JOIN product_models m ON p.id = m.product_id
        ORDER BY p.name ASC, m.model_name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($rows as $row) {
        $pId = $row['product_id'];
        if (!isset($products[$pId])) {
            $products[$pId] = [
                'id' => $pId,
                'code' => $row['product_code'],
                'name' => $row['product_name'],
                'models' => []
            ];
        }
        if ($row['model_id']) {
            $products[$pId]['models'][] = [
                'id' => $row['model_id'],
                'name' => $row['model_name']
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => array_values($products)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
