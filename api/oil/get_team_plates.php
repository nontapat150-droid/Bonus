<?php
// api/oil/get_team_plates.php
ob_start(); // เก็บ output ทั้งหมดก่อน เพื่อป้องกัน HTML แทรก JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';
require_once '../../config/auth.php';

// ล้าง buffer แล้วตั้งค่า header ใหม่
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Guard: ถ้า pdo ยังไม่พร้อม (db.php อาจ exit ก่อน)
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่']);
    exit;
}

requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // ดึง team_id ของผู้ใช้ปัจจุบัน
    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $myTeamId = $currentUser ? ($currentUser['team_id'] ?? null) : null;

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
        // ช่างเทคนิค
        if ($myTeamId) {
            // มีทีมแล้ว: แสดงเฉพาะทีมตัวเอง
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
            // ยังไม่มีทีม: แสดงทุกทีมให้เลือก
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

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
