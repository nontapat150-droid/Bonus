<?php
// api/users/save_user.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

ensureMaJobSchema($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$username = trim($input['username'] ?? '');
$full_name = trim($input['full_name'] ?? '');
$role = $input['role'] ?? 'technician';
$roles = $input['roles'] ?? null;
$password = $input['password'] ?? '';
$team_id = $input['team_id'] ?? null;
$allow_late_time = $input['allow_late_time'] ?? '08:30';
$days_off = $input['days_off'] ?? [];
$days_off_json = json_encode($days_off);

if (is_array($roles) && !empty($roles)) {
    $role = $roles[0];
} elseif (!is_array($roles)) {
    $roles = [$role];
}

if (empty($team_id) || $team_id === 'none' || $team_id === '') {
    $team_id = null;
} else {
    $team_id = (int)$team_id;
}

$needsLateTime = count(array_intersect($roles, ['sales', 'technician', 'ma_technician', 'intern'])) > 0;
if (!$needsLateTime) {
    $allow_late_time = '08:30';
}

if (empty($username) || empty($full_name)) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

try {
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'technician', 'ma_technician', 'sales', 'intern') NOT NULL DEFAULT 'technician'");
        // แก้ไข Collation ให้แยกแยะวรรณยุกต์ (เช่น ปอ กับ ป๋อ จะถือว่าเป็นคนละชื่อ)
        $pdo->exec("ALTER TABLE users MODIFY COLUMN username VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN full_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN days_off VARCHAR(255) DEFAULT NULL AFTER allow_late_time");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    } catch (Exception $e) {}

    if ($id) {
        $primaryRole = saveUserRoles($pdo, (int)$id, $roles);

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, password_hash = ?, team_id = ?, allow_late_time = ?, days_off = ? WHERE id = ?");
            $stmt->execute([$username, $full_name, $primaryRole, $hash, $team_id, $allow_late_time, $days_off_json, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, team_id = ?, allow_late_time = ?, days_off = ? WHERE id = ?");
            $stmt->execute([$username, $full_name, $primaryRole, $team_id, $allow_late_time, $days_off_json, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'ปรับปรุงข้อมูลผู้ใช้สำเร็จ']);
    } else {
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'กรุณากำหนดรหัสผ่านสำหรับผู้ใช้ใหม่']);
            exit;
        }

        $primaryRole = $roles[0] ?? 'technician';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, role, password_hash, team_id, allow_late_time, days_off) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $full_name, $primaryRole, $hash, $team_id, $allow_late_time, $days_off_json]);
        $newId = (int)$pdo->lastInsertId();
        saveUserRoles($pdo, $newId, $roles);
        echo json_encode(['success' => true, 'message' => 'เพิ่มผู้ใช้ใหม่สำเร็จ']);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
