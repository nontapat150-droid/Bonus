<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$admin_id = $user['id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sns = $input['sns'] ?? [];
    $target_user_id = $input['target_user_id'] ?? null;

    if (empty($sns)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีรายการ SN']);
        exit;
    }

    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุช่างผู้รับของ']);
        exit;
    }

    if ($admin_id == $target_user_id) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถโอนย้ายให้ตัวเองได้']);
        exit;
    }

    $pdo->beginTransaction();
    $processed = 0;

    foreach ($sns as $sn) {
        $sn = trim($sn);
        
        $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ? AND status = 'outbound'");
        $stmt->execute([$sn]);
        $item = $stmt->fetch();

        if ($item) {
            // ไม่ต้องเปลี่ยน status ใน items เพราะยังอยู่ 'outbound' เหมือนเดิม
            // บันทึก Log ใหม่เป็น 'transfer'
            $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id, target_user_id) VALUES (?, 'transfer', ?, ?)")
                ->execute([$item['id'], $admin_id, $target_user_id]);
            
            $processed++;
        }
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

            $title = "มีการโอนย้ายสินค้า";
            $message = "[$senderName] โอนย้ายอุปกรณ์ (มี SN) จำนวน $processed ชิ้น ให้กับ [$receiverName]";
            
            $pdo->prepare("INSERT INTO notifications (title, message, type, is_global, created_by) VALUES (?, ?, 'admin_only', 0, ?)")
                ->execute([$title, $message, $admin_id]);
                
            $pdo->prepare("INSERT INTO notifications (title, message, target_user_id, created_by) VALUES (?, ?, ?, ?)")
                ->execute([$title, $message, $target_user_id, $admin_id]);
                

        } catch (Exception $e) {} // ignore notification errors
    }

    echo json_encode(['success' => true, 'processed' => $processed]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
