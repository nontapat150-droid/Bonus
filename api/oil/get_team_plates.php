<?php
// api/oil/get_team_plates.php
// ดึงรายการป้ายทะเบียน (ทีม) ทั้งหมดในระบบ พร้อมจำนวนเคสงานของแต่ละทีม
// สำหรับระบบเติมน้ำมัน - แสดงป้ายทะเบียนทีมของตัวเอง + ทีมอื่น
require_once '../../config/db.php';
require_once '../../config/auth.php';
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $error['message']]);
        exit;
    }
});

requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // ดึง team_id ของผู้ใช้ปัจจุบัน
    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch();
    $myTeamId = $currentUser['team_id'] ?? null;

    $isAdmin = hasRole(['admin', 'super_admin']);

    if ($isAdmin) {
        // แอดมินดูได้ทุกทีม
        $stmt = $pdo->query("
            SELECT t.id, t.team_name, 
                   COUNT(j.id) as job_count
            FROM teams t
            LEFT JOIN jobs j ON j.team_id = t.id
            GROUP BY t.id, t.team_name
            ORDER BY t.team_name ASC
        ");
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // ช่างเทคนิคเห็นเฉพาะทีมตัวเอง
        if ($myTeamId) {
            $stmt = $pdo->prepare("
                SELECT t.id, t.team_name, 
                       COUNT(j.id) as job_count
                FROM teams t
                LEFT JOIN jobs j ON j.team_id = t.id
                WHERE t.id = ?
                GROUP BY t.id, t.team_name
            ");
            $stmt->execute([$myTeamId]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // ถ้าช่างยังไม่มีทีม ให้แสดงทั้งหมดเพื่อให้เลือกครั้งแรก
            $stmt = $pdo->query("
                SELECT t.id, t.team_name, 
                       COUNT(j.id) as job_count
                FROM teams t
                LEFT JOIN jobs j ON j.team_id = t.id
                GROUP BY t.id, t.team_name
                ORDER BY t.team_name ASC
            ");
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $teams,
        'my_team_id' => $myTeamId
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
