<?php
// api/oil/edit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$tech_id = $input['tech_id'] ?? null;
$license_plate = trim($input['license_plate'] ?? '');
$date_recorded = $input['date_recorded'] ?? null;
$mileage = intval($input['mileage'] ?? 0);
$liters = floatval($input['liters'] ?? 0);
$price_per_liter = floatval($input['price_per_liter'] ?? 0);
$job_count = intval($input['job_count'] ?? 0);

if (!$id || !$tech_id || !$license_plate || !$date_recorded || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// 🚨 --- ระบบตรวจจับข้อมูลซ้ำสำหรับการแก้ไข (ต้องไม่ซ้ำกับ ID อื่น) ---
$stmtCheckDup = $pdo->prepare("SELECT date_recorded FROM oil_records WHERE license_plate = ? AND mileage = ? AND id != ? LIMIT 1");
$stmtCheckDup->execute([$license_plate, $mileage, $id]);
$dupRecord = $stmtCheckDup->fetch(PDO::FETCH_ASSOC);

if ($dupRecord) {
    $dupDate = date('d/m/Y H:i', strtotime($dupRecord['date_recorded']));
    echo json_encode(['success' => false, 'error' => "แก้ไขข้อมูลล้มเหลว: ตรวจพบข้อมูลซ้ำ! รถทะเบียน [{$license_plate}] มีเลขไมล์ [{$mileage}] ถูกบันทึกไว้ในรายการอื่นแล้ว (เมื่อ {$dupDate})"]);
    exit;
}
// ----------------------------------------------------------

$date_recorded_mysql = date('Y-m-d H:i:s', strtotime($date_recorded));
$total_price = isset($input['total_price']) && $input['total_price'] !== '' ? round(floatval($input['total_price'])) : round($liters * $price_per_liter);

try {
    $pdo->beginTransaction();

    // ดึงทะเบียนรถเดิมมาตรวจสอบ เผื่อว่ามีการแก้ไขเปลี่ยนทะเบียนรถ
    $stmtOld = $pdo->prepare("SELECT license_plate FROM oil_records WHERE id = ?");
    $stmtOld->execute([$id]);
    $old_plate = $stmtOld->fetchColumn();

    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$tech_id]);
    $fullName = $stmtUser->fetchColumn();

    $stmt = $pdo->prepare("UPDATE oil_records SET 
        tech_id = ?, license_plate = ?, filler_name = ?, date_recorded = ?, mileage = ?,
        liters = ?, price_per_liter = ?, total_price = ?, job_count = ?
        WHERE id = ?");
    $stmt->execute([
        $tech_id, $license_plate, $fullName ?: null, $date_recorded_mysql, $mileage,
        $liters, $price_per_liter, $total_price, $job_count, $id
    ]);
    
    // ระบบคำนวณระยะทางใหม่ทั้งหมดตามวันที่
    $platesToRecalc = array_unique([$license_plate, $old_plate]);
    
    foreach ($platesToRecalc as $plateToRecalc) {
        if (empty($plateToRecalc)) continue;
        
        $stmtRecalc = $pdo->prepare("SELECT id, mileage FROM oil_records WHERE license_plate = ? ORDER BY date_recorded ASC, id ASC");
        $stmtRecalc->execute([$plateToRecalc]);
        $recordsForRecalc = $stmtRecalc->fetchAll(PDO::FETCH_ASSOC);
        
        $prev_mileage = null;
        $updateDistStmt = $pdo->prepare("UPDATE oil_records SET distance = ? WHERE id = ?");
        
        foreach ($recordsForRecalc as $rRow) {
            $curr_m = (int)$rRow['mileage'];
            $dist = 0;
            if ($prev_mileage !== null) {
                $dist = $curr_m - $prev_mileage;
                if ($dist < 0) $dist = 0;
            }
            $updateDistStmt->execute([$dist, $rRow['id']]);
            $prev_mileage = $curr_m;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>