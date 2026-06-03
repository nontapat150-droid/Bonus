<?php
// api/dispatch/update_ma_job_status.php — ปิดงาน/ไม่สำเร็จ/เลื่อนนัด งาน MA
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['technician', 'ma_technician', 'admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

ensureMaJobSchema($pdo);

$user = getCurrentUser();
$userId = (int)$user['id'];
$jobId = (int)($_POST['job_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$remark = trim((string)($_POST['remark'] ?? ''));
$rescheduleDate = trim((string)($_POST['reschedule_date'] ?? ''));

if (!$jobId || !$status) {
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
    if ($rescheduleDate === '') {
        echo json_encode(['success' => false, 'error' => 'กรุณาเลือกวันที่เลื่อนนัด']);
        exit;
    }
    $parsed = date_create($rescheduleDate);
    if (!$parsed) {
        echo json_encode(['success' => false, 'error' => 'รูปแบบวันที่ไม่ถูกต้อง']);
        exit;
    }
    $rescheduleDate = $parsed->format('Y-m-d');
}

if ($status === 'completed') {
    if (!isset($_FILES['proof_images'])) {
        echo json_encode(['success' => false, 'error' => 'กรุณาอัปโหลดรูปภาพหลักฐานการจบงาน']);
        exit;
    }
    $files = $_FILES['proof_images'];
    $hasFile = false;
    if (is_array($files['error'])) {
        foreach ($files['error'] as $err) {
            if ($err === UPLOAD_ERR_OK) { $hasFile = true; break; }
        }
    } elseif ($files['error'] === UPLOAD_ERR_OK) {
        $hasFile = true;
    }
    if (!$hasFile) {
        echo json_encode(['success' => false, 'error' => 'กรุณาอัปโหลดรูปภาพหลักฐานการจบงาน']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("SELECT j.*, t.team_name FROM ma_jobs j LEFT JOIN teams t ON j.team_id = t.id WHERE j.id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบงาน MA ที่ระบุ']);
        exit;
    }

    if (hasRole(['technician', 'ma_technician']) && !hasRole(['admin', 'super_admin'])) {
        $teamId = null;
        $uStmt = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $teamId = $uStmt->fetchColumn();
        $allowed = ($teamId && (int)$job['team_id'] === (int)$teamId)
            || ($job['team_name'] && strcasecmp($job['team_name'], $user['username']) === 0);
        if (!$allowed && (int)$job['assigned_user_id'] !== $userId) {
            echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์อัปเดตงานนี้']);
            exit;
        }
    }

    if ($job['status'] === 'completed') {
        echo json_encode(['success' => false, 'error' => 'งานนี้ถูกปิดสำเร็จไปแล้ว']);
        exit;
    }

    $pdo->beginTransaction();

    if ($status === 'completed') {
        $uploadDir = '../../assets/uploads/ma_jobs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $files = $_FILES['proof_images'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        $saved = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            $err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            if ($err !== UPLOAD_ERR_OK) continue;

            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;

            $filename = 'ma_' . $jobId . '_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                $pdo->prepare("INSERT INTO ma_job_completion_images (ma_job_id, image_path, uploaded_by) VALUES (?, ?, ?)")
                    ->execute([$jobId, 'assets/uploads/ma_jobs/' . $filename, $userId]);
                $saved++;
            }
        }

        if ($saved === 0) {
            throw new Exception('ไม่สามารถบันทึกรูปภาพได้ กรุณาลองใหม่');
        }

        $pdo->prepare("UPDATE ma_jobs SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$jobId]);

        addMaCustomerHistory($pdo, [
            'non_number' => $job['access_no'],
            'customer_name' => $job['customer'] ?? '',
            'phone' => $job['phone'] ?? '',
            'address' => $job['address'] ?? '',
            'ma_job_id' => $jobId,
            'action' => 'completed',
            'symptoms' => $job['symptoms'] ?? null,
            'area_provider' => $job['area_provider'] ?? null,
            'remark' => $remark ?: null,
            'tech_id' => $userId,
            'team_id' => $job['team_id'] ?? null,
            'action_date' => date('Y-m-d')
        ]);

        $message = 'ปิดงาน MA สำเร็จ';

    } elseif ($status === 'failed') {
        $pdo->prepare("UPDATE ma_jobs SET status = 'failed', remark = CONCAT(COALESCE(remark,''), '\n[ไม่สำเร็จ] ', ?), updated_at = NOW() WHERE id = ?")
            ->execute([$remark, $jobId]);

        addMaCustomerHistory($pdo, [
            'non_number' => $job['access_no'],
            'customer_name' => $job['customer'] ?? '',
            'phone' => $job['phone'] ?? '',
            'address' => $job['address'] ?? '',
            'ma_job_id' => $jobId,
            'action' => 'failed',
            'symptoms' => $job['symptoms'] ?? null,
            'area_provider' => $job['area_provider'] ?? null,
            'remark' => $remark,
            'tech_id' => $userId,
            'team_id' => $job['team_id'] ?? null,
            'action_date' => date('Y-m-d')
        ]);

        $message = 'บันทึกสถานะไม่สำเร็จเรียบร้อย';

    } else {
        $prevDate = $job['plan_arrival_date'];
        $pdo->prepare("UPDATE ma_jobs SET plan_arrival_date = ?, status = NULL, remark = CONCAT(COALESCE(remark,''), '\n[เลื่อนนัด] ', ?), updated_at = NOW() WHERE id = ?")
            ->execute([$rescheduleDate, $remark, $jobId]);

        $pdo->prepare("INSERT INTO ma_job_reschedules (ma_job_id, previous_plan_date, new_plan_date, remark, created_by) VALUES (?, ?, ?, ?, ?)")
            ->execute([$jobId, $prevDate, $rescheduleDate, $remark ?: null, $userId]);

        addMaCustomerHistory($pdo, [
            'non_number' => $job['access_no'],
            'customer_name' => $job['customer'] ?? '',
            'phone' => $job['phone'] ?? '',
            'address' => $job['address'] ?? '',
            'ma_job_id' => $jobId,
            'action' => 'rescheduled',
            'symptoms' => $job['symptoms'] ?? null,
            'area_provider' => $job['area_provider'] ?? null,
            'remark' => ($remark ?: '') . ' → ' . $rescheduleDate,
            'tech_id' => $userId,
            'team_id' => $job['team_id'] ?? null,
            'action_date' => $rescheduleDate
        ]);

        $message = 'เลื่อนนัดงาน MA เรียบร้อย';
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
