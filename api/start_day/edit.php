<?php
// api/start_day/edit.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $error['message']]);
        exit;
    }
});

require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Session Expired: กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'technician';
$isAdmin = in_array($role, ['admin', 'super_admin']);

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสข้อมูล']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM start_day_records WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) throw new Exception("ไม่พบข้อมูลประวัตินี้ในระบบ");
    if (!$isAdmin && $record['user_id'] != $user_id) throw new Exception("คุณไม่มีสิทธิ์แก้ไขประวัติของคนอื่น");

    // 🌟 ถ้าเป็น Admin / Super Admin ถึงจะยอมให้อัปเดตข้อความ
    if ($isAdmin) {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $non_number = trim($_POST['non_number'] ?? '');
        $has_initial_fee = isset($_POST['has_initial_fee']) ? (int)$_POST['has_initial_fee'] : 0;

        if (empty($customer_name) || empty($non_number)) throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        if (mb_strlen($non_number) !== 10) throw new Exception("เลข Non ต้องมี 10 ตัวพอดี");

        // เช็คเลข Non ซ้ำ (ยกเว้นตัวเอง)
        $stmtCheck = $pdo->prepare("SELECT u.full_name FROM start_day_records r LEFT JOIN users u ON r.user_id = u.id WHERE r.non_number = ? AND r.id != ?");
        $stmtCheck->execute([$non_number, $id]);
        $duplicate = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($duplicate) {
            $owner = $duplicate['full_name'] ? $duplicate['full_name'] : 'ช่างคนอื่น';
            throw new Exception("เลข Non '{$non_number}' ถูกใช้ไปแล้ว! (บันทึกโดย: {$owner})");
        }

        $update = $pdo->prepare("UPDATE start_day_records SET customer_name = ?, non_number = ?, has_initial_fee = ? WHERE id = ?");
        $update->execute([$customer_name, $non_number, $has_initial_fee, $id]);
    }

    // 🌟 อัปเดตรูปภาพ (ทำได้ทุกคน)
    if (isset($_FILES['start_day_images'])) {
        $files = $_FILES['start_day_images'];
        $count = count($files['name']);
        
        if ($count > 0 && $files['error'][0] === UPLOAD_ERR_OK) {
            if ($count > 10) throw new Exception("อัปโหลดได้สูงสุด 10 รูป");

            // ดึงไฟล์เก่ามาลบทิ้ง
            $stmtOldImgs = $pdo->prepare("SELECT image_path FROM start_day_images WHERE record_id = ?");
            $stmtOldImgs->execute([$id]);
            $oldImgs = $stmtOldImgs->fetchAll(PDO::FETCH_COLUMN);
            $upload_dir = '../../assets/uploads/start_day/';
            
            foreach($oldImgs as $oldImg) {
                if (file_exists($upload_dir . $oldImg)) unlink($upload_dir . $oldImg);
            }
            $pdo->prepare("DELETE FROM start_day_images WHERE record_id = ?")->execute([$id]);

            // บันทึกไฟล์ใหม่
            $stmtImage = $pdo->prepare("INSERT INTO start_day_images (record_id, image_path) VALUES (?, ?)");
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG");
                    
                    $filename = uniqid('sd_', true) . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                        $stmtImage->execute([$id, $filename]);
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>