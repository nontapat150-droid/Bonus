<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_close.php';

header('Content-Type: application/json');
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรายการ']);
    exit;
}

$user = getCurrentUser();
$isAdmin = hasRole(['admin', 'super_admin']);

try {
    $stmt = $pdo->prepare("SELECT c.*, j.plan_arrival_date, j.access_no, j.customer, j.package, j.product, j.order_no AS job_order_no,
                                  u.full_name AS tech_name
                           FROM job_close_3bb c
                           JOIN jobs j ON c.job_id = j.id
                           JOIN users u ON c.tech_id = u.id
                           WHERE c.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูล']);
        exit;
    }

    if (!$isAdmin && (int)$row['tech_id'] !== (int)$user['id']) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ดูรายการนี้']);
        exit;
    }

    $plan = $row['plan_arrival_date'] ?? null;
    $row['edit_deadline'] = job_close_deadline_iso($plan);
    $row['edit_deadline_label'] = job_close_deadline_label($plan);
    $row['can_edit'] = job_close_can_edit($plan, $isAdmin);

    echo json_encode(['success' => true, 'data' => $row]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
