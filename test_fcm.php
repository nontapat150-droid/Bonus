<?php
// Test sending a Firebase push notification directly through PHP
require_once __DIR__ . '/config/firebase.php';

echo "Testing sendFirebasePush...\n";
$res1 = sendFirebasePush('Test Subject', 'Hello from the new JSON key!', 'global');
echo "Result global: " . var_export($res1, true) . "\n";

$res2 = sendFirebasePush('Admin Subject', 'Hello admins!', 'admin_only');
echo "Result admin: " . var_export($res2, true) . "\n";
?>
