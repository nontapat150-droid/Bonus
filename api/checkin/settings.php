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
$pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('late_time', '08:30:00')");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole(['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $time = $data['late_time'] ?? '08:30';
    $time_formatted = date('H:i:s', strtotime($time)); // format to H:i:s
    
    $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'late_time'")->execute([$time_formatted]);
    echo json_encode(['success' => true]);
    exit;
} else {
    $late_time = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'")->fetchColumn() ?: '08:30:00';
    echo json_encode(['success' => true, 'late_time' => date('H:i', strtotime($late_time))]);
    exit;
}