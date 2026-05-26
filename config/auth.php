<?php
// config/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
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
        // Redirect to a safe page if unauthorized
        header("Location: index.php?error=unauthorized");
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
