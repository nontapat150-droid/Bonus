<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT i.id, i.status, l.target_user_id, l.action FROM inventory_items i JOIN (SELECT item_id, MAX(id) as max_id FROM inventory_logs GROUP BY item_id) latest ON i.id = latest.item_id JOIN inventory_logs l ON latest.max_id = l.id LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
