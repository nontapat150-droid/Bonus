<?php
// api/dispatch/add_manual_ma_job.php
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

$access_no = trim($input['access_no'] ?? '');
$plan_date = trim($input['plan_arrival_date'] ?? '');

if (empty($access_no) || empty($plan_date)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุ NON และ วันที่']);
    exit;
}

try {
    ensureMaJobSchema($pdo); // Make sure schema is up to date

    $stmt = $pdo->prepare("
        INSERT INTO ma_jobs (
            access_no, customer, phone, address, sub_district, district, plan_arrival_date, 
            remark, symptoms, lat, lng, order_no, 
            assigned_user_id, area_provider, price, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, 'pending'
        )
    ");

    $lat = isset($input['lat']) && trim($input['lat']) !== '' ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) && trim($input['lng']) !== '' ? (float)$input['lng'] : null;
    $price = isset($input['price']) && trim($input['price']) !== '' ? (float)$input['price'] : null;
    $assigned_user_id = isset($input['assigned_user_id']) && trim($input['assigned_user_id']) !== '' ? (int)$input['assigned_user_id'] : null;
    $area_provider = in_array($input['area_provider'] ?? '', ['AIS', '3BB']) ? $input['area_provider'] : null;

    $stmt->execute([
        $access_no,
        trim($input['customer'] ?? ''),
        trim($input['phone'] ?? ''),
        trim($input['address'] ?? ''),
        trim($input['sub_district'] ?? ''),
        trim($input['district'] ?? ''),
        $plan_date,
        trim($input['remark'] ?? ''),
        trim($input['symptoms'] ?? ''),
        $lat,
        $lng,
        trim($input['order_no'] ?? ''),
        $assigned_user_id,
        $area_provider,
        $price
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { 
        echo json_encode(['success' => false, 'error' => 'มีข้อมูลงาน (NON) ซ้ำในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
}
