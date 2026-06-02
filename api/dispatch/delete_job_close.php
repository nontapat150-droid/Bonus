<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

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

    $syncYearMonth = null;
    $syncTeamId = null;
    if (!empty($record['job_log_id'])) {
        $stmtLog = $pdo->prepare("
            SELECT DATE_FORMAT(jl.timestamp, '%Y-%m') AS year_month, j.team_id
            FROM job_logs jl
            INNER JOIN jobs j ON j.id = jl.job_id
            WHERE jl.id = ?
            LIMIT 1
        ");
        $stmtLog->execute([(int)$record['job_log_id']]);
        $logRow = $stmtLog->fetch(PDO::FETCH_ASSOC);
        if ($logRow) {
            $syncYearMonth = $logRow['year_month'];
            $syncTeamId = $logRow['team_id'] ? (int)$logRow['team_id'] : null;
        }
    }
    if (!$syncTeamId && !empty($record['job_id'])) {
        $stmtJob = $pdo->prepare("SELECT team_id FROM jobs WHERE id = ? LIMIT 1");
        $stmtJob->execute([(int)$record['job_id']]);
        $tid = $stmtJob->fetchColumn();
        if ($tid) {
            $syncTeamId = (int)$tid;
        }
    }

    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM job_close_3bb WHERE id = ?")->execute([$closeId]);

    if (!empty($record['job_log_id'])) {
        $pdo->prepare("DELETE FROM job_logs WHERE id = ?")->execute([(int)$record['job_log_id']]);
    }

    $pdo->prepare("UPDATE jobs SET status = 'dispatched', remark = NULL WHERE id = ? AND status = 'completed'")
        ->execute([(int)$record['job_id']]);

    $pdo->commit();

    if ($syncTeamId && $syncYearMonth) {
        syncTeamOilMonth($pdo, $syncTeamId, $syncYearMonth);
    } elseif (!empty($record['job_id'])) {
        syncTeamOilFromJob($pdo, (int)$record['job_id']);
    }

    echo json_encode(['success' => true, 'message' => 'ลบประวัติปิดงานเรียบร้อย']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
