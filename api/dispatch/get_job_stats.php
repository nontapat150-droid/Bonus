<?php
// api/dispatch/get_job_stats.php
// Get job completion statistics for oil system integration
// Usage: /api/dispatch/get_job_stats.php?type=daily&date=YYYY-MM-DD&tech_id=ID
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$type = $_GET['type'] ?? 'daily';  // daily, monthly, by_tech
$date = $_GET['date'] ?? date('Y-m-d');
$tech_id = $_GET['tech_id'] ?? null;

try {
    if ($type === 'daily') {
        // Get completed jobs for specific day (across all techs or specific tech)
        $query = "SELECT 
                    jl.tech_id,
                    u.firstname,
                    u.lastname,
                    COUNT(CASE WHEN jl.status = 'completed' THEN 1 END) as completed_jobs,
                    COUNT(CASE WHEN jl.status = 'failed' THEN 1 END) as failed_jobs,
                    COUNT(*) as total_records
                  FROM job_logs jl
                  LEFT JOIN users u ON jl.tech_id = u.id
                  WHERE DATE(jl.timestamp) = ?";
        
        $params = [$date];
        
        if ($tech_id) {
            $query .= " AND jl.tech_id = ?";
            $params[] = $tech_id;
        }
        
        $query .= " GROUP BY jl.tech_id, u.id ORDER BY completed_jobs DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'type' => 'daily',
            'date' => $date,
            'stats' => $stats,
            'summary' => [
                'total_completed' => array_sum(array_column($stats, 'completed_jobs')),
                'total_failed' => array_sum(array_column($stats, 'failed_jobs'))
            ]
        ]);
        
    } else if ($type === 'monthly') {
        // Get completed jobs for month
        $year_month = substr($date, 0, 7);
        
        $query = "SELECT 
                    DATE(jl.timestamp) as work_date,
                    COUNT(CASE WHEN jl.status = 'completed' THEN 1 END) as completed_jobs,
                    COUNT(CASE WHEN jl.status = 'failed' THEN 1 END) as failed_jobs
                  FROM job_logs jl
                  WHERE DATE_FORMAT(jl.timestamp, '%Y-%m') = ?";
        
        $params = [$year_month];
        
        if ($tech_id) {
            $query .= " AND jl.tech_id = ?";
            $params[] = $tech_id;
        }
        
        $query .= " GROUP BY DATE(jl.timestamp) ORDER BY work_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'type' => 'monthly',
            'month' => $year_month,
            'stats' => $stats,
            'summary' => [
                'total_completed' => array_sum(array_column($stats, 'completed_jobs')),
                'total_failed' => array_sum(array_column($stats, 'failed_jobs'))
            ]
        ]);
        
    } else if ($type === 'by_tech') {
        // Get stats per technician
        $query = "SELECT 
                    jl.tech_id,
                    u.firstname,
                    u.lastname,
                    COUNT(CASE WHEN jl.status = 'completed' THEN 1 END) as completed_jobs,
                    COUNT(CASE WHEN jl.status = 'failed' THEN 1 END) as failed_jobs,
                    MAX(jl.timestamp) as last_update
                  FROM job_logs jl
                  LEFT JOIN users u ON jl.tech_id = u.id
                  WHERE DATE(jl.timestamp) = ?
                  GROUP BY jl.tech_id, u.id
                  ORDER BY completed_jobs DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$date]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'type' => 'by_tech',
            'date' => $date,
            'stats' => $stats
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'ข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}
?>
