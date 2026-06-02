<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$user = getCurrentUser();
$user_id = $user['id'];
$input = json_decode(file_get_contents('php://input'), true);

$item_ids = $input['item_ids'] ?? [];

if (empty($item_ids) || !is_array($item_ids)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีรายการสินค้า']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ตรวจสอบว่า item นี้อยู่ที่ช่างคนนี้จริงไหม
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    
    // ดึง log ล่าสุดของแต่ละ item ว่า target_user_id คือช่างคนนี้หรือไม่
    $sqlCheck = "
        SELECT i.id, i.sn
        FROM inventory_items i
        JOIN (
            SELECT item_id, MAX(id) as max_id
            FROM inventory_logs
            GROUP BY item_id
        ) latest ON i.id = latest.item_id
        JOIN inventory_logs l ON latest.max_id = l.id
        WHERE i.id IN ($placeholders)
          AND i.status = 'outbound'
          AND l.target_user_id = ?
    ";
    
    $params = array_merge($item_ids, [$user_id]);
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($params);
    $valid_items = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

    if (count($valid_items) !== count($item_ids)) {
        throw new Exception("บางรายการไม่ได้อยู่ในกระเป๋าของคุณ หรือถูกใช้งานไปแล้ว");
    }

    $valid_ids = array_column($valid_items, 'id');
    $valid_placeholders = implode(',', array_fill(0, count($valid_ids), '?'));

    // 2. อัปเดตสถานะเป็น used
    $updateStmt = $pdo->prepare("UPDATE inventory_items SET status = 'used' WHERE id IN ($valid_placeholders)");
    $updateStmt->execute($valid_ids);

    // 3. บันทึกประวัติ (Log) ใช้งาน
    $logStmt = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, user_id) VALUES (?, 'used', ?)");
    foreach ($valid_ids as $id) {
        $logStmt->execute([$id, $user_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
