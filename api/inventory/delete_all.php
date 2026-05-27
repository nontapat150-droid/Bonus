<?php
// api/inventory/delete_all.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

// จำกัดสิทธิ์ให้เฉพาะ Admin และ Super Admin เท่านั้นที่ลบได้
requireLogin(['admin', 'super_admin']); 

try {
    $pdo->beginTransaction();

    // ลบข้อมูลตามลำดับเพื่อไม่ให้ติด Foreign Key
    $pdo->exec("DELETE FROM inventory_logs");
    $pdo->exec("DELETE FROM inventory_items");
    $pdo->exec("DELETE FROM product_models");
    $pdo->exec("DELETE FROM products");

    // รีเซ็ตเลข Auto Increment ให้กลับไปเริ่มที่ 1 ใหม่ (เพื่อให้ระบบสะอาดหมดจด)
    $pdo->exec("ALTER TABLE inventory_logs AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE inventory_items AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE product_models AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE products AUTO_INCREMENT = 1");

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ล้างข้อมูลคลังสินค้าทั้งหมดเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}