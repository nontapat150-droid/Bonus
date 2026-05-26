<?php
// api/dispatch/teams/save_team.php
require_once '../../../config/db.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$team_name = trim($input['team_name'] ?? '');

if (empty($team_name)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุชื่อทีม']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO teams (team_name) VALUES (?)");
    $stmt->execute([$team_name]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'ชื่อทีมนี้มีอยู่แล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}