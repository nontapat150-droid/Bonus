<?php
// Bonus/api/announcements/manage.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

// อนุญาตเฉพาะแอดมินเท่านั้น
if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึงการจัดการประกาศ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, message, image_url, expires_at FROM announcements ORDER BY id DESC LIMIT 1");
        $announcement = $stmt->fetch();
        echo json_encode(['success' => true, 'data' => $announcement]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $message = trim($_POST['message'] ?? '');
    $duration_val = intval($_POST['duration_val'] ?? 0);
    $duration_unit = $_POST['duration_unit'] ?? 'never';
    $existingImage = trim($_POST['existing_image_url'] ?? '');

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อความประกาศ']);
        exit;
    }

    $expires_at = null;
    if ($duration_unit !== 'never' && $duration_val > 0) {
        $unit_map = ['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days'];
        if (isset($unit_map[$duration_unit])) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$duration_val {$unit_map[$duration_unit]}"));
        }
    }

    $imageUrl = $existingImage;
    $uploadDir = __DIR__ . '/../../uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, GIF, WEBP เท่านั้น']);
            exit;
        }

        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(10)) . '.' . strtolower($extension);
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'error' => 'อัปโหลดรูปภาพไม่สำเร็จ']);
            exit;
        }

        if ($existingImage) {
            $oldFile = __DIR__ . '/../../' . ltrim($existingImage, '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $imageUrl = 'uploads/announcements/' . $safeName;
    }

    try {
        $pdo->exec("TRUNCATE TABLE announcements");
        $stmt = $pdo->prepare("INSERT INTO announcements (message, image_url, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$message, $imageUrl, $expires_at]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($action === 'delete') {
    try {
        $stmt = $pdo->query("SELECT image_url FROM announcements ORDER BY id DESC LIMIT 1");
        $current = $stmt->fetchColumn();
        if ($current) {
            $oldFile = __DIR__ . '/../../' . ltrim($current, '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $pdo->exec("TRUNCATE TABLE announcements");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'รูปแบบคำสั่งไม่ถูกต้อง']);
}
?>