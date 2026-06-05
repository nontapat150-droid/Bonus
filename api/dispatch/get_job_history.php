<?php
// api/dispatch/get_job_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// ให้สิทธิ์เฉพาะแอดมินดูได้
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    // ดึงข้อมูลประวัติ โดยเชื่อมตาราง job_logs, jobs และ users เข้าด้วยกัน
    $sql = "SELECT l.id, l.status, l.remark, l.timestamp, 
                   j.access_no, j.customer, j.task_type, 
                   u.full_name as tech_name
            FROM job_logs l
            JOIN jobs j ON l.job_id = j.id
            JOIN users u ON l.tech_id = u.id
            ORDER BY l.timestamp DESC";
            
    $stmt = $pdo->query($sql);
    $history = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}