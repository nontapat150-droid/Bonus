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
        
        // 1. ดึงข้อมูลเวลาเข้างานของ user คนนี้ (allow_late_time)
        $stmtUser = $pdo->prepare("SELECT allow_late_time FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $allow_late_time = $stmtUser->fetchColumn();

        // ค่าเริ่มต้นหากไม่ได้ตั้งไว้ (เผื่อไว้)
        if (!$allow_late_time) {
            $allow_late_time = '08:30:00'; 
        }

        // 2. คำนวณสถานะสาย
        $current_time = date('H:i:s');
        $is_late = ($current_time > $allow_late_time) ? 1 : 0;

        // 3. บันทึกลงฐานข้อมูล (เพิ่ม is_late)
        $stmt = $pdo->prepare("INSERT INTO checkins (user_id, image_path, is_late) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $filename, $is_late]);
    } else {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'เช็คอินสำเร็จเวลา ' . date('H:i')]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>