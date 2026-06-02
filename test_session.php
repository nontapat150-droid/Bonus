<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Test Admin';
ob_start();
require 'c:/xampp/htdocs/Github/Bonus/api/oil/get_team_plates.php';
$out = ob_get_clean();
file_put_contents('test_out.txt', $out);
echo "DONE";
