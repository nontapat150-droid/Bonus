<?php
// api/oil/get_records.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$license_plate = $_GET['license_plate'] ?? 'all';

$params = [];
$whereClause = "WHERE 1=1";

if ($start_date && $end_date) {
    $whereClause .= " AND DATE(o.date_recorded) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($license_plate !== 'all' && !empty($license_plate)) {
    $whereClause .= " AND o.license_plate = ?";
    $params[] = $license_plate;
}

try {
    ensureTeamOilCasesTable($pdo);

    $statsSql = "SELECT
                    COUNT(o.id) as total_records,
                    SUM(o.liters) as total_liters,
                    SUM(o.total_price) as total_cost
                 FROM oil_records o
                 $whereClause";
    $stmtStats = $pdo->prepare($statsSql);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch();

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
                 ORDER BY o.date_recorded ASC, o.id ASC";

    $stmtTable = $pdo->prepare($tableSql);
    $stmtTable->execute($params);
    $rawRecords = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

    $processed_records = [];
    $prevMileageByVehicle = [];
    $prevLitersByVehicle = [];

    foreach ($rawRecords as $row) {
        $currentMileage = isset($row['mileage']) ? (int)$row['mileage'] : 0;
        $vehicleKey = !empty($row['license_plate']) ? $row['license_plate'] : ($row['team_name'] ?? 'unknown');

        $distance = 0;
        if (isset($prevMileageByVehicle[$vehicleKey]) && $currentMileage > $prevMileageByVehicle[$vehicleKey]) {
            $distance = $currentMileage - $prevMileageByVehicle[$vehicleKey];
        } else {
            $distance = (float)$row['distance'];
        }

        $currentLiters = 0.0;
        if (isset($row['price_per_liter']) && (float)$row['price_per_liter'] > 0) {
            $currentLiters = (float)$row['total_price'] / (float)$row['price_per_liter'];
        }
        if ($currentLiters <= 0) {
            $currentLiters = isset($row['liters']) ? (float)$row['liters'] : 0.0;
        }

        $job_count = (int)$row['stored_job_count'];

        $cost_per_job = $job_count > 0 ? ($row['total_price'] / $job_count) : 0;
        $cost_per_km = $distance > 0 ? ($row['total_price'] / $distance) : 0;

        $previousLiters = isset($prevLitersByVehicle[$vehicleKey]) ? $prevLitersByVehicle[$vehicleKey] : 0;
        $km_per_liter = 0;
        if ($distance > 0 && $previousLiters > 0) {
            $km_per_liter = $distance / $previousLiters;
        }

        $row['distance'] = $distance;
        $row['liters'] = round($currentLiters, 2);
        $row['job_count'] = $job_count;
        $row['cost_per_job'] = round($cost_per_job, 2);
        $row['cost_per_km'] = round($cost_per_km, 2);
        $row['km_per_liter'] = round($km_per_liter, 2);

        $processed_records[] = $row;

        $prevMileageByVehicle[$vehicleKey] = $currentMileage;
        $prevLitersByVehicle[$vehicleKey] = $currentLiters;
    }

    $processed_records = array_reverse($processed_records);

    $teamFilter = ($license_plate !== 'all' && !empty($license_plate)) ? $license_plate : null;
    $total_jobs_period = sumTeamCasesInDateRange($pdo, $start_date, $end_date, $teamFilter);

    // Team-month rollup for avg cost per case in filtered period
    $teamMonthStats = [];
    $ymOil = sqlYearMonth('o.date_recorded');
    $statsSql2 = "SELECT
                    t.team_name,
                    {$ymOil} AS ym_label,
                    SUM(o.total_price) AS month_fuel_cost,
                    SUM(o.distance) AS month_distance,
                    SUM(o.liters) AS month_liters
                  FROM oil_records o
                  INNER JOIN teams t ON t.team_name = o.license_plate
                  $whereClause
                  GROUP BY t.id, t.team_name, {$ymOil}";
    $stmtTeamMonth = $pdo->prepare($statsSql2);
    $stmtTeamMonth->execute($params);
    foreach ($stmtTeamMonth->fetchAll(PDO::FETCH_ASSOC) as $tm) {
        $tid = getTeamIdByName($pdo, $tm['team_name']);
        $cases = $tid ? getTeamMonthlyCaseCount($pdo, $tid, $tm['ym_label'], false) : 0;
        $monthLiters = (float)$tm['month_liters'];
        $monthDist = (float)$tm['month_distance'];
        $monthCost = (float)$tm['month_fuel_cost'];
        $teamMonthStats[] = [
            'team_name' => $tm['team_name'],
            'year_month' => $tm['ym_label'],
            'completed_cases' => $cases,
            'month_fuel_cost' => round($monthCost, 2),
            'month_distance' => round($monthDist, 2),
            'month_liters' => round($monthLiters, 2),
            'avg_cost_per_case' => $cases > 0 ? round($monthCost / $cases, 2) : 0,
            'avg_km_per_liter' => $monthLiters > 0 ? round($monthDist / $monthLiters, 2) : 0,
            'avg_price_per_liter' => $monthLiters > 0 ? round($monthCost / $monthLiters, 2) : 0,
        ];
    }

    $ymRecord = sqlYearMonth('date_recorded');
    $currentYear = (int)date('Y');
    $monthlySql = "SELECT
                    ym.month_label,
                    COALESCE(oil.monthly_cost, 0) AS monthly_cost,
                    COALESCE(oil.monthly_liters, 0) AS monthly_liters,
                    COALESCE(cases.monthly_jobs, 0) AS monthly_jobs
                 FROM (
                    SELECT {$ymRecord} AS month_label
                    FROM oil_records
                    WHERE YEAR(date_recorded) = {$currentYear}
                    UNION
                    SELECT `year_month` AS month_label
                    FROM team_oil_cases
                    WHERE `year_month` >= '{$currentYear}-01' AND `year_month` <= '{$currentYear}-12'
                 ) ym
                 LEFT JOIN (
                    SELECT {$ymRecord} AS month_label,
                           SUM(total_price) AS monthly_cost,
                           SUM(liters) AS monthly_liters
                    FROM oil_records
                    WHERE YEAR(date_recorded) = {$currentYear}
                    GROUP BY {$ymRecord}
                 ) oil ON oil.month_label = ym.month_label
                 LEFT JOIN (
                    SELECT `year_month` AS month_label,
                           SUM(case_count) AS monthly_jobs
                    FROM team_oil_cases
                    WHERE `year_month` >= '{$currentYear}-01' AND `year_month` <= '{$currentYear}-12'
                    GROUP BY `year_month`
                 ) cases ON cases.month_label = ym.month_label
                 ORDER BY ym.month_label ASC";
    $stmtMonthly = $pdo->prepare($monthlySql);
    $stmtMonthly->execute();
    $monthlySummary = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_records' => (int)($stats['total_records'] ?? 0),
            'total_liters' => (float)($stats['total_liters'] ?? 0),
            'total_cost' => (float)($stats['total_cost'] ?? 0),
            'total_jobs' => $total_jobs_period,
            'avg_cost_per_case' => $total_jobs_period > 0
                ? round((float)($stats['total_cost'] ?? 0) / $total_jobs_period, 2)
                : 0,
        ],
        'chart' => $chartData,
        'monthly' => $monthlySummary,
        'team_month_stats' => $teamMonthStats,
        'records' => $processed_records
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
