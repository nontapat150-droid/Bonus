<?php
// api/dispatch/manual_assign.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobIds = $input['job_ids'] ?? [];
$teamId = $input['team_id'] ?? null;

if (empty($jobIds) || $teamId === null) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($teamId === 'unassigned' || $teamId === '') {
        $stmt = $pdo->prepare("UPDATE jobs SET team_id = NULL, seq = NULL, map_link = NULL WHERE id = ?");
        foreach ($jobIds as $id) {
            $stmt->execute([$id]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE jobs SET team_id = ? WHERE id = ?");
        foreach ($jobIds as $id) {
            $stmt->execute([$teamId, $id]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
