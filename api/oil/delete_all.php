<?php
// api/oil/delete_all.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    // Collect receipt image paths so we can delete files from disk later
    $paths = $pdo->query('SELECT image_path FROM oil_images')->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM oil_images');
    $pdo->exec('DELETE FROM oil_records');
    $pdo->commit();

    // Remove files from disk after successful DB delete
    foreach ($paths as $p) {
        $full = __DIR__ . '/../../' . ltrim($p, '/\\');
        if (file_exists($full)) {
            @unlink($full);
        }
    }

    // Reset auto increment values to keep IDs tidy
    try {
        $pdo->exec('ALTER TABLE oil_records AUTO_INCREMENT = 1');
        $pdo->exec('ALTER TABLE oil_images AUTO_INCREMENT = 1');
    } catch (Exception $e) {
        // ignore if reset fails due to permission or engine restrictions
    }

    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลน้ำมันทั้งหมดเรียบร้อยแล้ว']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $logDir = __DIR__ . '/../../uploads/import_logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $errFile = $logDir . '/oil_delete_all_exception_' . date('Ymd_His') . '.log';
        $details = ['message' => $e->getMessage(), 'code' => $e->getCode(), 'trace' => $e->getTraceAsString()];
        file_put_contents($errFile, json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $le) {
        // ignore logging failures
    }
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage(), 'log' => isset($errFile) ? basename($errFile) : null]);
}
