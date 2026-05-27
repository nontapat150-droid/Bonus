<?php
// api/checkin/submit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'รูปแบบการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

$user_id = $_SESSION['user_id'];
$upload_dir = '../../assets/uploads/checkins/';

// ตรวจสอบและสร้างโฟลเดอร์หากยังไม่มี
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

try {
    $pdo->beginTransaction();

    if (!isset($_FILES['checkin_image']) || $_FILES['checkin_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("กรุณาอัปโหลดรูปภาพสำหรับการเช็คอิน");
    }

    $file = $_FILES['checkin_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // ตรวจสอบนามสกุลไฟล์
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG เท่านั้น");
    }

    // ตั้งชื่อไฟล์ใหม่ให้ไม่ซ้ำกัน
    $filename = 'checkin_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    $target_file = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // บันทึกลงฐานข้อมูล (checkin_time จะถูกบันทึกอัตโนมัติตาม CURRENT_TIMESTAMP)
        $stmt = $pdo->prepare("INSERT INTO checkins (user_id, image_path) VALUES (?, ?)");
        $stmt->execute([$user_id, $filename]);
    } else {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'เช็คอินสำเร็จเวลา ' . date('H:i')]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}