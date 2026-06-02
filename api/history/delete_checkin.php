<?php
// api/history/delete_checkin.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

try {
    // get image path to delete file
    $stmt = $pdo->prepare("SELECT image_path FROM checkins WHERE id = ?");
    $stmt->execute([$id]);
    $imgPath = $stmt->fetchColumn();

    if ($imgPath) {
        $path = '../../assets/uploads/checkins/' . $imgPath;
        if (file_exists($path) && is_file($path)) @unlink($path);
    }

    $del = $pdo->prepare("DELETE FROM checkins WHERE id = ?");
    $del->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
