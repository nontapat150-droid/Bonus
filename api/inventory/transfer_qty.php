<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$current_user_id = $user['id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? []; // [{consumable_id: 'xxx', qty: 10}]
    $target_user_id = $input['target_user_id'] ?? null;

    if (empty($items)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีรายการ']);
        exit;
    }

    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุช่างผู้รับโอน']);
        exit;
    }

    if ($current_user_id == $target_user_id) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถโอนย้ายให้ตัวเองได้']);
        exit;
    }

    $pdo->beginTransaction();
    $processed = 0;

    foreach ($items as $item) {
        $consumable_id = trim($item['consumable_id'] ?? '');
        $qty = floatval($item['qty'] ?? 0);

        if (!$consumable_id || $qty <= 0) continue;

        // เช็คว่าคนโอนมีของพอไหม
        $stmt = $pdo->prepare("SELECT qty FROM user_consumables WHERE user_id = ? AND consumable_id = ? FOR UPDATE");
        $stmt->execute([$current_user_id, $consumable_id]);
        $my_stock = $stmt->fetch();

        if (!$my_stock || $my_stock['qty'] < $qty) {
            throw new Exception("คุณมีของไม่พอสำหรับโอนย้าย");
        }

        // ตัดสต็อกคนโอน
        $pdo->prepare("UPDATE user_consumables SET qty = qty - ? WHERE user_id = ? AND consumable_id = ?")
            ->execute([$qty, $current_user_id, $consumable_id]);

        // เพิ่มสต็อกให้คนรับ
        $stmtTarget = $pdo->prepare("SELECT id FROM user_consumables WHERE user_id = ? AND consumable_id = ?");
        $stmtTarget->execute([$target_user_id, $consumable_id]);
        
        if ($stmtTarget->fetch()) {
            $pdo->prepare("UPDATE user_consumables SET qty = qty + ? WHERE user_id = ? AND consumable_id = ?")
                ->execute([$qty, $target_user_id, $consumable_id]);
        } else {
            $pdo->prepare("INSERT INTO user_consumables (user_id, consumable_id, qty) VALUES (?, ?, ?)")
                ->execute([$target_user_id, $consumable_id, $qty]);
        }

        // บันทึก Log
        $pdo->prepare("INSERT INTO inventory_consumable_logs (consumable_id, action, qty, admin_id, target_user_id) VALUES (?, 'transfer', ?, ?, ?)")
            ->execute([$consumable_id, $qty, $current_user_id, $target_user_id]);
            
        $processed++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed' => $processed]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
