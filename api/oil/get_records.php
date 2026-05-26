<?php
// api/oil/get_records.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// Allow Admin and Super Admin
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$params = [];
$whereClause = "WHERE 1=1";

if ($start_date && $end_date) {
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

    // 2. Fetch Chart Data (Grouped by Date)
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
    $chartData = $stmtChart->fetchAll();

    // 3. Fetch Detailed Table Data with Images
    $tableSql = "SELECT
                    o.id, o.license_plate, o.liters, o.mileage, o.price_per_liter, o.total_price, o.date_recorded,
                    u.full_name as tech_name,
                    GROUP_CONCAT(i.image_path SEPARATOR ',') as images
                 FROM oil_records o
                 JOIN users u ON o.tech_id = u.id
                 LEFT JOIN oil_images i ON o.id = i.record_id
                 $whereClause
                 GROUP BY o.id
                 ORDER BY o.date_recorded DESC";
    $stmtTable = $pdo->prepare($tableSql);
    $stmtTable->execute($params);
    $tableData = $stmtTable->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_records' => (int)($stats['total_records'] ?? 0),
            'total_liters' => (float)($stats['total_liters'] ?? 0),
            'total_cost' => (float)($stats['total_cost'] ?? 0)
        ],
        'chart' => $chartData,
        'records' => $tableData
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}