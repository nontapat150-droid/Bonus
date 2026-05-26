<?php
// api/dispatch/upload_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobs = $input['jobs'] ?? [];

if (empty($jobs)) {
    echo json_encode(['success' => false, 'error' => 'No jobs data provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    // The requirement states: PHP will TRUNCATE the old jobs and INSERT new ones
    // Wait, TRUNCATE will fail if foreign keys are referencing it, but we only have `oil_records` which doesn't reference `jobs`. 
    // Wait, `jobs` references `teams` (team_id). TRUNCATE `jobs` is safe as no one references it.
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE jobs");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            plan_arrival_date, access_no, customer, phone, package, 
            address, status, product, lat, lng, order_no, 
            task_order, task_type, remark
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $imported = 0;
    foreach ($jobs as $job) {
        // Skip invalid rows (must have at least access_no and lat/lng)
        if (empty($job['access_no']) || empty($job['lat']) || empty($job['lng'])) {
            continue;
        }

        // Parse Excel serial date to YYYY-MM-DD if needed, but assuming JS sends string YYYY-MM-DD
        $stmt->execute([
            $job['plan_arrival'] ?? null,
            $job['access_no'],
            $job['customer'] ?? null,
            $job['phone'] ?? null,
            $job['package'] ?? null,
            $job['address'] ?? null,
            $job['status'] ?? null,
            $job['product'] ?? null,
            $job['lat'],
            $job['lng'],
            $job['order_no'] ?? null,
            $job['task_order'] ?? null,
            $job['task_type'] ?? null,
            $job['remark'] ?? null
        ]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
