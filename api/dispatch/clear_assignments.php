<?php
// api/dispatch/clear_assignments.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

try {
    $jobType = $_GET['type'] ?? 'jobs';
    $table = ($jobType === 'ma') ? 'ma_jobs' : 'jobs';

    $sql = "UPDATE {$table} SET team_id = NULL, seq = NULL, map_link = NULL";
    $pdo->exec($sql);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
