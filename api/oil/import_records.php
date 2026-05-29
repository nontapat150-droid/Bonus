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

    // ตรวจสอบคอลัมน์ filler_name ในตาราง oil_records หากยังไม่มีให้เพิ่ม
    try {
        $pdo->exec("ALTER TABLE oil_records ADD COLUMN IF NOT EXISTS filler_name VARCHAR(150) DEFAULT NULL");
    } catch (PDOException $e) {
        // MySQL บางเวอร์ชันไม่รองรับ IF NOT EXISTS ใน ALTER TABLE
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM oil_records LIKE 'filler_name'")->fetchAll();
            if (count($cols) === 0) {
                $pdo->exec("ALTER TABLE oil_records ADD COLUMN filler_name VARCHAR(150) DEFAULT NULL");
            }
        } catch (Exception $ex) {}
    }

    // เตรียมแผนที่ทีม (team_name => id) เพื่อตรวจสอบว่าชื่อทีมใน Excel มีอยู่ในระบบหรือไม่
    $stmtTeams = $pdo->query("SELECT id, team_name FROM teams");
    $teams = $stmtTeams->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtInsert = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded, filler_name) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $imported = 0;
    foreach ($records as $row) {
        $license_plate = trim($row['license_plate']);
        $liters = floatval($row['liters']);
        $mileage = intval($row['mileage']);
        $price_per_liter = floatval($row['price_per_liter']);
        $total_price = floatval($row['total_price']);
        
        $tech_name = trim($row['tech_name']);
        
        // หากชื่อผู้เติม (tech_name) ตรงกับผู้ใช้งานในระบบ ให้ผูกกับ tech_id และเก็บ filler_name
        // หากไม่พบ ให้เก็บ filler_name เป็นค่าว่าง เพื่อให้แอดมินแก้ไขภายหลัง แต่ยังต้องใส่ tech_id ชั่วคราวเป็นผู้ที่นำเข้าข้อมูล (admin)
        $tech_id = $_SESSION['user_id'];
        $filler_name = '';
        if ($tech_name) {
            $matched_id = array_search($tech_name, $users);
            if ($matched_id !== false) {
                $tech_id = $matched_id;
                $filler_name = $tech_name;
            } else {
                // ไม่พบชื่อช่างในระบบ -> เก็บ filler_name ว่าง เพื่อให้ admin แก้ไข
                $filler_name = '';
            }
        }

        // จัดการวันที่
        $date_recorded = $row['date'] ? date('Y-m-d H:i:s', strtotime($row['date'])) : date('Y-m-d H:i:s');

        // ตรวจสอบชื่อทีม/ป้ายทะเบียนว่ามีในระบบหรือไม่ ถ้าไม่มีให้เก็บเป็นค่าว่าง
        if ($license_plate !== '') {
            $matched_team = array_search($license_plate, $teams);
            if ($matched_team === false) {
                $license_plate = '';
            }
        }

        $stmtInsert->execute([$tech_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price, $date_recorded, $filler_name]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>