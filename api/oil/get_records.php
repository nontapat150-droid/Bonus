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
// รับค่าป้ายทะเบียนเพื่อใช้ในการกรอง
$license_plate = $_GET['license_plate'] ?? 'all';

$params = [];
$whereClause = "WHERE 1=1";

if ($start_date && $end_date) {
    $whereClause .= " AND DATE(o.date_recorded) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// กรองป้ายทะเบียนรถ (ถ้าผู้ใช้เลือกรถเฉพาะคัน)
if ($license_plate !== 'all' && !empty($license_plate)) {
    $whereClause .= " AND o.license_plate = ?";
    $params[] = $license_plate;
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

    // 3. ดึงข้อมูลตาราง (ใช้ข้อมูล distance และ job_count จากฐานข้อมูลโดยตรง)
    $tableSql = "SELECT
                    o.id, o.tech_id, o.license_plate, o.liters, o.mileage, o.price_per_liter, o.total_price, o.date_recorded, 
                    o.job_count as stored_job_count, o.distance,
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
                 ORDER BY o.date_recorded DESC"; 
    
    $stmtTable = $pdo->prepare($tableSql);
    $stmtTable->execute($params);
    $rawRecords = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

    $processed_records = [];
    $total_jobs_period = 0;

    foreach ($rawRecords as $row) {
        $distance = (float)$row['distance'];
        $job_count = (int)$row['stored_job_count'];
        $total_jobs_period += $job_count;

        $cost_per_job = $job_count > 0 ? ($row['total_price'] / $job_count) : 0;
        $cost_per_km = $distance > 0 ? ($row['total_price'] / $distance) : 0;
        $liters_per_km = $distance > 0 ? ($row['liters'] / $distance) : 0;

        $row['distance'] = $distance;
        $row['job_count'] = $job_count;
        $row['cost_per_job'] = round($cost_per_job, 2);
        $row['cost_per_km'] = round($cost_per_km, 2);
        $row['liters_per_km'] = round($liters_per_km, 2);

        $processed_records[] = $row;
    }

    // 4. Monthly Summary for Comparison
    $monthlySql = "SELECT
                    DATE_FORMAT(o.date_recorded, '%Y-%m') as month_label,
                    SUM(o.total_price) as monthly_cost,
                    SUM(o.liters) as monthly_liters,
                    SUM(o.job_count) as monthly_jobs
                 FROM oil_records o
                 WHERE YEAR(o.date_recorded) = YEAR(CURRENT_DATE)
                 GROUP BY DATE_FORMAT(o.date_recorded, '%Y-%m')
                 ORDER BY month_label ASC";
    $stmtMonthly = $pdo->prepare($monthlySql);
    $stmtMonthly->execute();
    $monthlySummary = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_records' => (int)($stats['total_records'] ?? 0),
            'total_liters' => (float)($stats['total_liters'] ?? 0),
            'total_cost' => (float)($stats['total_cost'] ?? 0),
            'total_jobs' => $total_jobs_period
        ],
        'chart' => $chartData,
        'monthly' => $monthlySummary,
        'records' => $processed_records
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>