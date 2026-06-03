<?php
// api/work_records/get.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('intern')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM work_records WHERE user_id = ? ORDER BY record_date DESC, created_at DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $records]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
