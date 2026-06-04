<?php
// api/inventory/get_today_inbound.php
// ดึงรายการนำเข้าสินค้าวันนี้ (INBOUND) สำหรับ Export
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    // สินค้าแบบมี SN ที่รับเข้าวันนี้ (action = 'in')
    $stmt1 = $pdo->prepare("
        SELECT 
            l.timestamp AS created_at,
            pm.model_name AS product_name,
            p.name AS model_name,
            i.sn,
            u.full_name AS user_name,
            NULL AS qty,
            NULL AS unit,
            l.action AS remark
        FROM inventory_logs l
        LEFT JOIN inventory_items i ON l.item_id = i.id
        LEFT JOIN product_models pm ON i.model_id = pm.id
        LEFT JOIN products p ON pm.product_id = p.id
        LEFT JOIN users u ON l.admin_id = u.id
        WHERE DATE(l.timestamp) = ?
          AND l.action = 'in'
        ORDER BY l.timestamp DESC
        LIMIT 2000
    ");
    $stmt1->execute([$date]);
    $snRows = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // วัสดุสิ้นเปลืองที่รับเข้าวันนี้ (action = 'add')
    $stmt2 = $pdo->prepare("
        SELECT 
            l.timestamp AS created_at,
            c.product_name,
            'วัสดุสิ้นเปลือง' AS model_name,
            '' AS sn,
            u.full_name AS user_name,
            l.qty,
            c.unit,
            '' AS remark
        FROM inventory_consumable_logs l
        LEFT JOIN inventory_consumable c ON l.consumable_id = c.id
        LEFT JOIN users u ON l.admin_id = u.id
        WHERE DATE(l.timestamp) = ?
          AND l.action IN ('add', 'in', 'IN', 'inbound')
        ORDER BY l.timestamp DESC
        LIMIT 2000
    ");
    $stmt2->execute([$date]);
    $consumableRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $all = array_merge($snRows, $consumableRows);


    // ถ้าไม่มี inbound filter ลองดึงทุก action ของวันนี้ (เผื่อ action name ต่างกัน)
    if (count($all) === 0) {
        $stmt3 = $pdo->prepare("
            SELECT 
                l.timestamp AS created_at,
                pm.model_name AS product_name,
                p.name AS model_name,
                i.sn,
                u.full_name AS user_name,
                '' AS qty,
                '' AS unit,
                l.action AS remark
            FROM inventory_logs l
            LEFT JOIN inventory_items i ON l.item_id = i.id
            LEFT JOIN product_models pm ON i.model_id = pm.id
            LEFT JOIN products p ON pm.product_id = p.id
            LEFT JOIN users u ON l.admin_id = u.id
            WHERE DATE(l.timestamp) = ?
            ORDER BY l.timestamp DESC
            LIMIT 2000
        ");
        $stmt3->execute([$date]);
        $all = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'data' => $all, 'date' => $date, 'count' => count($all)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
