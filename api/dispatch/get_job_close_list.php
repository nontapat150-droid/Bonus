<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/job_close.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$isAdmin = hasRole(['admin', 'super_admin', 'viewer']);
$filter_date = $_GET['date'] ?? '';
$filter_month = $_GET['month'] ?? '';

try {
    $sql = "SELECT c.id, c.job_id, c.install_provider, c.install_date, c.close_case_no, c.order_no,
                   c.customer_name, c.package_name, c.created_at, c.updated_at,
                   j.plan_arrival_date, j.access_no, j.address,
                   u.full_name AS tech_name, t.team_name
            FROM job_close_3bb c
            JOIN jobs j ON c.job_id = j.id
            JOIN users u ON c.tech_id = u.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE 1=1";
    $params = [];

    if (!$isAdmin) {
        $sql .= " AND c.tech_id = ?";
        $params[] = $user['id'];
    }

    if ($filter_date) {
        $sql .= " AND DATE(c.created_at) = ?";
        $params[] = $filter_date;
    } elseif ($filter_month) {
        $sql .= " AND DATE_FORMAT(c.created_at, '%Y-%m') = ?";
        $params[] = $filter_month;
    }

    $sql .= " ORDER BY c.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $plan = $row['plan_arrival_date'] ?? null;
        $row['edit_deadline'] = job_close_deadline_iso($plan);
        $row['edit_deadline_label'] = job_close_deadline_label($plan);
        $row['can_edit'] = job_close_can_edit($plan, $isAdmin);
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
