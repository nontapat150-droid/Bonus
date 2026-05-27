<?php
// api/dispatch/upload_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobs = $input['jobs'] ?? [];

if (empty($jobs)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลงาน']);
    exit;
}

try {
    // 1. ย้าย TRUNCATE มาไว้ก่อนเริ่ม Transaction
    // เพราะ TRUNCATE จะทำให้เกิด Implicit Commit อัตโนมัติใน MySQL
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE jobs");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 2. เริ่ม Transaction สำหรับการ Insert ข้อมูลรวดเดียว
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            plan_arrival_date, access_no, customer, phone, package,
            address, status, product, lat, lng, order_no,
            task_order, task_type, remark
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $imported = 0;
    foreach ($jobs as $job) {
        if (empty($job['access_no']) || empty($job['lat']) || empty($job['lng'])) {
            continue;
        }

        $stmt->execute([
            $job['plan_arrival_date'] ?? null, 
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

    // 3. ยืนยันการบันทึกข้อมูล (Commit)
    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    // 4. หากเกิด error ในระหว่าง insert ให้ย้อนกลับข้อมูล (Rollback)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}