<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

try {
    // JOIN ผู้ทำรายการ (admin) และ ผู้รับ (target_user)
    $sql = "SELECT 
                l.timestamp, 
                l.action, 
                i.sn, 
                p.name as product_name, 
                pm.model_name, 
                u.full_name as admin_name,
                t1.team_name as admin_team,
                tu.full_name as target_name,
                t2.team_name as target_team
            FROM inventory_logs l
            JOIN inventory_items i ON l.item_id = i.id
            JOIN product_models pm ON i.model_id = pm.id
            JOIN products p ON pm.product_id = p.id
            LEFT JOIN users u ON l.admin_id = u.id
            LEFT JOIN teams t1 ON u.team_id = t1.id
            LEFT JOIN users tu ON l.target_user_id = tu.id
            LEFT JOIN teams t2 ON tu.team_id = t2.id
            ORDER BY l.timestamp DESC 
            LIMIT 1000";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}