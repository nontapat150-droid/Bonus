<?php
// api/inventory/delete_all.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

// Check login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Check role
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึงฟังก์ชันนี้']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ลบข้อมูลตามลำดับเพื่อไม่ให้ติด Foreign Key
    // 1. Logs
    $pdo->exec("DELETE FROM inventory_logs");
    $pdo->exec("DELETE FROM inventory_consumable_logs");
    // 2. Items (has FK to Models)
    $pdo->exec("DELETE FROM inventory_items");
    // 3. User Consumables
    $pdo->exec("DELETE FROM user_consumables");
    // 4. Consumables
    $pdo->exec("DELETE FROM inventory_consumable");
    // 5. Models (has FK to Products)
    $pdo->exec("DELETE FROM product_models");
    // 6. Products
    $pdo->exec("DELETE FROM products");

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()]);
    exit;
}

// รีเซ็ตเลข Auto Increment ให้กลับไปเริ่มที่ 1 ใหม่ (อยู่นอก Transaction เพราะ ALTER TABLE จะทำ Implicit Commit)
try {
    $pdo->exec("ALTER TABLE inventory_logs AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE inventory_consumable_logs AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE inventory_items AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE user_consumables AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE product_models AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE products AUTO_INCREMENT = 1");
} catch (Exception $e) {
    // ถ้า ALTER ไม่สำเร็จ (เช่นไม่มีสิทธิ์) ก็ไม่เป็นไร ให้ข้ามไป
}

echo json_encode(['success' => true, 'message' => 'ล้างข้อมูลคลังสินค้าทั้งหมดเรียบร้อยแล้ว']);
