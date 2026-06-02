<?php
// api/history/delete_oil.php
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
    $pdo->beginTransaction();

    // get image path to delete file
    $stmt = $pdo->prepare("SELECT image_path FROM oil_images WHERE record_id = ?");
    $stmt->execute([$id]);
    $imgPath = $stmt->fetchColumn();

    if ($imgPath) {
        $path = '../../assets/uploads/oil_receipts/' . $imgPath;
        if (file_exists($path) && is_file($path)) @unlink($path);
        
        $pdo->prepare("DELETE FROM oil_images WHERE record_id = ?")->execute([$id]);
    }

    $del = $pdo->prepare("DELETE FROM oil_records WHERE id = ?");
    $del->execute([$id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
