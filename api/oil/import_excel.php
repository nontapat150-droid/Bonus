<?php
// api/oil/import_excel.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$records = $input['records'] ?? [];

if (!is_array($records) || empty($records)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลเพื่อนำเข้า']);
    exit;
}

$errors = [];
$inserted = 0;

try {
    $pdo->beginTransaction();

    // Resolve tech_id from session; fallback to an admin/super_admin if missing or invalid
    $sessionTechId = $_SESSION['user_id'] ?? null;
    $techId = null;
    if ($sessionTechId) {
        $check = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $check->execute([$sessionTechId]);
        $found = $check->fetchColumn();
        if ($found) $techId = (int)$found;
    }
    if (!$techId) {
        $fallback = $pdo->query("SELECT id FROM users WHERE role IN ('admin','super_admin') ORDER BY id LIMIT 1")->fetchColumn();
        if ($fallback) $techId = (int)$fallback;
    }
    // as a last resort, try id=1 (often seeded superadmin)
    if (!$techId) $techId = 1;

    $stmt = $pdo->prepare("INSERT INTO oil_records (tech_id, license_plate, liters, mileage, price_per_liter, total_price, date_recorded) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($records as $index => $row) {
        $license_plate = trim($row['license_plate'] ?? '');
        $liters = floatval($row['liters'] ?? 0);
        $mileage = intval($row['mileage'] ?? 0);
        $price_per_liter = floatval($row['price_per_liter'] ?? 0);
        $total_price = isset($row['total_price']) ? floatval($row['total_price']) : round($liters * $price_per_liter, 2);
        $date_recorded = trim($row['date_recorded'] ?? '');

        if ($license_plate === '' || $liters <= 0 || $mileage < 0 || $price_per_liter <= 0) {
            $errors[] = "แถวที่ " . ($index + 2) . " ข้อมูลไม่ครบถ้วน: ป้ายทะเบียน, ลิตร, ไมล์, หรือราคาไม่ถูกต้อง";
            continue;
        }

        if ($date_recorded !== '') {
            $timestamp = strtotime($date_recorded);
            if ($timestamp === false) {
                $errors[] = "แถวที่ " . ($index + 2) . " รูปแบบวันที่ไม่ถูกต้อง: {$date_recorded}";
                continue;
            }
            $date_recorded = date('Y-m-d H:i:s', $timestamp);
        } else {
            $date_recorded = date('Y-m-d H:i:s');
        }

        $stmt->execute([
            $techId,
            $license_plate,
            $liters,
            $mileage,
            $price_per_liter,
            $total_price,
            $date_recorded
        ]);
        $inserted++;
    }

    $pdo->commit();
    // If there were any errors, write structured log for debugging
    $logInfo = null;
    if (!empty($errors)) {
        try {
            $logDir = __DIR__ . '/../../uploads/import_logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/import_excel_' . date('Ymd_His') . '.log';
            $lines = [];
            foreach ($errors as $err) {
                // If an error is already a JSON-able array, leave as-is
                if (is_array($err)) {
                    $lines[] = json_encode($err, JSON_UNESCAPED_UNICODE);
                } else {
                    $lines[] = json_encode(['message' => $err], JSON_UNESCAPED_UNICODE);
                }
            }
            file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND | LOCK_EX);
            $logInfo = basename($logFile);
        } catch (Exception $le) {
            // If logging fails, include minimal note in response
            $logInfo = null;
        }
    }

    echo json_encode(['success' => true, 'inserted' => $inserted, 'errors' => $errors, 'log' => $logInfo]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Write exception details to log for debugging
    try {
        $logDir = __DIR__ . '/../../uploads/import_logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $errFile = $logDir . '/import_exception_' . date('Ymd_His') . '.log';
        $details = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ];
        file_put_contents($errFile, json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $le) {
        // ignore logging failure
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'log' => isset($errFile) ? basename($errFile) : null]);
}
