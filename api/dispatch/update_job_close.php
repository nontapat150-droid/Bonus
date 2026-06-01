<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_close.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$isAdmin = hasRole(['admin', 'super_admin']);
$input = json_decode(file_get_contents('php://input'), true);
$closeId = (int)($input['close_id'] ?? 0);
$payload = $input['close_3bb'] ?? null;

if ($closeId <= 0 || empty($payload)) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

function nullableStr($value) {
    $v = trim((string)($value ?? ''));
    return $v === '' ? null : $v;
}

function nullableDecimal($value) {
    if ($value === null || $value === '') return null;
    return is_numeric($value) ? (float)$value : null;
}

$provider = strtoupper(trim((string)($payload['install_provider'] ?? '')));
if (!in_array($provider, ['AIS', '3BB'], true)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกประเภทงานติดตั้ง (AIS หรือ 3BB)']);
    exit;
}
if (empty($payload['install_date'])) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกวันที่ติดตั้ง']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT c.*, j.plan_arrival_date FROM job_close_3bb c JOIN jobs j ON c.job_id = j.id WHERE c.id = ?");
    $stmt->execute([$closeId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูล']);
        exit;
    }

    if (!$isAdmin && (int)$record['tech_id'] !== (int)$user['id']) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์แก้ไขรายการนี้']);
        exit;
    }

    if (!job_close_can_edit($record['plan_arrival_date'] ?? null, $isAdmin)) {
        echo json_encode(['success' => false, 'error' => 'หมดเวลาแก้ไขแล้ว (แก้ไขได้ถึง 12:00 น. ของวันถัดไปจากวันมอบหมายงาน)']);
        exit;
    }

    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE job_close_3bb SET
        install_provider = ?, install_date = ?, order_no = ?,
        equipment_soa = ?, sn_playbox = ?, sn_onu = ?, sn_mesh = ?, sn_sim = ?, sn_ip_camera = ?,
        splitter = ?, port_used = ?, l3_name = ?, actual_cable_length = ?, ref_id_3bb = ?,
        sc_connector_blue = ?, initial_fee = ?, remark = ?, updated_at = NOW()
        WHERE id = ?");

    $update->execute([
        $provider,
        $payload['install_date'],
        nullableStr($payload['order_no'] ?? null),
        nullableStr($payload['equipment_soa'] ?? null),
        nullableStr($payload['sn_playbox'] ?? null),
        nullableStr($payload['sn_onu'] ?? null),
        nullableStr($payload['sn_mesh'] ?? null),
        nullableStr($payload['sn_sim'] ?? null),
        nullableStr($payload['sn_ip_camera'] ?? null),
        nullableStr($payload['splitter'] ?? null),
        nullableStr($payload['port_used'] ?? null),
        nullableStr($payload['l3_name'] ?? null),
        nullableDecimal($payload['actual_cable_length'] ?? null),
        nullableStr($payload['ref_id_3bb'] ?? null),
        nullableStr($payload['sc_connector_blue'] ?? null),
        nullableDecimal($payload['initial_fee'] ?? null),
        nullableStr($payload['remark'] ?? null),
        $closeId,
    ]);

    $remark = nullableStr($payload['remark'] ?? '') ?? '';
    if (!empty($record['job_log_id'])) {
        $pdo->prepare("UPDATE job_logs SET remark = ? WHERE id = ?")->execute([$remark, $record['job_log_id']]);
    }
    $pdo->prepare("UPDATE jobs SET remark = ? WHERE id = ?")->execute([$remark, $record['job_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกการแก้ไขสำเร็จ']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
