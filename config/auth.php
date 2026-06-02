<?php
// config/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirectOrJsonError('กรุณาเข้าสู่ระบบใหม่');
    }

    global $pdo;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                // User ID in session doesn't exist in DB anymore!
                session_destroy();
                redirectOrJsonError('เซสชันไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
            }
        } catch (Exception $e) {
            // Ignore DB errors here, handle elsewhere
        }
    }
}

function redirectOrJsonError($msg) {
    $isApiRequest = false;
    if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        $isApiRequest = true;
    }
    if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        $isApiRequest = true;
    }

    if ($isApiRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    header("Location: login.php");
    exit;
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $userRole = $_SESSION['role'];
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    return $userRole === $roles;
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        redirectOrJsonError('ไม่มีสิทธิ์เข้าถึง API นี้');
    }
}

function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null
    ];
}