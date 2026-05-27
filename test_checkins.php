<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT * FROM checkins");
$checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Checkins count: " . count($checkins) . "\n";
if (count($checkins) > 0) {
    print_r($checkins[0]);
}

$date = date('Y-m-d');
$stmt2 = $pdo->prepare("
        SELECT 
            c.id, 
            c.checkin_time, 
            u.full_name, 
            u.username,
            t.team_name,
            c.image_path
        FROM checkins c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE DATE(c.checkin_time) = ?
        ORDER BY c.checkin_time DESC
");
$stmt2->execute([$date]);
echo "API result count: " . count($stmt2->fetchAll()) . "\n";
