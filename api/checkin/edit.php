<?php
// api/checkin/edit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$new_time = $data['checkin_time'] ?? null;

if (!$id || !$new_time) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // ถ้าเป็นแค่ช่าง (technician) ให้เช็กก่อนว่าเป็นข้อมูลของตัวเองหรือเปล่า ป้องกันการแก้ของคนอื่น
    if (hasRole('technician')) {
        $stmt = $pdo->prepare("SELECT user_id FROM checkins WHERE id = ?");
        $stmt->execute([$id]);
        $owner_id = $stmt->fetchColumn();
        
        if ($owner_id != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'คุณสามารถแก้ไขได้เฉพาะข้อมูลเช็คอินของตนเองเท่านั้น']);
            exit;
        }
    }

    // แปลง Format เวลาให้ตรงกับ MySQL
    $formatted_time = date('Y-m-d H:i:s', strtotime($new_time));

    // อัปเดตเวลา
    $pdo->prepare("UPDATE checkins SET checkin_time = ? WHERE id = ?")->execute([$formatted_time, $id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>