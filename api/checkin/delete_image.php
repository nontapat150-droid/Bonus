<?php
// api/checkin/delete_image.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะ Super Admin เท่านั้นที่สามารถลบรูปภาพได้']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ ID ของข้อมูลที่ต้องการลบรูปภาพ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
    $stmt->execute([$id]);
    $imagePath = $stmt->fetchColumn();

    if (!$imagePath) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรูปภาพสำหรับเช็คอินนี้']);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE checkins SET image_path = '' WHERE id = ?")->execute([$id]);

    $file = '../../assets/uploads/checkins/' . $imagePath;
    if (file_exists($file)) {
        @unlink($file);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
