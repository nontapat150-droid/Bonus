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
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']); exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, qty FROM inventory_consumable WHERE product_name = ?");
    $stmt->execute([$name]);
    $exist = $stmt->fetch();

    if ($exist) {
        $newQty = $exist['qty'] + $qty;
        $pdo->prepare("UPDATE inventory_consumable SET qty = ? WHERE id = ?")->execute([$newQty, $exist['id']]);
    } else {
        $id = "C-" . time() . rand(100,999);
        $pdo->prepare("INSERT INTO inventory_consumable (id, product_name, qty, unit) VALUES (?, ?, ?, ?)")->execute([$id, $name, $qty, $unit]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>