<?php
// api/dispatch/add_manual_job.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$access_no = trim($input['access_no'] ?? '');
$plan_date = trim($input['plan_arrival_date'] ?? '');

$jobType = $_GET['type'] ?? 'jobs';
$table = ($jobType === 'ma') ? 'ma_jobs' : 'jobs';

if (empty($access_no) || empty($plan_date)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุ Circuit ID และ วันที่เข้าทำ']);
    exit;
}

try {
    $team_id = null;
    if (!empty($input['assigned_user_id'])) {
        $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmtUser->execute([(int)$input['assigned_user_id']]);
        $team_id = $stmtUser->fetchColumn() ?: null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO {$table} (
            access_no, customer, phone, address, plan_arrival_date, 
            package, remark, lat, lng, product, order_no, 
            task_order, task_type, status, team_id
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, 'pending', ?
        )
    ");

    // ดึงข้อมูลเดิมจากระบบลูกค้ากลางถ้าไม่มีให้มา
    $lat = isset($input['lat']) && trim($input['lat']) !== '' ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) && trim($input['lng']) !== '' ? (float)$input['lng'] : null;
    $customerName = trim($input['customer'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = trim($input['address'] ?? '');

    // Include ma_job for customer logic
    require_once '../../config/ma_job.php';
    ensureMaJobSchema($pdo);

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
        $plan_date,
        trim($input['package'] ?? ''),
        trim($input['remark'] ?? ''),
        $lat,
        $lng,
        trim($input['product'] ?? ''),
        trim($input['order_no'] ?? ''),
        trim($input['task_order'] ?? ''),
        trim($input['task_type'] ?? ''),
        $team_id
    ]);

    $jobId = $pdo->lastInsertId();

    // บันทึกประวัติลูกค้า
    addMaCustomerHistory($pdo, [
        'non_number' => $access_no,
        'customer_name' => $customerName,
        'phone' => $phone,
        'address' => $address,
        'job_id' => $jobId,
        'action' => 'manual_add_install',
        'remark' => trim($input['remark'] ?? ''),
        'lat' => $lat,
        'lng' => $lng,
        'action_date' => $plan_date ?: date('Y-m-d')
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'error' => 'มีข้อมูลงาน (Circuit ID) ซ้ำในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
}
