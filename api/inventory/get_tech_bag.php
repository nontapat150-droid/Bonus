<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$user_id = $user['id'];

// If admin, they can specify a target_user_id to view
if (in_array($role, ['admin', 'super_admin']) && isset($_GET['target_user_id']) && $_GET['target_user_id'] !== '') {
    $target_user_id = $_GET['target_user_id'];
} else {
    $target_user_id = $user_id; // Default to self
}

try {
    // หา item ทั้งหมดที่ status = 'outbound' 
    // และถูกโอน/เบิกมาที่ $target_user_id (ไม่จำกัดแค่วันนี้ เพื่อให้อยู่ในกระเป๋าตลอดจนกว่าจะใช้งาน)
    $sql = "
        SELECT 
            i.id, i.sn, pm.model_name as product_name, p.name as model_name, l.timestamp
        FROM inventory_items i
        JOIN product_models pm ON i.model_id = pm.id
        JOIN products p ON pm.product_id = p.id
        JOIN (
            SELECT item_id, MAX(id) as max_id
            FROM inventory_logs
            GROUP BY item_id
        ) latest ON i.id = latest.item_id
        JOIN inventory_logs l ON latest.max_id = l.id
        WHERE i.status = 'outbound' 
          AND l.target_user_id = ?
        ORDER BY l.timestamp DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลวัสดุสิ้นเปลืองที่ช่างคนนี้มีอยู่ (User Consumables)
    $sqlConsumables = "
        SELECT 
            uc.consumable_id,
            ic.product_name,
            uc.qty,
            ic.unit
        FROM user_consumables uc
        JOIN inventory_consumable ic ON uc.consumable_id = ic.id
        WHERE uc.user_id = ? AND uc.qty > 0
        ORDER BY ic.product_name ASC
    ";
    $stmtC = $pdo->prepare($sqlConsumables);
    $stmtC->execute([$target_user_id]);
    $consumables = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $items,
        'consumables' => $consumables
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
