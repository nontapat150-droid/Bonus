<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$qty = floatval($input['qty'] ?? 0);
$unit = trim($input['unit'] ?? 'ชิ้น');

if (!$name || $qty <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$user = getCurrentUser();
$admin_id = $user['id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, qty FROM inventory_consumable WHERE product_name = ?");
    $stmt->execute([$name]);
    $existing = $stmt->fetch();

    $consumable_id = '';

    if ($existing) {
        $consumable_id = $existing['id'];
        $pdo->prepare("UPDATE inventory_consumable SET qty = qty + ? WHERE id = ?")->execute([$qty, $consumable_id]);
    } else {
        $consumable_id = 'CON-' . uniqid();
        $pdo->prepare("INSERT INTO inventory_consumable (id, product_name, qty, unit) VALUES (?, ?, ?, ?)")->execute([$consumable_id, $name, $qty, $unit]);
    }

    // Log inbound consumable
    $stmtLog = $pdo->prepare("INSERT INTO inventory_consumable_logs (consumable_id, action, qty, admin_id) VALUES (?, 'in', ?, ?)");
    $stmtLog->execute([$consumable_id, $qty, $admin_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>