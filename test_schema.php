<?php $isApiCall=true; require 'config/db.php'; print_r($pdo->query('SHOW COLUMNS FROM oil_records')->fetchAll(PDO::FETCH_COLUMN)); ?>
