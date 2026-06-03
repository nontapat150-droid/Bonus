<?php
// api/dispatch/update_job_status.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';
require_once '../../config/job_reschedule.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];

if (!hasRole('technician')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะช่าง Office เท่านั้นที่สามารถอัปเดตสถานะงานติดตั้งได้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$job_id = $input['job_id'] ?? null;
$status = $input['status'] ?? null;
$remark = trim($input['remark'] ?? '');
$close3bb = $input['close_3bb'] ?? null;
$reschedule_date = trim($input['reschedule_date'] ?? '');

if (!$job_id || !$status) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$validStatuses = ['completed', 'failed', 'rescheduled'];
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
    exit;
}

if ($status === 'failed' && $remark === '') {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุหมายเหตุสาเหตุที่ไม่สำเร็จ']);
    exit;
}

if ($status === 'rescheduled') {
    if ($reschedule_date === '') {
        echo json_encode(['success' => false, 'error' => 'กรุณาเลือกวันที่นัดติดตั้งใหม่']);
        exit;
    }
    $parsedReschedule = date_create($reschedule_date);
    if (!$parsedReschedule) {
        echo json_encode(['success' => false, 'error' => 'รูปแบบวันที่ไม่ถูกต้อง']);
        exit;
    }
    $reschedule_date = $parsedReschedule->format('Y-m-d');
}

if ($status === 'completed' && empty($close3bb)) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลปิดงานก่อนยืนยัน']);
    exit;
}

if ($status === 'completed') {
    $provider = strtoupper(trim((string)($close3bb['install_provider'] ?? '')));
    if (!in_array($provider, ['AIS', '3BB'], true)) {
        echo json_encode(['success' => false, 'error' => 'กรุณาเลือกประเภทงานติดตั้ง (AIS หรือ 3BB)']);
        exit;
    }
    if (empty($close3bb['install_date'])) {
        echo json_encode(['success' => false, 'error' => 'กรุณาเลือกวันที่ติดตั้ง']);
        exit;
    }
}

function nullableStr($value) {
    $v = trim((string)($value ?? ''));
    return $v === '' ? null : $v;
}

function nullableDecimal($value) {
    if ($value === null || $value === '') return null;
    return is_numeric($value) ? (float)$value : null;
}

try {
    $stmtJob = $pdo->prepare("SELECT j.*, t.team_name FROM jobs j LEFT JOIN teams t ON j.team_id = t.id WHERE j.id = ?");
    $stmtJob->execute([$job_id]);
    $job = $stmtJob->fetch();

    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบงานที่ระบุ']);
        exit;
    }

    if ($job['status'] === 'completed') {
        echo json_encode(['success' => false, 'error' => 'งานนี้ถูกปิดสำเร็จไปแล้ว']);
        exit;
    }

    if ($status === 'rescheduled') {
        ensureJobReschedulesTable($pdo);
        ensureNotificationExtraColumns($pdo);
    }

    $pdo->beginTransaction();

    $previousPlanDate = $job['plan_arrival_date'] ?? null;
    $job_log_id = null;
    $notificationId = null;

    if ($status === 'completed') {
        $logRemark = nullableStr($close3bb['remark'] ?? '') ?? '';
        $stmt = $pdo->prepare("UPDATE jobs SET status = ?, remark = ? WHERE id = ?");
        $stmt->execute([$status, $logRemark, $job_id]);

        $logStmt = $pdo->prepare("INSERT INTO job_logs (job_id, tech_id, status, remark) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$job_id, $user['id'], $status, $logRemark]);
        $job_log_id = (int)$pdo->lastInsertId();

        $installDate = !empty($close3bb['install_date']) ? $close3bb['install_date'] : ($job['plan_arrival_date'] ?? null);
        $provider = strtoupper(trim((string)($close3bb['install_provider'] ?? '3BB')));

        $closeStmt = $pdo->prepare("INSERT INTO job_close_3bb (
            job_id, job_log_id, tech_id, install_provider, install_date, close_case_no, order_no,
            customer_name, package_name, main_package, equipment_soa,
            sn_playbox, sn_onu, sn_mesh, sn_sim, sn_ip_camera,
            splitter, port_used, l3_name, actual_cable_length, ref_id_3bb,
            sc_connector_blue, initial_fee, remark
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $closeStmt->execute([
            $job_id,
            $job_log_id,
            $user['id'],
            $provider,
            $installDate,
            nullableStr($close3bb['close_case_no'] ?? $job['access_no']),
            nullableStr($close3bb['order_no'] ?? $job['order_no']),
            nullableStr($close3bb['customer_name'] ?? $job['customer']),
            nullableStr($close3bb['package_name'] ?? $job['package']),
            nullableStr($close3bb['main_package'] ?? $job['product']),
            nullableStr($close3bb['equipment_soa'] ?? null),
            nullableStr($close3bb['sn_playbox'] ?? null),
            nullableStr($close3bb['sn_onu'] ?? null),
            nullableStr($close3bb['sn_mesh'] ?? null),
            nullableStr($close3bb['sn_sim'] ?? null),
            nullableStr($close3bb['sn_ip_camera'] ?? null),
            nullableStr($close3bb['splitter'] ?? null),
            nullableStr($close3bb['port_used'] ?? null),
            nullableStr($close3bb['l3_name'] ?? null),
            nullableDecimal($close3bb['actual_cable_length'] ?? null),
            nullableStr($close3bb['ref_id_3bb'] ?? null),
            nullableStr($close3bb['sc_connector_blue'] ?? null),
            nullableDecimal($close3bb['initial_fee'] ?? null),
            nullableStr($close3bb['remark'] ?? null),
        ]);
    } elseif ($status === 'rescheduled') {
        $logRemark = $remark !== '' ? $remark : 'ลูกค้าขอเลื่อนวันนัดติดตั้ง';
        $jobRemark = $logRemark . ' (เลื่อนจาก ' . formatThaiDateShort($previousPlanDate) . ' → ' . formatThaiDateShort($reschedule_date) . ')';

        $stmt = $pdo->prepare("UPDATE jobs SET status = NULL, remark = ?, plan_arrival_date = ? WHERE id = ?");
        $stmt->execute([$jobRemark, $reschedule_date, $job_id]);

        $logStmt = $pdo->prepare("INSERT INTO job_logs (job_id, tech_id, status, remark) VALUES (?, ?, 'rescheduled', ?)");
        $logStmt->execute([$job_id, $user['id'], $logRemark]);
        $job_log_id = (int)$pdo->lastInsertId();

        $notificationId = notifyAdminJobRescheduled(
            $pdo,
            $job,
            $user,
            $job['team_name'] ?? null,
            $previousPlanDate,
            $reschedule_date,
            $logRemark
        );

        $resStmt = $pdo->prepare("
            INSERT INTO job_reschedules (
                job_id, job_log_id, tech_id, team_id,
                previous_plan_date, new_plan_date, remark, notification_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $resStmt->execute([
            $job_id,
            $job_log_id,
            $user['id'],
            $job['team_id'] ?? null,
            $previousPlanDate,
            $reschedule_date,
            $logRemark,
            $notificationId,
        ]);
    } else {
        $logRemark = $remark;
        $stmt = $pdo->prepare("UPDATE jobs SET status = ?, remark = ? WHERE id = ?");
        $stmt->execute([$status, $logRemark, $job_id]);

        $logStmt = $pdo->prepare("INSERT INTO job_logs (job_id, tech_id, status, remark) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$job_id, $user['id'], $status, $logRemark]);
        $job_log_id = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    if ($status === 'completed' && !empty($job['team_id'])) {
        syncTeamOilMonth($pdo, (int)$job['team_id'], date('Y-m'));
    }

    $messages = [
        'completed' => 'บันทึกการปิดงานสำเร็จ',
        'failed' => 'บันทึกงานไม่สำเร็จเรียบร้อย',
        'rescheduled' => 'เลื่อนนัดติดตั้งเรียบร้อย งานจะแสดงอีกครั้งในวันที่ ' . formatThaiDateShort($reschedule_date),
    ];

    echo json_encode([
        'success' => true,
        'message' => $messages[$status] ?? 'บันทึกเรียบร้อย',
        'reschedule_date' => $status === 'rescheduled' ? $reschedule_date : null,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
}
