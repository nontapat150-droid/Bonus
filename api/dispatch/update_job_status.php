<?php
// api/dispatch/update_job_status.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];

// ตรวจสอบสิทธิ์ว่าใช่ช่างหรือไม่
if ($role !== 'technician') {
    echo json_encode(['success' => false, 'error' => 'เฉพาะช่างเท่านั้นที่สามารถอัปเดตสถานะงานได้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$job_id = $input['job_id'] ?? null;
$status = $input['status'] ?? null; // รอรับค่า 'completed' (จบงาน) หรือ 'failed' (ไม่สำเร็จ)
$remark = $input['remark'] ?? '';

// ตรวจสอบข้อมูลเบื้องต้น
if (!$job_id || !$status) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// หากงานไม่สำเร็จ บังคับให้ต้องกรอกหมายเหตุ
if ($status === 'failed' && empty(trim($remark))) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุหมายเหตุเมื่อทำงานไม่สำเร็จ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. อัปเดตสถานะและหมายเหตุในตาราง jobs
    $stmt = $pdo->prepare("UPDATE jobs SET status = ?, remark = ? WHERE id = ?");
    $stmt->execute([$status, $remark, $job_id]);

    // 2. บันทึกประวัติการทำรายการลงในตาราง job_logs
    $logStmt = $pdo->prepare("INSERT INTO job_logs (job_id, tech_id, status, remark) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$job_id, $user['id'], $status, $remark]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'อัปเดตสถานะสำเร็จ']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}