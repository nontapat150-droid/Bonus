<?php
// api/customer/delete.php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Delete jobs (finding by matching criteria used in grouping)
    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE access_no = ? OR customer = ? OR phone = ?");
    $stmt->execute([$id, $id, $id]);
    $jobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $oilSyncPairs = [];
    if (!empty($jobIds)) {
        $inQuery = implode(',', array_fill(0, count($jobIds), '?'));
        $oilSyncPairs = collectTeamOilMonthsForJobIds($pdo, $jobIds);

        // Delete job_logs
        $pdo->prepare("DELETE FROM job_logs WHERE job_id IN ($inQuery)")->execute($jobIds);
        
        // Delete job_close_3bb
        $pdo->prepare("DELETE FROM job_close_3bb WHERE job_id IN ($inQuery)")->execute($jobIds);
        
        // Delete jobs
        $pdo->prepare("DELETE FROM jobs WHERE id IN ($inQuery)")->execute($jobIds);
    }

    // 2. Delete start_day_records
    $stmt2 = $pdo->prepare("SELECT id FROM start_day_records WHERE non_number = ? OR customer_name = ?");
    $stmt2->execute([$id, $id]);
    $sdIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($sdIds)) {
        $inQuery2 = implode(',', array_fill(0, count($sdIds), '?'));
        
        // Fetch images to delete physical files
        try {
            $stmtImg = $pdo->prepare("SELECT image_path FROM start_day_images WHERE record_id IN ($inQuery2)");
            $stmtImg->execute($sdIds);
            $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($images as $img) {
                $path = '../../assets/uploads/start_day/' . $img;
                if (file_exists($path) && is_file($path)) @unlink($path);
                $path2 = '../../' . $img; // Fallback
                if (file_exists($path2) && is_file($path2)) @unlink($path2);
            }

            // Delete start_day_images
            $pdo->prepare("DELETE FROM start_day_images WHERE record_id IN ($inQuery2)")->execute($sdIds);
        } catch (Exception $e) {}
        
        // Delete start_day_records
        $pdo->prepare("DELETE FROM start_day_records WHERE id IN ($inQuery2)")->execute($sdIds);
    }
    
    // 3. Delete MA jobs
    $stmt3 = $pdo->prepare("SELECT id FROM ma_customers WHERE non_number = ? OR customer_name = ? OR phone = ?");
    $stmt3->execute([$id, $id, $id]);
    $maCustomerIds = $stmt3->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($maCustomerIds)) {
        $inQuery3 = implode(',', array_fill(0, count($maCustomerIds), '?'));
        
        // Fetch images to delete physical files
        try {
            $stmtMaImg = $pdo->prepare("SELECT image_path FROM ma_images WHERE ma_job_id IN (SELECT id FROM ma_customer_history WHERE customer_id IN ($inQuery3))");
            $stmtMaImg->execute($maCustomerIds);
            $maImages = $stmtMaImg->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($maImages as $img) {
                $path = '../../assets/uploads/ma_jobs/' . $img;
                if (file_exists($path) && is_file($path)) @unlink($path);
                $path2 = '../../' . $img; // Fallback
                if (file_exists($path2) && is_file($path2)) @unlink($path2);
            }
            
            $pdo->prepare("DELETE FROM ma_images WHERE ma_job_id IN (SELECT id FROM ma_customer_history WHERE customer_id IN ($inQuery3))")->execute($maCustomerIds);
        } catch (Exception $e) {}
        
        $pdo->prepare("DELETE FROM ma_customer_history WHERE customer_id IN ($inQuery3)")->execute($maCustomerIds);
        $pdo->prepare("DELETE FROM ma_customers WHERE id IN ($inQuery3)")->execute($maCustomerIds);
    }

    $pdo->commit();

    if (!empty($oilSyncPairs)) {
        syncCollectedTeamOilMonths($pdo, $oilSyncPairs);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
