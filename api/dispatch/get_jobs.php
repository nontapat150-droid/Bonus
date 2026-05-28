<?php
// api/dispatch/get_jobs.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$username = $user['username'];
$user_id = $user['id']; // ดึงรหัส user เพื่อไปเช็คทีม

// 🌟 ดึงข้อมูล team_id ล่าสุดจากฐานข้อมูลโดยตรง (ป้องกันปัญหาระบบจำ Session เก่า)
$stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$team_id = $stmtUser->fetchColumn();

$filter_date = $_GET['date'] ?? 'all'; 
$filter_status = $_GET['status'] ?? 'active'; 

try {
    $sql = "SELECT j.*, t.team_name 
            FROM jobs j 
            LEFT JOIN teams t ON j.team_id = t.id 
            WHERE 1=1";
    $params = [];

    // 1. ตรวจสอบเงื่อนไขการดึงงานสำหรับช่าง
    if ($role === 'technician') {
        if ($team_id) {
            // โชว์งานที่อยู่ในทีมเดียวกัน หรือ งานที่ระบุทีมเป็นชื่อ username
            $sql .= " AND (j.team_id = ? OR t.team_name = ?)";
            $params[] = $team_id;
            $params[] = $username;
        } else {
            // ถ้าช่างยังไม่มีทีม โชว์แค่งานที่ระบุชื่อตรงกับ username
            $sql .= " AND t.team_name = ?";
            $params[] = $username;
        }
    }

    // 2. กรองตามวันที่ 
    if ($filter_date !== 'all' && !empty($filter_date)) {
        $sql .= " AND j.plan_arrival_date = ?";
        $params[] = $filter_date;
    }

    // 3. กรองซ่อนงานที่ถูกกด จบงาน(completed) หรือ ไม่สำเร็จ(failed) ไปแล้ว
    if ($filter_status === 'active') {
        $sql .= " AND (j.status IS NULL OR j.status NOT IN ('completed', 'failed'))";
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
        'debug' => [  // 👈 เพิ่มโหมด Debug เพื่อให้เราตรวจสอบได้ว่าช่างอยู่ทีมไหน
            'role' => $role,
            'team_id_in_db' => $team_id,
            'username' => $username
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}