<?php
// api/oil/submit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$user_id = $_SESSION['user_id'];
$isAdmin = hasRole(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'วิธีการส่งข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    $license_plate = trim($_POST['license_plate'] ?? '');
    $mileage = intval($_POST['mileage'] ?? 0);
    $liters = floatval($_POST['liters'] ?? 0);
    $price_per_liter = floatval($_POST['price_per_liter'] ?? 0);
    $job_count = intval($_POST['job_count'] ?? 0);
    $filler_name = trim($_SESSION['full_name'] ?? '');

    $tech_id = $user_id;
    if ($isAdmin && !empty($_POST['tech_id'])) {
        $tech_id = intval($_POST['tech_id']);
    }

    $date_recorded = date('Y-m-d H:i:s');
    if ($isAdmin && !empty($_POST['date_recorded'])) {
        $date_recorded = date('Y-m-d H:i:s', strtotime($_POST['date_recorded']));
    }

    if (empty($license_plate) || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
    }

    // คำนวณระยะทางจากรอบก่อนหน้าอัตโนมัติ
    $distance = 0;
    $stmtLastMile = $pdo->prepare("SELECT mileage FROM oil_records WHERE license_plate = ? AND date_recorded < ? ORDER BY date_recorded DESC LIMIT 1");
    $stmtLastMile->execute([$license_plate, $date_recorded]);
    $lastMileage = $stmtLastMile->fetchColumn();
    if ($lastMileage && $mileage >= $lastMileage) {
        $distance = $mileage - $lastMileage;
    }

    // ปัดเศษราคารวมอัตโนมัติ
    $total_price = isset($_POST['total_price']) && $_POST['total_price'] !== '' ? round(floatval($_POST['total_price'])) : round($liters * $price_per_liter);

    if ($job_count <= 0) {
        $stmtTeam = $pdo->prepare("SELECT id FROM teams WHERE team_name = ? LIMIT 1");
        $stmtTeam->execute([$license_plate]);
        $teamId = $stmtTeam->fetchColumn();

        if ($teamId) {
            $stmtLast = $pdo->prepare("SELECT date_recorded FROM oil_records WHERE license_plate = ? ORDER BY date_recorded DESC LIMIT 1");
            $stmtLast->execute([$license_plate]);
            $lastDate = $stmtLast->fetchColumn();

            if ($lastDate) {
                $jobStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE team_id = ? AND DATE(COALESCE(plan_arrival_date, created_at)) > DATE(?) AND DATE(COALESCE(plan_arrival_date, created_at)) <= DATE(?)");
                $jobStmt->execute([$teamId, $lastDate, $date_recorded]);
            } else {
                $jobStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE team_id = ? AND DATE(COALESCE(plan_arrival_date, created_at)) = DATE(?)");
                $jobStmt->execute([$teamId, $date_recorded]);
            }
            $job_count = (int)$jobStmt->fetchColumn();
        }
    }

    $stmt = $pdo->prepare("SELECT id, last_tech_id FROM vehicles WHERE license_plate = ?");
    $stmt->execute([$license_plate]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        if ($vehicle['last_tech_id'] !== null && $vehicle['last_tech_id'] != $user_id) {
            $stmt = $pdo->prepare("UPDATE vehicles SET last_tech_id = ? WHERE id = ?");
            $stmt->execute([$user_id, $vehicle['id']]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO vehicles (license_plate, last_tech_id) VALUES (?, ?)");
        $stmt->execute([$license_plate, $user_id]);
    }

    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded, filler_name, distance, job_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tech_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price, $date_recorded, $filler_name, $distance, $job_count]);
    $record_id = $pdo->lastInsertId();

    $upload_dir = '../../assets/uploads/oil_receipts/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (isset($_FILES['oil_images'])) {
        $files = $_FILES['oil_images'];
        $count = count($files['name']);
        if ($count > 10) throw new Exception("อัปโหลดได้สูงสุด 10 รูปเท่านั้น");

        $stmtImage = $pdo->prepare("INSERT INTO oil_images (record_id, image_path) VALUES (?, ?)");
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG เท่านั้น");

                $filename = uniqid('oil_', true) . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                    $stmtImage->execute([$record_id, $filename]);
                }
            }
        }
    } else {
         if (!$isAdmin) throw new Exception("กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>