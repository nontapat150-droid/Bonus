<?php
// api/users/save_user.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$username = trim($input['username'] ?? '');
$full_name = trim($input['full_name'] ?? '');
$role = $input['role'] ?? 'technician';
$password = $input['password'] ?? '';

if (empty($username) || empty($full_name)) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

try {
    if ($id) {
        // Update
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$username, $full_name, $role, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $full_name, $role, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'ปรับปรุงข้อมูลผู้ใช้สำเร็จ']);
    } else {
        // Insert
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'กรุณากำหนดรหัสผ่านสำหรับผู้ใช้ใหม่']);
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, role, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $full_name, $role, $hash]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มผู้ใช้ใหม่สำเร็จ']);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}