<?php
require 'config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE jobs");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo $e->getMessage();
}
