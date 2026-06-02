<?php
/**
 * One-time backfill: team_oil_cases + oil_records.job_count from job_logs.
 * Run as super_admin via browser.
 */
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/oil_job_sync.php';

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die('Unauthorized access.');
}

echo '<h2>Backfill team_oil_cases &amp; oil_records.job_count</h2>';

try {
    ensureTeamOilCasesTable($pdo);
    echo '<div style="color:green;">Table team_oil_cases ready.</div>';

    $result = backfillAllTeamOilCases($pdo);
    echo '<div style="color:green;">Synced ' . (int)$result['pairs_synced'] . ' team-month pair(s).</div>';
} catch (Exception $e) {
    echo '<div style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<br><a href="../index.php">กลับหน้าหลัก</a>';
