<?php
$file = 'c:/xampp/htdocs/Github/Bonus/api/oil/get_team_plates.php';
$c = file_get_contents($file);
$c = preg_replace('/^\xEF\xBB\xBF/', '', $c);
file_put_contents($file, $c);
echo "BOM removed if existed.\n";

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'technician';

ob_start();
require $file;
$out = ob_get_clean();

echo "Response length: " . strlen($out) . "\n";
echo "Response content:\n" . $out;
