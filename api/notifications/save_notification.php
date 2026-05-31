<?php
// api/notifications/save_notification.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$type = $_POST['type'] ?? 'all'; 
$team_id = $_POST['team_id'] ?? null;
$target_user_id = $_POST['target_user_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($title === '' || $message === '') {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกหัวข้อและข้อความ']);
    exit;
}

if ($type === 'team' && empty($team_id)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกทีมเป้าหมาย']);
    exit;
}
if ($type === 'user' && empty($target_user_id)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเลือกพนักงานเป้าหมาย']);
    exit;
}

$final_team_id = ($type === 'team') ? (int)$team_id : null;
$final_target_user_id = ($type === 'user') ? (int)$target_user_id : null;
$created_by = $_SESSION['user_id'];

try {
    // เช็คคอลัมน์อย่างปลอดภัยก่อนเพิ่ม
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'target_user_id'");
        if ($stmtCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN target_user_id INT DEFAULT NULL AFTER team_id");
            $pdo->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notif_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE");
        }
    } catch (Exception $e) { }

    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, team_id, target_user_id, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $message, $final_team_id, $final_target_user_id, $created_by]);

    // ==========================================
    // 🚀 ส่ง Push Notification ผ่าน OneSignal
    // ==========================================
    if (file_exists('../../config/onesignal.php')) {
        require_once '../../config/onesignal.php';
        
        if (defined('ONESIGNAL_APP_ID') && ONESIGNAL_APP_ID !== 'ใส่_APP_ID_ที่นี่' &&
            defined('ONESIGNAL_REST_API_KEY') && ONESIGNAL_REST_API_KEY !== 'ใส่_REST_API_KEY_ที่นี่') {
            
            $content = array(
                "en" => $message,
                "th" => $message
            );
            $headings = array(
                "en" => $title,
                "th" => $title
            );

            $fields = array(
                'app_id' => ONESIGNAL_APP_ID,
                'contents' => $content,
                'headings' => $headings,
                'url' => 'https://bonus2026.infinityfreeapp.com/' // URL เมื่อคลิกแจ้งเตือน
            );

            // กำหนดเป้าหมายการส่ง
            if ($type === 'user' && $final_target_user_id) {
                // ส่งรายบุคคล (ใช้ external_id ที่ผูกไว้ตอน login)
                $fields['include_aliases'] = array("external_id" => [(string)$final_target_user_id]);
                $fields['target_channel'] = "push";
            } elseif ($type === 'team' && $final_team_id) {
                // ส่งเป็นทีม: ดึง User ID ทั้งหมดในทีมนั้นมา
                $stmtTeam = $pdo->prepare("SELECT id FROM users WHERE team_id = ?");
                $stmtTeam->execute([$final_team_id]);
                $teamUsers = $stmtTeam->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($teamUsers)) {
                    // แปลงเป็น Array ของ String สำหรับ external_id
                    $externalIds = array_map('strval', $teamUsers);
                    $fields['include_aliases'] = array("external_id" => $externalIds);
                    $fields['target_channel'] = "push";
                } else {
                    // ไม่มีคนในทีม ข้ามการส่ง
                    $fields = null; 
                }
            } else {
                // ส่งให้ทุกคน (All Subscriptions)
                $fields['included_segments'] = array('Total Subscriptions');
            }

            if ($fields) {
                $fields = json_encode($fields);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                $response = curl_exec($ch);
                curl_close($ch);
                
                // Note: เราปล่อยผ่าน Error ของ cURL ไปเลย เพื่อไม่ให้ระบบหลังบ้านสะดุดหาก Push ส่งไม่ไป
            }
        }
    }
    // ==========================================

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>