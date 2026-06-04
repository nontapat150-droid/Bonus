<?php
// api/dispatch/upload_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

// เฉพาะ Admin หรือ Super Admin เท่านั้นที่นำเข้างานได้
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

ensureMaJobSchema($pdo); // Ensure schema is ready (adds lat/lng/job_id if needed)

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
        $access_no = trim($job['access_no'] ?? '');
        if (empty($access_no)) continue;

        $date = !empty($job['plan_arrival_date']) ? $job['plan_arrival_date'] : null;
        
        $lat = isset($job['lat']) && trim($job['lat']) !== '' ? $job['lat'] : null;
        $lng = isset($job['lng']) && trim($job['lng']) !== '' ? $job['lng'] : null;
        $customerName = trim($job['customer'] ?? '');
        $phone = trim($job['phone'] ?? '');
        $address = trim($job['address'] ?? '');

        // หากไม่มีพิกัด ลองดึงจากประวัติลูกค้า NON เดิม
        if ($lat === null || $lng === null) {
            $existingCust = getMaCustomerByNon($pdo, $access_no);
            if ($existingCust) {
                if ($lat === null && !empty($existingCust['lat'])) $lat = $existingCust['lat'];
                if ($lng === null && !empty($existingCust['lng'])) $lng = $existingCust['lng'];
                if (empty($customerName) && !empty($existingCust['customer_name'])) $customerName = $existingCust['customer_name'];
                if (empty($phone) && !empty($existingCust['phone'])) $phone = $existingCust['phone'];
                if (empty($address) && !empty($existingCust['address'])) $address = $existingCust['address'];
            }
        }

        $stmt->execute([
            $access_no,
            $customerName ?: null,
            $phone ?: null,
            $address ?: null,
            $date,
            $job['package'] ?? null,
            $job['remark'] ?? null,
            $lat,
            $lng,
            $job['product'] ?? null,
            $job['order_no'] ?? null,
            $job['task_order'] ?? null,
            $job['task_type'] ?? null
        ]);
        
        $jobId = $pdo->lastInsertId();
        
        // บันทึกประวัติลูกค้า
        addMaCustomerHistory($pdo, [
            'non_number' => $access_no,
            'customer_name' => $customerName,
            'phone' => $phone,
            'address' => $address,
            'job_id' => $jobId,
            'action' => 'imported_install',
            'remark' => $job['remark'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
            'action_date' => $date ?: date('Y-m-d')
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