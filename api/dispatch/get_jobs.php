<?php
// api/dispatch/get_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$username = $user['username'];

try {
    $sql = "SELECT j.*, t.team_name 
            FROM jobs j 
            LEFT JOIN teams t ON j.team_id = t.id 
            WHERE 1=1";
    $params = [];

    // Filter for Technician: Only show jobs assigned to their team (team_name = username)
    if ($role === 'technician') {
        $sql .= " AND t.team_name = ?";
        $params[] = $username;
    }

    $sql .= " ORDER BY COALESCE(j.seq, 9999) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    // Also fetch teams for Admin UI
    $teams = [];
    if (hasRole(['admin', 'super_admin'])) {
        $stmtTeams = $pdo->query("SELECT * FROM teams");
        $teams = $stmtTeams->fetchAll();
    }

    echo json_encode(['success' => true, 'data' => $jobs, 'teams' => $teams]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
