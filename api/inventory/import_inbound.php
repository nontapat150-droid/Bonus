<?php
// api/inventory/import_inbound.php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!hasRole(['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลสินค้า']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$successCount = 0;
$errors = [];

$stats = [
    'imported_sns' => 0,
    'duplicate_sns' => 0,
    'products_count' => 0,
    'models_count' => 0
];
$uniqueProducts = [];
$uniqueModels = [];

try {
    $pdo->beginTransaction();

    // เตรียม SQL Statements สำหรับเช็คและบันทึก
    $stmtFindProdByName = $pdo->prepare("SELECT id, product_code FROM products WHERE name = ?");
    $stmtFindProdByCode = $pdo->prepare("SELECT id, name FROM products WHERE product_code = ?");
    $stmtInstProd = $pdo->prepare("INSERT INTO products (product_code, name) VALUES (?, ?)");
    $stmtUpdateProdCode = $pdo->prepare("UPDATE products SET product_code = ? WHERE id = ?");

    $stmtFindModel = $pdo->prepare("SELECT id FROM product_models WHERE product_id = ? AND model_name = ?");    
    $stmtInstModel = $pdo->prepare("INSERT INTO product_models (product_id, model_name) VALUES (?, ?)");        

    $stmtCheckSN = $pdo->prepare("SELECT id FROM inventory_items WHERE sn = ?");
    $stmtInstItem = $pdo->prepare("INSERT INTO inventory_items (model_id, sn, status) VALUES (?, ?, 'in_stock')");
    $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (item_id, action, admin_id) VALUES (?, 'in', ?)");     

    foreach ($items as $index => $item) {
        $pCodeInput = trim($item['product_code'] ?? '');
        $pName = trim($item['product_name'] ?? '');
        $mName = trim($item['model_name'] ?? '');
        $sn = trim($item['sn'] ?? '');

        // ตรวจสอบข้อมูลที่บังคับ
        if (empty($pName) || empty($mName)) {
            $rowNum = $index + 2; // +2 เพราะ row 0 = header, index 0 = row 1
            $errors[] = "แถวที่ $rowNum: ข้อมูลไม่สมบูรณ์ (ชื่อสินค้า: '$pName', รุ่น: '$mName')";
            continue;
        }

        // ถ้าไม่มี SN ให้สร้างอัตโนมัติ
        if (empty($sn)) {
            $sn = 'SYS-' . strtoupper(uniqid());
        }

        $stmtCheckSN->execute([$sn]);
        if ($stmtCheckSN->fetch()) {
            $stats['duplicate_sns']++;
            $errors[] = "แถวที่ " . ($index + 1) . ": หมายเลขซีเรียล '$sn' มีอยู่ในระบบแล้ว";
            continue;
        }

        $prodId = null;

        // 1. ค้นหาสินค้าจาก "รหัสสินค้า" ที่อัปโหลดมาก่อน
        if (!empty($pCodeInput)) {
            $stmtFindProdByCode->execute([$pCodeInput]);
            $existingByCode = $stmtFindProdByCode->fetch();
            if ($existingByCode) {
                // ถ้ารหัสซ้ำ แต่ชื่อไม่เหมือนกัน ให้ตีกลับ
                if (strtolower($existingByCode['name']) !== strtolower($pName)) {
                    $errors[] = "แถวที่ " . ($index + 1) . ": รหัสสินค้า '$pCodeInput' ถูกใช้งานแล้วกับสินค้าอื่น ({$existingByCode['name']})";
                    continue;
                }
                $prodId = $existingByCode['id'];
            }
        }

        // 2. ถ้าหารหัสไม่เจอ ลองหาจาก "ชื่อสินค้า"
        if (!$prodId) {
            $stmtFindProdByName->execute([$pName]);
            $existingByName = $stmtFindProdByName->fetch();
            if ($existingByName) {
                $prodId = $existingByName['id'];
                // ถ้าในระบบมีชื่อนี้อยู่แล้ว แต่รหัสในระบบยังเป็นรหัสสุ่ม (ออโต้) แล้วใน Excel ใส่รหัสใหม่มาให้ -> ให้อัปเดตรหัสเข้า DB
                if (!empty($pCodeInput) && $existingByName['product_code'] !== $pCodeInput) {
                    try {
                        $stmtUpdateProdCode->execute([$pCodeInput, $prodId]);
                    } catch (PDOException $e) {} // ข้าม error ถ้ารหัสซ้ำ
                }
            }
        }

        // 3. ถ้าไม่เจอทั้งชื่อและรหัส แสดงว่าเป็นสินค้าใหม่ ให้สร้างใหม่
        if (!$prodId) {
            $pCode = !empty($pCodeInput) ? $pCodeInput : 'P-' . strtoupper(substr(md5($pName . time()), 0, 6));
            try {
                $stmtInstProd->execute([$pCode, $pName]);
                $prodId = $pdo->lastInsertId();
            } catch (PDOException $e) {
                $errors[] = "แถวที่ " . ($index + 1) . ": รหัสสินค้าหรือชื่อซ้ำในระบบ";
                continue;
            }
        }

        // --- เพิ่ม Model ---
        $stmtFindModel->execute([$prodId, $mName]);
        $modelId = $stmtFindModel->fetchColumn();

        if (!$modelId) {
            $stmtInstModel->execute([$prodId, $mName]);
            $modelId = $pdo->lastInsertId();
        }

        // --- เพิ่ม Item และบันทึก Log ---
        $stmtInstItem->execute([$modelId, $sn]);
        $itemId = $pdo->lastInsertId();

        $stmtLog->execute([$itemId, $admin_id]);

        $uniqueProducts[$prodId] = true;
        $uniqueModels[$modelId] = true;
        $stats['imported_sns']++;
        $successCount++;
    }

    $stats['products_count'] = count($uniqueProducts);
    $stats['models_count'] = count($uniqueModels);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'imported' => $successCount,
        'stats' => $stats,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>