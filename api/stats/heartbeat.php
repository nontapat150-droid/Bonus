<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Update last_active to current time
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    $response = ['success' => true];
    
    // If admin, calculate how many users are active within the last 1 minute
    if (hasRole(['admin', 'super_admin'])) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL 1 MINUTE");
        $response['online_users'] = $stmt->fetchColumn();
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
