<?php
// api/checkin/edit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$id = null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'multipart/form-data') !== false) {
    $id = $_POST['id'] ?? null;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
}

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // เช็คสิทธิ์ความเป็นเจ้าของ
    $stmt = $pdo->prepare("SELECT user_id FROM checkins WHERE id = ?");
    $stmt->execute([$id]);
    $owner_id = $stmt->fetchColumn();
    
    // ถ้าไม่ใช่แอดมิน/ซูเปอร์แอดมิน ให้แก้ได้แค่ของตัวเอง
    if (!hasRole(['super_admin', 'admin']) && $owner_id != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'คุณสามารถแก้ไขได้เฉพาะข้อมูลเช็คอินของตนเองเท่านั้น']);
        exit;
    }

    $upload_dir = '../../assets/uploads/checkins/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // หากมีการอัปโหลดรูปภาพใหม่มา
    if (isset($_FILES['checkin_image']) && $_FILES['checkin_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['checkin_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG เท่านั้น');
        }

        $filename = 'checkin_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            throw new Exception('เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ');
        }

        // ดึงรูปเก่ามาลบทิ้งเพื่อประหยัดพื้นที่
        $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage) {
            $oldFile = $upload_dir . $oldImage;
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        // อัปเดตเฉพาะรูปภาพในฐานข้อมูล (ลบเรื่องการอัปเดตเวลาออกแล้ว)
        $sql = 'UPDATE checkins SET image_path = ? WHERE id = ?';
        $pdo->prepare($sql)->execute([$filename, $id]);

        echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'error' => 'กรุณาเลือกรูปภาพใหม่เพื่ออัปเดต']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>