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
    $owner_id = $input['owner_id'] ?? '';
    
    if ($owner_id !== '' && hasRole(['admin', 'super_admin'])) {
        $source_user_id = $owner_id;
    } else {
        $source_user_id = $current_user_id;
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีรายการ']);
        exit;
    }

    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุช่างผู้รับโอน']);
        exit;
    }

    if ($source_user_id == $target_user_id) {
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
        $stmt->execute([$source_user_id, $consumable_id]);
        $my_stock = $stmt->fetch();

        if (!$my_stock || $my_stock['qty'] < $qty) {
            throw new Exception("คุณมีของไม่พอสำหรับโอนย้าย");
        }

        // ตัดสต็อกคนโอน
        $pdo->prepare("UPDATE user_consumables SET qty = qty - ? WHERE user_id = ? AND consumable_id = ?")
            ->execute([$qty, $source_user_id, $consumable_id]);

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

    // --- ส่งแจ้งเตือนอัตโนมัติ ---
    if ($processed > 0) {
        try {
            $senderName = $user['full_name'] ?? 'ช่างเทคนิค';
            $stmtT = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtT->execute([$target_user_id]);
            $tUser = $stmtT->fetch();
            $receiverName = $tUser ? $tUser['full_name'] : 'ช่างที่ไม่ทราบชื่อ';

            $title = "มีการโอนย้ายวัสดุสิ้นเปลือง";
            $message = "[$senderName] โอนย้ายวัสดุสิ้นเปลือง จำนวน $processed รายการ ให้กับ [$receiverName]";
            
            $pdo->prepare("INSERT INTO notifications (title, message, type, is_global, created_by) VALUES (?, ?, 'system', 0, ?)")
                ->execute([$title, $message, $current_user_id]);
            $notif_id = $pdo->lastInsertId();

            // แจ้งเฉพาะแอดมิน (admin, super_admin)
            $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin', 'super_admin')");
            $stmtAdmins->execute();
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($admins)) {
                $insertReads = [];
                $insertParams = [];
                foreach ($admins as $ad_id) {
                    $insertReads[] = "(?, ?)";
                    $insertParams[] = $notif_id;
                    $insertParams[] = $ad_id;
                }
                $pdo->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES " . implode(',', $insertReads))
                    ->execute($insertParams);
            }
        } catch (Exception $e) {} // ignore notification errors
    }

    echo json_encode(['success' => true, 'processed' => $processed]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
