<?php
// api/dispatch/ma_summary.php — สรุปข้อมูล MA ตามเงื่อนไขบริษัท (super_admin)
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole('super_admin')) {
    echo json_encode(['success' => false, 'error' => 'เฉพาะผู้ดูแลระบบเท่านั้น']);
    exit;
}

ensureMaJobSchema($pdo);
ensureMaCheckinSchema($pdo);

$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$minWorkDays = 26;
$minMaJobs = 130;
$deadlineTime = getMaCheckinLateTime($pdo);

try {
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $jobStmt = $pdo->prepare("
        SELECT COUNT(*) FROM ma_jobs
        WHERE plan_arrival_date BETWEEN ? AND ? AND status = 'completed'
    ");
    $jobStmt->execute([$monthStart, $monthEnd]);
    $totalMaJobs = (int)$jobStmt->fetchColumn();

    // ช่าง MA เท่านั้น — นับวันทำงานจาก ma_checkins
    $workStmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username,
               COUNT(DISTINCT DATE(mc.checkin_time)) AS work_days,
               SUM(CASE WHEN mc.is_late = 0 THEN 1 ELSE 0 END) AS on_time_checkins,
               SUM(CASE WHEN mc.is_late = 1 THEN 1 ELSE 0 END) AS late_checkins
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'ma_technician'
        LEFT JOIN ma_checkins mc ON mc.user_id = u.id
            AND DATE(mc.checkin_time) BETWEEN ? AND ?
            AND EXISTS (
                SELECT 1 FROM ma_jobs j 
                WHERE j.plan_arrival_date = DATE(mc.checkin_time) 
                  AND (j.assigned_user_id = u.id OR (u.team_id IS NOT NULL AND j.team_id = u.team_id))
            )
        WHERE u.status = 'approved'
        GROUP BY u.id, u.full_name, u.username

        UNION

        SELECT u.id, u.full_name, u.username,
               COUNT(DISTINCT DATE(mc.checkin_time)) AS work_days,
               SUM(CASE WHEN mc.is_late = 0 THEN 1 ELSE 0 END) AS on_time_checkins,
               SUM(CASE WHEN mc.is_late = 1 THEN 1 ELSE 0 END) AS late_checkins
        FROM users u
        LEFT JOIN ma_checkins mc ON mc.user_id = u.id
            AND DATE(mc.checkin_time) BETWEEN ? AND ?
            AND EXISTS (
                SELECT 1 FROM ma_jobs j 
                WHERE j.plan_arrival_date = DATE(mc.checkin_time) 
                  AND (j.assigned_user_id = u.id OR (u.team_id IS NOT NULL AND j.team_id = u.team_id))
            )
        WHERE u.status = 'approved' AND u.role = 'ma_technician'
          AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id)
        GROUP BY u.id, u.full_name, u.username

        ORDER BY work_days DESC, full_name ASC
    ");
    $workStmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
    $technicians = $workStmt->fetchAll(PDO::FETCH_ASSOC);

    // ลบ duplicate จาก UNION (กรณีมีทั้ง user_roles และ role หลัก)
    $seen = [];
    $uniqueTechs = [];
    foreach ($technicians as $tech) {
        $id = (int)$tech['id'];
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $uniqueTechs[] = $tech;
    }
    $technicians = $uniqueTechs;

    foreach ($technicians as &$tech) {
        $tech['work_days'] = (int)$tech['work_days'];
        $tech['on_time_checkins'] = (int)$tech['on_time_checkins'];
        $tech['late_checkins'] = (int)$tech['late_checkins'];
        $tech['meets_work_days'] = $tech['work_days'] >= $minWorkDays;

        $maStmt = $pdo->prepare("
            SELECT COUNT(*) FROM ma_jobs j
            WHERE j.plan_arrival_date BETWEEN ? AND ?
              AND (j.assigned_user_id = ? OR j.team_id IN (SELECT team_id FROM users WHERE id = ? AND team_id IS NOT NULL))
              AND j.status = 'completed'
        ");
        $maStmt->execute([$monthStart, $monthEnd, $tech['id'], $tech['id']]);
        $tech['completed_ma_jobs'] = (int)$maStmt->fetchColumn();
    }
    unset($tech);

    $meetsJobQuota = $totalMaJobs >= $minMaJobs;
    $qualifiedTechs = array_filter($technicians, function($t) { return $t['meets_work_days']; });

    echo json_encode([
        'success' => true,
        'month' => $month,
        'conditions' => [
            'min_work_days' => $minWorkDays,
            'min_ma_jobs' => $minMaJobs,
            'checkin_source' => 'ma_checkins',
            'deadline_time' => date('H:i', strtotime($deadlineTime))
        ],
        'summary' => [
            'total_ma_jobs' => $totalMaJobs,
            'meets_job_quota' => $meetsJobQuota,
            'qualified_technicians' => count($qualifiedTechs),
            'total_technicians' => count($technicians)
        ],
        'technicians' => $technicians
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
