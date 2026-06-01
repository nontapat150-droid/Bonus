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

// Ensure the announcements table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        type VARCHAR(50) DEFAULT 'popup',
        title VARCHAR(255) DEFAULT NULL, 
        message TEXT NOT NULL, 
        image_url VARCHAR(255) DEFAULT NULL, 
        expires_at DATETIME DEFAULT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Ignore
}

// Helper to check and add missing columns for backward compatibility
function ensureColumnExists($pdo, $column, $definition) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM announcements LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN `$column` $definition");
        }
    } catch (Exception $e) {
        // Ignore
    }
}

ensureColumnExists($pdo, 'title', 'VARCHAR(255) DEFAULT NULL AFTER id');
ensureColumnExists($pdo, 'type', "VARCHAR(50) DEFAULT 'popup' AFTER id");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $type = $_GET['type'] ?? '';
        if ($type) {
            $stmt = $pdo->prepare("SELECT * FROM announcements WHERE type = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$type]);
            $announcement = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $announcement]);
        } else {
            // Fetch both if no type is specified
            $stmtPopup = $pdo->query("SELECT * FROM announcements WHERE type = 'popup' ORDER BY id DESC LIMIT 1");
            $popup = $stmtPopup->fetch();

            $stmtMarquee = $pdo->query("SELECT * FROM announcements WHERE type = 'marquee' ORDER BY id DESC LIMIT 1");
            $marquee = $stmtMarquee->fetch();

            echo json_encode(['success' => true, 'data' => ['popup' => $popup, 'marquee' => $marquee]]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $type = $_POST['type'] ?? 'popup';
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $duration_val = intval($_POST['duration_val'] ?? 0);
    $duration_unit = $_POST['duration_unit'] ?? 'never';
    $existingImage = trim($_POST['existing_image_url'] ?? '');

    if ($type === 'popup' && empty($title)) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกหัวข้อประกาศ']);
        exit;
    }
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
    if ($type === 'popup') {
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
    } else {
        $imageUrl = null; // Marquee doesn't have an image
        $title = null; // Marquee doesn't have a title
    }

    try {
        // Delete previous announcement of the same type
        $stmtDel = $pdo->prepare("DELETE FROM announcements WHERE type = ?");
        $stmtDel->execute([$type]);

        // Insert new announcement
        $stmt = $pdo->prepare("INSERT INTO announcements (type, title, message, image_url, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $title, $message, $imageUrl, $expires_at]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($action === 'delete') {
    $type = $_POST['type'] ?? 'popup';

    try {
        if ($type === 'popup') {
            $stmt = $pdo->prepare("SELECT image_url FROM announcements WHERE type = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$type]);
            $current = $stmt->fetchColumn();
            if ($current) {
                $oldFile = __DIR__ . '/../../' . ltrim($current, '/');
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }

        $stmtDel = $pdo->prepare("DELETE FROM announcements WHERE type = ?");
        $stmtDel->execute([$type]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'รูปแบบคำสั่งไม่ถูกต้อง']);
}
?>