<?php
// api/oil/get_team_plates.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/oil_job_sync.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่']);
    exit;
}

requireLogin();

$user_id = $_SESSION['user_id'];
$currentYearMonth = date('Y-m');

try {
    ensureTeamOilCasesTable($pdo);

    $stmtUser = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $myTeamId = $currentUser ? ($currentUser['team_id'] ?? null) : null;

    $isAdmin = hasRole(['admin', 'super_admin']);

    $baseSql = "
        SELECT t.id, t.team_name,
               COUNT(j.id) AS job_count,
               COALESCE(toc.case_count, 0) AS monthly_completed_cases
        FROM teams t
        LEFT JOIN jobs j ON j.team_id = t.id
        LEFT JOIN team_oil_cases toc ON toc.team_id = t.id AND toc.year_month = ?
    ";

    if ($isAdmin) {
        $stmt = $pdo->prepare($baseSql . " GROUP BY t.id, t.team_name, toc.case_count ORDER BY t.team_name ASC");
        $stmt->execute([$currentYearMonth]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if ($myTeamId) {
            $stmt = $pdo->prepare($baseSql . " WHERE t.id = ? GROUP BY t.id, t.team_name, toc.case_count");
            $stmt->execute([$currentYearMonth, $myTeamId]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare($baseSql . " GROUP BY t.id, t.team_name, toc.case_count ORDER BY t.team_name ASC");
            $stmt->execute([$currentYearMonth]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    foreach ($teams as &$team) {
        $team['monthly_completed_cases'] = (int)($team['monthly_completed_cases'] ?? 0);
        $team['job_count'] = (int)($team['job_count'] ?? 0);
    }
    unset($team);

    echo json_encode([
        'success' => true,
        'data' => $teams,
        'my_team_id' => $myTeamId,
        'current_year_month' => $currentYearMonth,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
