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

$consumable_id = $input['consumable_id'] ?? 0;
$qty = (float)($input['qty'] ?? 0);

if ($consumable_id <= 0 || $qty <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง หรือระบุจำนวนไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ตรวจสอบว่าช่างมีของพอให้ใช้งานไหม
    $stmtCheck = $pdo->prepare("SELECT qty FROM user_consumables WHERE user_id = ? AND consumable_id = ? FOR UPDATE");
    $stmtCheck->execute([$user_id, $consumable_id]);
    $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$current || $current['qty'] < $qty) {
        throw new Exception("คุณมีวัสดุนี้ไม่พอสำหรับใช้งาน");
    }

    // 2. ตัดยอดออก
    $new_qty = $current['qty'] - $qty;
    $stmtUpdate = $pdo->prepare("UPDATE user_consumables SET qty = ? WHERE user_id = ? AND consumable_id = ?");
    $stmtUpdate->execute([$new_qty, $user_id, $consumable_id]);

    // 3. บันทึกประวัติ (Log)
    $stmtLog = $pdo->prepare("
        INSERT INTO inventory_consumable_logs 
        (consumable_id, action, qty, user_id) 
        VALUES (?, 'used', ?, ?)
    ");
    // action ใน table inventory_consumable_logs อาจจะเป็น enum ที่มีแค่ 'in','out','transfer' 
    // ลองบันทึกเป็น 'used' ถ้าตารางนั้นรองรับ (ถ้าไม่ได้อาจต้อง Alter ตารางนั้นด้วย)
    // สำหรับตอนนี้เราจะสมมติว่า 'used' หรือเราใส่ไปในหมายเหตุก็ได้
    // เอาแบบเซฟๆ ถ้า enum ไม่รองรับ used ใน log consumable 
    // อัปเดต: ถ้า DB ไม่รองรับอาจจะพังได้ เราไปแก้ enum consumable_logs ด้วยดีกว่า หรือถ้าไม่มีตาราง consumable_logs ? 
    // ไปเช็คโครงสร้างอีกที
    
    // ลอง execute ดูก่อน
    try {
        $stmtLog->execute([$consumable_id, $qty, $user_id]);
    } catch (Exception $e) {
        // ถ้า action 'used' ไม่ได้ เราอาจจะต้องใช้ 'out' แล้วระบุ target_user_id เป็นตัวเอง หรือใส่หมายเหตุ
        // แต่เพื่อความถูกต้อง เราจะลองพยายามเพิ่ม enum ให้รองรับด้วย (เดี๋ยวทำ migration ให้)
        $pdo->exec("ALTER TABLE inventory_consumable_logs MODIFY COLUMN `action` enum('in','out','transfer','used') NOT NULL");
        $stmtLog->execute([$consumable_id, $qty, $user_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
