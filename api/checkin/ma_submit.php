<?php
// api/checkin/ma_submit.php — เช็คอิน MA (บันทึกสายทันทีหากเกินเวลาที่ผู้ดูแลระบบกำหนด)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('ma_technician')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะช่าง MA เท่านั้นที่เช็คอินได้']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'รูปแบบการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
ensureMaCheckinSchema($pdo);

$upload_dir = '../../assets/uploads/ma_checkins/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

try {
    $pdo->beginTransaction();

    // ตรวจสอบว่าเช็คอิน MA ไปแล้วหรือยังในวันนี้
    $stmtCheck = $pdo->prepare("SELECT id FROM ma_checkins WHERE user_id = ? AND DATE(checkin_time) = CURDATE()");
    $stmtCheck->execute([$user_id]);
    if ($stmtCheck->fetchColumn()) {
        throw new Exception("คุณได้ทำการเช็คอิน MA ของวันนี้ไปแล้ว");
    }

    // รับได้ทั้ง 'ma_checkin_image' (จาก JS ใหม่) และ 'checkin_image' (เดิม)
    $fileKey = isset($_FILES['ma_checkin_image']) ? 'ma_checkin_image' : 'checkin_image';
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('กรุณาอัปโหลดรูปภาพสำหรับการเช็คอิน MA');
    }

    $file = $_FILES[$fileKey];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    if (!in_array($ext, $allowedExts)) {
        $mime = mime_content_type($file['tmp_name']);
        $mimeToExt = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif'  => 'gif', 'image/webp' => 'webp',
            'image/heic' => 'heic', 'image/heif' => 'heif',
        ];
        if (isset($mimeToExt[$mime])) {
            $ext = $mimeToExt[$mime];
        } else {
            throw new Exception('ไฟล์นี้ไม่ใช่รูปภาพ กรุณาถ่ายรูปใหม่อีกครั้ง');
        }
    }

    $filename = 'ma_checkin_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        throw new Exception('เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ');
    }

    $stmtTeam = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtTeam->execute([$user_id]);
    $team_id = $stmtTeam->fetchColumn();

    $today = date('Y-m-d');
    $stmtJob = $pdo->prepare("
        SELECT MIN(job_time) 
        FROM ma_jobs 
        WHERE plan_arrival_date = ? 
          AND (assigned_user_id = ? OR (team_id IS NOT NULL AND team_id = ?))
          AND job_time IS NOT NULL 
          AND job_time != ''
    ");
    $stmtJob->execute([$today, $user_id, $team_id]);
    $earliest_job_time = $stmtJob->fetchColumn();

    if ($earliest_job_time) {
        $timeParts = explode('-', $earliest_job_time);
        $parsedTime = strtotime(trim($timeParts[0]));
        if ($parsedTime) {
            $allow_late_time = date('H:i:s', $parsedTime);
        } else {
            $allow_late_time = getMaCheckinLateTime($pdo);
        }
    } else {
        $allow_late_time = getMaCheckinLateTime($pdo);
    }

    $current_time = date('H:i:s');
    $is_late = ($current_time > $allow_late_time) ? 1 : 0;

    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    $full_url = getBaseUrl() . '/assets/uploads/ma_checkins/' . $filename;
    $stmt = $pdo->prepare("INSERT INTO ma_checkins (user_id, image_path, is_late, lat, lng) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $full_url, $is_late, $lat, $lng]);

    $pdo->commit();

    $timeDisplay = date('H:i');
    $lateTimeDisplay = date('H:i', strtotime($allow_late_time));
    if ($is_late) {
        $message = "เช็คอิน MA สำเร็จเวลา {$timeDisplay} (มาสาย — เกินเวลา {$lateTimeDisplay})";
    } else {
        $message = "เช็คอิน MA สำเร็จเวลา {$timeDisplay} (ตรงเวลา)";
    }

    $image_url = $full_url;
    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_late' => (bool)$is_late,
        'checkin_time' => $timeDisplay,
        'deadline_time' => $lateTimeDisplay,
        'image_url' => $image_url,
        'lat' => $lat,
        'lng' => $lng
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
