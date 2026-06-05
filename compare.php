<?php
function base64UrlEncode($text) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}

$jsonPath = __DIR__ . '/apis-1cd5e-firebase-adminsdk-fbsvc-2d77164594.json';
$keyData = json_decode(file_get_contents($jsonPath), true);

$header = json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $keyData['private_key_id']]);
$now = 1700000000;
$claim = json_encode([
    'iss' => $keyData['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud' => $keyData['token_uri'],
    'exp' => $now + 3600,
    'iat' => $now
], JSON_UNESCAPED_SLASHES);

$base64UrlHeader = base64UrlEncode($header);
$base64UrlClaim = base64UrlEncode($claim);
$signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;

$signature = '';
openssl_sign($signatureInput, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
$base64UrlSignature = base64UrlEncode($signature);

echo "Header: " . $header . "\n";
echo "Claim: " . $claim . "\n";
echo "Input: " . $signatureInput . "\n";
echo "Signature: " . $base64UrlSignature . "\n";
?>
