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
$pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('late_time_admin_tech', '08:00:00')");
$pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('late_time_sales', '08:30:00')");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole(['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $time = $data['late_time'] ?? '08:30';
    $target = $data['target'] ?? 'all';
    $time_formatted = date('H:i:s', strtotime($time)); // format to H:i:s
    
    if ($target === 'admin_tech') {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('late_time_admin_tech', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$time_formatted, $time_formatted]);
        $pdo->prepare("UPDATE users SET allow_late_time = ? WHERE role IN ('admin', 'super_admin', 'technician')")->execute([$time_formatted]);
    } elseif ($target === 'sales') {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('late_time_sales', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$time_formatted, $time_formatted]);
        $pdo->prepare("UPDATE users SET allow_late_time = ? WHERE role = 'sales'")->execute([$time_formatted]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('late_time_admin_tech', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$time_formatted, $time_formatted]);
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('late_time_sales', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$time_formatted, $time_formatted]);
        $pdo->prepare("UPDATE users SET allow_late_time = ?")->execute([$time_formatted]);
    }

    echo json_encode(['success' => true]);
    exit;
} else {
    $late_admin_tech = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_time_admin_tech'")->fetchColumn() ?: '08:00:00';
    $late_sales = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_time_sales'")->fetchColumn() ?: '08:30:00';
    echo json_encode([
        'success' => true, 
        'late_time_admin_tech' => date('H:i', strtotime($late_admin_tech)),
        'late_time_sales' => date('H:i', strtotime($late_sales))
    ]);
    exit;
}