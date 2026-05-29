<?php
// api/notifications/save_notification.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$type = $_POST['type'] ?? 'all'; 
$team_id = $_POST['team_id'] ?? null;
$target_user_id = $_POST['target_user_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($title === '' || $message === '') {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกหัวข้อและข้อความ']);
    exit;
}

if ($type === 'team' && empty($team_id)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกทีมเป้าหมาย']);
    exit;
}
if ($type === 'user' && empty($target_user_id)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกพนักงานเป้าหมาย']);
    exit;
}

$final_team_id = ($type === 'team') ? (int)$team_id : null;
$final_target_user_id = ($type === 'user') ? (int)$target_user_id : null;
$created_by = $_SESSION['user_id'];

try {
    // เช็คคอลัมน์อย่างปลอดภัยก่อนเพิ่ม
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'target_user_id'");
        if ($stmtCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN target_user_id INT DEFAULT NULL AFTER team_id");
            $pdo->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notif_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE");
        }
    } catch (Exception $e) { }

    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, team_id, target_user_id, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $message, $final_team_id, $final_target_user_id, $created_by]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>