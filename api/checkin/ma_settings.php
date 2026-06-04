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

$user_id = $_SESSION['user_id'] ?? null;
$personalLateTime = getMaCheckinLateTime($pdo);
$hasJob = false;

if ($user_id) {
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
            $personalLateTime = date('H:i:s', $parsedTime);
            $hasJob = true;
        }
    }
}

$lateTime = getMaCheckinLateTime($pdo);
echo json_encode([
    'success' => true,
    'late_time' => date('H:i', strtotime($lateTime)),
    'personal_late_time' => date('H:i', strtotime($personalLateTime)),
    'has_job' => $hasJob,
    'can_edit' => hasRole('super_admin')
]);
