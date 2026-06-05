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

try {
    $existingCols = $pdo->query("SHOW COLUMNS FROM ma_checkins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('checkout_time', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN checkout_time DATETIME DEFAULT NULL");
    if (!in_array('checkout_image', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN checkout_image VARCHAR(255) DEFAULT NULL");
    if (!in_array('checkout_lat', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN checkout_lat VARCHAR(50) DEFAULT NULL");
    if (!in_array('checkout_lng', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN checkout_lng VARCHAR(50) DEFAULT NULL");
    if (!in_array('is_edited_image', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN is_edited_image TINYINT(1) DEFAULT 0");
    if (!in_array('admin_status', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN admin_status VARCHAR(20) DEFAULT NULL");
    if (!in_array('admin_edited', $existingCols, true)) $pdo->exec("ALTER TABLE ma_checkins ADD COLUMN admin_edited TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

$sql = "SELECT c.id, c.user_id, c.checkin_time, c.image_path, c.lat, c.lng, c.checkout_time, c.checkout_image, c.checkout_lat, c.checkout_lng, c.is_edited_image, c.admin_status, c.admin_edited, u.full_name, u.allow_late_time, t.team_name, TIME(c.checkin_time) as time_only
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

try {
    $sql .= " ORDER BY c.checkin_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$dashboard = ['total' => 0, 'on_time' => 0, 'late' => 0, 'leave' => 0];
$distinctDays = [];
$checked_in_user_ids = [];
$target_date = $filter_date ?: date('Y-m-d');

foreach($records as &$r) {
    $user_late_time = $r['allow_late_time'] ?: '08:30:00';
    
    if (!empty($r['admin_status'])) {
        if ($r['admin_status'] === 'leave') {
            $r['status_code'] = 'leave';
            $r['status_text'] = 'ลา';
            $dashboard['leave']++;
        } elseif ($r['admin_status'] === 'on_time') {
            $r['status_code'] = 'on_time';
            $r['status_text'] = 'มาตรงเวลา';
            $dashboard['on_time']++;
        } elseif ($r['admin_status'] === 'late') {
            $r['status_code'] = 'late';
            $r['status_text'] = 'มาสาย';
            $dashboard['late']++;
        }
    } else {
        if ($r['time_only'] > $user_late_time) {
            $r['status_code'] = 'late';
            $r['status_text'] = 'มาสาย';
            $dashboard['late']++;
        } else {
            $r['status_code'] = 'on_time';
            $r['status_text'] = 'มาตรงเวลา';
            $dashboard['on_time']++;
        }
    }
    $dashboard['total']++;
    $dayKey = date('Y-m-d', strtotime($r['checkin_time']));
    $distinctDays[$dayKey] = true;
    
    if (strpos($r['checkin_time'], $target_date) === 0) {
        $checked_in_user_ids[] = $r['user_id'];
    }
}
unset($r);

if (!$filter_month || $filter_date) {
    $day_name = date('l', strtotime($target_date));
    
    $users_sql = "SELECT id, full_name, team_id, days_off FROM users WHERE days_off LIKE ? AND (role = 'ma_technician' OR id IN (SELECT user_id FROM user_roles WHERE role = 'ma_technician'))";
    $stmt_off = $pdo->prepare($users_sql);
    $stmt_off->execute(['%"' . $day_name . '"%']);
    $off_users = $stmt_off->fetchAll(PDO::FETCH_ASSOC);

    foreach ($off_users as $ou) {
        if (!hasRole(['admin', 'super_admin']) && $ou['id'] != $user_id) continue;
        if (in_array($ou['id'], $checked_in_user_ids)) continue;
        
        $team_name = '';
        if ($ou['team_id']) {
            $stmt_t = $pdo->prepare("SELECT team_name FROM teams WHERE id = ?");
            $stmt_t->execute([$ou['team_id']]);
            $team_name = $stmt_t->fetchColumn();
        }
        
        $dummy = [
            'id' => 'day_off_' . $ou['id'],
            'user_id' => $ou['id'],
            'checkin_time' => $target_date . ' 00:00:00',
            'image_path' => null,
            'full_name' => $ou['full_name'],
            'team_name' => $team_name,
            'time_only' => '00:00:00',
            'status_code' => 'day_off',
            'status_text' => 'วันหยุด',
            'is_day_off' => true,
            'is_late' => 0
        ];
        $records[] = $dummy;
    }

    usort($records, function($a, $b) {
        return strcmp($b['checkin_time'], $a['checkin_time']);
    });
}

$dashboard['work_days'] = count($distinctDays);
$dashboard['deadline_time'] = date('H:i', strtotime($deadline));

echo json_encode([
    'success' => true,
    'records' => $records,
    'dashboard' => $dashboard,
    'deadline_time' => date('H:i', strtotime($deadline))
]);
