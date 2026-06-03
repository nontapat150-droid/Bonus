<?php
// api/dispatch/edit_job.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$job_id = $input['job_id'] ?? null;
$jobType = $input['job_type'] ?? 'jobs';
$table = ($jobType === 'ma') ? 'ma_jobs' : 'jobs';

if (!$job_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสงาน']);
    exit;
}

$access_no = trim($input['access_no'] ?? '');
$plan_date = trim($input['plan_arrival_date'] ?? '');

if (empty($access_no) || empty($plan_date)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุ Circuit ID/NON และ วันที่เข้าทำ']);
    exit;
}

try {
    if ($jobType === 'ma') {
        ensureMaJobSchema($pdo);
        $stmt = $pdo->prepare("
            UPDATE {$table} SET 
                access_no = ?, customer = ?, phone = ?, area_provider = ?, 
                sub_district = ?, district = ?, address = ?, plan_arrival_date = ?, 
                lat = ?, lng = ?, price = ?, symptoms = ?, remark = ?, order_no = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $lat = isset($input['lat']) && trim($input['lat']) !== '' ? (float)$input['lat'] : null;
        $lng = isset($input['lng']) && trim($input['lng']) !== '' ? (float)$input['lng'] : null;
        $price = isset($input['price']) && trim($input['price']) !== '' ? (float)$input['price'] : null;

        $stmt->execute([
            $access_no,
            trim($input['customer'] ?? ''),
            trim($input['phone'] ?? ''),
            trim($input['area_provider'] ?? ''),
            trim($input['sub_district'] ?? ''),
            trim($input['district'] ?? ''),
            trim($input['address'] ?? ''),
            $plan_date,
            $lat,
            $lng,
            $price,
            trim($input['symptoms'] ?? ''),
            trim($input['remark'] ?? ''),
            trim($input['order_no'] ?? ''),
            $job_id
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE {$table} SET 
                access_no = ?, customer = ?, phone = ?, address = ?, plan_arrival_date = ?, 
                package = ?, remark = ?, lat = ?, lng = ?, product = ?, order_no = ?, 
                task_order = ?, task_type = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $lat = isset($input['lat']) && trim($input['lat']) !== '' ? (float)$input['lat'] : null;
        $lng = isset($input['lng']) && trim($input['lng']) !== '' ? (float)$input['lng'] : null;

        $stmt->execute([
            $access_no,
            trim($input['customer'] ?? ''),
            trim($input['phone'] ?? ''),
            trim($input['address'] ?? ''),
            $plan_date,
            trim($input['package'] ?? ''),
            trim($input['remark'] ?? ''),
            $lat,
            $lng,
            trim($input['product'] ?? ''),
            trim($input['order_no'] ?? ''),
            trim($input['task_order'] ?? ''),
            trim($input['task_type'] ?? ''),
            $job_id
        ]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'error' => 'มีข้อมูลงาน (Circuit ID) ซ้ำในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
}
