<?php
// api/work_records/save.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('intern')) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$record_date = $input['record_date'] ?? date('Y-m-d');
$title = trim($input['title'] ?? '');
$content = trim($input['content'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่องาน']);
    exit;
}

try {
    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE work_records SET record_date = ?, title = ?, content = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$record_date, $title, $content, $id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'อัปเดตรายงานสำเร็จ']);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO work_records (user_id, record_date, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $record_date, $title, $content]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มรายงานสำเร็จ']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
