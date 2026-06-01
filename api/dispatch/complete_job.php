<?php
// ไฟล์: api/dispatch/complete_job.php
require_once '../../config/db.php'; // ⚠️ ตรวจสอบ Path ไฟล์เชื่อมต่อฐานข้อมูลให้ถูกต้อง

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

// ตรวจสอบว่ามี Job ID ส่งมาหรือไม่
if (!isset($data['job_id']) || empty($data['job_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Job ID']);
    exit;
}

try {
    // อัปเดตข้อมูลลงตาราง jobs โดยตรง
    $sql = "UPDATE jobs SET 
                status = 'completed',
                install_date = ?,
                splitter = ?,
                code_soa = ?,
                distance_requested = ?,
                patch_cord_black = ?,
                patch_cord_yellow = ?,
                tube_black = ?,
                tube_white = ?,
                nameplate = ?,
                completion_remark = ?
            WHERE id = ?";
            
    $stmt = $pdo->prepare($sql);
    
    // ทำการ Execute พร้อมแนบตัวแปร
    $stmt->execute([
        $data['install_date'],
        $data['splitter'],
        $data['code_soa'],
        (int)$data['distance'],
        (int)$data['patch_black'],
        (int)$data['patch_yellow'],
        (int)$data['tube_black'],
        (int)$data['tube_white'],
        (int)$data['nameplate'],
        $data['remark'],
        $data['job_id']
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลจบงานสำเร็จ']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอัปเดตข้อมูลได้ หรือสถานะอาจอัปเดตไปแล้ว']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>