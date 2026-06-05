<?php
require_once __DIR__ . '/config/firebase.php';

$jsonPath = __DIR__ . '/apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json';

// Test fetching time just like config does
$chTime = curl_init('https://google.com');
curl_setopt($chTime, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chTime, CURLOPT_HEADER, true);
curl_setopt($chTime, CURLOPT_NOBODY, true);
curl_setopt($chTime, CURLOPT_SSL_VERIFYPEER, false);
$resTime = curl_exec($chTime);
curl_close($chTime);

if (preg_match('/^Date:\s+(.*)$/mi', $resTime, $matches)) {
    echo "Google Time: " . trim($matches[1]) . " => " . strtotime(trim($matches[1])) . "\n";
} else {
    echo "Google Time fetch failed. Output:\n" . $resTime . "\n";
}

$accessToken = getFirebaseAccessToken($jsonPath);
echo "Access Token: ";
var_dump($accessToken);

echo "\nTesting sendFirebasePush...\n";
$res1 = sendFirebasePush('Test Subject', 'Hello from the new JSON key!', 'global');
echo "Result global: " . var_export($res1, true) . "\n";
?>
