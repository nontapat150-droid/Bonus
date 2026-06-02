<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$date = $input['date'] ?? '';
$month = $input['month'] ?? '';

if (!$type) {
    echo json_encode(['success' => false, 'error' => 'ไม่ระบุประเภทข้อมูล']);
    exit;
}

try {
    $pdo->beginTransaction();
    $deletedCount = 0;

    $whereParams = [];
    $whereSql = "1=1";

    if ($date) {
        $whereSql = "DATE(date_col) = ?";
        $whereParams[] = $date;
    } else if ($month) {
        $whereSql = "DATE_FORMAT(date_col, '%Y-%m') = ?";
        $whereParams[] = $month;
    }

    if ($type === 'checkin') {
        $sql = str_replace('date_col', 'checkin_time', "SELECT id FROM checkins WHERE $whereSql");
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM checkins WHERE id IN ($inQuery)")->execute($ids);
            $deletedCount = count($ids);
        }
    } 
    else if ($type === 'start_day') {
        $sql = str_replace('date_col', 'created_at', "SELECT id FROM start_day_records WHERE $whereSql");
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            
            // Delete images
            $imgStmt = $pdo->prepare("SELECT image_path FROM start_day_images WHERE record_id IN ($inQuery)");
            $imgStmt->execute($ids);
            $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($images as $img) {
                @unlink('../../assets/uploads/start_day/' . $img);
                @unlink('../../' . $img);
            }
            $pdo->prepare("DELETE FROM start_day_images WHERE record_id IN ($inQuery)")->execute($ids);
            
            // Delete records
            $pdo->prepare("DELETE FROM start_day_records WHERE id IN ($inQuery)")->execute($ids);
            $deletedCount = count($ids);
        }
    } 
    else if ($type === 'oil') {
        $sql = str_replace('date_col', 'date_recorded', "SELECT id FROM oil_records WHERE $whereSql");
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM oil_records WHERE id IN ($inQuery)")->execute($ids);
            $deletedCount = count($ids);
        }
    }
    else if ($type === 'inventory') {
        // Clear items logs
        $sqlItems = str_replace('date_col', 'created_at', "SELECT * FROM inventory_logs WHERE $whereSql");
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($whereParams);
        $itemLogs = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itemLogs as $log) {
            if ($log['action'] === 'out') {
                $pdo->prepare("UPDATE inventory_items SET status = 'in_stock' WHERE id = ?")->execute([$log['item_id']]);
            } elseif ($log['action'] === 'in') {
                $pdo->prepare("DELETE FROM inventory_items WHERE id = ? AND status = 'in_stock'")->execute([$log['item_id']]);
            }
            $pdo->prepare("DELETE FROM inventory_logs WHERE id = ?")->execute([$log['id']]);
            $deletedCount++;
        }

        // Clear consumable logs
        $sqlConsumables = str_replace('date_col', 'created_at', "SELECT * FROM inventory_consumable_logs WHERE $whereSql");
        $stmtConsumables = $pdo->prepare($sqlConsumables);
        $stmtConsumables->execute($whereParams);
        $consumableLogs = $stmtConsumables->fetchAll(PDO::FETCH_ASSOC);

        foreach ($consumableLogs as $log) {
            if ($log['action'] === 'out') {
                $pdo->prepare("UPDATE inventory_consumable SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$log['qty'], $log['consumable_id']]);
                if ($log['target_user_id']) {
                    $pdo->prepare("UPDATE user_consumables SET qty = GREATEST(0, qty - ?) WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['target_user_id'], $log['consumable_id']]);
                }
            } elseif ($log['action'] === 'in') {
                $pdo->prepare("UPDATE inventory_consumable SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?")->execute([$log['qty'], $log['consumable_id']]);
            } elseif ($log['action'] === 'transfer') {
                $pdo->prepare("UPDATE user_consumables SET qty = GREATEST(0, qty - ?) WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['target_user_id'], $log['consumable_id']]);
                $pdo->prepare("UPDATE user_consumables SET qty = qty + ? WHERE user_id = ? AND consumable_id = ?")->execute([$log['qty'], $log['admin_id'], $log['consumable_id']]);
            }
            $pdo->prepare("DELETE FROM inventory_consumable_logs WHERE id = ?")->execute([$log['id']]);
            $deletedCount++;
        }
    }
    else if ($type === 'job_close') {
        $sql = str_replace('date_col', 'created_at', "SELECT * FROM job_close_3bb WHERE $whereSql");
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        $closes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($closes as $record) {
            $pdo->prepare("DELETE FROM job_close_3bb WHERE id = ?")->execute([$record['id']]);
            if (!empty($record['job_log_id'])) {
                $pdo->prepare("DELETE FROM job_logs WHERE id = ?")->execute([(int)$record['job_log_id']]);
            }
            $pdo->prepare("UPDATE jobs SET status = 'dispatched', remark = NULL WHERE id = ? AND status = 'completed'")
                ->execute([(int)$record['job_id']]);
            $deletedCount++;
        }
    } else {
        throw new Exception('ไม่รองรับประเภทข้อมูลนี้');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
