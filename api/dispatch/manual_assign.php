<?php
// api/dispatch/manual_assign.php — จ่ายงานด้วยมือ (แยก Office / MA)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobIds = $input['job_ids'] ?? [];
$teamId = $input['team_id'] ?? null;
$jobType = $input['job_type'] ?? 'jobs';
$isMa = ($jobType === 'ma');

if (empty($jobIds) || $teamId === null) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    ensureMaJobSchema($pdo);
    $pdo->beginTransaction();

    if ($isMa) {
        if ($teamId === 'unassigned' || $teamId === '') {
            $stmt = $pdo->prepare("UPDATE ma_jobs SET team_id = NULL, team_match_status = NULL, assigned_user_id = NULL, seq = NULL, map_link = NULL WHERE id = ?");
            foreach ($jobIds as $id) {
                $stmt->execute([$id]);
                recordMaJobHistory($pdo, $id, 'unassigned');
            }
        } else {
            $assignedUserId = null;
            $teamNameImport = null;
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND status = 'approved' ORDER BY id ASC LIMIT 1");
            $uStmt->execute([(int)$teamId]);
            $assignedUserId = $uStmt->fetchColumn() ?: null;
            $tStmt = $pdo->prepare("SELECT team_name FROM teams WHERE id = ?");
            $tStmt->execute([(int)$teamId]);
            $teamNameImport = $tStmt->fetchColumn() ?: null;

            $stmt = $pdo->prepare("UPDATE ma_jobs SET team_id = ?, team_name_import = COALESCE(?, team_name_import), team_match_status = 'matched', assigned_user_id = ?, updated_at = NOW() WHERE id = ?");
            foreach ($jobIds as $id) {
                $stmt->execute([(int)$teamId, $teamNameImport, $assignedUserId, $id]);
                recordMaJobHistory($pdo, $id, 'assigned');
            }
        }
    } else {
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
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'job_type' => $isMa ? 'ma' : 'jobs']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
