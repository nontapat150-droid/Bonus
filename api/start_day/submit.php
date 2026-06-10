<?php
// api/start_day/submit.php
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

date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'วิธีการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    $user_id = $_SESSION['user_id'];
    $customer_name = trim($_POST['customer_name'] ?? '');
    $non_number = trim($_POST['non_number'] ?? '');
    $has_initial_fee = isset($_POST['has_initial_fee']) ? (int)$_POST['has_initial_fee'] : 0;
    $created_at = date('Y-m-d H:i:s');

    if (empty($customer_name) || empty($non_number)) {
        throw new Exception("กรุณากรอกชื่อลูกค้าและเลข Non ให้ครบถ้วน");
    }

    // 🌟 1. ดักจับความยาวเลข Non ต้อง 10 หลักพอดี
    if (mb_strlen($non_number) !== 10) {
        throw new Exception("เลข Non ต้องมี 10 ตัวพอดี (คุณกรอกมา " . mb_strlen($non_number) . " ตัว)");
    }

    // 🌟 2. ดักจับเลข Non ซ้ำในระบบ
    $stmtCheck = $pdo->prepare("
        SELECT r.id, u.full_name 
        FROM start_day_records r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.non_number = ?
    ");
    $stmtCheck->execute([$non_number]);
    $duplicate = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($duplicate) {
        $owner = $duplicate['full_name'] ? $duplicate['full_name'] : 'ช่างคนอื่น';
        throw new Exception("เลข Non '{$non_number}' ถูกใช้ไปแล้ว! (บันทึกโดย: {$owner})");
    }

    $stmt = $pdo->prepare("INSERT INTO start_day_records (user_id, customer_name, non_number, has_initial_fee, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $customer_name, $non_number, $has_initial_fee, $created_at]);
    $record_id = $pdo->lastInsertId();

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
                    $full_url = getBaseUrl() . '/assets/uploads/start_day/' . $filename;
                    $stmtImage->execute([$record_id, $full_url]);
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

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database SQL Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>