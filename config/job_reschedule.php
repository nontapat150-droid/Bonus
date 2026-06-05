<?php
// config/job_reschedule.php — เลื่อนนัดติดตั้ง + แจ้งเตือนแอดมิน

function ensureJobReschedulesTable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `job_reschedules` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `job_id` int(11) NOT NULL,
          `job_log_id` int(11) DEFAULT NULL,
          `tech_id` int(11) NOT NULL,
          `team_id` int(11) DEFAULT NULL,
          `previous_plan_date` date DEFAULT NULL,
          `new_plan_date` date NOT NULL,
          `remark` text DEFAULT NULL,
          `notification_id` int(11) DEFAULT NULL,
          `acknowledged_by` int(11) DEFAULT NULL,
          `acknowledged_at` datetime DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_job_reschedules_job` (`job_id`),
          KEY `idx_job_reschedules_team` (`team_id`),
          KEY `idx_job_reschedules_new_date` (`new_plan_date`),
          CONSTRAINT `fk_job_reschedules_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_job_reschedules_tech` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_job_reschedules_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_job_reschedules_ack_user` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensureNotificationExtraColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('type', $cols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN `type` varchar(50) DEFAULT NULL AFTER message");
        }
        if (!in_array('is_global', $cols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN `is_global` tinyint(1) NOT NULL DEFAULT 0 AFTER `type`");
        }
        if (!in_array('reference_id', $cols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN `reference_id` int(11) DEFAULT NULL AFTER `is_global`");
        }
        $checked = true;
    } catch (Exception $e) {
        // ignore
    }
}

function formatThaiDateShort(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    return date('d/m/Y', $ts);
}

/**
 * สร้างแจ้งเตือนแอดมินเมื่อมีการเลื่อนนัด
 *
 * @return int|null notification id
 */
function notifyAdminJobRescheduled(
    PDO $pdo,
    array $job,
    array $techUser,
    ?string $teamName,
    ?string $previousDate,
    string $newDate,
    string $remark
): ?int {
    ensureNotificationExtraColumns($pdo);

    $techName = $techUser['full_name'] ?? $techUser['username'] ?? 'ช่าง';
    $teamLabel = $teamName ?: 'ไม่ระบุทีม';
    $customer = $job['customer'] ?? $job['access_no'] ?? '-';
    $accessNo = $job['access_no'] ?? '-';

    $title = "เลื่อนนัดติดตั้ง: {$accessNo}";
    $message = "ช่าง: {$techName}\nทีม: {$teamLabel}\nลูกค้า: {$customer}\n"
        . "เลื่อนจาก " . formatThaiDateShort($previousDate) . " → " . formatThaiDateShort($newDate);
    if ($remark !== '') {
        $message .= "\nหมายเหตุ: {$remark}";
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications (title, message, type, is_global, team_id, created_by)
        VALUES (?, ?, 'job_reschedule', 0, ?, ?)
    ");
    $stmt->execute([
        $title,
        $message,
        $job['team_id'] ?? null,
        (int)$techUser['id'],
    ]);
    
    $notifId = (int)$pdo->lastInsertId() ?: null;
    
    // Send push notification to all admins via OneSignal
    if (file_exists(__DIR__ . '/onesignal.php')) {
        require_once __DIR__ . '/onesignal.php';
        if (function_exists('sendOneSignalPush')) {
            sendOneSignalPush($pdo, $title, $message, 'admin_only');
        }
    }

    // Send push notification to all admins via Firebase
    if (file_exists(__DIR__ . '/firebase.php')) {
        require_once __DIR__ . '/firebase.php';
        if (function_exists('sendFirebasePush')) {
            sendFirebasePush($title, $message, 'admin_only');
        }
    }

    return $notifId;
}
