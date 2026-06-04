<?php
// api/dispatch/upload_ma_jobs.php — นำเข้างาน MA จาก Excel (คอลัมน์: เวลา/NON/ชื่อลูกค้า/เบอร์/อาการ/ที่อยู่/ทีมช่าง/พื้นที่/หมายเหตุ)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

ensureMaJobSchema($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$jobs = $input['jobs'] ?? [];

if (empty($jobs)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสำหรับนำเข้า']);
    exit;
}

try {
    $pdo->beginTransaction();
    $createdBy = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO ma_jobs (
            access_no, customer, phone, address, plan_arrival_date, job_time,
            symptoms, area_provider, team_id, team_name_import, team_match_status,
            assigned_user_id, remark, lat, lng, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $imported = 0;
    $unmatchedTeams = [];

    foreach ($jobs as $job) {
        $non = trim((string)($job['access_no'] ?? ''));
        if ($non === '') continue;

        $teamName = trim((string)($job['team_name'] ?? ''));
        $teamId = null;
        $matchStatus = null;
        $assignedUserId = null;

        if ($teamName !== '') {
            $team = findTeamByName($pdo, $teamName);
            if ($team) {
                $teamId = (int)$team['id'];
                $matchStatus = 'matched';
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND status = 'approved' ORDER BY id ASC LIMIT 1");
                $uStmt->execute([$teamId]);
                $assignedUserId = $uStmt->fetchColumn() ?: null;
            } else {
                $matchStatus = 'unmatched';
                $unmatchedTeams[] = $teamName;
            }
        }

        // ดึงข้อมูลลูกค้าและพิกัดจากระบบหากมี
        $dbCustomer = null;
        $dbPhone = null;
        $dbAddress = null;
        $dbLat = null;
        $dbLng = null;

        // เช็คจากตารางงานติดตั้งก่อน (ข้อมูลจะแม่นยำและมีพิกัด)
        $stmtEx = $pdo->prepare("SELECT customer, phone, address, lat, lng FROM jobs WHERE access_no = ? ORDER BY id DESC LIMIT 1");
        $stmtEx->execute([$non]);
        $existingJob = $stmtEx->fetch(PDO::FETCH_ASSOC);

        if ($existingJob) {
            $dbCustomer = $existingJob['customer'];
            $dbPhone = $existingJob['phone'];
            $dbAddress = $existingJob['address'];
            $dbLat = $existingJob['lat'];
            $dbLng = $existingJob['lng'];
        } else {
            // ถ้าระบบติดตั้งไม่มี เช็คจากประวัติลูกค้า MA เดิม
            $stmtMa = $pdo->prepare("SELECT customer_name, phone, address FROM ma_customers WHERE non_number = ? LIMIT 1");
            $stmtMa->execute([$non]);
            $existingMa = $stmtMa->fetch(PDO::FETCH_ASSOC);
            if ($existingMa) {
                $dbCustomer = $existingMa['customer_name'];
                $dbPhone = $existingMa['phone'];
                $dbAddress = $existingMa['address'];
            }
        }

        // ผสานข้อมูล (ถ้าใน Excel มีให้ใช้ Excel, ถ้าไม่มีให้ใช้จากระบบ)
        $finalCustomer = !empty($job['customer']) ? $job['customer'] : $dbCustomer;
        $finalPhone = !empty($job['phone']) ? $job['phone'] : $dbPhone;
        $finalAddress = !empty($job['address']) ? $job['address'] : $dbAddress;

        $area = normalizeMaAreaProvider($job['area_provider'] ?? '');
        $planDate = !empty($job['plan_arrival_date']) ? $job['plan_arrival_date'] : date('Y-m-d');

        $stmt->execute([
            $non,
            $finalCustomer,
            $finalPhone,
            $finalAddress,
            $planDate,
            $job['job_time'] ?? null,
            $job['symptoms'] ?? null,
            $area,
            $teamId,
            $teamName ?: null,
            $matchStatus,
            $assignedUserId,
            $job['remark'] ?? null,
            $dbLat,
            $dbLng
        ]);

        $maJobId = (int)$pdo->lastInsertId();

        addMaCustomerHistory($pdo, [
            'non_number' => $non,
            'customer_name' => $finalCustomer ?? '',
            'phone' => $finalPhone ?? '',
            'address' => $finalAddress ?? '',
            'ma_job_id' => $maJobId,
            'action' => 'imported',
            'symptoms' => $job['symptoms'] ?? null,
            'area_provider' => $area,
            'remark' => $job['remark'] ?? null,
            'team_id' => $teamId,
            'action_date' => $planDate
        ]);

        if ($teamId) {
            notifyMaJobAssignment(
                $pdo,
                $teamId,
                'งาน MA ใหม่: ' . $non,
                'ลูกค้า: ' . ($job['customer'] ?? '-') . ' | อาการ: ' . ($job['symptoms'] ?? '-'),
                $createdBy
            );
        }

        $imported++;
    }

    $pdo->commit();

    $msg = "นำเข้า {$imported} งานเรียบร้อย";
    if (!empty($unmatchedTeams)) {
        $unique = array_unique($unmatchedTeams);
        $msg .= ' (ทีมไม่ตรง: ' . implode(', ', array_slice($unique, 0, 5)) . (count($unique) > 5 ? '...' : '') . ' — แอดมินสามารถเลือกทีมใหม่ได้)';
    }

    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'message' => $msg,
        'unmatched_teams' => array_values(array_unique($unmatchedTeams))
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
