<?php
// api/users/get_users.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    ensureMaJobSchema($pdo);

    $stmt = $pdo->query("
        SELECT u.id, u.username, u.role, u.full_name, u.created_at, u.team_id, u.allow_late_time, t.team_name 
        FROM users u 
        LEFT JOIN teams t ON u.team_id = t.id 
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roleStmt = $pdo->query("SELECT user_id, role FROM user_roles");
    $roleMap = [];
    foreach ($roleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $roleMap[(int)$row['user_id']][] = $row['role'];
    }

    foreach ($users as &$u) {
        $uid = (int)$u['id'];
        $u['roles'] = $roleMap[$uid] ?? [$u['role']];
        if (!in_array($u['role'], $u['roles'], true)) {
            $u['roles'][] = $u['role'];
        }
    }
    unset($u);

    echo json_encode(['success' => true, 'data' => $users]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}