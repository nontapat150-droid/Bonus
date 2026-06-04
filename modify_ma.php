<?php
$file = __DIR__ . '/api/dispatch/ma_summary.php';
$content = file_get_contents($file);

// 1. Update totalMaJobs
$old1 = "SELECT COUNT(*) FROM ma_jobs\n        WHERE plan_arrival_date BETWEEN ? AND ?";
$new1 = "SELECT COUNT(*) FROM ma_jobs\n        WHERE plan_arrival_date BETWEEN ? AND ? AND status = 'completed'";
// Also handle CRLF
$old1_win = "SELECT COUNT(*) FROM ma_jobs\r\n        WHERE plan_arrival_date BETWEEN ? AND ?";
$new1_win = "SELECT COUNT(*) FROM ma_jobs\r\n        WHERE plan_arrival_date BETWEEN ? AND ? AND status = 'completed'";

$content = str_replace($old1, $new1, $content);
$content = str_replace($old1_win, $new1_win, $content);

// 2. Update work_days UNION query part 1
$old2_1 = "        LEFT JOIN ma_checkins mc ON mc.user_id = u.id\n            AND DATE(mc.checkin_time) BETWEEN ? AND ?\n        WHERE u.status = 'approved'\n        GROUP BY u.id, u.full_name, u.username";
$new2_1 = "        LEFT JOIN ma_checkins mc ON mc.user_id = u.id\n            AND DATE(mc.checkin_time) BETWEEN ? AND ?\n            AND EXISTS (\n                SELECT 1 FROM ma_jobs j \n                WHERE j.plan_arrival_date = DATE(mc.checkin_time) \n                  AND (j.assigned_user_id = u.id OR (u.team_id IS NOT NULL AND j.team_id = u.team_id))\n            )\n        WHERE u.status = 'approved'\n        GROUP BY u.id, u.full_name, u.username";

$old2_1_win = str_replace("\n", "\r\n", $old2_1);
$new2_1_win = str_replace("\n", "\r\n", $new2_1);

$content = str_replace($old2_1, $new2_1, $content);
$content = str_replace($old2_1_win, $new2_1_win, $content);

// 3. Update work_days UNION query part 2
$old2_2 = "        LEFT JOIN ma_checkins mc ON mc.user_id = u.id\n            AND DATE(mc.checkin_time) BETWEEN ? AND ?\n        WHERE u.status = 'approved' AND u.role = 'ma_technician'\n          AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id)\n        GROUP BY u.id, u.full_name, u.username";
$new2_2 = "        LEFT JOIN ma_checkins mc ON mc.user_id = u.id\n            AND DATE(mc.checkin_time) BETWEEN ? AND ?\n            AND EXISTS (\n                SELECT 1 FROM ma_jobs j \n                WHERE j.plan_arrival_date = DATE(mc.checkin_time) \n                  AND (j.assigned_user_id = u.id OR (u.team_id IS NOT NULL AND j.team_id = u.team_id))\n            )\n        WHERE u.status = 'approved' AND u.role = 'ma_technician'\n          AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id)\n        GROUP BY u.id, u.full_name, u.username";

$old2_2_win = str_replace("\n", "\r\n", $old2_2);
$new2_2_win = str_replace("\n", "\r\n", $new2_2);

$content = str_replace($old2_2, $new2_2, $content);
$content = str_replace($old2_2_win, $new2_2_win, $content);

file_put_contents($file, $content);
echo "Modification complete.\n";
