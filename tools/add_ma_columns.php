<?php
require 'config/db.php';
$cols = [
    'subdistrict' => 'VARCHAR(100) DEFAULT NULL',
    'district' => 'VARCHAR(100) DEFAULT NULL',
    'ais' => 'VARCHAR(50) DEFAULT NULL',
    'provider_3bb' => 'VARCHAR(50) DEFAULT NULL',
    'price' => 'DECIMAL(10,2) DEFAULT NULL',
    'electricity_activity' => 'VARCHAR(100) DEFAULT NULL',
    'checkin_photo' => 'VARCHAR(50) DEFAULT NULL',
    'photo_taking' => 'VARCHAR(50) DEFAULT NULL',
    'close_job_2100' => 'VARCHAR(50) DEFAULT NULL',
    'notify_repair_sp' => 'VARCHAR(50) DEFAULT NULL',
    'close_note_not_match_soa' => 'VARCHAR(50) DEFAULT NULL',
    'signal_after_online' => 'VARCHAR(50) DEFAULT NULL',
    'power_rx' => 'VARCHAR(50) DEFAULT NULL',
    'line_bot_photo' => 'VARCHAR(50) DEFAULT NULL',
    'close_node_1200' => 'VARCHAR(50) DEFAULT NULL',
    'splice_cable' => 'VARCHAR(50) DEFAULT NULL',
    'sleeve_shrink_tube' => 'VARCHAR(50) DEFAULT NULL',
    'drop_wire_clamp' => 'VARCHAR(50) DEFAULT NULL',
    'patch_cord_out' => 'VARCHAR(50) DEFAULT NULL',
    'lan' => 'VARCHAR(50) DEFAULT NULL',
    'request_lmr' => 'VARCHAR(50) DEFAULT NULL',
    'splice_new' => 'VARCHAR(50) DEFAULT NULL',
    'ma_mat' => 'VARCHAR(50) DEFAULT NULL',
    'insect_bites_cable' => 'VARCHAR(50) DEFAULT NULL',
    'install_date' => 'DATE DEFAULT NULL',
    'install_cable_length' => 'DECIMAL(10,2) DEFAULT NULL',
    'install_technician' => 'VARCHAR(100) DEFAULT NULL',
    'line_bot' => 'VARCHAR(50) DEFAULT NULL',
    'cause' => 'TEXT DEFAULT NULL',
    'fix_action' => 'TEXT DEFAULT NULL',
    'old_sn_pb' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_pb' => 'VARCHAR(100) DEFAULT NULL',
    'old_sn_onu_router' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_onu_router' => 'VARCHAR(100) DEFAULT NULL',
    'old_sn_wifi' => 'VARCHAR(100) DEFAULT NULL',
    'new_sn_wifi' => 'VARCHAR(100) DEFAULT NULL',
    'source' => 'VARCHAR(100) DEFAULT NULL',
    'destination' => 'VARCHAR(100) DEFAULT NULL',
    'distance' => 'DECIMAL(10,2) DEFAULT NULL',
    'oil_price_per_liter' => 'DECIMAL(10,2) DEFAULT NULL',
    'oil_cost' => 'DECIMAL(10,2) DEFAULT NULL'
];

foreach ($cols as $col => $type) {
    try {
        $pdo->exec("ALTER TABLE ma_jobs ADD COLUMN `$col` $type");
        echo "Added $col\n";
    } catch (Exception $e) {
        echo "Skipped $col (might exist)\n";
    }
}
