<?php
// api/checkin/checkout.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'รูปแบบการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$type = $_POST['type'] ?? 'regular';

$table = ($type === 'ma') ? 'ma_checkins' : 'checkins';
$upload_dir = ($type === 'ma') ? '../../assets/uploads/ma_checkins/' : '../../assets/uploads/checkins/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

try {
    // Add checkout columns if they don't exist
    try {
        $existingCols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('checkout_time', $existingCols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN checkout_time DATETIME DEFAULT NULL");
        if (!in_array('checkout_image', $existingCols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN checkout_image VARCHAR(255) DEFAULT NULL");
        if (!in_array('checkout_lat', $existingCols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN checkout_lat VARCHAR(50) DEFAULT NULL");
        if (!in_array('checkout_lng', $existingCols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN checkout_lng VARCHAR(50) DEFAULT NULL");
    } catch (PDOException $e) { }

    $pdo->beginTransaction();

    if (!isset($_FILES['checkout_image']) || $_FILES['checkout_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('กรุณาอัปโหลดรูปภาพสำหรับการเลิกงาน');
    }

    $file = $_FILES['checkout_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WebP');
    }

    $filename = ($type === 'ma' ? 'ma_checkout_' : 'checkout_') . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        throw new Exception('เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ');
    }

    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    // หา record ล่าสุดของวันนี้สำหรับ user_id นี้
    $stmt = $pdo->prepare("SELECT id, checkout_time FROM $table WHERE user_id = ? AND DATE(checkin_time) = CURDATE() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $checkinRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($checkinRow) {
        if (!empty($checkinRow['checkout_time'])) {
            throw new Exception("คุณได้ทำการลงเวลาเลิกงานของวันนี้ไปแล้ว");
        }
        $checkin_id = $checkinRow['id'];
        
        // อัปเดต record เดิม
        $updateStmt = $pdo->prepare("UPDATE $table SET checkout_time = NOW(), checkout_image = ?, checkout_lat = ?, checkout_lng = ? WHERE id = ?");
        $updateStmt->execute([$filename, $lat, $lng, $checkin_id]);
    } else {
        throw new Exception('ไม่พบข้อมูลการเข้างานของวันนี้ (กรุณาเข้างานก่อนลงเวลาเลิกงาน)');
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกเวลาเลิกงานสำเร็จ',
        'checkout_time' => date('H:i')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
