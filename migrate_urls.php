<?php
// migrate_urls.php
require 'config/db.php';

$base_url = "https://bonusais.com/assets/uploads";

$tables = [
    ['table' => 'checkins', 'col' => 'image_path', 'folder' => 'checkins'],
    ['table' => 'checkins', 'col' => 'checkout_image', 'folder' => 'checkins'],
    ['table' => 'ma_checkins', 'col' => 'image_path', 'folder' => 'ma_checkins'],
    ['table' => 'ma_checkins', 'col' => 'checkout_image', 'folder' => 'ma_checkins'],
    ['table' => 'start_day_images', 'col' => 'image_path', 'folder' => 'start_day'],
    ['table' => 'oil_images', 'col' => 'image_path', 'folder' => 'oil_receipts'],
    ['table' => 'ma_job_completion_images', 'col' => 'image_path', 'folder' => 'ma_jobs'],
];

foreach ($tables as $t) {
    $table = $t['table'];
    $col = $t['col'];
    $folder = $t['folder'];
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($stmt->rowCount() > 0) {
            $sql = "UPDATE `$table` SET `$col` = CONCAT('$base_url/$folder/', `$col`) 
                    WHERE `$col` IS NOT NULL AND `$col` != '' AND `$col` NOT LIKE 'http%' AND `$col` NOT LIKE 'assets/%'";
            $updated = $pdo->exec($sql);
            echo "Updated $updated rows in $table.$col\n";
        }
    } catch (Exception $e) {
        echo "Error on $table.$col: " . $e->getMessage() . "\n";
    }
}

// Special case: if any path was stored as 'assets/uploads/...' 
$tables2 = [
    ['table' => 'checkins', 'col' => 'image_path'],
    ['table' => 'checkins', 'col' => 'checkout_image'],
    ['table' => 'ma_checkins', 'col' => 'image_path'],
    ['table' => 'ma_checkins', 'col' => 'checkout_image'],
    ['table' => 'start_day_images', 'col' => 'image_path'],
    ['table' => 'oil_images', 'col' => 'image_path'],
    ['table' => 'ma_job_completion_images', 'col' => 'image_path'],
];

foreach ($tables2 as $t) {
    $table = $t['table'];
    $col = $t['col'];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($stmt->rowCount() > 0) {
            $sql = "UPDATE `$table` SET `$col` = CONCAT('https://bonusais.com/', `$col`) 
                    WHERE `$col` LIKE 'assets/%'";
            $updated = $pdo->exec($sql);
            if ($updated > 0) echo "Fixed $updated rows in $table.$col with 'assets/...' prefix\n";
        }
    } catch (Exception $e) { }
}

echo "Migration Complete.\n";
?>
