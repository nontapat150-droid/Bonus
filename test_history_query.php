<?php
require 'config/db.php';

try {
    $pdo->exec("ALTER TABLE checkins ADD COLUMN IF NOT EXISTS lat VARCHAR(50) DEFAULT NULL, ADD COLUMN IF NOT EXISTS lng VARCHAR(50) DEFAULT NULL");
} catch (Exception $e) {
    echo "First alter error: " . $e->getMessage() . "\n";
    try { $pdo->exec("ALTER TABLE checkins ADD COLUMN lat VARCHAR(50) DEFAULT NULL"); } catch (Exception $e2) { echo "lat: " . $e2->getMessage() . "\n"; }
    try { $pdo->exec("ALTER TABLE checkins ADD COLUMN lng VARCHAR(50) DEFAULT NULL"); } catch (Exception $e2) { echo "lng: " . $e2->getMessage() . "\n"; }
}

try {
    $sql = "SELECT c.id, c.checkin_time, c.image_path, c.lat, c.lng, u.full_name, u.allow_late_time, t.team_name, TIME(c.checkin_time) as time_only
            FROM checkins c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE 1=1 ORDER BY c.checkin_time DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Success: " . count($records) . " records found.";
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
