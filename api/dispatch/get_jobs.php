<?php
// api/dispatch/get_jobs.php

// 🌟 1. ดักจับ Error ขั้นรุนแรง (Fatal Error) ให้พ่นกลับเป็น JSON เสมอ
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// ปิดการโชว์ Error ของ PHP แบบปกติ เพื่อไม่ให้รบกวน JSON
ini_set('display_errors', 0);

require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$username = $user['username'];
$user_id = $user['id']; 

try {
    // ดึงข้อมูล team_id ล่าสุดจากฐานข้อมูลโดยตรง
    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $team_id = $stmtUser->fetchColumn();

    $filter_date = $_GET['date'] ?? 'all'; 
    $filter_status = $_GET['status'] ?? 'active'; 

    $sql = "SELECT j.*, t.team_name 
            FROM jobs j 
            LEFT JOIN teams t ON j.team_id = t.id 
            WHERE 1=1";
    $params = [];

    // ตรวจสอบเงื่อนไขการดึงงานสำหรับช่าง
    if ($role === 'technician') {
        if ($team_id) {
            $sql .= " AND (j.team_id = ? OR t.team_name = ?)";
            $params[] = $team_id;
            $params[] = $username;
        } else {
            $sql .= " AND t.team_name = ?";
            $params[] = $username;
        }
    }

    // กรองตามวันที่ 
    if ($filter_date !== 'all' && !empty($filter_date)) {
        $sql .= " AND j.plan_arrival_date = ?";
        $params[] = $filter_date;
    }

    // ซ่อนเฉพาะงานที่ปิดสำเร็จแล้ว
    if ($filter_status === 'active') {
        $sql .= " AND (j.status IS NULL OR j.status <> 'completed')";
    }

    $sql .= " ORDER BY j.plan_arrival_date ASC, COALESCE(j.seq, 9999) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    $teams = [];
    if (hasRole(['admin', 'super_admin'])) {
        $stmtTeams = $pdo->query("SELECT * FROM teams");
        $teams = $stmtTeams->fetchAll();
    }

    echo json_encode([
        'success' => true, 
        'data' => $jobs, 
        'teams' => $teams,
        'debug' => [  
            'role' => $role,
            'team_id_in_db' => $team_id,
            'username' => $username
        ]
    ]);

} catch (PDOException $e) {
    // 🌟 2. ดักจับ Error เฉพาะเรื่องฐานข้อมูลและส่งกลับเป็น JSON
    echo json_encode([
        'success' => false, 
        'error' => 'SQL Error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // 🌟 3. ดักจับ Error ทั่วไปในระบบ
    echo json_encode([
        'success' => false, 
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}