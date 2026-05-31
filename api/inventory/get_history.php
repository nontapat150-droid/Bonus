<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$user_id = $user['id'];

$filter_team_id = $_GET['team_id'] ?? '';
$filter_user_id = $_GET['user_id'] ?? '';

try {
    $whereClauses1 = [];
    $whereClauses2 = [];
    $params1 = [];
    $params2 = [];

    if ($role === 'technician') {
        $whereClauses1[] = "(l1.target_user_id = ? OR l1.admin_id = ?)";
        $whereClauses2[] = "(l2.target_user_id = ? OR l2.admin_id = ?)";
        array_push($params1, $user_id, $user_id);
        array_push($params2, $user_id, $user_id);
    } else {
        if ($filter_team_id !== '') {
            $whereClauses1[] = "tu1.team_id = ?";
            $whereClauses2[] = "tu2.team_id = ?";
            array_push($params1, $filter_team_id);
            array_push($params2, $filter_team_id);
        }
        if ($filter_user_id !== '') {
            $whereClauses1[] = "l1.target_user_id = ?";
            $whereClauses2[] = "l2.target_user_id = ?";
            array_push($params1, $filter_user_id);
            array_push($params2, $filter_user_id);
        }
    }

    $whereSql1 = count($whereClauses1) > 0 ? "WHERE " . implode(" AND ", $whereClauses1) : '';
    $whereSql2 = count($whereClauses2) > 0 ? "WHERE " . implode(" AND ", $whereClauses2) : '';

    $sql = "
        SELECT * FROM (
            SELECT 
                l1.id,
                'sn' as log_type,
                l1.timestamp, 
                l1.action, 
                i.sn, 
                pm.model_name as product_name, 
                p.name as model_name, 
                u1.full_name as admin_name,
                t1a.team_name as admin_team,
                tu1.full_name as target_name,
                t1t.team_name as target_team
            FROM inventory_logs l1
            LEFT JOIN inventory_items i ON l1.item_id = i.id
            LEFT JOIN product_models pm ON i.model_id = pm.id
            LEFT JOIN products p ON pm.product_id = p.id
            LEFT JOIN users u1 ON l1.admin_id = u1.id
            LEFT JOIN teams t1a ON u1.team_id = t1a.id
            LEFT JOIN users tu1 ON l1.target_user_id = tu1.id
            LEFT JOIN teams t1t ON tu1.team_id = t1t.id
            $whereSql1

            UNION ALL

            SELECT 
                l2.id,
                'consumable' as log_type,
                l2.timestamp, 
                l2.action, 
                CONCAT(l2.qty, ' ', c.unit) as sn, 
                c.product_name, 
                'วัสดุสิ้นเปลือง' as model_name, 
                u2.full_name as admin_name,
                t2a.team_name as admin_team,
                tu2.full_name as target_name,
                t2t.team_name as target_team
            FROM inventory_consumable_logs l2
            LEFT JOIN inventory_consumable c ON l2.consumable_id = c.id
            LEFT JOIN users u2 ON l2.admin_id = u2.id
            LEFT JOIN teams t2a ON u2.team_id = t2a.id
            LEFT JOIN users tu2 ON l2.target_user_id = tu2.id
            LEFT JOIN teams t2t ON tu2.team_id = t2t.id
            $whereSql2
        ) as combined_logs
        ORDER BY timestamp DESC 
        LIMIT 1000
    ";
    
    $stmt = $pdo->prepare($sql);
    $allParams = array_merge($params1, $params2);
    $stmt->execute($allParams);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>