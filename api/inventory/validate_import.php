<?php
/**
 * API: ทดสอบไฟล์ Excel ก่อนนำเข้า
 * Validate Excel file format before import
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
$errors = [];
$warnings = [];
$validRows = [];
$skippedRows = [];

try {
    // อ่านไฟล์ CSV/Excel
    $filePath = $file['tmp_name'];
    $fileName = $file['name'];
    
    // ตรวจสอบนามสกุล
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        throw new Exception('ไฟล์ต้องเป็น CSV หรือ Excel (.csv, .xlsx, .xls)');
    }

    // อ่านไฟล์ CSV
    $rows = [];
    if ($ext === 'csv') {
        $handle = fopen($filePath, 'r');
        if (!$handle) throw new Exception('ไม่สามารถเปิดไฟล์ได้');
        
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    } else {
        // สำหรับ XLSX/XLS ให้ใช้ JavaScript เพราะ PHP ต้อง library เพิ่มเติม
        throw new Exception('ตอนนี้รองรับเฉพาะไฟล์ CSV เท่านั้น ลองบันทึกไฟล์ Excel เป็น CSV');
    }

    if (empty($rows)) {
        throw new Exception('ไฟล์ว่างเปล่า');
    }

    // ตรวจสอบ header
    $headerRow = $rows[0];
    $message = "ข้อมูล Header แถวที่ 1: " . implode(', ', $headerRow);

    // ประมวลผล data rows
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // ข้ามแถวว่าง
        if (empty(array_filter($row))) continue;

        $productName = isset($row[0]) ? trim($row[0]) : '';
        $model = isset($row[1]) ? trim($row[1]) : '';
        $sn = isset($row[2]) ? trim($row[2]) : '';

        if ($productName && $model) {
            $validRows[] = [
                'row' => $i + 1,
                'product_name' => $productName,
                'model' => $model,
                'sn' => $sn ?: '(จะสร้างอัตโนมัติ)'
            ];
        } else {
            $skippedRows[] = [
                'row' => $i + 1,
                'reason' => 'ข้อมูลไม่สมบูรณ์ (ชื่อ: ' . ($productName ?: 'ว่าง') . ', รุ่น: ' . ($model ?: 'ว่าง') . ')'
            ];
        }
    }

    if (count($validRows) === 0) {
        throw new Exception('ไม่พบข้อมูลที่ถูกต้อง: ' . count($skippedRows) . ' แถวถูกข้าม');
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'valid_count' => count($validRows),
        'skipped_count' => count($skippedRows),
        'valid_rows' => array_slice($validRows, 0, 5), // แสดง 5 แถวแรก
        'skipped_rows' => array_slice($skippedRows, 0, 5),
        'total_rows' => count($rows) - 1 // ไม่นับ header
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'hint' => 'ตรวจสอบว่าคอลัมน์อยู่ในลำดับที่ถูกต้อง: ชื่อสินค้า | รุ่น | ซีเรียล'
    ]);
}
?>
