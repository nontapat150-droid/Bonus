<?php
// api/dispatch/reassign_job.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$job_id = $input['job_id'] ?? null;
$team_id = $input['team_id'] ?? null;
$job_type = $input['job_type'] ?? 'jobs';

if (!$job_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสงาน']);
    exit;
}

$table = ($job_type === 'ma') ? 'ma_jobs' : 'jobs';

try {
    ensureMaJobSchema($pdo);

    $team_id = $input['team_id'] ?? null;
    $assigned_user_id = $input['assigned_user_id'] ?? null;
    if (empty($team_id)) $team_id = null;
    if (empty($assigned_user_id)) $assigned_user_id = null;

    if ($job_type === 'ma') {
        $matchStatus = $team_id ? 'matched' : null;
        $teamNameImport = null;

        if ($team_id) {
            $tStmt = $pdo->prepare("SELECT team_name FROM teams WHERE id = ?");
            $tStmt->execute([$team_id]);
            $teamNameImport = $tStmt->fetchColumn() ?: null;

            if (!$assigned_user_id) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND status = 'approved' ORDER BY id ASC LIMIT 1");
                $uStmt->execute([$team_id]);
                $assigned_user_id = $uStmt->fetchColumn() ?: null;
            }
        }

        $stmt = $pdo->prepare("UPDATE ma_jobs SET team_id = ?, team_name_import = COALESCE(?, team_name_import), team_match_status = ?, assigned_user_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$team_id, $teamNameImport, $matchStatus, $assigned_user_id, $job_id]);

        if ($team_id) {
            $jStmt = $pdo->prepare("SELECT access_no, customer, symptoms FROM ma_jobs WHERE id = ?");
            $jStmt->execute([$job_id]);
            $job = $jStmt->fetch(PDO::FETCH_ASSOC);
            if ($job) {
                notifyMaJobAssignment(
                    $pdo,
                    (int)$team_id,
                    'งาน MA โอนให้ทีม: ' . ($job['access_no'] ?? ''),
                    'ลูกค้า: ' . ($job['customer'] ?? '-') . ' | อาการ: ' . ($job['symptoms'] ?? '-'),
                    (int)$_SESSION['user_id']
                );
            }
        }
    } else {
        if (!$team_id && $assigned_user_id) {
            $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
            $stmtUser->execute([(int)$assigned_user_id]);
            $team_id = $stmtUser->fetchColumn() ?: null;
        }
        $stmt = $pdo->prepare("UPDATE jobs SET team_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$team_id, $job_id]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
