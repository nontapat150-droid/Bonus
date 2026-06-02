<?php
// api/dispatch/acknowledge_reschedule.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_reschedule.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรายการ']);
    exit;
}

$user = getCurrentUser();

try {
    ensureJobReschedulesTable($pdo);

    $stmt = $pdo->prepare("SELECT id, notification_id FROM job_reschedules WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบประวัติเลื่อนนัด']);
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE job_reschedules
        SET acknowledged_by = ?, acknowledged_at = NOW()
        WHERE id = ?
    ")->execute([(int)$user['id'], $id]);

    if (!empty($row['notification_id'])) {
        $nid = (int)$row['notification_id'];
        $pdo->prepare("
            INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
            VALUES (?, ?, NOW())
        ")->execute([$nid, (int)$user['id']]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'รับทราบเรียบร้อย']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
