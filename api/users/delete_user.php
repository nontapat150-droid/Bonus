<?php
// api/users/delete_user.php
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
$current_user_id = $_SESSION['user_id'];

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสผู้ใช้']);
    exit;
}

if ($id == $current_user_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'ลบผู้ใช้ออกจากระบบแล้ว']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบผู้ใช้ได้เนื่องจากมีการอ้างอิงข้อมูลในส่วนอื่น']);
}