<?php
// api/announcements/manage.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// อนุญาตเฉพาะแอดมินเท่านั้น
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึงการจัดการประกาศ']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $message = trim($_POST['message'] ?? '');
    $duration_val = intval($_POST['duration_val'] ?? 0);
    $duration_unit = $_POST['duration_unit'] ?? 'never';

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อความประกาศ']);
        exit;
    }

    $expires_at = null;
    if ($duration_unit !== 'never' && $duration_val > 0) {
        $unit_map = ['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days'];
        if (isset($unit_map[$duration_unit])) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$duration_val {$unit_map[$duration_unit]}"));
        }
    }

    try {
        // เคลียร์ประกาศเก่าทิ้งทั้งหมด ให้เหลือแค่ 1 แถวเสมอ
        $pdo->exec("TRUNCATE TABLE announcements");
        
        // เพิ่มประกาศใหม่
        $stmt = $pdo->prepare("INSERT INTO announcements (message, expires_at) VALUES (?, ?)");
        $stmt->execute([$message, $expires_at]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($action === 'delete') {
    try {
        $pdo->exec("TRUNCATE TABLE announcements");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'รูปแบบคำสั่งไม่ถูกต้อง']);
}
?>