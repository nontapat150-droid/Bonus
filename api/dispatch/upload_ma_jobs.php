<?php
// api/dispatch/upload_ma_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

// ตรวจสอบและสร้างคอลัมน์ใหม่ใน ma_jobs แบบอัตโนมัติหากยังไม่มี
$cols = [
    'subdistrict' => 'VARCHAR(100) DEFAULT NULL',
    'district' => 'VARCHAR(100) DEFAULT NULL',
    'ais' => 'VARCHAR(50) DEFAULT NULL',
    'provider_3bb' => 'VARCHAR(50) DEFAULT NULL',
    'price' => 'DECIMAL(10,2) DEFAULT NULL',
    'electricity_activity' => 'VARCHAR(100) DEFAULT NULL',
    'checkin_photo' => 'VARCHAR(50) DEFAULT NULL',
    'photo_taking' => 'VARCHAR(50) DEFAULT NULL',
    'close_job_2100' => 'VARCHAR(50) DEFAULT NULL',
    'notify_repair_sp' => 'VARCHAR(50) DEFAULT NULL',
    'close_note_not_match_soa' => 'VARCHAR(50) DEFAULT NULL',
    'signal_after_online' => 'VARCHAR(50) DEFAULT NULL',
    'power_rx' => 'VARCHAR(50) DEFAULT NULL',
    'line_bot_photo' => 'VARCHAR(50) DEFAULT NULL',
    'close_node_1200' => 'VARCHAR(50) DEFAULT NULL',
    'splice_cable' => 'VARCHAR(50) DEFAULT NULL',
    'sleeve_shrink_tube' => 'VARCHAR(50) DEFAULT NULL',
    'drop_wire_clamp' => 'VARCHAR(50) DEFAULT NULL',
    'patch_cord_out' => 'VARCHAR(50) DEFAULT NULL',
    'lan' => 'VARCHAR(50) DEFAULT NULL',
    'request_lmr' => 'VARCHAR(50) DEFAULT NULL',
    'splice_new' => 'VARCHAR(50) DEFAULT NULL',
    'ma_mat' => 'VARCHAR(50) DEFAULT NULL',
    'insect_bites_cable' => 'VARCHAR(50) DEFAULT NULL',
    'install_date' => 'DATE DEFAULT NULL',
    'install_cable_length' => 'VARCHAR(50) DEFAULT NULL',
    'install_technician' => 'VARCHAR(100) DEFAULT NULL',
    'line_bot' => 'VARCHAR(50) DEFAULT NULL',
    'cause' => 'TEXT DEFAULT NULL',
    'fix_action' => 'TEXT DEFAULT NULL',
    'old_sn_pb' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_pb' => 'VARCHAR(100) DEFAULT NULL',
    'old_sn_onu_router' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_onu_router' => 'VARCHAR(100) DEFAULT NULL',
    'old_sn_wifi' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_wifi' => 'VARCHAR(100) DEFAULT NULL',
    'source' => 'VARCHAR(100) DEFAULT NULL',
    'destination' => 'VARCHAR(100) DEFAULT NULL',
    'distance' => 'DECIMAL(10,2) DEFAULT NULL',
    'oil_price_per_liter' => 'DECIMAL(10,2) DEFAULT NULL',
    'oil_cost' => 'DECIMAL(10,2) DEFAULT NULL'
];

try {
    $existingCols = $pdo->query("SHOW COLUMNS FROM ma_jobs")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $col => $type) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE ma_jobs ADD COLUMN `$col` $type");
        }
    }
} catch (Exception $e) {
    // Ignore schema update errors, just proceed
}

$input = json_decode(file_get_contents('php://input'), true);
$jobs = $input['jobs'] ?? [];

if (empty($jobs)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสำหรับนำเข้า']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO ma_jobs (
            access_no, customer, phone, address, plan_arrival_date, 
            lat, lng, subdistrict, district, order_no,
            install_technician, ais, provider_3bb, price, electricity_activity,
            checkin_photo, photo_taking, close_job_2100, notify_repair_sp, close_note_not_match_soa,
            signal_after_online, power_rx, line_bot_photo, close_node_1200, splice_cable,
            sleeve_shrink_tube, drop_wire_clamp, patch_cord_out, lan, request_lmr,
            splice_new, ma_mat, insect_bites_cable, install_date, install_cable_length,
            line_bot, cause, fix_action, old_sn_pb, new_sn_pb,
            old_sn_onu_router, new_sn_onu_router, old_sn_wifi, new_sn_wifi, source,
            destination, distance, oil_price_per_liter, oil_cost, remark, status
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )
    ");

    $imported = 0;
    foreach ($jobs as $job) {
        if (empty($job['access_no'])) continue;

        $date = !empty($job['plan_arrival_date']) ? $job['plan_arrival_date'] : null;
        $lat = !empty($job['lat']) ? (float)$job['lat'] : null;
        $lng = !empty($job['lng']) ? (float)$job['lng'] : null;

        $stmt->execute([
            $job['access_no'],
            $job['customer'] ?? null,
            $job['phone'] ?? null,
            $job['address'] ?? null,
            $date,
            $lat,
            $lng,
            $job['subdistrict'] ?? null,
            $job['district'] ?? null,
            $job['order_no'] ?? null,
            $job['install_technician'] ?? null,
            $job['ais'] ?? null,
            $job['provider_3bb'] ?? null,
            $job['price'] !== '' ? (float)$job['price'] : null,
            $job['electricity_activity'] ?? null,
            $job['checkin_photo'] ?? null,
            $job['photo_taking'] ?? null,
            $job['close_job_2100'] ?? null,
            $job['notify_repair_sp'] ?? null,
            $job['close_note_not_match_soa'] ?? null,
            $job['signal_after_online'] ?? null,
            $job['power_rx'] ?? null,
            $job['line_bot_photo'] ?? null,
            $job['close_node_1200'] ?? null,
            $job['splice_cable'] ?? null,
            $job['sleeve_shrink_tube'] ?? null,
            $job['drop_wire_clamp'] ?? null,
            $job['patch_cord_out'] ?? null,
            $job['lan'] ?? null,
            $job['request_lmr'] ?? null,
            $job['splice_new'] ?? null,
            $job['ma_mat'] ?? null,
            $job['insect_bites_cable'] ?? null,
            $job['install_date'] ? $job['install_date'] : null,
            $job['install_cable_length'] ?? null,
            $job['line_bot'] ?? null,
            $job['cause'] ?? null,
            $job['fix_action'] ?? null,
            $job['old_sn_pb'] ?? null,
            $job['new_sn_pb'] ?? null,
            $job['old_sn_onu_router'] ?? null,
            $job['new_sn_onu_router'] ?? null,
            $job['old_sn_wifi'] ?? null,
            $job['new_sn_wifi'] ?? null,
            $job['source'] ?? null,
            $job['destination'] ?? null,
            $job['distance'] !== '' ? (float)$job['distance'] : null,
            $job['oil_price_per_liter'] !== '' ? (float)$job['oil_price_per_liter'] : null,
            $job['oil_cost'] !== '' ? (float)$job['oil_cost'] : null,
            $job['remark'] ?? null,
            'pending'
        ]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
