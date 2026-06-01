<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_close.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('technician')) {
    echo json_encode(['success' => true, 'alerts' => []]);
    exit;
}

$user = getCurrentUser();
$userId = (int)$user['id'];
$username = $user['username'] ?? '';

try {
    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $teamId = $stmtUser->fetchColumn();

    $sql = "SELECT j.id, j.access_no, j.customer, j.plan_arrival_date, j.team_id, t.team_name
            FROM jobs j
            LEFT JOIN teams t ON j.team_id = t.id
            WHERE (j.status IS NULL OR j.status NOT IN ('completed', 'failed'))
            AND j.plan_arrival_date IS NOT NULL";
    $params = [];

    if ($teamId) {
        $sql .= " AND (j.team_id = ? OR t.team_name = ?)";
        $params[] = $teamId;
        $params[] = $username;
    } else {
        $sql .= " AND t.team_name = ?";
        $params[] = $username;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts = [];
    foreach ($jobs as $job) {
        $plan = $job['plan_arrival_date'];
        $seconds = job_close_seconds_until_deadline($plan);
        if ($seconds === null || $seconds <= 0) {
            continue;
        }
        if (!job_close_is_urgent($plan)) {
            continue;
        }
        $alerts[] = [
            'job_id' => $job['id'],
            'access_no' => $job['access_no'],
            'customer' => $job['customer'],
            'plan_arrival_date' => $plan,
            'deadline_label' => job_close_deadline_label($plan),
            'hours_left' => round($seconds / 3600, 1),
        ];
    }

    echo json_encode(['success' => true, 'alerts' => $alerts, 'count' => count($alerts)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
