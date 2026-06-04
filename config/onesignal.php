<?php
// config/onesignal.php
// ไปสมัครใช้งาน OneSignal ฟรีที่ https://onesignal.com
// สร้าง App ใหม่ เลือกแพลตฟอร์ม Web Push
// นำ App ID และ REST API Key มาใส่ที่นี่

define('ONESIGNAL_APP_ID', 'a125af04-6897-44e7-9925-7d5b67631d12');
define('ONESIGNAL_REST_API_KEY', 'os_v2_app_ues26bdis5copgjfpvnwoyy5cjhdueloldeemnebxpohsdab5modixqwaowpzbyujv23zvuaqzgqj4tdpvv7url3qbpquupzxyai5cy');

function sendOneSignalPush($pdo, $title, $message, $type = 'all', $team_id = null, $target_user_id = null) {
    if (!defined('ONESIGNAL_APP_ID') || ONESIGNAL_APP_ID === 'ใส่_APP_ID_ที่นี่' || !defined('ONESIGNAL_REST_API_KEY') || ONESIGNAL_REST_API_KEY === 'ใส่_REST_API_KEY_ที่นี่') {
        return false;
    }

    $content = array("en" => $message, "th" => $message);
    $headings = array("en" => $title, "th" => $title);

    $fields = array(
        'app_id' => ONESIGNAL_APP_ID,
        'contents' => $content,
        'headings' => $headings,
        'url' => 'https://bonus2026.infinityfreeapp.com/'
    );

    if ($type === 'user' && $target_user_id) {
        $fields['include_aliases'] = array("external_id" => [(string)$target_user_id]);
        $fields['target_channel'] = "push";
    } elseif ($type === 'team' && $team_id && $pdo) {
        $stmtTeam = $pdo->prepare("SELECT id FROM users WHERE team_id = ?");
        $stmtTeam->execute([$team_id]);
        $teamUsers = $stmtTeam->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($teamUsers)) {
            $externalIds = array_map('strval', $teamUsers);
            $fields['include_aliases'] = array("external_id" => $externalIds);
            $fields['target_channel'] = "push";
        } else {
            return false;
        }
    } else {
        $fields['included_segments'] = array('Total Subscriptions');
    }

    $fields = json_encode($fields);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    $logMsg = date('Y-m-d H:i:s') . " - TYPE: $type - TARGET: " . json_encode($fields) . " - RESPONSE: $response - ERROR: $error\n";
    file_put_contents(__DIR__ . '/onesignal.log', $logMsg, FILE_APPEND);
    
    return $response;
}
?>