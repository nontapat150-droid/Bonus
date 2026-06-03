<?php
// api/users/get_technicians.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

try {
    $type = $_GET['type'] ?? 'all';

    if ($type === 'ma') {
        $roleCondition = "AND (u.role = 'ma_technician' OR ur.role = 'ma_technician')";
    } else if ($type === 'office') {
        $roleCondition = "AND (u.role = 'technician' OR ur.role = 'technician')";
    } else {
        $roleCondition = "AND (u.role IN ('technician', 'ma_technician') OR ur.role IN ('technician', 'ma_technician'))";
    }

    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.full_name, u.username, t.team_name
        FROM users u
        LEFT JOIN teams t ON u.team_id = t.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.status = 'approved' 
        $roleCondition
        ORDER BY u.full_name ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
