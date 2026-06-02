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
    // ดึงประวัติที่เกี่ยวข้องกับ technician คนนี้
    // 1. รับของจาก admin (outbound มาที่ตัวเอง)
    // 2. โอนของให้คนอื่น (transfer ออกจากตัวเอง)
    // 3. กดใช้งาน (used โดยตัวเอง)
    // 4. รับของโอนจากช่างคนอื่น (transfer มาที่ตัวเอง)

    $sql = "
        SELECT * FROM (
            SELECT 
                l1.id,
                'sn' as log_type,
                l1.timestamp, 
                l1.action, 
                i.sn, 
                pm.model_name as product_name, 
                p.name as model_name, 
                u1.full_name as admin_name,
                tu1.full_name as target_name
            FROM inventory_logs l1
            LEFT JOIN inventory_items i ON l1.item_id = i.id
            LEFT JOIN product_models pm ON i.model_id = pm.id
            LEFT JOIN products p ON pm.product_id = p.id
            LEFT JOIN users u1 ON l1.admin_id = u1.id
            LEFT JOIN users tu1 ON l1.target_user_id = tu1.id
            WHERE l1.target_user_id = ? OR l1.admin_id = ? OR l1.user_id = ?

            UNION ALL

            SELECT 
                l2.id,
                'consumable' as log_type,
                l2.timestamp, 
                l2.action, 
                CONCAT(l2.qty, ' ', c.unit) as sn, 
                c.product_name, 
                'วัสดุสิ้นเปลือง' as model_name, 
                u2.full_name as admin_name,
                tu2.full_name as target_name
            FROM inventory_consumable_logs l2
            LEFT JOIN inventory_consumable c ON l2.consumable_id = c.id
            LEFT JOIN users u2 ON l2.admin_id = u2.id
            LEFT JOIN users tu2 ON l2.target_user_id = tu2.id
            WHERE l2.target_user_id = ? OR l2.admin_id = ? OR l2.user_id = ?
        ) as combined_logs
        ORDER BY timestamp DESC 
        LIMIT 1000
    ";
    
    // Note: user_id field is used for 'used' action as the person who used it.
    // admin_id field is used for 'outbound', 'transfer' as the sender.
    // target_user_id is used as the receiver.

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $target_user_id, $target_user_id, $target_user_id, 
        $target_user_id, $target_user_id, $target_user_id
    ]);
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
