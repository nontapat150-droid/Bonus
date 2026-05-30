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

function isValidCoordinate($lat, $lng) {
    if ($lat === null || $lng === null || $lat === '' || $lng === '') return false;
    $lat = (float)$lat;
    $lng = (float)$lng;
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && !($lat == 0.0 && $lng == 0.0);
}

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function centroid(array $jobs) {
    if (empty($jobs)) return null;
    $sumLat = 0.0;
    $sumLng = 0.0;
    foreach ($jobs as $job) {
        $sumLat += (float)$job['lat'];
        $sumLng += (float)$job['lng'];
    }
    return ['lat' => $sumLat / count($jobs), 'lng' => $sumLng / count($jobs)];
}

function nearestJobIndex(array $jobs, array $point) {
    $bestIndex = null;
    $bestDistance = PHP_FLOAT_MAX;
    foreach ($jobs as $index => $job) {
        $distance = haversineDistance($point['lat'], $point['lng'], (float)$job['lat'], (float)$job['lng']);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $bestIndex = $index;
        }
    }
    return $bestIndex;
}

function buildNearestRoute(array $jobs) {
    if (empty($jobs)) return [];
    $unvisited = array_values($jobs);
    $currentPoint = centroid($unvisited);
    $route = [];

    while (!empty($unvisited)) {
        $nextIndex = nearestJobIndex($unvisited, $currentPoint);
        if ($nextIndex === null) break;
        $current = $unvisited[$nextIndex];
        $route[] = $current;
        $currentPoint = ['lat' => (float)$current['lat'], 'lng' => (float)$current['lng']];
        array_splice($unvisited, $nextIndex, 1);
    }

    return $route;
}

function buildMapLink(array $route) {
    if (empty($route)) return null;
    $origin = $route[0]['lat'] . ',' . $route[0]['lng'];
    $destination = $route[count($route) - 1]['lat'] . ',' . $route[count($route) - 1]['lng'];
    $waypoints = [];

    for ($i = 1; $i < count($route) - 1; $i++) {
        $waypoints[] = $route[$i]['lat'] . ',' . $route[$i]['lng'];
    }

    $mapLink = "https://www.google.com/maps/dir/?api=1&origin=$origin&destination=$destination&travelmode=driving";
    if (!empty($waypoints)) {
        $mapLink .= "&waypoints=" . implode('|', $waypoints);
    }
    return $mapLink;
}

try {
    $pdo->beginTransaction();

    $activeSql = "(status IS NULL OR status NOT IN ('completed', 'failed', 'Finish'))";
    $stmtTeams = $pdo->query("SELECT DISTINCT team_id FROM jobs WHERE team_id IS NOT NULL AND $activeSql");
    $teams = $stmtTeams->fetchAll(PDO::FETCH_COLUMN);

    $stmtJobs = $pdo->prepare("SELECT id, lat, lng FROM jobs WHERE team_id = ? AND lat IS NOT NULL AND lng IS NOT NULL AND $activeSql");
    $stmtUpdate = $pdo->prepare("UPDATE jobs SET seq = ?, map_link = ? WHERE id = ?");

    $processedTeams = 0;

    foreach ($teams as $teamId) {
        $stmtJobs->execute([$teamId]);
        $jobs = array_values(array_filter($stmtJobs->fetchAll(), function($job) {
            return isValidCoordinate($job['lat'], $job['lng']);
        }));

        if (empty($jobs)) continue;

        $route = buildNearestRoute($jobs);
        $mapLink = buildMapLink($route);

        foreach ($route as $seq => $job) {
            $stmtUpdate->execute([$seq + 1, $mapLink, $job['id']]);
        }
        $processedTeams++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed_teams' => $processedTeams]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
