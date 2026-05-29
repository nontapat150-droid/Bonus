<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');
requireLogin();

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'] ?? null;
$is_admin = hasRole(['admin', 'super_admin']);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        team_id INT DEFAULT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_notification_user (notification_id, user_id),
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT n.id, n.title, n.message, n.team_id, t.team_name, n.created_at,
            COALESCE(u.full_name, 'System') AS creator_name,
            CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM notifications n
            LEFT JOIN teams t ON n.team_id = t.id
            LEFT JOIN users u ON n.created_by = u.id
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
            ORDER BY n.created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        $sql = "SELECT n.id, n.title, n.message, n.team_id, t.team_name, n.created_at,
            COALESCE(u.full_name, 'System') AS creator_name,
            CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM notifications n
            LEFT JOIN teams t ON n.team_id = t.id
            LEFT JOIN users u ON n.created_by = u.id
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
            WHERE n.team_id IS NULL";
        $params = [$user_id];
        if ($team_id) {
            $sql .= " OR n.team_id = ?";
            $params[] = $team_id;
        }
        $sql .= " ORDER BY n.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unreadCount = 0;
    foreach ($notifications as &$notification) {
        $notification['team_name'] = $notification['team_name'] ?: 'ทุกทีม';
        $notification['is_read'] = (int)$notification['is_read'];
        if (!$notification['is_read']) {
            $unreadCount++;
        }
    }

    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unreadCount]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
