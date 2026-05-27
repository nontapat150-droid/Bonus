<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sns = $input['sns'] ?? [];
    $target_user_id = $input['target_user_id'] ?? null; // รับค่าผู้รับ
    $admin_id = $_SESSION['user_id']; // ผู้ทำรายการ (คนยิงเบิก)

    if (empty($sns)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีรายการ SN']);
        exit;
    }

    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุผู้รับของ หรือทีมที่เบิกไป']);
        exit;
    }

    $pdo->beginTransaction();
    $processed = 0;

    foreach ($sns as $sn) {
        $sn = trim($sn);
        
        $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ? AND status = 'in_stock'");
        $stmt->execute([$sn]);
        $item = $stmt->fetch();

        if ($item) {
            // อัปเดตสถานะเป็นเบิกออก
            $pdo->prepare("UPDATE inventory_items SET status = 'outbound' WHERE id = ?")->execute([$item['id']]);
            
            // บันทึก Log พร้อมข้อมูล target_user_id
            $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id, target_user_id) VALUES (?, 'out', ?, ?)")
                ->execute([$item['id'], $admin_id, $target_user_id]);
            
            $processed++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed' => $processed]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}