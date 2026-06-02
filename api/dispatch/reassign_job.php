<?php
// api/dispatch/reassign_job.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$job_id = $input['job_id'] ?? null;
$team_id = $input['team_id'] ?? null;

if (!$job_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสงาน']);
    exit;
}

try {
    // ถ้า $team_id เป็นค่าว่าง ให้ถือว่ายกเลิกการจ่ายงาน (ตั้งเป็น null)
    if (empty($team_id)) {
        $team_id = null;
    }

    $stmt = $pdo->prepare("UPDATE {$table} SET team_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$team_id, $job_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
