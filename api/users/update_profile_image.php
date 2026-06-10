<?php
// api/users/update_profile_image.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();
$userSession = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเลือกไฟล์รูปภาพที่ถูกต้อง']);
    exit;
}

$uploadDir = '../../uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileTmpPath = $_FILES['profile_image']['tmp_name'];
$fileName = $_FILES['profile_image']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, webp, gif)']);
    exit;
}

$newFileName = 'user_' . $userSession['id'] . '_' . time() . '.' . $fileExtension;
$destPath = $uploadDir . $newFileName;

if (move_uploaded_file($fileTmpPath, $destPath)) {
    $full_url = getBaseUrl() . '/uploads/profiles/' . $newFileName;
    $imageUrl = $full_url;
    
    try {
        // Fetch old image to delete it if exists
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$userSession['id']]);
        $oldImage = $stmt->fetchColumn();
        
        if ($oldImage) {
            $oldFilename = basename(parse_url($oldImage, PHP_URL_PATH));
            if (file_exists('../../uploads/profiles/' . $oldFilename)) {
                unlink('../../uploads/profiles/' . $oldFilename);
            }
        }

        $updateStmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $updateStmt->execute([$imageUrl, $userSession['id']]);
        
        echo json_encode(['success' => true, 'message' => 'อัปเดตรูปโปรไฟล์สำเร็จ', 'profile_image' => $imageUrl]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปโหลดไฟล์ได้']);
}
