<?php
// api/customer/search_info.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$search = $_GET['q'] ?? '';

if (empty(trim($search))) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุคำค้นหา']);
    exit;
}

$search = trim($search);

try {
    // 1. ค้นหาในตาราง jobs
    $stmtJobs = $pdo->prepare("
        SELECT j.*, t.team_name 
        FROM jobs j 
        LEFT JOIN teams t ON j.team_id = t.id 
        WHERE j.access_no LIKE ? OR j.customer LIKE ? OR j.phone LIKE ?
        ORDER BY j.created_at DESC
    ");
    $searchTerm = "%$search%";
    $stmtJobs->execute([$searchTerm, $searchTerm, $searchTerm]);
    $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

    // 2. ค้นหาในตาราง start_day_records (ค่าแรกเข้า)
    $stmtStartDay = $pdo->prepare("
        SELECT s.*, u.full_name as tech_name 
        FROM start_day_records s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.non_number LIKE ? OR s.customer_name LIKE ?
        ORDER BY s.created_at DESC
    ");
    $stmtStartDay->execute([$searchTerm, $searchTerm]);
    $startDays = $stmtStartDay->fetchAll(PDO::FETCH_ASSOC);

    // Grouping by access_no / non_number
    // เราจะใช้ Access No / NON เป็น key หลักในการแสดง Card
    
    $customers = [];

    // Process Jobs
    foreach ($jobs as $job) {
        $id = $job['access_no'] ?: 'UNKNOWN_'.uniqid();
        if (!isset($customers[$id])) {
            $customers[$id] = [
                'id' => $id,
                'customer_name' => $job['customer'],
                'phone' => $job['phone'],
                'address' => $job['address'],
                'package' => $job['package'],
                'product' => $job['product'],
                'jobs' => [],
                'start_days' => []
            ];
        }
        
        // ดึงประวัติการปิดงาน (ถ้ามี)
        $stmtClose = $pdo->prepare("SELECT * FROM job_close_3bb WHERE job_id = ? ORDER BY created_at DESC");
        $stmtClose->execute([$job['id']]);
        $closes = $stmtClose->fetchAll(PDO::FETCH_ASSOC);

        // ดึง Logs
        $stmtLogs = $pdo->prepare("SELECT jl.*, u.full_name, t.team_name FROM job_logs jl LEFT JOIN users u ON jl.user_id = u.id LEFT JOIN teams t ON u.team_id = t.id WHERE jl.job_id = ? ORDER BY jl.created_at DESC");
        $stmtLogs->execute([$job['id']]);
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $job['closes'] = $closes;
        $job['logs'] = $logs;
        $customers[$id]['jobs'][] = $job;
    }

    // Process Start Days
    foreach ($startDays as $sd) {
        $id = $sd['non_number'] ?: 'UNKNOWN_'.uniqid();
        if (!isset($customers[$id])) {
            $customers[$id] = [
                'id' => $id,
                'customer_name' => $sd['customer_name'],
                'phone' => '',
                'address' => '',
                'package' => '',
                'product' => '',
                'jobs' => [],
                'start_days' => []
            ];
        }
        
        // ดึงรูปภาพค่าแรกเข้า
        $stmtImg = $pdo->prepare("SELECT image_path FROM start_day_images WHERE record_id = ?");
        $stmtImg->execute([$sd['id']]);
        $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
        
        $sd['images'] = $images;
        $customers[$id]['start_days'][] = $sd;
    }

    // Convert to indexed array
    $results = array_values($customers);

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
