<?php
// api/dispatch/auto_assign.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$quotas = $input['quotas'] ?? []; // format: [{ team_name: "tech1", limit: 5 }, ...]

if (empty($quotas)) {
    echo json_encode(['success' => false, 'error' => 'No quotas provided']);
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

    // Ensure teams exist and get IDs
    $teamMap = [];
    foreach ($quotas as $q) {
        $tName = $q['team_name'];
        $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
        $stmt->execute([$tName]);
        $tId = $stmt->fetchColumn();

        if (!$tId) {
            $stmtInsert = $pdo->prepare("INSERT INTO teams (team_name) VALUES (?)");
            $stmtInsert->execute([$tName]);
            $tId = $pdo->lastInsertId();
        }
        $teamMap[$tName] = [
            'id' => $tId,
            'limit' => intval($q['limit']),
            'assigned' => 0
        ];
    }

    // Get unassigned jobs with valid coordinates
    $stmtJobs = $pdo->query("SELECT id, lat, lng FROM jobs WHERE team_id IS NULL AND lat IS NOT NULL AND lng IS NOT NULL");
    $unassignedJobs = $stmtJobs->fetchAll();

    if (empty($unassignedJobs)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'No unassigned jobs found']);
        exit;
    }

    // Calculate centroid
    $sumLat = 0; $sumLng = 0; $count = 0;
    foreach ($unassignedJobs as $job) {
        $sumLat += (float)$job['lat'];
        $sumLng += (float)$job['lng'];
        $count++;
    }
    $centerLat = $sumLat / $count;
    $centerLng = $sumLng / $count;

    // Calculate distance to centroid for each job
    foreach ($unassignedJobs as &$job) {
        $job['distance'] = haversineDistance($centerLat, $centerLng, (float)$job['lat'], (float)$job['lng']);
    }
    unset($job);

    // Sort jobs by distance ascending
    usort($unassignedJobs, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    $stmtAssign = $pdo->prepare("UPDATE jobs SET team_id = ? WHERE id = ?");
    $assignedTotal = 0;

    foreach ($teamMap as $teamName => &$teamInfo) {
        while ($teamInfo['assigned'] < $teamInfo['limit'] && !empty($unassignedJobs)) {
            $job = array_shift($unassignedJobs);
            $stmtAssign->execute([$teamInfo['id'], $job['id']]);
            $teamInfo['assigned']++;
            $assignedTotal++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'assigned' => $assignedTotal]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
