<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($stmt->fetch()) echo 'EXISTS'; else echo 'MISSING';
