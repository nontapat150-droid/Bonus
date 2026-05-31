<?php
// tools/repair_swapped_inventory.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Script นี้ใช้สำหรับสลับชื่อสินค้าและชื่อรุ่นในฐานข้อมูลกรณีที่นำเข้าสลับกันมา
// ชื่อสินค้า (products.name) <-> ชื่อรุ่น (product_models.model_name)

header('Content-Type: text/plain; charset=utf-8');

// ตรวจสอบสิทธิ์ (ควรเป็น Super Admin เท่านั้นที่รันไฟล์นี้ได้)
// หรือใช้การเช็ค IP หรือรันผ่าน CLI เท่านั้น
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) {
    die("Unauthorized access. This script can only be run by a Super Admin or via CLI.");
}

try {
    $pdo->beginTransaction();

    echo "--- เริ่มกระบวนการสลับข้อมูลสินค้าและรุ่น ---\n";

    // 1. ดึงข้อมูลทั้งหมดมาตรวจสอบ
    $stmt = $pdo->query("SELECT pm.id as model_id, p.id as product_id, p.name as current_pname, pm.model_name as current_mname 
                         FROM products p 
                         JOIN product_models pm ON p.id = pm.product_id");
    $rows = $stmt->fetchAll();

    echo "พบรายการที่ต้องตรวจสอบ: " . count($rows) . " รายการ\n";

    $updates = 0;
    foreach ($rows as $row) {
        $mid = $row['model_id'];
        $pid = $row['product_id'];
        $newPName = $row['current_mname']; // เอาชื่อรุ่นมาเป็นชื่อสินค้า
        $newMName = $row['current_pname']; // เอาชื่อสินค้ามาเป็นชื่อรุ่น

        // อัปเดตชื่อสินค้า (Product)
        $pdo->prepare("UPDATE products SET name = ? WHERE id = ?")->execute([$newPName, $pid]);
        
        // อัปเดตชื่อรุ่น (Model)
        $pdo->prepare("UPDATE product_models SET model_name = ? WHERE id = ?")->execute([$newMName, $mid]);

        $updates++;
    }

    $pdo->commit();
    echo "--- ดำเนินการสำเร็จ! ---\n";
    echo "อัปเดตไปทั้งสิ้น: $updates รายการ\n";
    echo "กรุณาตรวจสอบหน้าสต็อกอีกครั้ง\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
}
