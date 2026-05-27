<?php
// api/dispatch/bulk_delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No IDs provided']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM jobs WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);

    echo json_encode([
        'success' => true,
        'deleted' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
