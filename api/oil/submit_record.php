<?php
// api/oil/submit_record.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$user_id = $_SESSION['user_id'];

const MAX_OIL_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

function createImageResource($path, $mimeType) {
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            return imagecreatefromjpeg($path);
        case 'image/png':
            return imagecreatefrompng($path);
        case 'image/gif':
            return imagecreatefromgif($path);
        default:
            return false;
    }
}

function compressUploadedImage($sourcePath, $destinationPath, $mimeType, $maxSize) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        throw new Exception('ไม่สามารถอ่านข้อมูลรูปภาพได้');
    }

    list($width, $height) = $imageInfo;
    $srcImage = createImageResource($sourcePath, $mimeType);
    if (!$srcImage) {
        throw new Exception('ไม่รองรับชนิดไฟล์รูปภาพนี้');
    }

    $quality = 90;
    $scale = 1.0;

    while (true) {
        $newWidth = max(600, (int)round($width * $scale));
        $newHeight = max(600, (int)round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($mimeType === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
            imagejpeg($canvas, $destinationPath, $quality);
        } elseif ($mimeType === 'image/png') {
            imagepng($canvas, $destinationPath, 9);
        } elseif ($mimeType === 'image/gif') {
            imagegif($canvas, $destinationPath);
        }

        imagedestroy($canvas);

        if (filesize($destinationPath) <= $maxSize) {
            imagedestroy($srcImage);
            return true;
        }

        if ($quality > 40) {
            $quality -= 10;
        } elseif ($scale > 0.4) {
            $scale -= 0.1;
        } else {
            imagedestroy($srcImage);
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'วิธีการส่งข้อมูลไม่ถูกต้อง']);
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
                $temp_path = $files['tmp_name'][$i];

                if (filesize($temp_path) > MAX_OIL_IMAGE_SIZE) {
                    if (!compressUploadedImage($temp_path, $target_file, $files['type'][$i], MAX_OIL_IMAGE_SIZE)) {
                        throw new Exception("ขนาดรูปภาพใหญ่เกินไปและไม่สามารถบีบอัดให้อยู่ภายใน 5MB ได้");
                    }
                    $stmtImage->execute([$record_id, $filename]);
                } else {
                    if (move_uploaded_file($temp_path, $target_file)) {
                        $stmtImage->execute([$record_id, $filename]);
                    } else {
                        throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ");
                    }
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