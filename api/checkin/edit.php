<?php
// api/checkin/edit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$id = null;
$new_time = null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'multipart/form-data') !== false) {
    $id = $_POST['id'] ?? null;
    $new_time = $_POST['checkin_time'] ?? null;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $new_time = $data['checkin_time'] ?? null;
}

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // 1. เช็คสิทธิ์ความเป็นเจ้าของ
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

    $updateFields = [];
    $params = [];

    // หากมีการแก้ไขเวลา ให้คำนวณสถานะสาย (is_late) ใหม่อีกครั้ง
    if ($new_time) {
        $formatted_time = date('Y-m-d H:i:s', strtotime($new_time));
        $updateFields[] = 'checkin_time = ?';
        $params[] = $formatted_time;

        $stmtUser = $pdo->prepare("SELECT allow_late_time FROM users WHERE id = ?");
        $stmtUser->execute([$owner_id]);
        $allow_late_time = $stmtUser->fetchColumn() ?: '08:30:00';

        $check_time_only = date('H:i:s', strtotime($new_time));
        $is_late = ($check_time_only > $allow_late_time) ? 1 : 0;

        $updateFields[] = 'is_late = ?';
        $params[] = $is_late;
    }

    // หากมีการอัปโหลดรูปภาพใหม่มาด้วย
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

        // ดึงรูปเก่ามาลบทิ้งเพื่อประหยัดพื้นที่เซิร์ฟเวอร์
        $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage) {
            $oldFile = $upload_dir . $oldImage;
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $updateFields[] = 'image_path = ?';
        $params[] = $filename;
    }

    if (count($updateFields) > 0) {
        $params[] = $id;
        $sql = 'UPDATE checkins SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute($params);
    }

    echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>