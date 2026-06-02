<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ลบประวัติปิดงาน']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$closeId = (int)($input['close_id'] ?? $_GET['id'] ?? 0);

if ($closeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรายการ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, job_id, job_log_id FROM job_close_3bb WHERE id = ?");
    $stmt->execute([$closeId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบประวัติปิดงาน']);
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM job_close_3bb WHERE id = ?")->execute([$closeId]);

    if (!empty($record['job_log_id'])) {
        $pdo->prepare("DELETE FROM job_logs WHERE id = ?")->execute([(int)$record['job_log_id']]);
    }

    $pdo->prepare("UPDATE jobs SET status = 'dispatched', remark = NULL WHERE id = ? AND status = 'completed'")
        ->execute([(int)$record['job_id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'ลบประวัติปิดงานเรียบร้อย']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
