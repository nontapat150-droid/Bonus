<?php
// api/dispatch/complete_job.php
// Deprecated: ใช้ api/dispatch/update_job_status.php พร้อม close_3bb แทน
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'success' => false,
    'message' => 'API นี้เลิกใช้งานแล้ว กรุณาใช้ฟอร์มปิดงาน 3BB บนหน้า Dispatch'
]);
