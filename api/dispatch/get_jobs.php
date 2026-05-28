<?php
// api/dispatch/get_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$username = $user['username'];

// รับค่าจากฝั่ง Frontend (ถ้าไม่ส่งมา ค่าเริ่มต้นคือดูงานทั้งหมดที่ยังไม่เสร็จ)
$filter_date = $_GET['date'] ?? 'all'; // 'all' หรือ 'YYYY-MM-DD'
$filter_status = $_GET['status'] ?? 'active'; // 'active' (งานที่ต้องทำ) หรือ 'all'

try {
    $sql = "SELECT j.*, t.team_name 
            FROM jobs j 
            LEFT JOIN teams t ON j.team_id = t.id 
            WHERE 1=1";
    $params = [];

    // 1. Filter for Technician: แสดงแค่งานของทีมตัวเอง
    if ($role === 'technician') {
        $sql .= " AND t.team_name = ?";
        $params[] = $username;
    }

    // 2. กรองตามวันที่ (ถ้าเลือกวันที่เฉพาะเจาะจง)
    if ($filter_date !== 'all' && !empty($filter_date)) {
        $sql .= " AND j.plan_arrival_date = ?";
        $params[] = $filter_date;
    }

    // 3. กรองตามสถานะงาน (เพื่อให้งานที่ทำเสร็จแล้ว หรือยกเลิกไปแล้ว ไม่มาปนในหน้ารายการปกติ)
    if ($filter_status === 'active') {
        $sql .= " AND (j.status IS NULL OR j.status NOT IN ('completed', 'failed'))";
    }

    // จัดเรียงตามวันที่เข้าทำงานก่อน แล้วค่อยเรียงตามลำดับ(seq)
    $sql .= " ORDER BY j.plan_arrival_date ASC, COALESCE(j.seq, 9999) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    // ดึงข้อมูลทีมสำหรับ Admin UI
    $teams = [];
    if (hasRole(['admin', 'super_admin'])) {
        $stmtTeams = $pdo->query("SELECT * FROM teams");
        $teams = $stmtTeams->fetchAll();
    }

    echo json_encode(['success' => true, 'data' => $jobs, 'teams' => $teams]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}