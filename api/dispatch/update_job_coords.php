<?php
// api/dispatch/update_job_coords.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// รองรับทั้ง Admin และช่างเทคนิคที่ได้รับมอบหมายงาน (เผื่อช่างต้องการแก้พิกัดให้ตรง)
$user_id = $_SESSION['user_id'];
$isAdmin = hasRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$job_id = $input['job_id'] ?? null;
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;

if (!$job_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสงาน']);
    exit;
}

// Convert to float or null
$lat = (trim($lat) !== '') ? (float)$lat : null;
$lng = (trim($lng) !== '') ? (float)$lng : null;

try {
    // ถ้าไม่ใช่แอดมิน ตรวจสอบสิทธิ์ว่างานนี้เป็นของทีมช่างคนนี้หรือไม่ (อุปกรณ์เสริม หากต้องการให้ช่างแก้ได้ด้วย)
    // แต่เพื่อความปลอดภัย อาจจะจำกัดให้เฉพาะแอดมินหรือช่างในทีม
    if (!$isAdmin) {
        $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $currentUser = $stmtUser->fetch();
        $myTeamId = $currentUser['team_id'] ?? null;

        $stmtJob = $pdo->prepare("SELECT team_id FROM {$table} WHERE id = ?");
        $stmtJob->execute([$job_id]);
        $job = $stmtJob->fetch();

        if (!$job || $job['team_id'] != $myTeamId) {
            echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์แก้ไขพิกัดของงานนี้']);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE {$table} SET lat = ?, lng = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$lat, $lng, $job_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
