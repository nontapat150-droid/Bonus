<?php
// api/checkin/delete_image.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// ล็อกสิทธิ์เฉพาะ super_admin เท่านั้น
if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะ Super Admin เท่านั้นที่สามารถลบรูปภาพได้']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ ID ของข้อมูล']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
    $stmt->execute([$id]);
    $imgPath = $stmt->fetchColumn();

    if ($imgPath) {
        $file = '../../assets/uploads/checkins/' . $imgPath;
        if (file_exists($file)) {
            @unlink($file);
        }
        
        // เคลียร์ค่ารูปในฐานข้อมูลเป็น NULL 
        $pdo->prepare("UPDATE checkins SET image_path = NULL WHERE id = ?")->execute([$id]);
    }

    echo json_encode(['success' => true, 'message' => 'ลบรูปภาพสำเร็จ']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>