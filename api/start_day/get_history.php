<?php
// api/start_day/get_history.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // ดึงเฉพาะข้อมูลของตัวเอง (WHERE user_id = ?) เรียงจากใหม่ไปเก่า
    $sql = "
        SELECT r.*, 
        (SELECT image_path FROM start_day_images i WHERE i.record_id = r.id LIMIT 1) as evidence_image
        FROM start_day_records r
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // จัดรูปแบบวันที่และเวลาให้สวยงามพร้อมแสดงผล
    $formatted_records = array_map(function($r) {
        $r['date_str'] = date('d/m/Y', strtotime($r['created_at']));
        $r['time_str'] = date('H:i', strtotime($r['created_at']));
        return $r;
    }, $records);

    echo json_encode(['success' => true, 'data' => $formatted_records]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>