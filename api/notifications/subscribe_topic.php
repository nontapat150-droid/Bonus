<?php
// api/notifications/subscribe_topic.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/firebase.php';

header('Content-Type: application/json');
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

$user = getCurrentUser();
$user_id = $user['id'];
$team_id = $user['team_id'] ?? null;
$is_admin = hasRole(['admin', 'super_admin']);

$jsonPath = __DIR__ . '/../../apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json';
$accessToken = getFirebaseAccessToken($jsonPath);

if (!$accessToken) {
    echo json_encode(['success' => false, 'error' => 'Failed to generate access token. Check JSON credentials.']);
    exit;
}

$topics = ['global', 'user_' . $user_id];
if ($team_id) {
    $topics[] = 'team_' . $team_id;
}
if ($is_admin) {
    $topics[] = 'admin'; // ตรงกับ config/firebase.php
}

$results = [];
foreach ($topics as $topic) {
    // ใช้ Instance ID API ของ Google เพื่อผูก Token เข้ากับ Topic
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/{$token}/rel/topics/{$topic}");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results[$topic] = ($httpCode === 200) ? 'success' : 'failed (' . $httpCode . ')';
}

echo json_encode(['success' => true, 'subscribed_topics' => $results]);
?>
