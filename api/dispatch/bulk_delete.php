<?php
// api/dispatch/bulk_delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No IDs provided']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $oilSyncPairs = collectTeamOilMonthsForJobIds($pdo, $ids);

    // Delete job_logs
    $pdo->prepare("DELETE FROM job_logs WHERE job_id IN ($placeholders)")->execute($ids);
    
    // Delete job_close_3bb
    $pdo->prepare("DELETE FROM job_close_3bb WHERE job_id IN ($placeholders)")->execute($ids);
    
    // Delete jobs
    $sql = "DELETE FROM jobs WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);

    syncCollectedTeamOilMonths($pdo, $oilSyncPairs);

    echo json_encode([
        'success' => true,
        'deleted' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
