<?php
/**
 * API: ทดสอบไฟล์ Excel/CSV ก่อนนำเข้า
 * ปัจจุบันระบบหลักใช้การตรวจสอบฝั่ง Client (JavaScript + SheetJS) 
 * ไฟล์นี้ใช้สำหรับตรวจสอบเบื้องต้นกรณีอัปโหลด CSV โดยตรง
 */
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
    exit;
}

$file = $_FILES['file'];
$validRows = [];
$skippedRows = [];

try {
    $filePath = $file['tmp_name'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'csv') {
        throw new Exception('API นี้รองรับเฉพาะไฟล์ .csv เท่านั้น สำหรับ .xlsx กรุณาใช้ผ่านหน้าจอระบบนำเข้าปกติ');
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) throw new Exception('ไม่สามารถเปิดไฟล์ได้');
    
    $headers = fgetcsv($handle); // ข้ามหัว
    $rowIdx = 2;

    while (($row = fgetcsv($handle)) !== false) {
        if (empty(array_filter($row))) continue;

        $pName = isset($row[0]) ? trim($row[0]) : '';
        $model = isset($row[1]) ? trim($row[1]) : '';
        $sn = isset($row[2]) ? trim($row[2]) : '';

        if ($pName) {
            $validRows[] = [
                'row' => $rowIdx,
                'product_name' => $pName,
                'model' => $model ?: 'Standard',
                'sn' => $sn ?: '(Auto-generated)'
            ];
        } else {
            $skippedRows[] = ['row' => $rowIdx, 'reason' => 'ไม่มีชื่อสินค้า'];
        }
        $rowIdx++;
    }
    fclose($handle);

    echo json_encode([
        'success' => true,
        'valid_count' => count($validRows),
        'skipped_count' => count($skippedRows),
        'preview' => array_slice($validRows, 0, 10)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
