<?php
// api/oil/recalculate.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึงป้ายทะเบียนรถ "ทั้งหมด" ที่มีอยู่ในระบบ
    $stmtPlates = $pdo->query("SELECT DISTINCT license_plate FROM oil_records WHERE license_plate IS NOT NULL AND license_plate != ''");
    $plates = $stmtPlates->fetchAll(PDO::FETCH_COLUMN);

    $updateDistStmt = $pdo->prepare("UPDATE oil_records SET distance = ? WHERE id = ?");

    // วนลูปรถทีละคัน เพื่อนำมาเรียงวันที่และคำนวณระยะทาง
    foreach ($plates as $plate) {
        // เรียงตามวันที่บันทึก (เก่าไปใหม่)
        $stmtRecalc = $pdo->prepare("SELECT id, mileage FROM oil_records WHERE license_plate = ? ORDER BY date_recorded ASC, id ASC");
        $stmtRecalc->execute([$plate]);
        $records = $stmtRecalc->fetchAll(PDO::FETCH_ASSOC);

        $prev_mileage = null;
        
        foreach ($records as $row) {
            $curr_m = (int)$row['mileage'];
            $dist = 0;
            
            if ($prev_mileage !== null) {
                $dist = $curr_m - $prev_mileage;
                if ($dist < 0) $dist = 0; // ป้องกันระยะทางติดลบกรณีคีย์เลขไมล์ผิด
            }
            
            // อัปเดตระยะทางที่ถูกต้องกลับลงไปในฐานข้อมูล
            $updateDistStmt->execute([$dist, $row['id']]);
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