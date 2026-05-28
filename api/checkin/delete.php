<?php
// api/checkin/delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// ล็อกสิทธิ์เฉพาะ super_admin เท่านั้น
if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะ Super Admin เท่านั้นที่สามารถลบข้อมูลได้']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ ID ของข้อมูลที่ต้องการลบ']);
    exit;
}

try {
    // ดึงชื่อไฟล์รูปภาพออกมาก่อน เพื่อนำไปลบออกจาก Folder
    $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
    $stmt->execute([$id]);
    $imgPath = $stmt->fetchColumn();

    // ลบข้อมูลออกจากฐานข้อมูล
    $pdo->prepare("DELETE FROM checkins WHERE id = ?")->execute([$id]);

    // ลบไฟล์รูปภาพออกจาก Server
    if ($imgPath) {
        $file = '../../assets/uploads/checkins/' . $imgPath;
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>