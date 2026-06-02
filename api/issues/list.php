<?php
// api/issues/list.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            i.id, 
            i.message, 
            i.image_url, 
            i.status, 
            i.created_at,
            u.full_name,
            u.role
        FROM issue_reports i
        LEFT JOIN users u ON i.user_id = u.id
        ORDER BY i.created_at DESC
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $reports]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
