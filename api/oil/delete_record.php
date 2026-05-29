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
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'รหัสไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึง license_plate ก่อนลบ เพื่อเอาไปใช้คำนวณไมล์ที่เหลือใหม่
    $stmtGet = $pdo->prepare("SELECT license_plate FROM oil_records WHERE id = ?");
    $stmtGet->execute([$id]);
    $plate = $stmtGet->fetchColumn();

    // ลบรูปภาพที่เกี่ยวข้อง
    $stmtImg = $pdo->prepare("SELECT image_path FROM oil_images WHERE record_id = ?");
    $stmtImg->execute([$id]);
    $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
    foreach ($images as $img) {
        $filePath = '../../assets/uploads/oil_receipts/' . $img['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM oil_images WHERE record_id = ?");
    $stmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM oil_records WHERE id = ?");
    $stmt->execute([$id]);

    // 🚀 --- ระบบคำนวณระยะทางใหม่ทั้งหมดตามวันที่ (หลังจากลบ) ---
    if ($plate) {
        $stmtRecalc = $pdo->prepare("SELECT id, mileage FROM oil_records WHERE license_plate = ? ORDER BY date_recorded ASC, id ASC");
        $stmtRecalc->execute([$plate]);
        $recordsForRecalc = $stmtRecalc->fetchAll(PDO::FETCH_ASSOC);
        
        $prev_mileage = null;
        $updateDistStmt = $pdo->prepare("UPDATE oil_records SET distance = ? WHERE id = ?");
        
        foreach ($recordsForRecalc as $rRow) {
            $curr_m = (int)$rRow['mileage'];
            $dist = 0;
            if ($prev_mileage !== null) {
                $dist = $curr_m - $prev_mileage;
                if ($dist < 0) $dist = 0; // ป้องกันระยะทางติดลบกรณีคีย์เลขไมล์ผิด
            }
            $updateDistStmt->execute([$dist, $rRow['id']]);
            $prev_mileage = $curr_m;
        }
    }
    // -------------------------------------------------------------

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>