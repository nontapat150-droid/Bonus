<?php
// api/start_day/submit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// 6. ตั้งเวลาโซนประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'วิธีการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. ดึง Account ของช่างคนนั้นๆ จาก Session
    $user_id = $_SESSION['user_id'];
    
    // 3. รับค่า 3 ช่อง
    $customer_name = trim($_POST['customer_name'] ?? '');
    $non_number = trim($_POST['non_number'] ?? '');
    $has_initial_fee = isset($_POST['has_initial_fee']) ? (int)$_POST['has_initial_fee'] : 0;
    
    // 5. บันทึกเวลาตามเวลาที่อัปโหลดจริงๆ ของเซิร์ฟเวอร์
    $created_at = date('Y-m-d H:i:s');

    if (empty($customer_name) || empty($non_number)) {
        throw new Exception("กรุณากรอกชื่อลูกค้าและเลข Non ให้ครบถ้วน");
    }

    // บันทึกข้อมูลลงฐานข้อมูล (ตารางใหม่)
    $stmt = $pdo->prepare("INSERT INTO start_day_records (user_id, customer_name, non_number, has_initial_fee, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $customer_name, $non_number, $has_initial_fee, $created_at]);
    $record_id = $pdo->lastInsertId();

    // 4. ระบบแนบรูปภาพ
    $upload_dir = '../../assets/uploads/start_day/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (isset($_FILES['start_day_images'])) {
        $files = $_FILES['start_day_images'];
        $count = count($files['name']);

        if ($count > 10) throw new Exception("อัปโหลดได้สูงสุด 10 รูปเท่านั้น");

        $stmtImage = $pdo->prepare("INSERT INTO start_day_images (record_id, image_path) VALUES (?, ?)");

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG เท่านั้น");
                }

                $filename = uniqid('sd_', true) . '.' . $ext;
                $target_file = $upload_dir . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                    $stmtImage->execute([$record_id, $filename]);
                } else {
                    throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ");
                }
            }
        }
    } else {
         throw new Exception("กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป");
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>