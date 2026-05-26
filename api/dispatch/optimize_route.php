<?php
// api/dispatch/optimize_route.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

try {
    $pdo->beginTransaction();

    // Fetch all teams with assigned, unfinished jobs
    $stmtTeams = $pdo->query("SELECT DISTINCT team_id FROM jobs WHERE team_id IS NOT NULL AND status != 'Finish'");
    $teams = $stmtTeams->fetchAll(PDO::FETCH_COLUMN);

    $stmtJobs = $pdo->prepare("SELECT id, lat, lng FROM jobs WHERE team_id = ? AND status != 'Finish' AND lat IS NOT NULL AND lng IS NOT NULL");
    $stmtUpdate = $pdo->prepare("UPDATE jobs SET seq = ?, map_link = ? WHERE id = ?");

    $processedTeams = 0;

    foreach ($teams as $teamId) {
        $stmtJobs->execute([$teamId]);
        $jobs = $stmtJobs->fetchAll();

        if (empty($jobs)) continue;

        $unvisited = $jobs;
        $route = [];

        // Start from the first job (could be replaced by technician's current location if known)
        $current = array_shift($unvisited);
        $route[] = $current;

        while (!empty($unvisited)) {
            $minDist = PHP_FLOAT_MAX;
            $nextIndex = -1;

            foreach ($unvisited as $index => $job) {
                $dist = haversineDistance((float)$current['lat'], (float)$current['lng'], (float)$job['lat'], (float)$job['lng']);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nextIndex = $index;
                }
            }

            if ($nextIndex !== -1) {
                $current = $unvisited[$nextIndex];
                $route[] = $current;
                array_splice($unvisited, $nextIndex, 1);
            } else {
                break;
            }
        }

        // Generate Map Link
        $origin = $route[0]['lat'] . ',' . $route[0]['lng'];
        $destination = $route[count($route)-1]['lat'] . ',' . $route[count($route)-1]['lng'];
        $waypoints = [];

        for ($i = 1; $i < count($route) - 1; $i++) {
            $waypoints[] = $route[$i]['lat'] . ',' . $route[$i]['lng'];
        }

        $mapLink = "https://www.google.com/maps/dir/?api=1&origin=$origin&destination=$destination";
        if (!empty($waypoints)) {
            $mapLink .= "&waypoints=" . implode('|', $waypoints);
        }

        // Update DB
        foreach ($route as $seq => $job) {
            $stmtUpdate->execute([$seq + 1, $mapLink, $job['id']]);
        }
        $processedTeams++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed_teams' => $processedTeams]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}