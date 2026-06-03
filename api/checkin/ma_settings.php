<?php
// api/checkin/ma_settings.php — ตั้งเวลาเช็คอิน MA (เฉพาะผู้ดูแลระบบ)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();
ensureMaCheckinSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('super_admin')) {
        echo json_encode(['success' => false, 'error' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่ตั้งเวลาเช็คอิน MA ได้']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $time = trim($data['late_time'] ?? '');

    if ($time === '') {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุเวลา']);
        exit;
    }

    $timeFormatted = date('H:i:s', strtotime($time));
    if (!$timeFormatted) {
        echo json_encode(['success' => false, 'error' => 'รูปแบบเวลาไม่ถูกต้อง']);
        exit;
    }

    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('late_time_ma_technician', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
            ->execute([$timeFormatted, $timeFormatted]);
        echo json_encode([
            'success' => true,
            'late_time' => date('H:i', strtotime($timeFormatted)),
            'message' => 'บันทึกเวลาเช็คอิน MA เรียบร้อย'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึก']);
    }
    exit;
}

$lateTime = getMaCheckinLateTime($pdo);
echo json_encode([
    'success' => true,
    'late_time' => date('H:i', strtotime($lateTime)),
    'can_edit' => hasRole('super_admin')
]);
