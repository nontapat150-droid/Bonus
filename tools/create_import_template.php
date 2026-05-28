<?php
/**
 * สร้างไฟล์ template สำหรับการนำเข้าสินค้า
 * Script นี้สร้างไฟล์ Excel พร้อมตัวอย่างข้อมูล
 */

require_once __DIR__ . '/../vendor/autoload.php'; // ตรวจสอบว่ามี PhpSpreadsheet installed
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// ส่วนหัว JSON สำหรับให้ frontend ดึง
header('Content-Type: application/json');

try {
    // ตรวจสอบสิทธิ์
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'ไม่ได้ล็อกอิน']);
        exit;
    }

    // สร้างข้อมูล CSV template
    $template = "ชื่อสินค้า,รุ่น (Model),ซีเรียล (SN),หมายเหตุ\n";
    $template .= "ตัวอย่าง: iPhone 15,A3001,SN123456,จากซัพพลายเออร์ A\n";
    $template .= "ตัวอย่าง: Samsung Galaxy S24,SM-S921B,SN789012,จากซัพพลายเออร์ B\n";
    $template .= "ตัวอย่าง: MacBook Pro,MBP16-M3,SN456789,\n";
    
    // ส่งไฟล์ CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_' . date('Ymd') . '.csv"');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM สำหรับ Excel
    echo $template;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
