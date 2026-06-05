<?php
// api/notifications/get_notifications.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

date_default_timezone_set('Asia/Bangkok');

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'] ?? null;
$is_admin = hasRole(['admin', 'super_admin']);

try {
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'target_user_id'");
        if ($stmtCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN target_user_id INT DEFAULT NULL AFTER team_id");
            $pdo->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notif_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE");
        }
        
        $stmtCol2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_checkin_push_date'");
        if ($stmtCol2->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_checkin_push_date DATE DEFAULT NULL");
        }
        
        $stmtCol3 = $pdo->query("SHOW COLUMNS FROM teams LIKE 'last_job_push_date'");
        if ($stmtCol3->rowCount() == 0) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN last_job_push_date DATE DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // ดึงการแจ้งเตือนจากระบบ (แอดมินดูได้ทั้งหมด)
    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT n.id, n.title, n.message, n.team_id, t.team_name, n.target_user_id, tu.full_name as target_user_name, n.created_at,
            COALESCE(u.full_name, 'System') AS creator_name,
            CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM notifications n
            LEFT JOIN teams t ON n.team_id = t.id
            LEFT JOIN users u ON n.created_by = u.id
            LEFT JOIN users tu ON n.target_user_id = tu.id
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
            ORDER BY n.created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        // ของพนักงานทั่วไป ดึงเฉพาะที่ส่งหาตัวเอง หรือของทีมตัวเอง
        $sql = "SELECT n.id, n.title, n.message, n.team_id, t.team_name, n.target_user_id, tu.full_name as target_user_name, n.created_at,
            COALESCE(u.full_name, 'System') AS creator_name,
            CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM notifications n
            LEFT JOIN teams t ON n.team_id = t.id
            LEFT JOIN users u ON n.created_by = u.id
            LEFT JOIN users tu ON n.target_user_id = tu.id
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
            WHERE ((n.team_id IS NULL AND n.target_user_id IS NULL AND n.type != 'admin_only')
               OR (n.target_user_id = ?))";
        
        $params = [$user_id, $user_id];
        if ($team_id) {
            $sql .= " OR (n.team_id = ?)";
            $params[] = $team_id;
        }
        $sql .= " ORDER BY n.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $db_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================================
    // ระบบแจ้งเตือนอัจฉริยะ (Smart Alerts)
    // ==========================================
    $smart_notifications = [];
    $unreadCount = 0;

    $stmtUser = $pdo->prepare("SELECT allow_late_time, last_checkin_push_date FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $userLateTime = $userRow['allow_late_time'] ?: '08:30:00';
    $lastCheckinPush = $userRow['last_checkin_push_date'];

    $stmtCheckin = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE user_id = ? AND DATE(checkin_time) = CURDATE()");
    $stmtCheckin->execute([$user_id]);
    $hasCheckedIn = $stmtCheckin->fetchColumn() > 0;

    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    if (!$hasCheckedIn && $currentTime >= '06:00:00' && $currentTime <= $userLateTime) {
        $alertTitle = '⏰ ใกล้ถึงเวลาเช็คอินแล้วนะ!';
        $alertMsg = "คุณยังไม่ได้ทำการเช็คอินเข้างานในวันนี้ (เวลาเข้างานของคุณคือ $userLateTime) อย่าลืมอัปโหลดรูปเช็คอินนะครับ";
        
        $smart_notifications[] = [
            'id' => 'alert_checkin',
            'title' => $alertTitle,
            'message' => $alertMsg,
            'target_name' => 'เฉพาะคุณ',
            'creator_name' => 'ระบบอัตโนมัติ (AI)',
            'created_at' => date('Y-m-d H:i:s'),
            'is_read' => 0
        ];
        $unreadCount++;
        
        // Push Notification once per day
        if ($lastCheckinPush !== $currentDate) {
            $pdo->prepare("UPDATE users SET last_checkin_push_date = ? WHERE id = ?")->execute([$currentDate, $user_id]);
            if (file_exists('../../config/onesignal.php')) {
                require_once '../../config/onesignal.php';
                if (function_exists('sendOneSignalPush')) {
                    sendOneSignalPush($pdo, $alertTitle, $alertMsg, 'user', null, $user_id);
                }
            }
            if (file_exists('../../config/firebase.php')) {
                require_once '../../config/firebase.php';
                if (function_exists('sendFirebasePush')) {
                    sendFirebasePush($alertTitle, $alertMsg, 'user', null, $user_id);
                }
            }
        }
    }

    if ($team_id) {
        $stmtTeamPush = $pdo->prepare("SELECT last_job_push_date FROM teams WHERE id = ?");
        $stmtTeamPush->execute([$team_id]);
        $lastJobPush = $stmtTeamPush->fetchColumn();
        
        $stmtJob = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE team_id = ? AND (DATE(created_at) = CURDATE() OR plan_arrival_date = CURDATE())");
        $stmtJob->execute([$team_id]);
        $jobCount = $stmtJob->fetchColumn();

        if ($jobCount > 0) {
            $alertTitle = '📦 มีงานมอบหมายใหม่';
            $alertMsg = "วันนี้ทีมของคุณมีงานที่ต้องรับผิดชอบจำนวน $jobCount งาน กรุณาตรวจสอบแผนที่และรายละเอียดในระบบจัดส่งอัจฉริยะ";
            
            $smart_notifications[] = [
                'id' => 'alert_job',
                'title' => $alertTitle,
                'message' => $alertMsg,
                'target_name' => 'ทีมของคุณ',
                'creator_name' => 'ระบบจัดส่ง',
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => 0 
            ];
            $unreadCount++;
            
            // Push Notification once per day per team
            if ($lastJobPush !== $currentDate) {
                $pdo->prepare("UPDATE teams SET last_job_push_date = ? WHERE id = ?")->execute([$currentDate, $team_id]);
                if (file_exists('../../config/onesignal.php')) {
                    require_once '../../config/onesignal.php';
                    if (function_exists('sendOneSignalPush')) {
                        sendOneSignalPush($pdo, $alertTitle, $alertMsg, 'team', $team_id);
                    }
                }
                if (file_exists('../../config/firebase.php')) {
                    require_once '../../config/firebase.php';
                    if (function_exists('sendFirebasePush')) {
                        sendFirebasePush($alertTitle, $alertMsg, 'team', $team_id);
                    }
                }
            }
        }
    }

    // รวมการแจ้งเตือน
    foreach ($db_notifications as &$notification) {
        if (!empty($notification['target_user_id'])) {
            $notification['target_name'] = 'ส่งเฉพาะ: ' . $notification['target_user_name'];
        } elseif (!empty($notification['team_id'])) {
            $notification['target_name'] = 'ทีม: ' . $notification['team_name'];
        } else {
            $notification['target_name'] = 'ทุกคน';
        }

        $notification['is_read'] = (int)$notification['is_read'];
        if (!$notification['is_read']) {
            $unreadCount++;
        }
    }
    
    $final_notifications = array_merge($smart_notifications, $db_notifications);

    echo json_encode(['success' => true, 'notifications' => $final_notifications, 'unread_count' => $unreadCount]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>