<?php
// api/inventory/confirm_outbound.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sns = $input['sns'] ?? [];

if (empty($sns)) {
    echo json_encode(['success' => false, 'error' => 'No items selected for outbound']);
    exit;
}

$admin_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare("UPDATE inventory_items SET status = 'outbound' WHERE sn = ? AND status = 'in_stock'");
    $stmtGetId = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'out', ?)");

    $processed = 0;

    foreach ($sns as $sn) {
        $stmtUpdate->execute([$sn]);
        if ($stmtUpdate->rowCount() > 0) {
            $stmtGetId->execute([$sn]);
            $itemId = $stmtGetId->fetchColumn();
            
            if ($itemId) {
                $stmtLog->execute([$itemId, $admin_id]);
                $processed++;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed' => $processed]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
