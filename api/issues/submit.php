<?php
// api/issues/submit.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$message = trim($_POST['message'] ?? '');
$imageUrl = null;

if (empty($message) && empty($_FILES['image']['name'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อความหรือแนบรูปภาพ']);
    exit;
}

// Handle file upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/issues/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExtensions)) {
         echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่รองรับ (รองรับเฉพาะ jpg, jpeg, png, gif, webp)']);
         exit;
    }
    
    // Generate unique file name
    $newFileName = 'issue_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $imageUrl = 'uploads/issues/' . $newFileName;
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO issue_reports (user_id, message, image_url, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$user['id'], $message, $imageUrl]);
    
    echo json_encode(['success' => true, 'message' => 'ส่งรายงานปัญหาเรียบร้อยแล้ว แอดมินจะดำเนินการตรวจสอบให้เร็วที่สุด']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
