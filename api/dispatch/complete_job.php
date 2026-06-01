<?php
// ไฟล์: api/dispatch/complete_job.php
require_once '../../config/db.php'; // ⚠️ แก้ไข Path เชื่อมต่อฐานข้อมูลของคุณตรงนี้

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['job_id']) || empty($data['job_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Job ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // อัปเดตสถานะงานในตารางหลัก
    $stmtUpdateJob = $pdo->prepare("UPDATE jobs SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmtUpdateJob->execute([$data['job_id']]);

    // บันทึกรายละเอียดลง job_logs
    $sqlInsertLog = "INSERT INTO job_logs (
                        job_id, status, install_date, splitter, code_soa, distance_requested,
                        patch_cord_black, patch_cord_yellow, tube_black, tube_white, nameplate, remark, created_at
                    ) VALUES (?, 'completed', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
    $stmtLog = $pdo->prepare($sqlInsertLog);
    $stmtLog->execute([
        $data['job_id'],
        $data['install_date'],
        $data['splitter'],
        $data['code_soa'],
        (int)$data['distance'],
        (int)$data['patch_black'],
        (int)$data['patch_yellow'],
        (int)$data['tube_black'],
        (int)$data['tube_white'],
        (int)$data['nameplate'],
        $data['remark']
    ]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลจบงานสำเร็จ']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>