<?php
// api/users/get_teams.php
// ดึงรายการทีม (ป้ายทะเบียน) ทั้งหมดในระบบ สำหรับให้ admin เลือกเปลี่ยนทีมของช่าง
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, team_name FROM teams ORDER BY team_name ASC");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $teams]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
