<?php
// api/oil/import_excel.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$records = $input['records'] ?? [];

if (!is_array($records) || empty($records)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลเพื่อนำเข้า']);
    exit;
}

// สร้างคอลัมน์ในฐานข้อมูลอัตโนมัติหากยังไม่มี
$columnsToAdd = [
    "distance" => "DECIMAL(10,2) DEFAULT 0",
    "baht_per_km" => "DECIMAL(10,2) DEFAULT 0",
    "filler_name" => "VARCHAR(150) DEFAULT NULL"
];

foreach ($columnsToAdd as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE oil_records ADD COLUMN `$col` $def");
    } catch (PDOException $e) {
        // คอลัมน์มีอยู่แล้ว ข้ามไป
    }
}

$errors = [];
$inserted = 0;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded, distance, baht_per_km, filler_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($records as $index => $row) {
        $license_plate = trim($row['license_plate'] ?? '');
        $liters = floatval($row['liters'] ?? 0);
        $mileage = intval($row['mileage'] ?? 0);
        $price_per_liter = floatval($row['price_per_liter'] ?? 0);
        $total_price = floatval($row['total_price'] ?? 0);
        $distance = floatval($row['distance'] ?? 0);
        $baht_per_km = floatval($row['baht_per_km'] ?? 0);
        $filler_name = trim($row['filler_name'] ?? '');
        $date_recorded = trim($row['date_recorded'] ?? '');

        // ป้องกันค่าวันที่เป็น null หรือมีปัญหา
        if ($date_recorded === '' || $date_recorded === null) {
            $date_recorded = date('Y-m-d H:i:s');
        }

        if ($license_plate === '' || $liters <= 0 || $mileage < 0) {
            $errors[] = "แถวที่ " . ($index + 2) . " ข้อมูลไม่ครบถ้วน";
            continue;
        }

        // ค้นหา Tech ID ในระบบ ถ้าเจอให้ผูกชื่อ ถ้าไม่เจอใช้ default = 1
        $techId = 1; 
        if ($filler_name !== '') {
            $stmtFind = $pdo->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
            $stmtFind->execute([$filler_name]);
            $found = $stmtFind->fetchColumn();
            if ($found) {
                $techId = (int)$found;
            }
        }

        $stmt->execute([
            $techId,
            $license_plate,
            $liters,
            $mileage,
            $price_per_liter,
            $total_price,
            $date_recorded,
            $distance,
            $baht_per_km,
            $filler_name
        ]);
        $inserted++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'inserted' => $inserted, 'errors' => $errors]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>