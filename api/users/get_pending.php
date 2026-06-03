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
        SELECT u.id, u.username, u.full_name, u.role, t.team_name 
        FROM users u 
        LEFT JOIN teams t ON u.team_id = t.id 
        WHERE u.status = 'pending'
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
        if (!in_array($u['role'], $u['roles'], true) && $u['role']) {
            $u['roles'][] = $u['role'];
        }
    }
    unset($u);

    echo json_encode(['success' => true, 'data' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>