<?php
// api/oil/import_records.php
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

if (empty($records)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสำหรับนำเข้า']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึงรายชื่อช่างทั้งหมดมาเตรียมไว้เพื่อจับคู่ชื่อใน Excel ให้เป็น ID
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users");
    $users = $stmtUsers->fetchAll(PDO::FETCH_KEY_PAIR); // จะได้อาร์เรย์ [full_name => id]

    $stmtInsert = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");

    $imported = 0;
    foreach ($records as $row) {
        $license_plate = trim($row['license_plate']);
        $liters = floatval($row['liters']);
        $mileage = intval($row['mileage']);
        $price_per_liter = floatval($row['price_per_liter']);
        $total_price = floatval($row['total_price']);
        
        $tech_name = trim($row['tech_name']);
        
        // หากันว่าชื่อใน Excel ตรงกับช่างคนไหนในระบบ (ถ้าไม่เจอให้ใส่เป็นชื่อแอดมินที่กดนำเข้า)
        $tech_id = $_SESSION['user_id'];
        if ($tech_name) {
            $matched_id = array_search($tech_name, $users);
            if ($matched_id !== false) {
                $tech_id = $matched_id;
            }
        }

        // จัดการวันที่
        $date_recorded = $row['date'] ? date('Y-m-d H:i:s', strtotime($row['date'])) : date('Y-m-d H:i:s');

        $stmtInsert->execute([$tech_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price, $date_recorded]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>