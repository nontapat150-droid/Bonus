<?php
// api/users/get_users.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.role, u.full_name, u.created_at, u.team_id, t.team_name 
        FROM users u 
        LEFT JOIN teams t ON u.team_id = t.id 
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}