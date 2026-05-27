<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$user_id = $user['id'];

$sn = $_GET['sn'] ?? '';

if (!$sn) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุหมายเลข SN']);
    exit;
}

try {
    // ดึงข้อมูลสินค้า
    $stmt = $pdo->prepare("
        SELECT 
            i.id, i.sn, i.status, 
            p.name as product_name, 
            pm.model_name 
        FROM inventory_items i
        JOIN product_models pm ON i.model_id = pm.id
        JOIN products p ON pm.product_id = p.id
        WHERE i.sn = ?
    ");
    $stmt->execute([$sn]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบหมายเลขซีเรียลนี้ในระบบ']);
        exit;
    }

    if ($item['status'] === 'in_stock') {
        echo json_encode(['success' => false, 'error' => 'สินค้านี้ยังอยู่ในคลังหลัก (ยังไม่ได้เบิกออก)']);
        exit;
    }

    // หาล็อกล่าสุดว่าใครเป็นคนถือของชิ้นนี้อยู่
    $logStmt = $pdo->prepare("
        SELECT target_user_id 
        FROM inventory_logs 
        WHERE item_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $logStmt->execute([$item['id']]);
    $latestLog = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$latestLog || !$latestLog['target_user_id']) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบประวัติผู้ถือครองปัจจุบัน']);
        exit;
    }

    // ถ้าไม่ใช่แอดมิน ต้องตรวจว่าของอยู่กับช่างคนนี้จริงไหม
    if ($role === 'technician' && $latestLog['target_user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'สินค้านี้ไม่ได้อยู่ที่คุณ (ไม่สามารถโอนย้ายได้)']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $item['id'],
            'sn' => $item['sn'],
            'product_name' => $item['product_name'],
            'model_name' => $item['model_name']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
