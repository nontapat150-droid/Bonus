<?php
// api/oil/submit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

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
    // นับเคสจากงานที่กดจบงานสำเร็จเท่านั้น (ไม่รับค่าจากฟอร์ม)
    $filler_name = trim($_SESSION['full_name'] ?? '');

    $tech_id = $user_id;
    if ($isAdmin && !empty($_POST['tech_id'])) {
        $tech_id = intval($_POST['tech_id']);
    }

    $date_recorded = date('Y-m-d H:i:s');
    if (!empty($_POST['date_recorded'])) {
        $date_recorded = date('Y-m-d H:i:s', strtotime($_POST['date_recorded']));
    }

    if (empty($license_plate) || $mileage <= 0 || $liters <= 0 || $price_per_liter <= 0) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
    }

    // 🚨 --- ระบบตรวจจับข้อมูลซ้ำ (Duplicate Detection) ---
    $stmtCheckDup = $pdo->prepare("SELECT date_recorded, liters FROM oil_records WHERE license_plate = ? AND mileage = ? LIMIT 1");
    $stmtCheckDup->execute([$license_plate, $mileage]);
    $dupRecord = $stmtCheckDup->fetch(PDO::FETCH_ASSOC);

    if ($dupRecord) {
        $dupDate = date('d/m/Y H:i', strtotime($dupRecord['date_recorded']));
        throw new Exception("ตรวจพบข้อมูลซ้ำ! รถทะเบียน [{$license_plate}] มีการบันทึกเลขไมล์ [{$mileage}] ไปแล้วเมื่อวันที่ {$dupDate} (จำนวน {$dupRecord['liters']} ลิตร)");
    }
    // --------------------------------------------------

    // ปัดเศษราคารวมอัตโนมัติ
    $total_price = isset($_POST['total_price']) && $_POST['total_price'] !== '' ? round(floatval($_POST['total_price'])) : round($liters * $price_per_liter);

    $teamId = getTeamIdByName($pdo, $license_plate);
    $yearMonth = date('Y-m', strtotime($date_recorded));

    $job_count = resolveTeamMonthlyJobCount($pdo, $teamId ? (int)$teamId : null, $yearMonth);

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

    // ผูกช่างกับทีมอัตโนมัติ (ตาม request "ในหน้าระบบน้ำมันหากล็อคอินกับชื่อช่างให้ผูกชื่อช่างกับทีมเอาไว้เลย")
    if (!$isAdmin) {
        // หา team_id ถ้ายังไม่ได้หาในรอบ job_count
        if (!isset($teamId) || !$teamId) {
            $stmtTeam = $pdo->prepare("SELECT id FROM teams WHERE team_name = ? LIMIT 1");
            $stmtTeam->execute([$license_plate]);
            $teamId = $stmtTeam->fetchColumn();
        }
        
        if ($teamId) {
            // ตรวจสอบว่าช่างคนนี้มีทีมหรือยัง
            $stmtCheckUserTeam = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
            $stmtCheckUserTeam->execute([$user_id]);
            $currentTeamId = $stmtCheckUserTeam->fetchColumn();
            
            if (!$currentTeamId) {
                $stmtUpdateUserTeam = $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?");
                $stmtUpdateUserTeam->execute([$teamId, $user_id]);
            }
        }
    }

    // เพิ่มข้อมูลลงตาราง โดยกำหนดให้ระยะทางเริ่มต้นเป็น 0 ชั่วคราว
    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded, filler_name, distance, job_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$tech_id, $license_plate, $liters, $mileage, $price_per_liter, $total_price, $date_recorded, $filler_name, $job_count]);
    $record_id = $pdo->lastInsertId();

    // ระบบคำนวณระยะทางใหม่ทั้งหมดแบบอัตโนมัติ (เรียงตามวันที่เติมน้ำมัน)
    $stmtRecalc = $pdo->prepare("SELECT id, mileage FROM oil_records WHERE license_plate = ? ORDER BY date_recorded ASC, id ASC");
    $stmtRecalc->execute([$license_plate]);
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

    $upload_dir = '../../assets/uploads/oil_receipts/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $uploadedCount = 0;
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

    if (isset($_FILES['oil_images']) && !empty($_FILES['oil_images']['name'][0])) {
        $files = $_FILES['oil_images'];
        $count = count($files['name']);
        if ($count > 10) throw new Exception("อัปโหลดได้สูงสุด 10 รูปเท่านั้น");

        $stmtImage = $pdo->prepare("INSERT INTO oil_images (record_id, image_path) VALUES (?, ?)");
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue; // ข้ามไฟล์ที่มีปัญหา

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

            // ถ้า extension ไม่รู้จัก ให้ตรวจสอบ MIME type แทน
            if (!in_array($ext, $allowedExts)) {
                $mime = mime_content_type($files['tmp_name'][$i]);
                $mimeToExt = [
                    'image/jpeg' => 'jpg', 'image/png' => 'png',
                    'image/gif'  => 'gif', 'image/webp' => 'webp',
                    'image/heic' => 'heic', 'image/heif' => 'heif',
                ];
                if (isset($mimeToExt[$mime])) {
                    $ext = $mimeToExt[$mime];
                } else {
                    continue; // ข้ามไฟล์ที่ไม่ใช่รูปภาพ
                }
            }

            // ถ้าเป็น HEIC/HEIF ให้บันทึกเป็น jpg (แปลงไม่ได้บนเซิร์ฟเวอร์ แต่เก็บ original ไว้)
            $filename = uniqid('oil_', true) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                $full_url = getBaseUrl() . '/assets/uploads/oil_receipts/' . $filename;
                $stmtImage->execute([$record_id, $full_url]);
                $uploadedCount++;
            }
        }

        if ($uploadedCount === 0 && !$isAdmin) {
            throw new Exception("ไม่สามารถอัปโหลดรูปภาพได้ กรุณาตรวจสอบประเภทและขนาดไฟล์ (รองรับ JPG, PNG, WEBP)");
        }
    } else {
        // ตรวจสอบว่า POST ถูก truncate เพราะ post_max_size หรือเปล่า
        if ($_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES)) {
            throw new Exception("ข้อมูลที่ส่งมีขนาดใหญ่เกินไป กรุณาลดขนาดรูปภาพลงแล้วลองใหม่อีกครั้ง");
        }
        if (!$isAdmin) throw new Exception("กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลและคำนวณไมล์เรียบร้อยแล้ว']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>