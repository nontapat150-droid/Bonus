<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

// สร้างตารางตั้งค่าอัตโนมัติหากยังไม่มี
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
)");
$roles = ['admin', 'super_admin', 'technician', 'sales'];
foreach($roles as $r) {
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('late_time_$r', '08:30:00')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole(['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $time = $data['late_time'] ?? '08:30';
    $role = $data['role'] ?? '';
    
    if (!$role) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุบทบาท']); exit;
    }

    $time_formatted = date('H:i:s', strtotime($time)); // format to H:i:s
    $setting_key = "late_time_" . $role;
    
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$setting_key, $time_formatted, $time_formatted]);
    
    // อัปเดตในตาราง users ให้ผู้ใช้ที่มีบทบาทนี้ด้วย
    $pdo->prepare("UPDATE users SET allow_late_time = ? WHERE role = ?")->execute([$time_formatted, $role]);

    echo json_encode(['success' => true]);
    exit;
} else {
    $settings = [];
    foreach($roles as $r) {
        $val = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_time_$r'")->fetchColumn() ?: '08:30:00';
        $settings[$r] = date('H:i', strtotime($val));
    }
    
    echo json_encode([
        'success' => true, 
        'settings' => $settings
    ]);
    exit;
}