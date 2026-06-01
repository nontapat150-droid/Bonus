<?php
// api/dispatch/upload_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// เฉพาะ Admin หรือ Super Admin เท่านั้นที่นำเข้างานได้
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobs = $input['jobs'] ?? [];

if (empty($jobs)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสำหรับนำเข้า']);
    exit;
}

try {
    $pdo->beginTransaction();

    // เตรียม SQL สำหรับเพิ่มข้อมูลงาน
    $stmt = $pdo->prepare("INSERT INTO jobs (access_no, customer, phone, address, plan_arrival_date, package, remark, lat, lng, product, order_no, task_order, task_type, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    $imported = 0;
    foreach ($jobs as $job) {
        // ข้ามบรรทัดที่ไม่มีรหัสงาน (Access No)
        if (empty($job['access_no'])) continue;

        $date = !empty($job['plan_arrival_date']) ? $job['plan_arrival_date'] : null;

        $stmt->execute([
            $job['access_no'],
            $job['customer'] ?? null,
            $job['phone'] ?? null,
            $job['address'] ?? null,
            $date,
            $job['package'] ?? null,
            $job['remark'] ?? null,
            $job['lat'] ?? null,
            $job['lng'] ?? null,
            $job['product'] ?? null,
            $job['order_no'] ?? null,
            $job['task_order'] ?? null,
            $job['task_type'] ?? null
        ]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>