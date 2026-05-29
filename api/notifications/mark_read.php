<?php
// api/notifications/mark_read.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_SESSION['user_id'];

if ($notification_id) {
    try {
        // ใช้ INSERT IGNORE เพื่อป้องกันการกดอ่านซ้ำแล้ว Error
        $stmt = $pdo->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)");
        $stmt->execute([$notification_id, $user_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
}
?>