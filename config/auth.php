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

function loadUserRolesIntoSession(PDO $pdo, $userId, $primaryRole = null) {
    $roles = [];
    try {
        $stmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // ตาราง user_roles อาจยังไม่มี
    }

    if ($primaryRole && !in_array($primaryRole, $roles, true)) {
        array_unshift($roles, $primaryRole);
    }

    if (empty($roles) && $primaryRole) {
        $roles = [$primaryRole];
    }
    if (empty($roles)) {
        $roles = ['technician'];
    }

    $_SESSION['roles'] = array_values(array_unique($roles));
    $_SESSION['role'] = $_SESSION['roles'][0];
}

function getUserRoles() {
    if (!isLoggedIn()) return [];
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles']) && !empty($_SESSION['roles'])) {
        return $_SESSION['roles'];
    }
    return [$_SESSION['role'] ?? 'technician'];
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $userRoles = getUserRoles();
    if (is_array($roles)) {
        return count(array_intersect($userRoles, $roles)) > 0;
    }
    return in_array($roles, $userRoles, true);
}

function isMaTechnicianOnly() {
    $roles = getUserRoles();
    $workRoles = array_values(array_intersect($roles, ['technician', 'ma_technician', 'admin', 'super_admin', 'intern', 'sales']));
    return count($workRoles) === 1 && in_array('ma_technician', $workRoles, true);
}

function isSalesOnly() {
    $roles = getUserRoles();
    $workRoles = array_values(array_intersect($roles, ['technician', 'ma_technician', 'admin', 'super_admin', 'intern', 'sales']));
    return count($workRoles) === 1 && in_array('sales', $workRoles, true);
}

function isInternOnly() {
    $roles = getUserRoles();
    $workRoles = array_values(array_intersect($roles, ['technician', 'ma_technician', 'admin', 'super_admin', 'intern', 'sales']));
    return count($workRoles) === 1 && in_array('intern', $workRoles, true);
}

/** ดู/จัดการงาน Office (ติดตั้ง) — แยกจาก MA */
function canViewDispatchOffice() {
    if (isMaTechnicianOnly()) return false;
    return hasRole(['admin', 'super_admin', 'technician']);
}

/** ดู/จัดการงาน MA — แยกจาก Office */
function canViewDispatchMA() {
    return hasRole(['admin', 'super_admin', 'ma_technician']);
}

function hasBothDispatchRoles() {
    return canViewDispatchOffice() && canViewDispatchMA();
}

/** @deprecated ใช้ canViewDispatchOffice() */
function canAccessDispatchInstall() {
    return canViewDispatchOffice();
}

/** เข้าหน้า dispatch ได้ถ้ามีสิทธิ์อย่างน้อยหนึ่งระบบ */
function canAccessDispatch() {
    return canViewDispatchOffice() || canViewDispatchMA();
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
        'roles' => getUserRoles(),
        'full_name' => $_SESSION['full_name'] ?? null
    ];
}

function saveUserRoles(PDO $pdo, $userId, array $roles) {
    $valid = ['super_admin', 'admin', 'technician', 'ma_technician', 'sales', 'intern'];
    $roles = array_values(array_unique(array_intersect($roles, $valid)));
    if (empty($roles)) {
        $roles = ['technician'];
    }

    try {
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
        $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?)");
        foreach ($roles as $r) {
            $ins->execute([$userId, $r]);
        }
    } catch (Exception $e) {
        // fallback: ใช้ users.role เท่านั้น
    }

    $priority = ['super_admin', 'admin', 'technician', 'ma_technician', 'sales', 'intern'];
    $primary = 'technician';
    foreach ($priority as $p) {
        if (in_array($p, $roles, true)) {
            $primary = $p;
            break;
        }
    }
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$primary, $userId]);
    return $primary;
}
