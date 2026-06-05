<?php
// api/inventory/check_sn.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$sn = $_GET['sn'] ?? '';

if (!$sn) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบหมายเลข SN']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmt->execute([$sn]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'status' => 'duplicate']);
    } else {
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
