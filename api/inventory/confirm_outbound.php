<?php
// api/inventory/confirm_outbound.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sns = $input['sns'] ?? [];
$target_user_id = $input['target_user_id'] ?? null;

if (empty($sns)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีรายการสินค้าที่เลือกสำหรับการเบิกออก']);
    exit;
}

if (empty($target_user_id)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุผู้รับของ (ช่าง)']);
    exit;
}

$admin_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmtGetItem = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ? AND status = 'in_stock' LIMIT 1");
    // ตัดสต็อก
    $stmtUpdate = $pdo->prepare("UPDATE inventory_items SET status = 'outbound' WHERE id = ?");
    // บันทึกประวัติและระบุ target_user_id
    $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id, target_user_id) VALUES (?, 'out', ?, ?)");    

    $processed = 0;

    foreach ($sns as $sn) {
        $sn = trim($sn);
        $stmtGetItem->execute([$sn]);
        $itemId = $stmtGetItem->fetchColumn();

        if ($itemId) {
            $stmtUpdate->execute([$itemId]); // ตัดสต็อก
            $stmtLog->execute([$itemId, $admin_id, $target_user_id]); // บันทึก Log 
            $processed++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed' => $processed]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>