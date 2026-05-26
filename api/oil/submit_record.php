<?php
// api/oil/submit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $pdo->beginTransaction();

    $license_plate = strtoupper(trim($_POST['license_plate'] ?? ''));
    $mileage = intval($_POST['mileage'] ?? 0);
    $liters = floatval($_POST['liters'] ?? 0);
    $price_per_liter = floatval($_POST['price_per_liter'] ?? 0);
    $total_price = $liters * $price_per_liter;

    if (empty($license_plate) || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
    }

    // 1. Check and Lock Vehicle
    $stmt = $pdo->prepare("SELECT id, last_tech_id FROM vehicles WHERE license_plate = ?");
    $stmt->execute([$license_plate]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        if ($vehicle['last_tech_id'] !== null && $vehicle['last_tech_id'] != $user_id) {
            // Depending on strictness, you might allow takeover or block it. Let's allow takeover but update the lock.
            $stmt = $pdo->prepare("UPDATE vehicles SET last_tech_id = ? WHERE id = ?");
            $stmt->execute([$user_id, $vehicle['id']]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO vehicles (license_plate, last_tech_id) VALUES (?, ?)");
        $stmt->execute([$license_plate, $user_id]);
    }

    // 2. Insert Oil Record
    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price]);
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
         throw new Exception("กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
