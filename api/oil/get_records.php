<?php
// api/oil/get_records.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$params = [];
$whereClause = "WHERE 1=1";

if ($start_date && $end_date) {
    // กรองวันที่สำหรับสถิติภาพรวม
    $whereClause .= " AND DATE(o.date_recorded) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

try {
    // 1. Fetch Aggregated Stats
    $statsSql = "SELECT
                    COUNT(o.id) as total_records,
                    SUM(o.liters) as total_liters,
                    SUM(o.total_price) as total_cost
                 FROM oil_records o
                 $whereClause";
    $stmtStats = $pdo->prepare($statsSql);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch();

    // 2. Fetch Chart Data
    $chartSql = "SELECT
                    DATE(o.date_recorded) as record_date,
                    SUM(o.total_price) as daily_cost,
                    SUM(o.liters) as daily_liters
                 FROM oil_records o
                 $whereClause
                 GROUP BY DATE(o.date_recorded)
                 ORDER BY DATE(o.date_recorded) ASC";
    $stmtChart = $pdo->prepare($chartSql);
    $stmtChart->execute($params);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงข้อมูลทั้งหมด และเรียงจากเก่าไปใหม่ เพื่อคำนวณระยะทางและช่วงเวลา
    $tableSql = "SELECT
                    o.id, o.tech_id, o.license_plate, o.liters, o.mileage, o.price_per_liter, o.total_price, o.date_recorded,
                    u.full_name as tech_name, u.team_id,
                    t.id as record_team_id,
                    t.team_name,
                    GROUP_CONCAT(i.image_path SEPARATOR ',') as images
                 FROM oil_records o
                 JOIN users u ON o.tech_id = u.id
                 LEFT JOIN teams t ON t.team_name = o.license_plate
                 LEFT JOIN oil_images i ON o.id = i.record_id
                 $whereClause
                 GROUP BY o.id
                 ORDER BY o.license_plate ASC, o.date_recorded ASC"; // ต้องเรียงเก่าไปใหม่เพื่อคำนวณไมล์
    
    $stmtTable = $pdo->prepare($tableSql);
    $stmtTable->execute($params);
    $rawRecords = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

    $last_record = [];
    $processed_records = [];
    $total_jobs_period = 0;

    foreach ($rawRecords as $row) {
        $plate = $row['license_plate'];
        $team_id = $row['record_team_id'] ?: $row['team_id'];
        $current_date = $row['date_recorded'];
        $current_mileage = (int)$row['mileage'];

        $prev_date = $last_record[$plate]['date_recorded'] ?? null;
        $prev_mileage = $last_record[$plate]['mileage'] ?? $current_mileage;

        // คำนวณระยะทางที่วิ่ง
        $distance = $current_mileage - $prev_mileage;
        if ($distance < 0) $distance = 0; // กันพิมพ์เลขไมล์ผิด

        $job_count = 0;

        // คำนวณเคสงาน (Jobs) ที่ได้รับมอบหมายเฉพาะใน "ช่วงเวลานี้"
        if ($team_id) {
            if ($prev_date) {
                // มีการเติมครั้งที่แล้ว: หางานที่เกิดขึ้นระหว่างรอบที่แล้ว ถึง รอบนี้
                $jobStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM jobs 
                    WHERE team_id = ? 
                    AND DATE(COALESCE(plan_arrival_date, created_at)) > DATE(?) 
                    AND DATE(COALESCE(plan_arrival_date, created_at)) <= DATE(?)
                ");
                $jobStmt->execute([$team_id, $prev_date, $current_date]);
            } else {
                // ครั้งแรก: หางานเฉพาะของวันนั้น
                $jobStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM jobs 
                    WHERE team_id = ? 
                    AND DATE(COALESCE(plan_arrival_date, created_at)) = DATE(?)
                ");
                $jobStmt->execute([$team_id, $current_date]);
            }
            $job_count = (int)$jobStmt->fetchColumn();
            $total_jobs_period += $job_count;
        }

        // คำนวณต้นทุน
        $cost_per_job = $job_count > 0 ? ($row['total_price'] / $job_count) : 0;
        $cost_per_km = $distance > 0 ? ($row['total_price'] / $distance) : 0;

        // แนบค่ากลับไปให้ Frontend
        $row['distance'] = $distance;
        $row['job_count'] = $job_count;
        $row['cost_per_job'] = round($cost_per_job, 2);
        $row['cost_per_km'] = round($cost_per_km, 2);

        $last_record[$plate] = $row;
        $processed_records[] = $row;
    }

    // เรียงกลับจาก วันใหม่สุด -> เก่าสุด เพื่อแสดงบนตาราง
    usort($processed_records, function($a, $b) {
        return strtotime($b['date_recorded']) - strtotime($a['date_recorded']);
    });

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_records' => (int)($stats['total_records'] ?? 0),
            'total_liters' => (float)($stats['total_liters'] ?? 0),
            'total_cost' => (float)($stats['total_cost'] ?? 0),
            'total_jobs' => $total_jobs_period
        ],
        'chart' => $chartData,
        'records' => $processed_records
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>