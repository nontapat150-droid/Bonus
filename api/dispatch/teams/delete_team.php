<?php
// api/dispatch/teams/delete_team.php
require_once '../../../config/db.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสทีม']);
    exit;
}

try {
    // Unassign jobs before deleting team
    $stmtUpdate = $pdo->prepare("UPDATE jobs SET team_id = NULL WHERE team_id = ?");
    $stmtUpdate->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}