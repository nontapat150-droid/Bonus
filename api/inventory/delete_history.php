<?php
// api/inventory/delete_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$log_type = $data['type'] ?? 'item'; // 'item' or 'consumable'

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($log_type === 'consumable') {
        $stmt = $pdo->prepare("SELECT * FROM inventory_consumable_logs WHERE id = ?");
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        if ($log) {
            // Revert stock
            if ($log['action'] === 'out') {
                $pdo->prepare("UPDATE inventory_consumable SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$log['qty'], $log['consumable_id']]);
                if ($log['target_user_id']) {
                    $pdo->prepare("UPDATE user_consumables SET qty = GREATEST(0, qty - ?) WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['target_user_id'], $log['consumable_id']]);
                }
            } elseif ($log['action'] === 'in') {
                $pdo->prepare("UPDATE inventory_consumable SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?")->execute([$log['qty'], $log['consumable_id']]);
            } elseif ($log['action'] === 'transfer') {
                // Revert transfer
                $pdo->prepare("UPDATE user_consumables SET qty = GREATEST(0, qty - ?) WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['target_user_id'], $log['consumable_id']]);
                $pdo->prepare("UPDATE user_consumables SET qty = qty + ? WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['admin_id'], $log['consumable_id']]);
            }
            $pdo->prepare("DELETE FROM inventory_consumable_logs WHERE id = ?")->execute([$id]);
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM inventory_logs WHERE id = ?");
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        if ($log) {
            // Revert item
            if ($log['action'] === 'out') {
                $pdo->prepare("UPDATE inventory_items SET status = 'in_stock' WHERE id = ?")->execute([$log['item_id']]);
            } elseif ($log['action'] === 'in') {
                // Should we delete item? Let's just remove it if it was in_stock
                $pdo->prepare("DELETE FROM inventory_items WHERE id = ? AND status = 'in_stock'")->execute([$log['item_id']]);
            }
            $pdo->prepare("DELETE FROM inventory_logs WHERE id = ?")->execute([$id]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ลบประวัติและคืนยอดสำเร็จ']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
