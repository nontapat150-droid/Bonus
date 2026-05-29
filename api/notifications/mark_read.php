<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$notification_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีรหัสแจ้งเตือน']);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_notification_user (notification_id, user_id),
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)");
    $stmt->execute([(int)$notification_id, $user_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
