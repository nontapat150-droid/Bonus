<?php
// api/dispatch/delete_all_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

try {
    $oilSyncPairs = [];
    ensureTeamOilCasesTable($pdo);

    $fromCases = $pdo->query("SELECT team_id, `year_month` AS ym_key FROM team_oil_cases")->fetchAll(PDO::FETCH_ASSOC);
    $ymOil = sqlYearMonth('o.date_recorded');
    $fromOil = $pdo->query("
        SELECT DISTINCT t.id AS team_id, {$ymOil} AS ym_key
        FROM oil_records o
        INNER JOIN teams t ON t.team_name = o.license_plate
    ")->fetchAll(PDO::FETCH_ASSOC);
    $oilSyncPairs = array_merge($fromCases, $fromOil);

    $jobType = $_GET['type'] ?? 'jobs';
    
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    if ($jobType === 'ma') {
        $pdo->exec('TRUNCATE TABLE ma_jobs');
    } else {
        $pdo->exec('TRUNCATE TABLE job_close_3bb');
        $pdo->exec('TRUNCATE TABLE job_logs');
        $pdo->exec('TRUNCATE TABLE jobs');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    syncCollectedTeamOilMonths($pdo, $oilSyncPairs);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
