<?php
// api/dispatch/get_reschedule_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_reschedule.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$pendingOnly = ($_GET['pending'] ?? '') === '1';
$limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));

try {
    ensureJobReschedulesTable($pdo);

    $sql = "SELECT
                jr.id,
                jr.job_id,
                jr.previous_plan_date,
                jr.new_plan_date,
                jr.remark,
                jr.created_at,
                jr.acknowledged_at,
                j.access_no,
                j.customer,
                j.phone,
                u.full_name AS tech_name,
                t.team_name,
                ack.full_name AS acknowledged_by_name
            FROM job_reschedules jr
            INNER JOIN jobs j ON j.id = jr.job_id
            INNER JOIN users u ON u.id = jr.tech_id
            LEFT JOIN teams t ON t.id = jr.team_id
            LEFT JOIN users ack ON ack.id = jr.acknowledged_by
            WHERE 1=1";

    if ($pendingOnly) {
        $sql .= " AND jr.acknowledged_at IS NULL";
    }

    $sql .= " ORDER BY jr.created_at DESC LIMIT " . $limit;

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'records' => $rows]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
