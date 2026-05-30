<?php
// api/dispatch/auto_assign.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$quotas = $input['quotas'] ?? [];

if (empty($quotas)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุโควตา']);
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

function farthestSeed(array $jobs, array $anchors) {
    if (empty($jobs)) return null;
    if (empty($anchors)) {
        $center = centroid($jobs);
        return nearestJobIndex($jobs, $center);
    }

    $bestIndex = null;
    $bestDistance = -1;
    foreach ($jobs as $index => $job) {
        $nearestAnchorDistance = PHP_FLOAT_MAX;
        foreach ($anchors as $anchor) {
            $distance = haversineDistance($anchor['lat'], $anchor['lng'], (float)$job['lat'], (float)$job['lng']);
            if ($distance < $nearestAnchorDistance) $nearestAnchorDistance = $distance;
        }
        if ($nearestAnchorDistance > $bestDistance) {
            $bestDistance = $nearestAnchorDistance;
            $bestIndex = $index;
        }
    }
    return $bestIndex;
}

function buildNearestRoute(array $jobs, ?array $startPoint = null) {
    if (empty($jobs)) return [];
    $unvisited = array_values($jobs);
    $route = [];
    $currentPoint = $startPoint ?: centroid($unvisited);

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

// Clustering: จัดกลุ่มงานให้เป็นบริเวณ เพื่อให้แต่ละทีมอยู่ในบริเวณเดียวกันแบบจำกัดจำนวน (Strict Quota)
function clusterJobsWithLimits(array $jobs, array $teamLimits, array $teamAnchors) {
    if (empty($jobs) || empty($teamLimits)) return [];
    
    $numClusters = count($teamLimits);
    $clusters = array_fill(0, $numClusters, []);
    $remainingJobs = array_values($jobs);
    $clusterCenters = [];
    
    // ขั้นตอนที่ 1: เลือก seed points สำหรับแต่ละ cluster
    for ($i = 0; $i < $numClusters; $i++) {
        if (isset($teamAnchors[$i]) && $teamAnchors[$i] !== null) {
            // ใช้ anchor ของงานที่ทีมนี้มีอยู่แล้ว เพื่อให้ได้งานใกล้บริเวณเดิม
            $clusterCenters[$i] = ['lat' => (float)$teamAnchors[$i]['lat'], 'lng' => (float)$teamAnchors[$i]['lng']];
        } else {
            // ทีมนี้ยังไม่มีงาน ให้เลือก seed จากงานที่เหลือให้ห่างกันที่สุด
            if (empty($clusterCenters) && !empty($remainingJobs)) {
                $center = centroid($remainingJobs);
                $seedIdx = nearestJobIndex($remainingJobs, $center);
            } else if (!empty($remainingJobs)) {
                $seedIdx = farthestSeed($remainingJobs, $clusterCenters);
            } else {
                $seedIdx = null;
            }
            
            if ($seedIdx !== null) {
                $seed = $remainingJobs[$seedIdx];
                $clusterCenters[$i] = ['lat' => (float)$seed['lat'], 'lng' => (float)$seed['lng']];
            } else {
                $clusterCenters[$i] = ['lat' => 0.0, 'lng' => 0.0];
            }
        }
    }
    
    $assigned = array_fill(0, $numClusters, 0);
    
    // ขั้นตอนที่ 2: Assign jobs ให้กับ cluster ที่ใกล้ที่สุดแบบ global greedy
    while (!empty($remainingJobs)) {
        $best = null;
        $bestClusterIdx = -1;
        
        foreach ($remainingJobs as $jobIdx => $job) {
            for ($cIdx = 0; $cIdx < $numClusters; $cIdx++) {
                // ถ้า cluster เต็มตามโควตาของทีมแล้ว ให้ข้ามไป
                if ($assigned[$cIdx] >= $teamLimits[$cIdx]) continue;
                
                if ($clusterCenters[$cIdx]['lat'] === 0.0 && $clusterCenters[$cIdx]['lng'] === 0.0) continue;
                
                $distance = haversineDistance(
                    $clusterCenters[$cIdx]['lat'],
                    $clusterCenters[$cIdx]['lng'],
                    (float)$job['lat'],
                    (float)$job['lng']
                );
                
                // หาคู่ (งาน, ทีม) ที่มีระยะทางใกล้ที่สุดจากทุกคู่ที่เป็นไปได้
                if ($best === null || $distance < $best['distance']) {
                    $best = [
                        'distance' => $distance,
                        'jobIdx' => $jobIdx,
                        'clusterIdx' => $cIdx
                    ];
                    $bestClusterIdx = $cIdx;
                }
            }
        }
        
        if ($best === null) break; // ทุกทีมได้งานครบตามโควตาแล้ว
        
        $job = $remainingJobs[$best['jobIdx']];
        $clusters[$bestClusterIdx][] = $job;
        $assigned[$bestClusterIdx]++;
        
        // Update cluster center โดยถ่วงน้ำหนักรวมกับ anchor เดิม(ถ้ามี) เพื่อไม่ให้ศูนย์กลางกระโดดหนีไปไกล
        $currentJobsForCentroid = $clusters[$bestClusterIdx];
        if (isset($teamAnchors[$bestClusterIdx]) && $teamAnchors[$bestClusterIdx] !== null) {
            $currentJobsForCentroid[] = $teamAnchors[$bestClusterIdx];
        }
        $clusterCenters[$bestClusterIdx] = centroid($currentJobsForCentroid);
        
        array_splice($remainingJobs, $best['jobIdx'], 1);
    }
    
    return $clusters;
}

try {
    $pdo->beginTransaction();

    $teamMap = [];
    foreach ($quotas as $q) {
        $teamName = trim($q['team_name'] ?? '');
        $limit = max(0, (int)($q['limit'] ?? 0));
        if ($teamName === '' || $limit <= 0) continue;

        $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
        $stmt->execute([$teamName]);
        $teamId = $stmt->fetchColumn();

        if (!$teamId) {
            $stmtInsert = $pdo->prepare("INSERT INTO teams (team_name) VALUES (?)");
            $stmtInsert->execute([$teamName]);
            $teamId = $pdo->lastInsertId();
        }

        $teamMap[$teamName] = [
            'id' => (int)$teamId,
            'limit' => $limit,
            'assigned' => 0,
            'route' => [],
            'anchor' => null,
            'current' => null
        ];
    }

    if (empty($teamMap)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุจำนวนงานให้ทีมอย่างน้อย 1 ทีม']);
        exit;
    }

    $activeSql = "(status IS NULL OR status NOT IN ('completed', 'failed', 'Finish'))";
    $stmtJobs = $pdo->query("SELECT id, lat, lng FROM jobs WHERE team_id IS NULL AND lat IS NOT NULL AND lng IS NOT NULL AND $activeSql ORDER BY plan_arrival_date ASC, id ASC");
    $remainingJobs = array_values(array_filter($stmtJobs->fetchAll(), function($job) {
        return isValidCoordinate($job['lat'], $job['lng']);
    }));

    if (empty($remainingJobs)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'ไม่พบงานรอจ่ายที่มีพิกัดถูกต้อง']);
        exit;
    }

    $stmtExisting = $pdo->prepare("SELECT id, lat, lng FROM jobs WHERE team_id = ? AND lat IS NOT NULL AND lng IS NOT NULL AND $activeSql");
    $anchors = [];
    foreach ($teamMap as $teamName => &$teamInfo) {
        $stmtExisting->execute([$teamInfo['id']]);
        $existingJobs = array_values(array_filter($stmtExisting->fetchAll(), function($job) {
            return isValidCoordinate($job['lat'], $job['lng']);
        }));
        if (!empty($existingJobs)) {
            $teamInfo['anchor'] = centroid($existingJobs);
            $anchors[] = $teamInfo['anchor'];
        }
    }
    unset($teamInfo);

    // ⭐ ขั้นตอนใหม่: Clustering - จัดกลุ่มงานให้ใกล้กันแบบ Strict Quota
    // วัตถุประสงค์: แต่ละบริเวณจะมีแค่ทีมเดียว ไม่มีทีมอื่นปนแน่นอน และตามจำนวนโควตาเป๊ะๆ
    $teamLimits = [];
    $teamAnchors = [];
    foreach ($teamMap as $teamName => $teamInfo) {
        $teamLimits[] = $teamInfo['limit'];
        $teamAnchors[] = $teamInfo['anchor'];
    }
    
    $jobClusters = clusterJobsWithLimits($remainingJobs, $teamLimits, $teamAnchors);
    
    // Assign clusters ให้กับทีม
    $clusterIdx = 0;
    foreach ($teamMap as $teamName => &$teamInfo) {
        if ($clusterIdx < count($jobClusters)) {
            $toAssign = $jobClusters[$clusterIdx];
            
            $teamInfo['route'] = $toAssign;
            $teamInfo['assigned'] = count($toAssign);
            
            if (!empty($toAssign)) {
                $teamInfo['anchor'] = centroid($toAssign);
            }
        }
        $clusterIdx++;
    }
    unset($teamInfo);
    
    $assignedTotal = 0;
    foreach ($teamMap as $teamInfo) {
        $assignedTotal += $teamInfo['assigned'];
    }

    if ($assignedTotal === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถจับคู่งานกับทีมได้']);
        exit;
    }

    $stmtAssign = $pdo->prepare("UPDATE jobs SET team_id = ? WHERE id = ?");
    foreach ($teamMap as $teamInfo) {
        foreach ($teamInfo['route'] as $job) {
            $stmtAssign->execute([$teamInfo['id'], $job['id']]);
        }
    }

    $stmtTeamJobs = $pdo->prepare("SELECT id, lat, lng FROM jobs WHERE team_id = ? AND lat IS NOT NULL AND lng IS NOT NULL AND $activeSql");
    $stmtUpdateRoute = $pdo->prepare("UPDATE jobs SET seq = ?, map_link = ? WHERE id = ?");
    $assignedByTeam = [];

    foreach ($teamMap as $teamName => $teamInfo) {
        if ($teamInfo['assigned'] === 0) continue;

        $stmtTeamJobs->execute([$teamInfo['id']]);
        $teamJobs = array_values(array_filter($stmtTeamJobs->fetchAll(), function($job) {
            return isValidCoordinate($job['lat'], $job['lng']);
        }));
        $route = buildNearestRoute($teamJobs, $teamInfo['anchor']);
        $mapLink = buildMapLink($route);

        foreach ($route as $seq => $job) {
            $stmtUpdateRoute->execute([$seq + 1, $mapLink, $job['id']]);
        }

        $assignedByTeam[] = [
            'team_name' => $teamName,
            'assigned' => $teamInfo['assigned']
        ];
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'assigned' => $assignedTotal,
        'teams' => $assignedByTeam
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
