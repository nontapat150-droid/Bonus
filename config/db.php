<?php
// config/db.php
// ปิดการแสดงข้อผิดพลาดทางหน้าจอ เพื่อไม่ให้ข้อความไปรบกวน JSON
ini_set('display_errors', 0);

date_default_timezone_set('Asia/Bangkok');

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol . $domainName;
}

$host = 'localhost';
$db   = 'zvucfpsz_Ro';
$user = 'zvucfpsz_BO';
$pass = '@2*]BC9AuGO^%P&-';
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // ดักจับ Error และส่งกลับเป็น JSON เสมอสำหรับ API endpoints
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $phpSelf = $_SERVER['PHP_SELF'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    
    $isApiCall = (
        stripos($uri, '/api/') !== false ||
        stripos($script, '/api/') !== false ||
        stripos($phpSelf, '/api/') !== false ||
        stripos($accept, 'application/json') !== false
    );
    
    if ($isApiCall) {
        // Clear any previous output buffer
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        exit;
    } else {
        // Display a generic error for non‑API calls
        echo "<h2>ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</h2>";
        echo "<p>กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ตหรือสอบถามผู้ดูแลระบบ</p>";
        exit;
    }
    exit;
}
?>