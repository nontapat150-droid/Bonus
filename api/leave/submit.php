<?php
// api/leave/submit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$body = json_decode(file_get_contents('php://input'), true);
$start_date = trim($body['start_date'] ?? '');
$end_date   = trim($body['end_date'] ?? '');
$reason     = trim($body['reason'] ?? '');

if (!$start_date || !$end_date || !$reason) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

// Validate dates
$startDt = DateTime::createFromFormat('Y-m-d', $start_date);
$endDt   = DateTime::createFromFormat('Y-m-d', $end_date);
if (!$startDt || !$endDt) {
    echo json_encode(['success' => false, 'error' => 'รูปแบบวันที่ไม่ถูกต้อง']);
    exit;
}
if ($endDt < $startDt) {
    echo json_encode(['success' => false, 'error' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น']);
    exit;
}

// Calculate business days (calendar days inclusive)
$diff = $startDt->diff($endDt);
$days = $diff->days + 1;

$userId = $_SESSION['user_id'];

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, start_date, end_date, days, reason) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $start_date, $end_date, $days, $reason]);

    echo json_encode(['success' => true, 'days' => $days, 'message' => 'ส่งคำขอลางานเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
