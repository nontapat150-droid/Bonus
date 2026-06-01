<?php
// config/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $isApiRequest = false;
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            $isApiRequest = true;
        }
        if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $isApiRequest = true;
        }

        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบใหม่']);
            exit;
        }

        header("Location: login.php");
        exit;
    }
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
        $isApiRequest = false;
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            $isApiRequest = true;
        }
        if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $isApiRequest = true;
        }

        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง API นี้']);
            exit;
        }

        // Redirect to a safe page if unauthorized
        header("Location: index.php?error=ไม่มีสิทธิ์เข้าถึง");
        exit;
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