<?php
// api/inventory/get_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT 
                l.timestamp,
                l.action,
                i.sn,
                p.name as product_name,
                pm.model_name,
                u.full_name as admin_name
            FROM inventory_logs l
            JOIN inventory_items i ON l.item_id = i.id
            JOIN product_models pm ON i.model_id = pm.id
            JOIN products p ON pm.product_id = p.id
            JOIN users u ON l.admin_id = u.id
            ORDER BY l.timestamp DESC
            LIMIT 500"; // Limit to recent 500 for performance

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
