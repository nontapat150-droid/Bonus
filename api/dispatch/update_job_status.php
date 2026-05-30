<?php
// api/dispatch/update_job_status.php
// Update job status to 'completed' or 'failed' with optional remarks
// Each completed job is tracked for oil system calculations
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
$status = $input['status'] ?? null; // 'completed' (จบงาน) หรือ 'failed' (ไม่สำเร็จ)
$remark = $input['remark'] ?? '';

// ตรวจสอบข้อมูลเบื้องต้น
if (!$job_id || !$status) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบว่าสถานะที่ส่งมาถูกต้อง
$validStatuses = ['completed', 'failed'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
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
    // นี้คือการติดตามการสำเร็จของงานสำหรับการคำนวณในระบบน้ำมัน (จำนวนกรณีงานต่อวัน)
    $logStmt = $pdo->prepare("INSERT INTO job_logs (job_id, tech_id, status, remark) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$job_id, $user['id'], $status, $remark]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'อัปเดตสถานะสำเร็จ']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
}