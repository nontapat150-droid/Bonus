<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$filter_date = $_GET['date'] ?? '';
$filter_month = $_GET['month'] ?? ''; 

// ดึงข้อมูลเช็คอิน พร้อมกับ allow_late_time ของแต่ละผู้ใช้
$sql = "SELECT c.id, c.checkin_time, c.image_path, u.full_name, u.allow_late_time, t.team_name, TIME(c.checkin_time) as time_only
        FROM checkins c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE 1=1";
$params = [];

// ถ้าไม่ใช่แอดมิน ให้ดูได้แค่ของตัวเอง
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

// คำนวณ Dashboard โดยใช้ allow_late_time ของแต่ละผู้ใช้
$dashboard = ['total' => 0, 'on_time' => 0, 'late' => 0];
foreach($records as &$r) {
    // ใช้ allow_late_time ของผู้ใช้นั้น ๆ เป็นเกณฑ์
    $user_late_time = $r['allow_late_time'] ?: '08:30:00';
    
    if ($r['time_only'] > $user_late_time) {
        $r['status_code'] = 'late';
        $r['status_text'] = 'มาสาย';
        $dashboard['late']++;
    } else {
        $r['status_code'] = 'on_time';
        $r['status_text'] = 'มาตรงเวลา';
        $dashboard['on_time']++;
    }
    $dashboard['total']++;
}

echo json_encode([
    'success' => true, 
    'records' => $records, 
    'dashboard' => $dashboard
]);