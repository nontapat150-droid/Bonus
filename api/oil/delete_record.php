<?php
// api/oil/delete_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? $input['id'] ?? null;

if (!$ids) {
    echo json_encode(['success' => false, 'error' => 'ต้องระบุ id ของรายการที่ต้องการลบ']);
    exit;
}

if (!is_array($ids)) $ids = [$ids];
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function($v){ return $v > 0; });

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'id ไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch image paths to delete files
    $in = implode(',', array_fill(0, count($ids), '?'));
    $getImgs = $pdo->prepare("SELECT image_path FROM oil_images WHERE record_id IN ($in)");
    $getImgs->execute($ids);
    $paths = $getImgs->fetchAll(PDO::FETCH_COLUMN);

    // Delete image rows
    $delImgs = $pdo->prepare("DELETE FROM oil_images WHERE record_id IN ($in)");
    $delImgs->execute($ids);

    // Delete records
    $del = $pdo->prepare("DELETE FROM oil_records WHERE id IN ($in)");
    $del->execute($ids);

    $pdo->commit();

    // Remove files from disk after commit
    foreach ($paths as $p) {
        $full = __DIR__ . '/../../' . ltrim($p, '/\\');
        if (file_exists($full)) {
            @unlink($full);
        }
    }

    echo json_encode(['success' => true, 'deleted' => count($ids)]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // log
    try {
        $logDir = __DIR__ . '/../../uploads/import_logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $errFile = $logDir . '/delete_exception_' . date('Ymd_His') . '.log';
        $details = ['message' => $e->getMessage(), 'code' => $e->getCode(), 'trace' => $e->getTraceAsString()];
        file_put_contents($errFile, json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $le) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'log' => isset($errFile) ? basename($errFile) : null]);
}
