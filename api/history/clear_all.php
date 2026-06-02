<?php
// api/history/clear_all.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'checkin';
$fDate = $input['date'] ?? '';
$fMonth = $input['month'] ?? '';

if (!in_array($type, ['checkin', 'start_day', 'oil', 'inventory', 'job_close'])) {
    echo json_encode(['success' => false, 'error' => 'ประเภทไม่รองรับ']);
    exit;
}

try {
    $pdo->beginTransaction();
    $deletedCount = 0;
    $dateCond = '';
    $params = [];
    if ($fDate) {
        $dateCond = "DATE(:col) = :date"; // placeholder will be replaced per query
    } elseif ($fMonth) {
        $dateCond = "DATE_FORMAT(:col, '%Y-%m') = :month";
    }

    switch ($type) {
        case 'checkin':
            // fetch ids and image paths for deletion
            $sqlSelect = "SELECT id, image_path FROM checkins";
            if ($fDate) $sqlSelect .= " WHERE DATE(checkin_time) = :date";
            elseif ($fMonth) $sqlSelect .= " WHERE DATE_FORMAT(checkin_time, '%Y-%m') = :month";
            $stmt = $pdo->prepare($sqlSelect);
            if ($fDate) $stmt->bindValue(':date', $fDate);
            elseif ($fMonth) $stmt->bindValue(':month', $fMonth);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($row['image_path']) {
                    $path = '../../assets/uploads/checkins/' . $row['image_path'];
                    if (file_exists($path) && is_file($path)) @unlink($path);
                }
            }
            $sqlDelete = "DELETE FROM checkins";
            if ($fDate) $sqlDelete .= " WHERE DATE(checkin_time) = :date";
            elseif ($fMonth) $sqlDelete .= " WHERE DATE_FORMAT(checkin_time, '%Y-%m') = :month";
            $stmtDel = $pdo->prepare($sqlDelete);
            if ($fDate) $stmtDel->bindValue(':date', $fDate);
            elseif ($fMonth) $stmtDel->bindValue(':month', $fMonth);
            $stmtDel->execute();
            $deletedCount = $stmtDel->rowCount();
            break;
        case 'start_day':
            // Delete images first
            $sqlSelect = "SELECT r.id, i.image_path FROM start_day_records r LEFT JOIN start_day_images i ON i.record_id = r.id";
            if ($fDate) $sqlSelect .= " WHERE DATE(r.created_at) = :date";
            elseif ($fMonth) $sqlSelect .= " WHERE DATE_FORMAT(r.created_at, '%Y-%m') = :month";
            $stmt = $pdo->prepare($sqlSelect);
            if ($fDate) $stmt->bindValue(':date', $fDate);
            elseif ($fMonth) $stmt->bindValue(':month', $fMonth);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($row['image_path']) {
                    $path = '../../assets/uploads/start_day/' . $row['image_path'];
                    if (file_exists($path) && is_file($path)) @unlink($path);
                }
            }
            $sqlDelete = "DELETE FROM start_day_records";
            if ($fDate) $sqlDelete .= " WHERE DATE(created_at) = :date";
            elseif ($fMonth) $sqlDelete .= " WHERE DATE_FORMAT(created_at, '%Y-%m') = :month";
            $stmtDel = $pdo->prepare($sqlDelete);
            if ($fDate) $stmtDel->bindValue(':date', $fDate);
            elseif ($fMonth) $stmtDel->bindValue(':month', $fMonth);
            $stmtDel->execute();
            $deletedCount = $stmtDel->rowCount();
            break;
        case 'oil':
            // Delete images
            $sqlSelect = "SELECT id, image_path FROM oil_records";
            if ($fDate) $sqlSelect .= " WHERE DATE(date_recorded) = :date";
            elseif ($fMonth) $sqlSelect .= " WHERE DATE_FORMAT(date_recorded, '%Y-%m') = :month";
            $stmt = $pdo->prepare($sqlSelect);
            if ($fDate) $stmt->bindValue(':date', $fDate);
            elseif ($fMonth) $stmt->bindValue(':month', $fMonth);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($row['image_path']) {
                    $path = '../../assets/uploads/oil_receipts/' . $row['image_path'];
                    if (file_exists($path) && is_file($path)) @unlink($path);
                }
            }
            $sqlDelete = "DELETE FROM oil_records";
            if ($fDate) $sqlDelete .= " WHERE DATE(date_recorded) = :date";
            elseif ($fMonth) $sqlDelete .= " WHERE DATE_FORMAT(date_recorded, '%Y-%m') = :month";
            $stmtDel = $pdo->prepare($sqlDelete);
            if ($fDate) $stmtDel->bindValue(':date', $fDate);
            elseif ($fMonth) $stmtDel->bindValue(':month', $fMonth);
            $stmtDel->execute();
            $deletedCount = $stmtDel->rowCount();
            break;
        case 'inventory':
            // Deleting inventory logs (no file cleanup needed)
            $sqlDelete = "DELETE FROM inventory_logs";
            if ($fDate) $sqlDelete .= " WHERE DATE(timestamp) = :date";
            elseif ($fMonth) $sqlDelete .= " WHERE DATE_FORMAT(timestamp, '%Y-%m') = :month";
            $stmtDel = $pdo->prepare($sqlDelete);
            if ($fDate) $stmtDel->bindValue(':date', $fDate);
            elseif ($fMonth) $stmtDel->bindValue(':month', $fMonth);
            $stmtDel->execute();
            $deletedCount = $stmtDel->rowCount();
            break;
        case 'job_close':
            // Delete job close records; also revert job status to 'in_progress' if needed
            $sqlSelect = "SELECT id, job_id FROM job_close_3bb";
            if ($fDate) $sqlSelect .= " WHERE DATE(created_at) = :date";
            elseif ($fMonth) $sqlSelect .= " WHERE DATE_FORMAT(created_at, '%Y-%m') = :month";
            $stmt = $pdo->prepare($sqlSelect);
            if ($fDate) $stmt->bindValue(':date', $fDate);
            elseif ($fMonth) $stmt->bindValue(':month', $fMonth);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                // revert job status
                $pdo->prepare("UPDATE jobs SET status = 'in_progress' WHERE id = ?")
                    ->execute([$row['job_id']]);
            }
            $sqlDelete = "DELETE FROM job_close_3bb";
            if ($fDate) $sqlDelete .= " WHERE DATE(created_at) = :date";
            elseif ($fMonth) $sqlDelete .= " WHERE DATE_FORMAT(created_at, '%Y-%m') = :month";
            $stmtDel = $pdo->prepare($sqlDelete);
            if ($fDate) $stmtDel->bindValue(':date', $fDate);
            elseif ($fMonth) $stmtDel->bindValue(':month', $fMonth);
            $stmtDel->execute();
            $deletedCount = $stmtDel->rowCount();
            break;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
