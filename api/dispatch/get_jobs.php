<?php
// api/dispatch/get_jobs.php

// 🌟 1. ปิดการโชว์ Error เป็น HTML แล้วให้พ่นเป็น JSON เสมอ
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $error['message']]);
        exit;
    }
});

require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/ma_job.php';

header('Content-Type: application/json');

// 🌟 2. ป้องกัน Session หมดอายุ แล้วโดนเด้งไปหน้าเว็บ HTML (ทำให้ JSON พัง)
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Session หมดอายุ: กรุณารีเฟรชหน้าเว็บและเข้าสู่ระบบใหม่']);
    exit;
}

$user = getCurrentUser();
$role = $user['role'] ?? 'technician';
$username = $user['username'] ?? '';
$user_id = $user['id'] ?? 0;

$filter_date = $_GET['date'] ?? 'all'; 
$filter_status = $_GET['status'] ?? 'active'; 

try {
    ensureMaJobSchema($pdo);
    // 🌟 3. ย้ายคำสั่ง SQL ชุดนี้มาไว้ใน try...catch เพื่อป้องกันระบบแครช
    $team_id = null;
    if ($user_id) {
        $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $team_id = $stmtUser->fetchColumn();
    }

    $jobType = $_GET['type'] ?? 'jobs';
    $isMa = ($jobType === 'ma');

    if ($isMa) {
        if (!canViewDispatchMA()) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ดูงาน MA']);
            exit;
        }
        if (!hasRole(['admin', 'super_admin']) && !hasRole('ma_technician')) {
            echo json_encode(['success' => false, 'error' => 'บัญชีนี้ไม่มีบทบาทช่าง MA']);
            exit;
        }
    } else {
        if (!canViewDispatchOffice()) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ดูงาน Office']);
            exit;
        }
        if (!hasRole(['admin', 'super_admin']) && !hasRole('technician')) {
            echo json_encode(['success' => false, 'error' => 'บัญชีนี้ไม่มีบทบาทช่าง Office']);
            exit;
        }
    }

    $table = $isMa ? 'ma_jobs' : 'jobs';

    $sql = "SELECT j.*, t.team_name 
            FROM {$table} j 
            LEFT JOIN teams t ON j.team_id = t.id 
            WHERE 1=1";
    $params = [];

    if (!hasRole(['admin', 'super_admin'])) {
        if ($isMa && hasRole('ma_technician')) {
            if ($team_id) {
                $sql .= " AND (j.team_id = ? OR t.team_name = ? OR j.assigned_user_id = ?)";
                $params[] = $team_id;
                $params[] = $username;
                $params[] = $user_id;
            } else {
                $sql .= " AND (t.team_name = ? OR j.assigned_user_id = ?)";
                $params[] = $username;
                $params[] = $user_id;
            }
        } elseif (!$isMa && hasRole('technician')) {
            if ($team_id) {
                $sql .= " AND (j.team_id = ? OR t.team_name = ?)";
                $params[] = $team_id;
                $params[] = $username;
            } else {
                $sql .= " AND t.team_name = ?";
                $params[] = $username;
            }
        }
    }

    if ($filter_date !== 'all' && !empty($filter_date)) {
        $sql .= " AND j.plan_arrival_date = ?";
        $params[] = $filter_date;
    }

    if ($filter_status === 'active') {
        // ไม่แสดงงานปิดสำเร็จและงานไม่สำเร็จ (งานเลื่อนนัดกลับมาเป็น status NULL)
        $sql .= " AND (j.status IS NULL OR j.status NOT IN ('completed', 'failed'))";
    }

    $sql .= " ORDER BY j.plan_arrival_date ASC, COALESCE(j.seq, 9999) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ข้อมูลเลื่อนนัด (เฉพาะงาน Office)
    if (!$isMa) {
        try {
            $jobIds = array_column($jobs, 'id');
            if (!empty($jobIds)) {
                $ph = implode(',', array_fill(0, count($jobIds), '?'));
                $rsStmt = $pdo->prepare("
                    SELECT jr.job_id, jr.previous_plan_date, jr.new_plan_date, jr.created_at
                    FROM job_reschedules jr
                    INNER JOIN (
                        SELECT job_id, MAX(id) AS max_id
                        FROM job_reschedules
                        WHERE job_id IN ($ph)
                        GROUP BY job_id
                    ) latest ON latest.max_id = jr.id
                ");
                $rsStmt->execute($jobIds);
                $rsMap = [];
                foreach ($rsStmt->fetchAll(PDO::FETCH_ASSOC) as $rs) {
                    $rsMap[(int)$rs['job_id']] = $rs;
                }
                foreach ($jobs as &$jobRow) {
                    $jid = (int)$jobRow['id'];
                    if (isset($rsMap[$jid])) {
                        $jobRow['last_reschedule_from'] = $rsMap[$jid]['previous_plan_date'];
                        $jobRow['last_reschedule_to'] = $rsMap[$jid]['new_plan_date'];
                    }
                }
                unset($jobRow);
            }
        } catch (Exception $e) {}
    } else {
        try {
            $jobIds = array_column($jobs, 'id');
            if (!empty($jobIds)) {
                $ph = implode(',', array_fill(0, count($jobIds), '?'));
                $rsStmt = $pdo->prepare("
                    SELECT mr.ma_job_id AS job_id, mr.previous_plan_date, mr.new_plan_date, mr.created_at
                    FROM ma_job_reschedules mr
                    INNER JOIN (
                        SELECT ma_job_id, MAX(id) AS max_id FROM ma_job_reschedules
                        WHERE ma_job_id IN ($ph) GROUP BY ma_job_id
                    ) latest ON latest.max_id = mr.id
                ");
                $rsStmt->execute($jobIds);
                $rsMap = [];
                foreach ($rsStmt->fetchAll(PDO::FETCH_ASSOC) as $rs) {
                    $rsMap[(int)$rs['job_id']] = $rs;
                }
                foreach ($jobs as &$jobRow) {
                    $jid = (int)$jobRow['id'];
                    if (isset($rsMap[$jid])) {
                        $jobRow['last_reschedule_from'] = $rsMap[$jid]['previous_plan_date'];
                        $jobRow['last_reschedule_to'] = $rsMap[$jid]['new_plan_date'];
                    }
                }
                unset($jobRow);
            }
        } catch (Exception $e) {}
    }

    $teams = [];
    if (hasRole(['admin', 'super_admin'])) {
        $stmtTeams = $pdo->query("SELECT * FROM teams");
        $teams = $stmtTeams->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true, 
        'data' => $jobs, 
        'teams' => $teams
    ]);

} catch (PDOException $e) {
    // ถ้ามีปัญหาเกี่ยวกับฐานข้อมูล เช่น คอลัมน์ขาดหาย จะแจ้งตรงนี้
    echo json_encode(['success' => false, 'error' => 'Database SQL Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'System Error: ' . $e->getMessage()]);
}
?>