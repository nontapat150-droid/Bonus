<?php
require 'c:/xampp/htdocs/Github/Bonus/config/db.php';
try {
    $stmt = $pdo->query('SELECT t.id, t.team_name, COUNT(j.id) as job_count FROM teams t LEFT JOIN jobs j ON j.team_id = t.id GROUP BY t.id, t.team_name');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
