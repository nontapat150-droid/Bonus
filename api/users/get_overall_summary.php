<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'super_admin']);

$month = $_GET['month'] ?? date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

try {
    // Ensure days_off column exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN days_off JSON DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if exists
    }

    // 1. Get all active users
    $stmtUsers = $pdo->query("SELECT id, full_name, role, team_id, allow_late_time, days_off FROM users WHERE status = 'approved'");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get checkins (Office & Sales & Interns)
    $stmtCheckins = $pdo->prepare("SELECT user_id, DATE(checkin_time) as cdate, TIME(checkin_time) as ctime FROM checkins WHERE DATE(checkin_time) BETWEEN ? AND ?");
    $stmtCheckins->execute([$start_date, $end_date]);
    $checkins = $stmtCheckins->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get MA checkins
    $stmtMaCheckins = $pdo->prepare("SELECT user_id, DATE(checkin_time) as cdate, is_late FROM ma_checkins WHERE DATE(checkin_time) BETWEEN ? AND ?");
    $stmtMaCheckins->execute([$start_date, $end_date]);
    $ma_checkins = $stmtMaCheckins->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get oil records (count by tech_id)
    $stmtOil = $pdo->prepare("SELECT tech_id, COUNT(*) as cnt FROM oil_records WHERE date_recorded BETWEEN ? AND ? GROUP BY tech_id");
    $stmtOil->execute([$start_date, $end_date]);
    $oil_counts = $stmtOil->fetchAll(PDO::FETCH_KEY_PAIR);

    // 5. Get start day records
    $stmtStartDay = $pdo->prepare("SELECT user_id, COUNT(*) as cnt FROM start_day_records WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY user_id");
    $stmtStartDay->execute([$start_date, $end_date]);
    $start_day_counts = $stmtStartDay->fetchAll(PDO::FETCH_KEY_PAIR);

    // 6. Get MA Jobs completed
    $stmtMaJobs = $pdo->prepare("SELECT assigned_user_id, COUNT(*) as cnt FROM ma_jobs WHERE status = 'completed' AND DATE(updated_at) BETWEEN ? AND ? AND assigned_user_id IS NOT NULL GROUP BY assigned_user_id");
    $stmtMaJobs->execute([$start_date, $end_date]);
    $ma_job_counts = $stmtMaJobs->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtMaJobsByTeam = $pdo->prepare("SELECT team_id, COUNT(*) as cnt FROM ma_jobs WHERE status = 'completed' AND DATE(updated_at) BETWEEN ? AND ? AND team_id IS NOT NULL AND assigned_user_id IS NULL GROUP BY team_id");
    $stmtMaJobsByTeam->execute([$start_date, $end_date]);
    $ma_job_counts_by_team = $stmtMaJobsByTeam->fetchAll(PDO::FETCH_KEY_PAIR);

    // 7. Get Install Jobs completed (jobs table uses team_id, not assigned_user_id)
    $stmtInstallJobs = $pdo->prepare("SELECT team_id, COUNT(*) as cnt FROM jobs WHERE status = 'completed' AND DATE(updated_at) BETWEEN ? AND ? AND team_id IS NOT NULL GROUP BY team_id");
    $stmtInstallJobs->execute([$start_date, $end_date]);
    $install_job_counts = $stmtInstallJobs->fetchAll(PDO::FETCH_KEY_PAIR);

    // 8. Get Leave requests
    $stmtLeaves = $pdo->prepare("SELECT user_id, SUM(days) as sum_days FROM leave_requests WHERE status = 'approved' AND start_date <= ? AND end_date >= ? GROUP BY user_id");
    $stmtLeaves->execute([$end_date, $start_date]);
    $leave_counts = $stmtLeaves->fetchAll(PDO::FETCH_KEY_PAIR);

    // Prepare fast lookup arrays
    $checkinMap = [];
    foreach ($checkins as $c) {
        $checkinMap[$c['user_id']][$c['cdate']] = $c['ctime'];
    }
    
    $maCheckinMap = [];
    foreach ($ma_checkins as $c) {
        $maCheckinMap[$c['user_id']][$c['cdate']] = $c['is_late'];
    }

    $summary_total = ['on_time' => 0, 'late' => 0, 'day_off' => 0, 'leaves' => 0];

    // Determine end date for loop (don't loop past today)
    $today = date('Y-m-d');
    $loop_end = ($end_date > $today) ? $today : $end_date;

    $userStats = [];

    foreach ($users as $u) {
        $uid = $u['id'];
        $days_off = json_decode($u['days_off'] ?: '[]', true) ?: [];
        $allow_late = $u['allow_late_time'] ?: '08:30:00';
        
        $team_id = $u['team_id'];
        $ma_jobs = ($ma_job_counts[$uid] ?? 0) + ($team_id ? ($ma_job_counts_by_team[$team_id] ?? 0) : 0);
        $install_jobs = $team_id ? ($install_job_counts[$team_id] ?? 0) : 0;

        $stats = [
            'id' => $uid,
            'full_name' => $u['full_name'],
            'role' => $u['role'],
            'on_time' => 0,
            'late' => 0,
            'day_off' => 0,
            'oil_count' => $oil_counts[$uid] ?? 0,
            'start_day_count' => $start_day_counts[$uid] ?? 0,
            'ma_job_count' => $ma_jobs,
            'install_job_count' => $install_jobs,
            'leave_count' => $leave_counts[$uid] ?? 0,
            'history' => []
        ];

        // Loop through each day in the month up to loop_end
        $currentDate = $start_date;
        while ($currentDate <= $loop_end) {
            $dayName = date('l', strtotime($currentDate));
            $is_day_off = in_array($dayName, $days_off);
            
            $checked_in = false;
            $is_late = false;
            $status_text = '';
            
            if (isset($maCheckinMap[$uid][$currentDate])) {
                $checked_in = true;
                $is_late = (int)$maCheckinMap[$uid][$currentDate] === 1;
            } elseif (isset($checkinMap[$uid][$currentDate])) {
                $checked_in = true;
                $is_late = $checkinMap[$uid][$currentDate] > $allow_late;
            }
            
            if ($checked_in) {
                if ($is_late) {
                    $stats['late']++;
                    $summary_total['late']++;
                    $status_text = 'late';
                } else {
                    $stats['on_time']++;
                    $summary_total['on_time']++;
                    $status_text = 'on_time';
                }
            } else {
                if ($is_day_off) {
                    $stats['day_off']++;
                    $summary_total['day_off']++;
                    $status_text = 'day_off';
                } else {
                    $status_text = 'absent';
                }
            }

            if ($status_text !== 'absent') {
                $stats['history'][] = [
                    'date' => $currentDate,
                    'status' => $status_text
                ];
            }
            
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        $summary_total['leaves'] += $stats['leave_count'];
        
        // Sort history desc
        usort($stats['history'], function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $userStats[] = $stats;
    }

    // Sort userStats by total on_time desc
    usort($userStats, function($a, $b) {
        if ($a['on_time'] == $b['on_time']) {
            return $a['late'] - $b['late']; // Ascending late if on_time is equal
        }
        return $b['on_time'] - $a['on_time']; // Descending on_time
    });

    echo json_encode([
        'success' => true,
        'summary' => $summary_total,
        'users' => $userStats
    ]);

} catch (PDOException $e) {
    file_put_contents('debug.txt', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
