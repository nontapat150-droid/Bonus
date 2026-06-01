<?php
// ไฟล์: api/dispatch/complete_job.php
// ⚠️ ตรวจสอบ Path ไฟล์เชื่อมต่อฐานข้อมูลของคุณให้ถูกต้อง
require_once '../../config/db.php'; 

header('Content-Type: application/json');

// รับข้อมูล JSON ที่ส่งมาจากหน้าแผนที่
$data = json_decode(file_get_contents("php://input"), true);

// ตรวจสอบว่ามีการส่ง Job ID มาหรือไม่
if (!isset($data['job_id']) || empty($data['job_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Job ID']);
    exit;
}

try {
    // 💡 สังเกตตรงนี้: เราเปลี่ยนมาใช้ UPDATE jobs แทน INSERT INTO job_logs แล้ว
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
                completion_remark = ?,
                updated_at = NOW()
            WHERE id = ?";
            
    $stmt = $pdo->prepare($sql);
    
    // ทำการ Execute นำข้อมูลไปอัปเดตในฐานข้อมูล
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
        $data['remark'], // หมายเหตุ
        $data['job_id']
    ]);

    // ตรวจสอบผลลัพธ์
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลจบงานสำเร็จ']);
    } else {
        // กรณีที่อัปเดตไม่ได้ (เช่น status เป็น completed อยู่แล้ว หรือไม่มี job_id นี้)
        echo json_encode(['status' => 'success', 'message' => 'อัปเดตสำเร็จ (หรือข้อมูลตรงกับของเดิม)']);
    }

} catch (PDOException $e) {
    // ดักจับ Error แจ้งเตือนกลับไปที่หน้าจอ
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>