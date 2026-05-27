<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

try {
    // ดึงผู้ใช้งานทั้งหมดที่ผ่านการอนุมัติแล้ว พร้อมชื่อทีม
    $sql = "SELECT u.id, u.full_name, u.role, t.team_name 
            FROM users u 
            LEFT JOIN teams t ON u.team_id = t.id 
            WHERE u.status = 'approved'
            ORDER BY t.team_name ASC, u.full_name ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'users' => $users, 
        'current_user_id' => $_SESSION['user_id']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}