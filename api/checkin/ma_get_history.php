<?php
// api/checkin/ma_get_history.php — ประวัติเช็คอิน MA
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();
ensureMaCheckinSchema($pdo);

$user_id = (int)$_SESSION['user_id'];
$filter_date = $_GET['date'] ?? '';
$filter_month = $_GET['month'] ?? '';

$deadline = getMaCheckinLateTime($pdo);

$sql = "SELECT c.id, c.checkin_time, c.image_path, c.is_late, u.full_name, t.team_name, TIME(c.checkin_time) AS time_only
        FROM ma_checkins c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE 1=1";
$params = [];

if (!hasRole(['admin', 'super_admin'])) {
    $sql .= " AND c.user_id = ?";
    $params[] = $user_id;
}

if ($filter_date) {
    $sql .= " AND DATE(c.checkin_time) = ?";
    $params[] = $filter_date;
} elseif ($filter_month) {
    $sql .= " AND DATE_FORMAT(c.checkin_time, '%Y-%m') = ?";
    $params[] = $filter_month;
}

$sql .= " ORDER BY c.checkin_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboard = ['total' => 0, 'on_time' => 0, 'late' => 0, 'work_days' => 0];
$distinctDays = [];

foreach ($records as &$r) {
    if ((int)$r['is_late'] === 1) {
        $r['status_code'] = 'late';
        $r['status_text'] = 'มาสาย';
        $dashboard['late']++;
    } else {
        $r['status_code'] = 'on_time';
        $r['status_text'] = 'ตรงเวลา';
        $dashboard['on_time']++;
    }
    $dashboard['total']++;
    $dayKey = date('Y-m-d', strtotime($r['checkin_time']));
    $distinctDays[$dayKey] = true;
}
unset($r);

$dashboard['work_days'] = count($distinctDays);
$dashboard['deadline_time'] = date('H:i', strtotime($deadline));

echo json_encode([
    'success' => true,
    'records' => $records,
    'dashboard' => $dashboard,
    'deadline_time' => date('H:i', strtotime($deadline))
]);
