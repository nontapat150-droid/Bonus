<?php
// api/oil/get_team_plates.php
// ดึงรายการป้ายทะเบียน (ทีม) ทั้งหมดในระบบ พร้อมจำนวนเคสงานของแต่ละทีม
// สำหรับระบบเติมน้ำมัน - แสดงป้ายทะเบียนทีมของตัวเอง + ทีมอื่น
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // ดึง team_id ของผู้ใช้ปัจจุบัน
    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch();
    $myTeamId = $currentUser['team_id'] ?? null;

    // ดึงรายการทีมทั้งหมด พร้อมนับจำนวนเคสงาน
    $stmt = $pdo->query("
        SELECT t.id, t.team_name, 
               COUNT(j.id) as job_count
        FROM teams t
        LEFT JOIN jobs j ON j.team_id = t.id
        GROUP BY t.id, t.team_name
        ORDER BY t.team_name ASC
    ");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $teams,
        'my_team_id' => $myTeamId
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
