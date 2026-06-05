<?php
// config/firebase.php

function getFirebaseAccessToken($jsonPath) {
    if (!file_exists($jsonPath)) return null;
    $keyData = json_decode(file_get_contents($jsonPath), true);
    if (!$keyData) return null;

    $header = json_encode([
        'alg' => 'RS256', 
        'typ' => 'JWT',
        'kid' => $keyData['private_key_id']
    ]);
    
    // Fetch real UTC time to fix clock skew (System clock may be off)
    $chTime = curl_init('https://google.com');
    curl_setopt($chTime, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chTime, CURLOPT_HEADER, true);
    curl_setopt($chTime, CURLOPT_NOBODY, true);
    $resTime = curl_exec($chTime);
    curl_close($chTime);

    $now = time();
    if (preg_match('/^Date:\s+(.*)$/mi', $resTime, $matches)) {
        $now = strtotime(trim($matches[1]));
    }
    
    $exp = $now + 3600;

    $claim = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $keyData['token_uri'],
        'exp' => $exp,
        'iat' => $now
    ], JSON_UNESCAPED_SLASHES);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

    $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;
    $signature = '';
    openssl_sign($signatureInput, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $signatureInput . '.' . $base64UrlSignature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $keyData['token_uri']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? null;
}

function sendFirebasePush($title, $message, $type, $team_id = null, $target_user_id = null) {
    $jsonPath = __DIR__ . '/../apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json';
    $keyData = json_decode(file_get_contents($jsonPath), true);
    if (!$keyData) return false;
    $projectId = $keyData['project_id'] ?? 'apis-1cd5e';

    $accessToken = getFirebaseAccessToken($jsonPath);
    if (!$accessToken) return false;

    $topic = 'global';
    if ($type === 'user' && $target_user_id) {
        $topic = 'user_' . $target_user_id;
    } elseif ($type === 'team' && $team_id) {
        $topic = 'team_' . $team_id;
    } elseif ($type === 'admin_only') {
        $topic = 'admin';
    }

    $postData = [
        'message' => [
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $message
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>
