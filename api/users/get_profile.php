<?php
// api/users/get_profile.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$userSession = getCurrentUser();

try {
    // Check and add profile_image column if it doesn't exist (fail-safe)
    try {
        $chk = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_image'");
        $chk->execute();
        if (!$chk->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `profile_image` VARCHAR(255) DEFAULT NULL AFTER `full_name`");
        }
    } catch (Exception $e) {
        // Ignore column creation error
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.full_name, u.role, u.status, u.profile_image, t.team_name as team_name
            FROM users u
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userSession['id']]);
        $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback if profile_image column is missing and migration failed
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.full_name, u.role, u.status, t.team_name as team_name
            FROM users u
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userSession['id']]);
        $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        $userProfile['profile_image'] = null; // Default to null
    }

    if (!$userProfile) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $userProfile]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
