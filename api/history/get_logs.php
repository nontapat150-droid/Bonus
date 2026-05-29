<?php
// api/history/get_logs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// อนุญาตเฉพาะแอดมินหรือผู้ที่กำหนดให้ดูข้อมูลทั้งหมดได้
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลประวัติรวม']);
    exit;
}

$type = $_GET['type'] ?? 'checkin';
$filter_date = $_GET['date'] ?? '';
$filter_month = $_GET['month'] ?? '';

try {
    $data = [];
    
    // ฟังก์ชันสร้างเงื่อนไข Date
    function buildDateCondition($col, $date, $month) {
        if ($date) return " AND DATE($col) = :date";
        if ($month) return " AND DATE_FORMAT($col, '%Y-%m') = :month";
        return "";
    }
    
    // ฟังก์ชันยัดตัวแปร PDO อย่างปลอดภัย
    function bindDateParams($stmt, $date, $month) {
        if ($date) $stmt->bindValue(':date', $date);
        elseif ($month) $stmt->bindValue(':month', $month);
    }

    if ($type === 'checkin') {
        $where = "WHERE 1=1 " . buildDateCondition('c.checkin_time', $filter_date, $filter_month);
        $sql = "SELECT c.id, c.checkin_time, c.image_path, u.full_name, u.allow_late_time, t.team_name, TIME(c.checkin_time) as time_only
                FROM checkins c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN teams t ON u.team_id = t.id
                $where ORDER BY c.checkin_time DESC LIMIT 500";
                
        $stmt = $pdo->prepare($sql);
        bindDateParams($stmt, $filter_date, $filter_month);
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($records as &$r) {
            $user_late_time = $r['allow_late_time'] ?: '08:30:00';
            if ($r['time_only'] > $user_late_time) {
                $r['status_code'] = 'late'; $r['status_text'] = 'มาสาย';
            } else {
                $r['status_code'] = 'on_time'; $r['status_text'] = 'มาตรงเวลา';
            }
        }
        $data = $records;

    } elseif ($type === 'start_day') {
        $where = "WHERE 1=1 " . buildDateCondition('r.created_at', $filter_date, $filter_month);
        $sql = "SELECT r.*, u.full_name, 
                (SELECT image_path FROM start_day_images i WHERE i.record_id = r.id LIMIT 1) as evidence_image
                FROM start_day_records r
                JOIN users u ON r.user_id = u.id
                $where ORDER BY r.created_at DESC LIMIT 500";
                
        $stmt = $pdo->prepare($sql);
        bindDateParams($stmt, $filter_date, $filter_month);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'oil') {
        $where = "WHERE 1=1 " . buildDateCondition('o.date_recorded', $filter_date, $filter_month);
        $sql = "SELECT o.*, u.full_name, 
                (SELECT image_path FROM oil_images i WHERE i.record_id = o.id LIMIT 1) as evidence_image
                FROM oil_records o
                JOIN users u ON o.tech_id = u.id
                $where ORDER BY o.date_recorded DESC LIMIT 500";
                
        $stmt = $pdo->prepare($sql);
        bindDateParams($stmt, $filter_date, $filter_month);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'inventory') {
        $where = "WHERE 1=1 " . buildDateCondition('l.timestamp', $filter_date, $filter_month);
        $sql = "SELECT l.*, i.sn, m.model_name, p.name as product_name, u.full_name as admin_name, 
                tu.full_name as target_name
                FROM inventory_logs l
                LEFT JOIN inventory_items i ON l.item_id = i.id
                LEFT JOIN product_models m ON i.model_id = m.id
                LEFT JOIN products p ON m.product_id = p.id
                LEFT JOIN users u ON l.admin_id = u.id
                LEFT JOIN users tu ON l.target_user_id = tu.id
                $where ORDER BY l.timestamp DESC LIMIT 500";
                
        $stmt = $pdo->prepare($sql);
        bindDateParams($stmt, $filter_date, $filter_month);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>