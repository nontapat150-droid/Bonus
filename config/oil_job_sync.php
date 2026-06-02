<?php
// config/oil_job_sync.php — sync completed dispatch cases to oil system (team + month)

/**
 * SQL expression for YYYY-MM from a datetime column (avoids % in PDO prepared statements).
 */
function sqlYearMonth(string $dateColumn): string
{
    return "CONCAT(YEAR({$dateColumn}), '-', LPAD(MONTH({$dateColumn}), 2, '0'))";
}

/**
 * Count distinct completed jobs closed for a team in a calendar month (by job_logs.timestamp).
 */
function countTeamCompletedCases(PDO $pdo, int $teamId, string $yearMonth): int
{
    $ym = sqlYearMonth('jl.timestamp');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT jl.job_id)
        FROM job_logs jl
        INNER JOIN jobs j ON j.id = jl.job_id
        WHERE j.team_id = ?
          AND jl.status = 'completed'
          AND {$ym} = ?
    ");
    $stmt->execute([$teamId, $yearMonth]);
    return (int)$stmt->fetchColumn();
}

/**
 * Upsert monthly case count for a team.
 */
function upsertTeamOilCases(PDO $pdo, int $teamId, string $yearMonth, int $count): void
{
    $stmt = $pdo->prepare("
        INSERT INTO team_oil_cases (team_id, `year_month`, case_count)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE case_count = VALUES(case_count), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$teamId, $yearMonth, $count]);
}

/**
 * Get team name by id.
 */
function getTeamNameById(PDO $pdo, int $teamId): ?string
{
    $stmt = $pdo->prepare("SELECT team_name FROM teams WHERE id = ? LIMIT 1");
    $stmt->execute([$teamId]);
    $name = $stmt->fetchColumn();
    return $name !== false ? (string)$name : null;
}

/**
 * Get team id by team name (oil_records.license_plate stores team_name).
 */
function getTeamIdByName(PDO $pdo, string $teamName): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ? LIMIT 1");
    $stmt->execute([$teamName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * Set job_count on all oil_records for a team in a given month.
 */
function syncOilRecordsJobCount(PDO $pdo, int $teamId, string $yearMonth, int $count): void
{
    $teamName = getTeamNameById($pdo, $teamId);
    if (!$teamName) {
        return;
    }

    $ym = sqlYearMonth('date_recorded');
    $stmt = $pdo->prepare("
        UPDATE oil_records
        SET job_count = ?
        WHERE license_plate = ?
          AND {$ym} = ?
    ");
    $stmt->execute([$count, $teamName, $yearMonth]);
}

/**
 * Recount completed cases, update team_oil_cases and oil_records for one team-month.
 */
function syncTeamOilMonth(PDO $pdo, int $teamId, string $yearMonth): int
{
    $count = countTeamCompletedCases($pdo, $teamId, $yearMonth);
    upsertTeamOilCases($pdo, $teamId, $yearMonth, $count);
    syncOilRecordsJobCount($pdo, $teamId, $yearMonth, $count);
    return $count;
}

/**
 * Get monthly completed case count (syncs if row missing or stale optional — always recount for accuracy).
 */
function getTeamMonthlyCaseCount(PDO $pdo, int $teamId, string $yearMonth, bool $sync = true): int
{
    if ($sync) {
        return syncTeamOilMonth($pdo, $teamId, $yearMonth);
    }

    $stmt = $pdo->prepare("
        SELECT case_count FROM team_oil_cases
        WHERE team_id = ? AND `year_month` = ?
        LIMIT 1
    ");
    $stmt->execute([$teamId, $yearMonth]);
    $row = $stmt->fetchColumn();
    return $row !== false ? (int)$row : 0;
}

/**
 * After a job close/delete: sync team-month from job's team and latest completed log month.
 */
function syncTeamOilFromJob(PDO $pdo, int $jobId, ?string $yearMonth = null): void
{
    $stmt = $pdo->prepare("SELECT team_id FROM jobs WHERE id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $teamId = $stmt->fetchColumn();
    if (!$teamId) {
        return;
    }
    $teamId = (int)$teamId;

    if ($yearMonth === null) {
        $ym = sqlYearMonth('timestamp');
        $stmtLog = $pdo->prepare("
            SELECT {$ym} AS ym
            FROM job_logs
            WHERE job_id = ? AND status = 'completed'
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        $stmtLog->execute([$jobId]);
        $yearMonth = $stmtLog->fetchColumn();
        if (!$yearMonth) {
            $yearMonth = date('Y-m');
        }
    }

    syncTeamOilMonth($pdo, $teamId, $yearMonth);
}

/**
 * Collect team+month pairs with completed closes for these jobs (call BEFORE deleting jobs/logs).
 *
 * @return array<int, array{team_id: int, ym_key: string}>
 */
function collectTeamOilMonthsForJobIds(PDO $pdo, array $jobIds): array
{
    $jobIds = array_values(array_filter(array_map('intval', $jobIds)));
    if (empty($jobIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $ym = sqlYearMonth('jl.timestamp');
    $stmt = $pdo->prepare("
        SELECT DISTINCT j.team_id, {$ym} AS ym_key
        FROM job_logs jl
        INNER JOIN jobs j ON j.id = jl.job_id
        WHERE jl.job_id IN ($placeholders)
          AND jl.status = 'completed'
          AND j.team_id IS NOT NULL
    ");
    $stmt->execute($jobIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recount and update oil case totals for pre-collected team-month pairs.
 */
function syncCollectedTeamOilMonths(PDO $pdo, array $pairs): void
{
    $seen = [];
    foreach ($pairs as $row) {
        $teamId = (int)($row['team_id'] ?? 0);
        $ym = (string)($row['ym_key'] ?? '');
        if ($teamId <= 0 || $ym === '') {
            continue;
        }
        $key = $teamId . '-' . $ym;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        syncTeamOilMonth($pdo, $teamId, $ym);
    }
}

/**
 * After jobs are removed: recount affected team-months (pairs collected before delete).
 */
function syncTeamOilAfterJobsDeleted(PDO $pdo, array $jobIds): void
{
    $pairs = collectTeamOilMonthsForJobIds($pdo, $jobIds);
    syncCollectedTeamOilMonths($pdo, $pairs);
}

/**
 * Resync all team-months that have oil records or stored case counts (e.g. after delete all jobs).
 */
function resyncAllKnownTeamOilMonths(PDO $pdo): void
{
    ensureTeamOilCasesTable($pdo);

    $pairs = [];
    $seen = [];

    $fromCases = $pdo->query("SELECT team_id, `year_month` AS ym_key FROM team_oil_cases")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fromCases as $row) {
        $key = (int)$row['team_id'] . '-' . $row['ym_key'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $pairs[] = $row;
        }
    }

    $ymOil = sqlYearMonth('o.date_recorded');
    $fromOil = $pdo->query("
        SELECT DISTINCT t.id AS team_id, {$ymOil} AS ym_key
        FROM oil_records o
        INNER JOIN teams t ON t.team_name = o.license_plate
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fromOil as $row) {
        $key = (int)$row['team_id'] . '-' . $row['ym_key'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $pairs[] = $row;
        }
    }

    syncCollectedTeamOilMonths($pdo, $pairs);
}

/**
 * Sync all team-month pairs that have completed job_logs (for backfill).
 */
function backfillAllTeamOilCases(PDO $pdo): array
{
    $ym = sqlYearMonth('jl.timestamp');
    $stmt = $pdo->query("
        SELECT DISTINCT j.team_id, {$ym} AS ym_key
        FROM job_logs jl
        INNER JOIN jobs j ON j.id = jl.job_id
        WHERE j.team_id IS NOT NULL
          AND jl.status = 'completed'
        ORDER BY j.team_id, ym_key
    ");
    $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $synced = 0;

    foreach ($pairs as $row) {
        syncTeamOilMonth($pdo, (int)$row['team_id'], $row['ym_key']);
        $synced++;
    }

    // Also sync months that have oil_records but no closes yet (job_count = 0)
    $seen = [];
    foreach ($pairs as $row) {
        $seen[(int)$row['team_id'] . '-' . $row['ym_key']] = true;
    }

    $ymOil = sqlYearMonth('o.date_recorded');
    $stmtOil = $pdo->query("
        SELECT DISTINCT t.id AS team_id, {$ymOil} AS ym_key
        FROM oil_records o
        INNER JOIN teams t ON t.team_name = o.license_plate
    ");
    foreach ($stmtOil->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int)$row['team_id'] . '-' . $row['ym_key'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        syncTeamOilMonth($pdo, (int)$row['team_id'], $row['ym_key']);
        $synced++;
    }

    return ['pairs_synced' => $synced];
}

/**
 * Ensure team_oil_cases table exists (idempotent).
 */
function ensureTeamOilCasesTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `team_oil_cases` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `team_id` int(11) NOT NULL,
          `year_month` varchar(7) NOT NULL,
          `case_count` int(11) NOT NULL DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_team_month` (`team_id`,`year_month`),
          CONSTRAINT `fk_team_oil_cases_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Sum completed cases in a date range (optionally one team by team name).
 */
function sumTeamCasesInDateRange(PDO $pdo, ?string $startDate, ?string $endDate, ?string $teamName = null): int
{
    $sql = "
        SELECT COALESCE(SUM(toc.case_count), 0)
        FROM team_oil_cases toc
        INNER JOIN teams t ON t.id = toc.team_id
        WHERE 1=1
    ";
    $params = [];

    if ($teamName) {
        $sql .= " AND t.team_name = ?";
        $params[] = $teamName;
    }

    if ($startDate && $endDate) {
        $startYm = date('Y-m', strtotime($startDate));
        $endYm = date('Y-m', strtotime($endDate));
        $sql .= " AND toc.`year_month` >= ? AND toc.`year_month` <= ?";
        $params[] = $startYm;
        $params[] = $endYm;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
