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
    $distance = floatval($_POST['distance'] ?? 0);
    $job_count = intval($_POST['job_count'] ?? 0);
    $total_price = $liters * $price_per_liter;
    $filler_name = trim($_SESSION['full_name'] ?? '');

    // แอดมินสามารถกำหนด tech_id ได้เอง แทนที่จะเป็นตัวเอง (คนคีย์)
    $tech_id = $user_id;
    if ($isAdmin && !empty($_POST['tech_id'])) {
        $tech_id = intval($_POST['tech_id']);
    }

    // การจัดการวันที่บันทึก (Date Recorded)
    $date_recorded = date('Y-m-d H:i:s'); // ค่าเริ่มต้นเป็นเวลาปัจจุบัน
    
    // ตรวจสอบว่าแอดมินหรือซูเปอร์แอดมิน เป็นผู้กำหนดเวลาย้อนหลังมาหรือไม่
    if ($isAdmin && !empty($_POST['date_recorded'])) {
        $date_recorded = date('Y-m-d H:i:s', strtotime($_POST['date_recorded']));
    }

    if (empty($license_plate) || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
    }

    // --- ส่วนเพิ่มเติม: คำนวณ Job Count อัตโนมัติหากไม่ได้ส่งมา (กรณีช่างเติมหน้างาน) ---
    if ($job_count <= 0) {
        // ค้นหา ID ทีมจากป้ายทะเบียน
        $stmtTeam = $pdo->prepare("SELECT id FROM teams WHERE team_name = ? LIMIT 1");
        $stmtTeam->execute([$license_plate]);
        $teamId = $stmtTeam->fetchColumn();

        if ($teamId) {
            // ค้นหาวันที่เติมครั้งล่าสุดของรถคันนี้
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

    // 1. Check and Lock Vehicle
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

    // 2. Insert Oil Record
    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded, filler_name, distance, job_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tech_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price, $date_recorded, $filler_name, $distance, $job_count]);
    $record_id = $pdo->lastInsertId();

    // 3. Handle File Uploads (Max 10)
    $upload_dir = '../../assets/uploads/oil_receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (isset($_FILES['oil_images'])) {
        $files = $_FILES['oil_images'];
        $count = count($files['name']);

        if ($count > 10) {
            throw new Exception("อัปโหลดได้สูงสุด 10 รูปเท่านั้น");
        }

        $stmtImage = $pdo->prepare("INSERT INTO oil_images (record_id, image_path) VALUES (?, ?)");

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพ JPG หรือ PNG เท่านั้น");
                }

                $filename = uniqid('oil_', true) . '.' . $ext;
                $target_file = $upload_dir . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                    $stmtImage->execute([$record_id, $filename]);
                } else {
                    throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ");
                }
            }
        }
    } else {
         if (!$isAdmin) {
             throw new Exception("กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป");
         }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>