<?php
// config/ma_job.php — ตัวช่วยระบบงาน MA

function ensureMaJobSchema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','technician','ma_technician','sales','intern') NOT NULL DEFAULT 'technician'");
    } catch (Exception $e) {}

    $tables = [
        "CREATE TABLE IF NOT EXISTS user_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_role_unique (user_id, role),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ma_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            non_number VARCHAR(50) NOT NULL UNIQUE,
            customer_name VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ma_customer_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            ma_job_id INT DEFAULT NULL,
            non_number VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            symptoms TEXT DEFAULT NULL,
            area_provider VARCHAR(10) DEFAULT NULL,
            remark TEXT DEFAULT NULL,
            tech_id INT DEFAULT NULL,
            team_id INT DEFAULT NULL,
            action_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES ma_customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ma_job_completion_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ma_job_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            uploaded_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ma_img_job (ma_job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ma_job_reschedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ma_job_id INT NOT NULL,
            previous_plan_date DATE DEFAULT NULL,
            new_plan_date DATE NOT NULL,
            remark TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ma_reschedule_job (ma_job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($tables as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) {}
    }

    $maCols = [
        'job_time' => 'VARCHAR(20) DEFAULT NULL',
        'symptoms' => 'TEXT DEFAULT NULL',
        'area_provider' => "ENUM('AIS','3BB') DEFAULT NULL",
        'team_name_import' => 'VARCHAR(100) DEFAULT NULL',
        'team_match_status' => "ENUM('matched','unmatched') DEFAULT NULL",
        'assigned_user_id' => 'INT DEFAULT NULL',
        'updated_at' => 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
    ];
    try {
        $existing = $pdo->query("SHOW COLUMNS FROM ma_jobs")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($maCols as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $pdo->exec("ALTER TABLE ma_jobs ADD COLUMN `$col` $type");
            }
        }
    } catch (Exception $e) {}
}

function normalizeMaAreaProvider($value) {
    $v = strtoupper(trim((string)$value));
    if ($v === 'AIS' || strpos($v, 'AIS') !== false) return 'AIS';
    if ($v === '3BB' || strpos($v, '3BB') !== false || strpos($v, '3 BB') !== false) return '3BB';
    return null;
}

function findTeamByName(PDO $pdo, $teamName) {
    $name = trim((string)$teamName);
    if ($name === '') return null;
    $stmt = $pdo->prepare("SELECT id, team_name FROM teams WHERE team_name = ? OR team_name LIKE ? LIMIT 1");
    $stmt->execute([$name, '%' . $name . '%']);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function upsertMaCustomer(PDO $pdo, $non, $customer, $phone, $address) {
    $non = trim((string)$non);
    if ($non === '') return null;

    $stmt = $pdo->prepare("SELECT id FROM ma_customers WHERE non_number = ? LIMIT 1");
    $stmt->execute([$non]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare("UPDATE ma_customers SET customer_name = COALESCE(NULLIF(?, ''), customer_name), phone = COALESCE(NULLIF(?, ''), phone), address = COALESCE(NULLIF(?, ''), address) WHERE id = ?")
            ->execute([$customer, $phone, $address, $existing['id']]);
        return (int)$existing['id'];
    }

    $pdo->prepare("INSERT INTO ma_customers (non_number, customer_name, phone, address) VALUES (?, ?, ?, ?)")
        ->execute([$non, $customer ?: null, $phone ?: null, $address ?: null]);
    return (int)$pdo->lastInsertId();
}

function addMaCustomerHistory(PDO $pdo, array $data) {
    $customerId = upsertMaCustomer(
        $pdo,
        $data['non_number'] ?? '',
        $data['customer_name'] ?? '',
        $data['phone'] ?? '',
        $data['address'] ?? ''
    );
    if (!$customerId) return null;

    $pdo->prepare("INSERT INTO ma_customer_history (customer_id, ma_job_id, non_number, action, symptoms, area_provider, remark, tech_id, team_id, action_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $customerId,
            $data['ma_job_id'] ?? null,
            $data['non_number'] ?? '',
            $data['action'] ?? 'imported',
            $data['symptoms'] ?? null,
            $data['area_provider'] ?? null,
            $data['remark'] ?? null,
            $data['tech_id'] ?? null,
            $data['team_id'] ?? null,
            $data['action_date'] ?? date('Y-m-d')
        ]);
    return $customerId;
}

function notifyMaJobAssignment(PDO $pdo, $teamId, $title, $message, $createdBy) {
    if (!$teamId) return;

    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'target_user_id'");
        if ($stmtCol->rowCount() === 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN target_user_id INT DEFAULT NULL AFTER team_id");
        }
    } catch (Exception $e) {}

    $stmtUsers = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND status = 'approved'");
    $stmtUsers->execute([$teamId]);
    $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    $ins = $pdo->prepare("INSERT INTO notifications (title, message, team_id, target_user_id, created_by) VALUES (?, ?, ?, ?, ?)");
    foreach ($userIds as $uid) {
        $ins->execute([$title, $message, $teamId, (int)$uid, $createdBy]);
    }
    if (empty($userIds)) {
        $ins->execute([$title, $message, $teamId, null, $createdBy]);
    }
}

function getRoleLabel($role) {
    $labels = [
        'technician' => 'ช่าง Office',
        'ma_technician' => 'ช่าง MA',
        'admin' => 'แอดมิน',
        'super_admin' => 'ผู้ดูแลระบบ',
        'intern' => 'เด็กฝึกงาน',
        'sales' => 'เซล'
    ];
    return $labels[$role] ?? $role;
}

function ensureMaCheckinSchema(PDO $pdo) {
    ensureMaJobSchema($pdo);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ma_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_late TINYINT(1) NOT NULL DEFAULT 0,
            KEY idx_ma_checkin_user (user_id),
            KEY idx_ma_checkin_time (checkin_time),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL
        )");
        $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('late_time_ma_technician', '08:30:00')");
    } catch (Exception $e) {}
}

function getMaCheckinLateTime(PDO $pdo) {
    ensureMaCheckinSchema($pdo);
    try {
        $val = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_time_ma_technician'")->fetchColumn();
        if ($val) {
            return date('H:i:s', strtotime($val));
        }
    } catch (Exception $e) {}
    return '08:30:00';
}

function userHasMaTechnicianRole(PDO $pdo, $userId) {
    if (hasRole('ma_technician')) return true;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role = 'ma_technician' LIMIT 1");
        $stmt->execute([(int)$userId]);
        if ($stmt->fetchColumn()) return true;
    } catch (Exception $e) {}
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([(int)$userId]);
    return $stmt->fetchColumn() === 'ma_technician';
}
