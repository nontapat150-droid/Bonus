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

if (empty($access_no) || empty($plan_date)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุ Circuit ID และ วันที่เข้าทำ']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            access_no, customer, phone, address, plan_arrival_date, 
            package, remark, lat, lng, product, order_no, 
            task_order, task_type, status
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, 'pending'
        )
    ");

    // แปลงค่าว่างให้เป็น null สำหรับตัวเลข
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
        trim($input['task_type'] ?? '')
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'error' => 'มีข้อมูลงาน (Circuit ID) ซ้ำในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
}
