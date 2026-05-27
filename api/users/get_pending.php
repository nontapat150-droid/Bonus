<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Join เพื่อดึงชื่อทีม (ทะเบียนรถ) มาแสดงด้วย
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.full_name, t.team_name 
        FROM users u 
        LEFT JOIN teams t ON u.team_id = t.id 
        WHERE u.status = 'pending'
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>