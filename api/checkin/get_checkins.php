<?php
// api/checkin/get_checkins.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['super_admin', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.checkin_time, 
            u.full_name, 
            u.username,
            t.team_name,
            c.image_path
        FROM checkins c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE DATE(c.checkin_time) = ?
        ORDER BY c.checkin_time DESC
    ");
    $stmt->execute([$date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
