<?php
// api/leave/get_all_leaves.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$status = $_GET['status'] ?? 'all';

try {
    $where = '';
    $params = [];
    if ($status !== 'all') {
        $where = 'WHERE lr.status = ?';
        $params[] = $status;
    }

    $stmt = $pdo->prepare("
        SELECT lr.*, u.full_name, u.role,
               u2.full_name AS reviewed_by_name
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        LEFT JOIN users u2 ON lr.reviewed_by = u2.id
        $where
        ORDER BY lr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count pending
    $countStmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pendingCount = (int)$countStmt->fetchColumn();

    echo json_encode(['success' => true, 'data' => $leaves, 'pending_count' => $pendingCount]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
