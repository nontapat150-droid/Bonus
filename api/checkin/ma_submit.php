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

    if (!isset($_FILES['checkin_image']) || $_FILES['checkin_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('กรุณาอัปโหลดรูปภาพสำหรับการเช็คอิน MA');
    }

    $file = $_FILES['checkin_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WebP');
    }

    $filename = 'ma_checkin_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        throw new Exception('เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ');
    }

    $allow_late_time = getMaCheckinLateTime($pdo);
    $current_time = date('H:i:s');
    $is_late = ($current_time > $allow_late_time) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO ma_checkins (user_id, image_path, is_late) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $filename, $is_late]);

    $pdo->commit();

    $timeDisplay = date('H:i');
    $lateTimeDisplay = date('H:i', strtotime($allow_late_time));
    if ($is_late) {
        $message = "เช็คอิน MA สำเร็จเวลา {$timeDisplay} (มาสาย — เกินเวลา {$lateTimeDisplay})";
    } else {
        $message = "เช็คอิน MA สำเร็จเวลา {$timeDisplay} (ตรงเวลา)";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_late' => (bool)$is_late,
        'checkin_time' => $timeDisplay,
        'deadline_time' => $lateTimeDisplay
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
