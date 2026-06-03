<?php
// api/dispatch/bulk_delete.php — ลบงานหลายรายการ (แยก Office / MA)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];
$jobType = $data['job_type'] ?? 'jobs';
$isMa = ($jobType === 'ma');

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No IDs provided']);
    exit;
}

try {
    ensureMaJobSchema($pdo);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($isMa) {
        $oilSyncPairs = collectTeamOilMonthsForMaJobIds($pdo, $ids);
        $pdo->prepare("DELETE FROM ma_job_completion_images WHERE ma_job_id IN ($placeholders)")->execute($ids);
        try {
            $pdo->prepare("DELETE FROM ma_job_reschedules WHERE ma_job_id IN ($placeholders)")->execute($ids);
        } catch (Exception $e) {}
        $stmt = $pdo->prepare("DELETE FROM ma_jobs WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        syncCollectedTeamOilMonths($pdo, $oilSyncPairs);
    } else {
        $oilSyncPairs = collectTeamOilMonthsForJobIds($pdo, $ids);
        $pdo->prepare("DELETE FROM job_logs WHERE job_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM job_close_3bb WHERE job_id IN ($placeholders)")->execute($ids);
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        syncCollectedTeamOilMonths($pdo, $oilSyncPairs);
    }

    echo json_encode([
        'success' => true,
        'deleted' => $stmt->rowCount(),
        'job_type' => $isMa ? 'ma' : 'jobs'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
