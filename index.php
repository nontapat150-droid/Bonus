<?php
// index.php (Ultimate SaaS Dashboard)
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/ma_job.php';

requireLogin();
ensureMaJobSchema($pdo);
if (isset($_SESSION['user_id'])) {
    loadUserRolesIntoSession($pdo, (int)$_SESSION['user_id'], $_SESSION['role'] ?? null);
}
$user = getCurrentUser();
// Fetch profile image if exists
try {
    $stmtUser = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmtUser->execute([$user['id']]);
    $user['profile_image'] = $stmtUser->fetchColumn();
} catch (Exception $e) {
    $user['profile_image'] = null;
}
$page = $_GET['page'] ?? 'home';

if ($page === 'home') {
    if (function_exists('isSalesOnly') && isSalesOnly()) {
        $page = 'checkin';
    } elseif (function_exists('isInternOnly') && isInternOnly()) {
        $page = 'work_records';
    } elseif (function_exists('isMaTechnicianOnly') && isMaTechnicianOnly()) {
        $page = 'checkin';
    }
}

// Fetch Real-time Stats for Dashboard based on Role
$stats = [];

$popupAnnouncement = null;
$marqueeAnnouncement = null;
$realtimeFeed = [];

if ($page === 'home') {
    try {
        $userRole = $_SESSION['role'] ?? 'technician';
        $userId = $_SESSION['user_id'] ?? 0;
        
        // --- 1. Stats สำหรับช่าง Office (technician) ---
        if (hasRole('technician') && !isMaTechnicianOnly() && !isSalesOnly() && !isInternOnly()) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE team_id = (SELECT team_id FROM users WHERE id = ?) AND DATE(created_at) = CURDATE()");
            $stmt->execute([$userId]);
            $stats['tech_jobs_today'] = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT SUM(qty) FROM user_consumables WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['tech_bag_qty'] = $stmt->fetchColumn() ?: 0;

            $stmt = $pdo->prepare("SELECT SUM(total_price) FROM oil_records WHERE tech_id = ? AND DATE(date_recorded) = CURDATE()");
            $stmt->execute([$userId]);
            $stats['tech_oil_today'] = $stmt->fetchColumn() ?: 0;
        }

        // --- 2. Stats สำหรับช่าง MA (ma_technician) ---
        if (hasRole('ma_technician') || isMaTechnicianOnly()) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ma_jobs WHERE team_id = (SELECT team_id FROM users WHERE id = ?) AND DATE(created_at) = CURDATE()");
            $stmt->execute([$userId]);
            $stats['ma_jobs_today'] = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ma_jobs WHERE team_id = (SELECT team_id FROM users WHERE id = ?) AND status = 'completed' AND DATE(updated_at) = CURDATE()");
            $stmt->execute([$userId]);
            $stats['ma_jobs_completed'] = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ma_jobs WHERE team_id = (SELECT team_id FROM users WHERE id = ?) AND status != 'completed'");
            $stmt->execute([$userId]);
            $stats['ma_jobs_pending'] = $stmt->fetchColumn();
        }

        // --- 3. Stats สำหรับ Admin ---
        if (hasRole(['admin', 'super_admin'])) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE");
            $stats['admin_online_users'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM checkins WHERE DATE(checkin_time) = CURDATE()");
            $stats['admin_checked_in'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('technician', 'ma_technician', 'intern')");
            $totalFieldStaff = $stmt->fetchColumn();
            $stats['admin_not_checked_in'] = max(0, $totalFieldStaff - $stats['admin_checked_in']);

            $stmt = $pdo->query("SELECT (SELECT COUNT(*) FROM inventory_items WHERE status = 'in_stock') + (SELECT COALESCE(SUM(qty), 0) FROM inventory_consumable)");
            $stats['admin_total_stock'] = $stmt->fetchColumn();
        }

        // --- 4. Stats สำหรับ Super Admin ---
        if (hasRole('super_admin')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE");
            $stats['super_online_users'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $stats['super_total_users'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM ma_customers");
            $stats['super_total_non'] = $stmt->fetchColumn();
            
            $feedSql = "
                SELECT 'oil' as type, u.full_name, CONCAT('เพิ่มข้อมูลน้ำมัน ', o.total_price, ' บาท') as detail, o.date_recorded as action_time
                FROM oil_records o JOIN users u ON o.tech_id = u.id
                UNION ALL
                SELECT 'checkin' as type, u.full_name, 'เช็คอินเข้างาน' as detail, c.checkin_time as action_time
                FROM checkins c JOIN users u ON c.user_id = u.id
                UNION ALL
                SELECT 'inventory' as type, u.full_name, CONCAT('เบิกอุปกรณ์ ', i.qty, ' ชิ้น') as detail, i.timestamp as action_time
                FROM inventory_consumable_logs i JOIN users u ON i.target_user_id = u.id WHERE i.action = 'out'
                ORDER BY action_time DESC LIMIT 15
            ";
            try {
                $realtimeFeed = $pdo->query($feedSql)->fetchAll();
            } catch (Exception $e) { }
        }
        
        // --- 🚀 จัดการและดึงข้อมูลประกาศ ---
        // 0. Migration: สร้างตารางและเพิ่มคอลัมน์ที่ขาดหายสำหรับฐานข้อมูลเก่า
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) DEFAULT 'popup',
                title VARCHAR(255) DEFAULT NULL,
                message TEXT NOT NULL,
                image_url VARCHAR(255) DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // เพิ่มคอลัมน์ type ถ้ายังไม่มี (ตารางเก่า)
            $chk = $pdo->prepare("SHOW COLUMNS FROM announcements LIKE 'type'");
            $chk->execute();
            if (!$chk->fetch()) {
                $pdo->exec("ALTER TABLE announcements ADD COLUMN `type` VARCHAR(50) DEFAULT 'popup' AFTER `id`");
                $pdo->exec("UPDATE announcements SET `type` = 'popup' WHERE `type` IS NULL OR `type` = ''");
            }

            // เพิ่มคอลัมน์ title ถ้ายังไม่มี
            $chk2 = $pdo->prepare("SHOW COLUMNS FROM announcements LIKE 'title'");
            $chk2->execute();
            if (!$chk2->fetch()) {
                $pdo->exec("ALTER TABLE announcements ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `type`");
            }
            
            // สร้างตาราง issue_reports
            $pdo->exec("CREATE TABLE IF NOT EXISTS issue_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT,
                image_url VARCHAR(255) DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // 🚀 Tech Bag Migration: Update ENUMs
            $pdo->exec("ALTER TABLE inventory_items MODIFY COLUMN `status` enum('in_stock','outbound','used') NOT NULL DEFAULT 'in_stock'");
            $pdo->exec("ALTER TABLE inventory_logs MODIFY COLUMN `action` enum('in','out','transfer','used') NOT NULL");
            
            // เพิ่มคอลัมน์ user_id ถ้ายังไม่มี สำหรับบันทึกช่างที่กดใช้
            $chk3 = $pdo->prepare("SHOW COLUMNS FROM inventory_logs LIKE 'user_id'");
            $chk3->execute();
            if (!$chk3->fetch()) {
                $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN `user_id` INT DEFAULT NULL AFTER `target_user_id`");
            }
            
            $chk4 = $pdo->prepare("SHOW COLUMNS FROM inventory_consumable_logs LIKE 'user_id'");
            $chk4->execute();
            if (!$chk4->fetch()) {
                $pdo->exec("ALTER TABLE inventory_consumable_logs ADD COLUMN `user_id` INT DEFAULT NULL AFTER `target_user_id`");
            }
            
            // เพิ่มคอลัมน์ profile_image
            $chk5 = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_image'");
            $chk5->execute();
            if (!$chk5->fetch()) {
                $pdo->exec("ALTER TABLE users ADD COLUMN `profile_image` VARCHAR(255) DEFAULT NULL AFTER `full_name`");
            }
            
            // เพิ่มคอลัมน์ last_active
            $chk6 = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'last_active'");
            $chk6->execute();
            if (!$chk6->fetch()) {
                $pdo->exec("ALTER TABLE users ADD COLUMN `last_active` TIMESTAMP NULL DEFAULT NULL");
            }
        } catch (Exception $e) { /* ignore migration errors silently */ }

        // Migration เพิ่มเติม (แยก try-catch เพื่อป้องกันการข้ามเมื่อเกิด error)
        try {
            // สร้างตาราง work_records สำหรับเด็กฝึกงาน
            $pdo->exec("CREATE TABLE IF NOT EXISTS work_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                record_date DATE NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) { }

        try {
            // อัปเดต ENUM ของ users ให้รองรับ intern
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'technician', 'sales', 'intern') NOT NULL DEFAULT 'technician'");
        } catch (Exception $e) { }

        // 1. ลบประกาศที่หมดอายุอัตโนมัติ
        $pdo->exec("DELETE FROM announcements WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        // 2. ดึงประกาศแยกระหว่างป๊อปอัปและป้ายวิ่ง
        $stmtPopup = $pdo->query("SELECT * FROM announcements WHERE type = 'popup' ORDER BY id DESC LIMIT 1");
        $popupAnnouncement = $stmtPopup->fetch();

        $stmtMarquee = $pdo->query("SELECT * FROM announcements WHERE type = 'marquee' ORDER BY id DESC LIMIT 1");
        $marqueeAnnouncement = $stmtMarquee->fetch();
        
    } catch (PDOException $e) {}
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Bonus. | Smart Business Suite</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- OneSignal Web Push SDK -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
          appId: "a125af04-6897-44e7-9925-7d5b67631d12",
        });
        
        // ผูก OneSignal ID กับ User ID ในฐานข้อมูล เพื่อให้ส่งรายบุคคลได้
        <?php if(isset($user['id'])): ?>
            OneSignal.login("<?= $user['id'] ?>");
        <?php endif; ?>
      });
    </script>
    
    <style>
        /* === 1. COLOR SYSTEM === */
        :root {
            /* Surfaces */
            --c-bg:            #ECEEF5;
            --c-surface:       #FFFFFF;
            --c-surface-2:     #F7F8FC;
            --c-surface-3:     #F0F2FA;
            --c-overlay:       rgba(10, 10, 30, 0.50);

            /* Primary */
            --c-primary:       #6C5CE7;
            --c-primary-hover: #5A4BD1;
            --c-primary-active:#4839B8;
            --c-primary-faint: #EDE9FF;
            --c-primary-glow:  rgba(108, 92, 231, 0.25);

            /* Text */
            --c-text-1:   #0D0D1A;
            --c-text-2:   #4B4F6A;
            --c-text-3:   #9499B5;
            --c-text-inv: #FFFFFF;

            /* Border */
            --c-border:       #E2E5F0;
            --c-border-focus: #6C5CE7;
            --c-border-hover: #C7CADF;

            /* Semantic */
            --c-success:  #10B981;  --c-success-bg: #ECFDF5;  --c-success-text: #065F46;
            --c-warning:  #F59E0B;  --c-warning-bg: #FFFBEB;  --c-warning-text: #78350F;
            --c-danger:   #EF4444;  --c-danger-bg:  #FEF2F2;  --c-danger-text:  #991B1B;
            --c-info:     #3B82F6;  --c-info-bg:    #EFF6FF;  --c-info-text:    #1E40AF;
            --c-neutral:  #6B7280;  --c-neutral-bg: #F3F4F6;  --c-neutral-text: #374151;
        }

        /* === 2. SHADOW SYSTEM === */
        :root {
            --shadow-0: none;
            --shadow-1: 0 1px 2px rgba(10,10,30, 0.04), 0 2px 8px rgba(10,10,30, 0.06);
            --shadow-2: 0 2px 4px rgba(10,10,30, 0.04), 0 8px 20px rgba(10,10,30, 0.09);
            --shadow-3: 0 4px 8px rgba(10,10,30, 0.05), 0 16px 32px rgba(10,10,30, 0.12), 0 0 0 1px rgba(10,10,30, 0.04);
            --shadow-4: 0 8px 16px rgba(10,10,30, 0.06), 0 32px 64px rgba(10,10,30, 0.16), 0 0 0 1px rgba(10,10,30, 0.05);
            --shadow-5: 0 12px 24px rgba(10,10,30, 0.10), 0 40px 80px rgba(10,10,30, 0.20);
            --shadow-drawer: 4px 0 32px rgba(10,10,30, 0.14);
            --shadow-btn: 0 4px 14px rgba(108,92,231, 0.40);
            --shadow-btn-hover: 0 6px 24px rgba(108,92,231, 0.55);
            --shadow-focus: 0 0 0 3px rgba(108,92,231, 0.22), 0 0 0 1px rgba(108,92,231, 0.60);
        }

        /* === 5. ANIMATION & TRANSITION SYSTEM === */
        :root {
            --ease-out:    cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
            --ease-snap:   cubic-bezier(0.2, 0, 0, 1);

            --dur-instant: 80ms;
            --dur-fast:    140ms;
            --dur-normal:  220ms;
            --dur-slow:    340ms;
            --dur-slower:  500ms;
        }

        /* Global Reset & Typography */
        body {
            font-family: 'Inter', 'Sarabun', sans-serif;
            background-color: var(--c-bg);
            color: var(--c-text-2);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            margin: 0; padding: 0;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { color: var(--c-text-1); font-weight: 700; letter-spacing: -0.01em; }

        /* === 8. COMPONENT DETAILS === */
        .card {
            background: var(--c-surface);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow-1);
            transition: box-shadow var(--dur-normal) var(--ease-out), transform var(--dur-normal) var(--ease-out);
            border: 1px solid var(--c-border);
        }
        .card:hover {
            box-shadow: var(--shadow-2);
            transform: translateY(-2px);
        }

        .btn-primary {
            background-color: var(--c-primary);
            color: var(--c-text-inv);
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            transition: background var(--dur-instant) ease, box-shadow var(--dur-normal) var(--ease-out), transform var(--dur-fast) var(--ease-out);
            box-shadow: var(--shadow-1);
            cursor: pointer;
        }
        .btn-primary:hover { box-shadow: var(--shadow-btn-hover); transform: translateY(-1px); }
        .btn-primary:active { transform: scale(0.98) translateY(0); box-shadow: var(--shadow-btn); }

        .input {
            background: var(--c-surface-2);
            border: 1.5px solid var(--c-border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--c-text-1);
            transition: border-color var(--dur-fast) ease, box-shadow var(--dur-fast) ease, background var(--dur-fast) ease;
        }
        .input:hover { border-color: var(--c-border-hover); background: var(--c-surface); }
        .input:focus { border-color: var(--c-border-focus); box-shadow: var(--shadow-focus); background: var(--c-surface); outline: none; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D0D3E8; border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: #A0A4C0; }

        /* Custom utility classes based on spec */
        .text-kpi { font-size: 40px; line-height: 1; font-weight: 800; letter-spacing: -0.03em; color: var(--c-text-1); }
        .badge-success { background: var(--c-success-bg); color: var(--c-success-text); border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
        .badge-danger { background: var(--c-danger-bg); color: var(--c-danger-text); border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
        .icon-box-primary { width: 40px; height: 40px; border-radius: 10px; background: var(--c-primary-faint); color: var(--c-primary); display: flex; align-items: center; justify-content: center; }
        .icon-box-success { width: 40px; height: 40px; border-radius: 10px; background: var(--c-success-bg); color: var(--c-success); display: flex; align-items: center; justify-content: center; }
        
        /* 🚨 --- MARQUEE CSS --- */
        .marquee-wrapper {
            position: relative; display: flex; align-items: center; overflow: hidden;
            background: linear-gradient(90deg, #4f46e5, #7c3aed); color: #ffffff;
            border-radius: 12px; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3); height: 48px;
        }
        .marquee-badge {
            position: absolute; left: 0; top: 0; bottom: 0; z-index: 10;
            background: #4338ca; padding: 0 16px; display: flex; align-items: center;
            font-weight: 800; font-size: 14px; letter-spacing: 0.5px;
            box-shadow: 4px 0 12px rgba(0,0,0,0.15);
        }
        .marquee-content {
            padding-left: 100%; display: inline-block; white-space: nowrap;
            font-weight: 600; font-size: 15px; animation: marqueeScroll 25s linear infinite;
        }
        .marquee-content:hover { animation-play-state: paused; }
        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }

        /* === 6. SIDEBAR & MAIN CONTENT LAYOUT === */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 30;
            width: 260px;
            background: var(--c-surface);
            border-right: 1px solid var(--c-border);
            display: flex; flex-direction: column; overflow: hidden;
            transition: width var(--dur-slow) var(--ease-snap);
            will-change: width;
        }
        .sidebar.collapsed { width: 68px; }

        .sidebar-logo { height: 64px; padding: 0 16px; display: flex; align-items: center; border-bottom: 1px solid var(--c-border); overflow: hidden; flex-shrink: 0; }
        .sidebar-logo-text { margin-left: 10px; white-space: nowrap; opacity: 1; transition: opacity var(--dur-normal) ease, width var(--dur-slow) var(--ease-snap); }
        .collapsed .sidebar-logo-text { opacity: 0; width: 0; pointer-events: none; }

        .nav-item {
            display: flex; align-items: center; gap: 12px; margin: 2px 8px; padding: 10px 12px; border-radius: 10px; cursor: pointer; white-space: nowrap; overflow: hidden;
            transition: background var(--dur-fast) var(--ease-out), color var(--dur-fast) ease;
            color: var(--c-text-2); text-decoration: none; font-weight: 500; font-size: 14px;
        }
        .nav-item:hover { background: var(--c-primary-faint); color: var(--c-primary); }
        .nav-item.active { background: var(--c-primary); color: var(--c-text-inv); box-shadow: var(--shadow-btn); }
        .nav-item .icon { flex-shrink: 0; width: 20px; height: 20px; transition: transform var(--dur-fast) var(--ease-spring); }
        .nav-item:hover .icon { transform: scale(1.1); }
        .nav-label { opacity: 1; transition: opacity var(--dur-normal) ease; }
        .collapsed .nav-label { opacity: 0; pointer-events: none; }

        .collapsed .nav-item:hover::after {
            content: attr(data-label);
            position: fixed; left: 76px; background: var(--c-text-1); color: #fff; font-size: 12px; font-weight: 500; padding: 5px 10px; border-radius: 6px; white-space: nowrap; box-shadow: var(--shadow-3); z-index: 60; pointer-events: none; animation: dropIn var(--dur-fast) var(--ease-out) both;
        }

        .sidebar-toggle {
            position: absolute; top: 20px; right: -12px; width: 24px; height: 24px; background: var(--c-surface); border: 1px solid var(--c-border); border-radius: 50%; box-shadow: var(--shadow-1); cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 35;
            transition: background var(--dur-fast) ease, box-shadow var(--dur-normal) ease, transform var(--dur-slow) var(--ease-spring);
        }
        .sidebar-toggle:hover { background: var(--c-primary); box-shadow: var(--shadow-btn); }
        .sidebar-toggle:hover .chevron { color: white; }
        .sidebar-toggle .chevron { transition: transform var(--dur-slow) var(--ease-snap); }
        .collapsed .sidebar-toggle .chevron { transform: rotate(180deg); }

        .sidebar-user { padding: 12px 8px; border-top: 1px solid var(--c-border); margin-top: auto; flex-shrink: 0; }

        #main-content-area {
            margin-left: 260px;
            min-height: 100vh;
            display: flex; flex-direction: column;
            transition: margin-left var(--dur-slow) var(--ease-snap);
            will-change: margin-left;
        }
        #main-content-area.sidebar-collapsed { margin-left: 68px; }

        /* Top Bar */
        .topbar {
            height: 64px;
            position: sticky; top: 0; z-index: 40;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid rgba(226,229,240, 0.8);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
        }

        /* === 7. MOBILE LAYOUT (< 768px) === */
        @media (max-width: 767px) {
            .sidebar { display: none; }
            #main-content-area { margin-left: 0 !important; padding-bottom: calc(80px + env(safe-area-inset-bottom)); }
            
            .topbar {
                height: 56px; padding: 0 16px; gap: 12px; justify-content: space-between;
                padding-top: env(safe-area-inset-top);
            }

            .mobile-drawer {
                position: fixed; top: 0; left: 0; bottom: 0; width: 280px; background: var(--c-surface); z-index: 75; box-shadow: var(--shadow-drawer); transform: translateX(-100%); transition: transform var(--dur-slow) var(--ease-snap); overflow-y: auto; overscroll-behavior: contain; padding-bottom: env(safe-area-inset-bottom);
            }
            .mobile-drawer.open { transform: translateX(0); }

            .mobile-drawer-backdrop {
                position: fixed; inset: 0; background: var(--c-overlay); z-index: 70; transition: opacity var(--dur-slow) ease; opacity: 0; pointer-events: none;
            }
            .mobile-drawer-backdrop.visible { opacity: 1; pointer-events: all; }

            .bottom-tabs {
                position: fixed; bottom: 0; left: 0; right: 0; height: 64px; background: rgba(255,255,255,0.95); backdrop-filter: blur(16px); border-top: 1px solid var(--c-border); box-shadow: 0 -4px 20px rgba(10,10,30, 0.07); display: flex; z-index: 30; padding-bottom: env(safe-area-inset-bottom);
            }
            .tab-item {
                flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; color: var(--c-text-3); transition: color var(--dur-fast) ease; position: relative; text-decoration: none;
            }
            .tab-item.active { color: var(--c-primary); }
            .tab-item.active::before { content: ''; position: absolute; top: 0; width: 32px; height: 3px; background: var(--c-primary); border-radius: 0 0 4px 4px; }
            .tab-icon { transition: transform var(--dur-fast) var(--ease-spring); }
            .tab-item.active .tab-icon { transform: scale(1.15) translateY(-1px); }
            .tab-label { font-size: 10px; font-weight: 600; }

            .hide-mobile { display: none; }
            .kpi-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }

            /* === Table → Card List === */
            .data-table { min-width: 100% !important; border-collapse: separate !important; }
            .data-table thead { display: none !important; }
            .data-table tbody { display: block !important; width: 100% !important; }
            .data-table tbody tr {
                display: flex !important; flex-direction: column;
                padding: 14px 16px;
                border: 1px solid var(--c-border);
                border-radius: 12px;
                margin-bottom: 8px;
                background: var(--c-surface);
                box-shadow: var(--shadow-1);
                width: 100% !important;
                box-sizing: border-box;
            }
            .data-table td {
                padding: 6px 0 !important;
                border: none !important;
                display: flex !important; justify-content: space-between; align-items: center;
                text-align: right;
                gap: 8px;
                width: 100% !important;
                box-sizing: border-box;
            }
            .data-table td[colspan] {
                justify-content: center !important;
                text-align: center !important;
                flex-direction: column;
            }
            .data-table td[colspan] > * {
                text-align: center !important;
            }
            .data-table td::before {
                content: attr(data-label);
                font-size: 11px; font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--c-text-3);
                text-align: left;
                flex-shrink: 0;
            }
            .data-table td:empty { display: none; }
            .data-table td > * { text-align: right; }
        }
        @media (min-width: 768px) {
            .mobile-drawer, .mobile-drawer-backdrop, .bottom-tabs { display: none !important; }
            .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        }
    </style>
</head>
<body>

    <?php include 'views/layouts/sidebar.php'; ?>

    <div id="main-content-area">
        
        <header class="topbar">
            <div class="flex items-center gap-2">
                <button id="mobileMenuBtn" class="md:hidden p-2 -ml-2 text-slate-500 hover:text-slate-800">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <button id="openIssueReportBtn" class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-full transition-colors hidden sm:flex">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <span>รายงานปัญหา</span>
                </button>
                <!-- Mobile specific icon-only button -->
                <button id="openIssueReportBtnMobile" class="p-2 text-rose-500 hover:bg-rose-50 rounded-full transition-colors sm:hidden">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                </button>
                <?php if (!hasRole('intern')): ?>
                <!-- ปุ่มลางาน Desktop -->
                <button id="openLeaveModalBtn" class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-full transition-colors">
                    <i data-lucide="calendar-off" class="w-4 h-4"></i>
                    <span>ลางาน</span>
                </button>
                <!-- ปุ่มลางาน Mobile icon-only -->
                <button id="openLeaveModalBtnMobile" class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-full transition-colors sm:hidden">
                    <i data-lucide="calendar-off" class="w-5 h-5"></i>
                </button>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 ml-auto">
                <button id="guideModalBtn" class="p-2 text-[var(--c-text-2)] hover:bg-[var(--c-surface-2)] rounded-full transition-colors" title="คู่มือการใช้งาน">
                    <i data-lucide="book-open" class="w-6 h-6"></i>
                </button>
                <button id="notificationBell" class="relative p-2 text-[var(--c-text-2)] hover:bg-[var(--c-surface-2)] rounded-full transition-colors">
                    <i data-lucide="bell" class="w-6 h-6"></i>
                    <span id="notificationUnreadDot" class="hidden absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 bg-rose-500 text-white text-[9px] font-black rounded-full border-2 border-white shadow-sm">0</span>
                </button>
            </div>
        </header>

        <?php include 'views/components/user_manual.php'; ?>

        <div id="notificationModal" class="hidden fixed inset-0 z-50 bg-black/40 p-4 backdrop-blur-sm flex justify-center items-center">
            <div class="w-full max-w-3xl rounded-[32px] bg-white shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0 bg-white z-10">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">แจ้งเตือนจากระบบ</h2>
                        <p class="text-slate-500 text-sm">ข้อความระบบและจากแอดมินให้ทีมของคุณ</p>
                    </div>
                    <button id="closeNotificationModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="px-5 py-4 space-y-5 overflow-y-auto custom-scrollbar flex-1 relative">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shrink-0">
                        <div class="text-slate-600 text-sm">แจ้งเตือนใหม่: <span id="notificationCount" class="font-semibold">0</span></div>
                        <?php if (hasRole(['admin', 'super_admin'])): ?>
                        <button id="openNotificationCreate" class="inline-flex items-center justify-center rounded-2xl bg-sky-600 text-white px-4 py-2 text-sm font-bold hover:bg-sky-700 transition">เพิ่มการแจ้งเตือน</button>
                        <?php endif; ?>
                    </div>

                    <?php if (hasRole(['admin', 'super_admin'])): ?>
                    <div id="notificationCreateCard" class="hidden rounded-3xl bg-slate-50 border border-slate-200 p-5 space-y-4 shrink-0">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">หัวเรื่อง</label>
                            <input id="notificationTitle" type="text" placeholder="กรอกหัวเรื่อง" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">ข้อความ</label>
                            <textarea id="notificationMessage" rows="4" placeholder="กรอกข้อความแจ้งเตือน" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">รูปแบบการส่ง</label>
                            <select id="notificationType" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none font-bold text-sky-700">
                                <option value="all">📢 ส่งให้ทุกคน</option>
                                <option value="team">🚗 ส่งเป็นทีม</option>
                                <option value="user">👤 ส่งรายบุคคล</option>
                            </select>
                        </div>

                        <div id="notificationTeamContainer" class="space-y-2 hidden">
                            <label class="text-sm font-semibold text-slate-700">ส่งถึงทีม</label>
                            <select id="notificationTeam" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                <option value="">กำลังโหลด...</option>
                            </select>
                        </div>

                        <div id="notificationUserContainer" class="space-y-2 hidden">
                            <label class="text-sm font-semibold text-slate-700">ส่งถึงพนักงาน</label>
                            <select id="notificationUser" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                <option value="">กำลังโหลด...</option>
                            </select>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                            <button id="cancelNotificationCreate" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">ยกเลิก</button>
                            <button id="sendNotificationBtn" class="rounded-2xl bg-sky-600 text-white px-4 py-3 text-sm font-bold hover:bg-sky-700 transition shadow-md">เพิ่มการแจ้งเตือน</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="notificationList" class="space-y-3 pb-4"></div>
                </div>
            </div>
        </div>

        <?php if (!hasRole('intern')): ?>
        <!-- ========== LEAVE REQUEST MODAL ========== -->
        <div id="leaveRequestModal" class="hidden fixed inset-0 z-[80] bg-black/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div class="w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden animate__animated animate__slideInUp sm:animate__zoomIn flex flex-col max-h-[92dvh]">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-100 flex items-center justify-center">
                            <i data-lucide="calendar-off" class="w-5 h-5 text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-lg">ยื่นคำขอลางาน</h3>
                            <p class="text-xs text-slate-400">กรอกข้อมูลและยืนยันเพื่อส่งคำขอ</p>
                        </div>
                    </div>
                    <button id="closeLeaveModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition text-xl font-bold">&times;</button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-slate-100 shrink-0 px-2 pt-1 gap-1">
                    <button id="leaveTabRequest" onclick="switchLeaveTab('request')" class="px-4 py-2.5 text-sm font-bold text-indigo-600 border-b-2 border-indigo-600 rounded-t-lg transition whitespace-nowrap">📝 ยื่นคำขอ</button>
                    <button id="leaveTabHistory" onclick="switchLeaveTab('history')" class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 border-b-2 border-transparent rounded-t-lg transition whitespace-nowrap">📋 ประวัติของฉัน</button>
                </div>

                <!-- Tab: ยื่นคำขอ -->
                <div id="leavePanelRequest" class="p-6 space-y-4 overflow-y-auto flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">
                                วันที่เริ่มลา <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="leaveStartDate" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-0 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">
                                วันที่สิ้นสุดลา <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="leaveEndDate" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-0 outline-none transition">
                        </div>
                    </div>

                    <!-- Days Count Display -->
                    <div id="leaveDaysDisplay" class="hidden bg-indigo-50 border border-indigo-200 rounded-2xl px-5 py-3 flex items-center gap-3">
                        <i data-lucide="calendar-check" class="w-5 h-5 text-indigo-500 shrink-0"></i>
                        <div>
                            <p class="text-xs text-indigo-500 font-semibold">จำนวนวันที่ลา</p>
                            <p class="text-2xl font-black text-indigo-700"><span id="leaveDaysCount">0</span> <span class="text-base font-semibold">วัน</span></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">
                            เหตุผลในการลา <span class="text-red-500">*</span>
                        </label>
                        <textarea id="leaveReason" rows="4" placeholder="ระบุเหตุผลการลา เช่น ลาป่วย, ลากิจ, ลาพักร้อน..." class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-0 outline-none transition resize-none"></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button id="cancelLeaveBtn" class="flex-1 px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm transition">ยกเลิก</button>
                        <button id="submitLeaveBtn" class="flex-1 px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm transition shadow-lg flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            ยืนยันคำขอลา
                        </button>
                    </div>
                </div>

                <!-- Tab: ประวัติ -->
                <div id="leavePanelHistory" class="hidden p-4 overflow-y-auto flex-1">
                    <div id="myLeaveHistoryList" class="space-y-3">
                        <div class="text-center py-8 text-slate-400 text-sm">กำลังโหลด...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <?php if ($popupAnnouncement): ?>
        <div id="siteAnnouncementModal" class="hidden fixed inset-0 z-[100] bg-black/50 px-4 py-8 flex items-center justify-center">
            <div class="max-w-3xl w-full bg-white rounded-3xl overflow-hidden shadow-2xl ring-1 ring-slate-900/10">
                <div class="relative pb-4">
                    <button id="closeAnnouncementBtn" class="absolute right-4 top-4 text-slate-600 hover:text-slate-900 text-2xl font-bold">&times;</button>

                    <?php if (!empty($popupAnnouncement['image_url'])): ?>
                    <img src="<?= htmlspecialchars($popupAnnouncement['image_url']) ?>" alt="ประกาศ" class="w-full h-72 object-cover">
                    <?php endif; ?>
                </div>
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] font-black text-slate-400">ประกาศ</p>
                            <h2 class="text-2xl font-black text-slate-900"><?= !empty($popupAnnouncement['title']) ? htmlspecialchars($popupAnnouncement['title']) : 'ประกาศใหม่' ?></h2>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                            <i data-lucide="bell" class="w-4 h-4"></i> ปิดได้
                        </span>
                    </div>
                    <?php if (!empty($popupAnnouncement['message'])): ?>
                    <p class="text-slate-700 text-sm leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($popupAnnouncement['message'])) ?></p>
                    <?php else: ?>
                    <p class="text-slate-500 text-sm">ประกาศนี้เป็นภาพเท่านั้น</p>
                    <?php endif; ?>
                </div>
                <div class="px-6 pb-6 border-t border-slate-200">
                    <label class="flex items-center gap-3 text-sm text-slate-600">
                        <input id="dontShowAnnouncementToday" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        ไม่ต้องแจ้งเตือนวันนี้
                    </label>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <main class="p-4 md:p-6 page-view">
            
            <?php if ($page === 'home'): ?>
                <div class="max-w-7xl mx-auto space-y-8">
                    
                    <?php if ($marqueeAnnouncement): ?>
                    <div class="marquee-wrapper animate__animated animate__fadeInDown">
                        <div class="marquee-badge">
                            <i data-lucide="megaphone" class="w-4 h-4 mr-2 text-yellow-300"></i> ประกาศ
                        </div>
                        <div class="marquee-content">
                            <?= htmlspecialchars($marqueeAnnouncement['message']) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <h1 class="text-2xl font-bold tracking-tight">Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋</h1>
                            <p class="text-sm text-[var(--c-text-3)]">Here's what's happening with your operations today, <?= date('d M Y') ?>.</p>
                        </div>
                        <?php if (hasRole(['admin', 'super_admin'])): ?>
                        <a href="index.php?page=site_settings" class="btn-primary shrink-0 !bg-amber-500 hover:!bg-amber-600 no-underline" style="--shadow-btn: 0 4px 14px rgba(245,158,11, 0.40); --shadow-btn-hover: 0 6px 24px rgba(245,158,11, 0.55);">
                            <i data-lucide="monitor-play" class="w-4 h-4"></i> จัดการประกาศวิ่ง
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="kpi-grid">
                        <?php if (hasRole('super_admin')): ?>
                            <!-- Super Admin KPIs -->
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-info-bg)] !text-[var(--c-info)]"><i data-lucide="radio" class="w-5 h-5"></i></div>
                                    <span class="text-[10px] text-[var(--c-text-3)] font-medium bg-[var(--c-surface-2)] px-2 py-1 rounded text-red-500 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> Live</span>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ผู้ใช้ระบบตอนนี้</p>
                                <h3 class="text-kpi"><?= number_format($stats['super_online_users'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="users" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ผู้ใช้ทั้งหมดในระบบ</p>
                                <h3 class="text-kpi"><?= number_format($stats['super_total_users'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-success group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-warning-bg)] !text-[var(--c-warning)]"><i data-lucide="hash" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">หมายเลข NON ทั้งหมด</p>
                                <h3 class="text-kpi"><?= number_format($stats['super_total_non'] ?? 0) ?></h3>
                            </div>
                            
                        <?php elseif (hasRole('admin')): ?>
                            <!-- Admin KPIs -->
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="radio" class="w-5 h-5"></i></div>
                                    <span class="text-[10px] text-[var(--c-text-3)] font-medium bg-[var(--c-surface-2)] px-2 py-1 rounded text-red-500 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> Live</span>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">คนที่ใช้อยู่แบบเรียลไทม์</p>
                                <h3 class="text-kpi"><?= number_format($stats['admin_online_users'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-success group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="check-circle" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">เช็คอินแล้ววันนี้</p>
                                <h3 class="text-kpi"><?= number_format($stats['admin_checked_in'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[#FDF2F8] !text-[#EC4899]"><i data-lucide="x-circle" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ยังไม่เช็คอิน</p>
                                <h3 class="text-kpi"><?= number_format($stats['admin_not_checked_in'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-info-bg)] !text-[var(--c-info)]"><i data-lucide="package" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">สินค้าทั้งหมดในคลัง</p>
                                <h3 class="text-kpi"><?= number_format($stats['admin_total_stock'] ?? 0) ?></h3>
                            </div>

                        <?php elseif (hasRole('ma_technician') || isMaTechnicianOnly()): ?>
                            <!-- MA Technician KPIs -->
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="tool" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">งาน MA วันนี้</p>
                                <h3 class="text-kpi"><?= number_format($stats['ma_jobs_today'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-success group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="check-square" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">งาน MA ที่จบแล้ว</p>
                                <h3 class="text-kpi"><?= number_format($stats['ma_jobs_completed'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[#FDF2F8] !text-[#EC4899]"><i data-lucide="alert-circle" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">งาน MA ที่ไม่จบ</p>
                                <h3 class="text-kpi"><?= number_format($stats['ma_jobs_pending'] ?? 0) ?></h3>
                            </div>

                        <?php elseif (hasRole('technician') && !isSalesOnly() && !isInternOnly()): ?>
                            <!-- Office Technician KPIs -->
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="zap" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">งานที่ได้รับวันนี้</p>
                                <h3 class="text-kpi"><?= number_format($stats['tech_jobs_today'] ?? 0) ?></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-info-bg)] !text-[var(--c-info)]"><i data-lucide="briefcase" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ของคงเหลือในกระเป๋า</p>
                                <h3 class="text-kpi"><?= number_format($stats['tech_bag_qty'] ?? 0) ?> <span class="text-lg font-normal text-[var(--c-text-3)]">ชิ้น</span></h3>
                            </div>
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-success group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-warning-bg)] !text-[var(--c-warning)]"><i data-lucide="fuel" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ค่าน้ำมันวันนี้</p>
                                <h3 class="text-kpi"><span class="text-2xl text-[var(--c-text-3)]">฿</span><?= number_format($stats['tech_oil_today'] ?? 0) ?></h3>
                            </div>

                        <?php else: ?>
                            <!-- Default Fallback -->
                            <div class="card relative group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="calendar" class="w-5 h-5"></i></div>
                                </div>
                                <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">วัน/เวลาเข้างาน</p>
                                <h3 class="text-lg font-bold text-slate-800"><?= date('H:i') ?></h3>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h2 class="text-lg font-bold mt-8 mb-4">เมนูด่วน</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if (!hasRole('sales')): ?>
                        <a href="index.php?page=dispatch" class="card flex flex-col justify-between hover:border-[var(--c-primary)] transition-colors group text-inherit no-underline">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 rounded-xl bg-[var(--c-primary)] text-white flex items-center justify-center shadow-btn group-hover:scale-105 transition-transform"><i data-lucide="map-pin"></i></div>
                                <i data-lucide="arrow-up-right" class="text-[var(--c-text-3)] group-hover:text-[var(--c-primary)] transition-colors"></i>
                            </div>
                            <div class="mt-6">
                                <h3 class="text-base font-bold text-[var(--c-text-1)]">ระบบจัดส่งอัจฉริยะ</h3>
                                <p class="text-sm text-[var(--c-text-3)] mt-1">คำนวณเส้นทางอัตโนมัติและจัดคิวงานให้ทีมช่างเทคนิค</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole(['admin', 'super_admin'])): ?>
                        <a href="index.php?page=inventory" class="card flex flex-col justify-between hover:border-[var(--c-success)] transition-colors group text-inherit no-underline">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 rounded-xl bg-[var(--c-success)] text-white flex items-center justify-center shadow-btn group-hover:scale-105 transition-transform" style="--shadow-btn: 0 4px 14px rgba(16,185,129, 0.40);"><i data-lucide="box"></i></div>
                                <i data-lucide="arrow-up-right" class="text-[var(--c-text-3)] group-hover:text-[var(--c-success)] transition-colors"></i>
                            </div>
                            <div class="mt-6">
                                <h3 class="text-base font-bold text-[var(--c-text-1)]">ระบบคลังสินค้า</h3>
                                <p class="text-sm text-[var(--c-text-3)]">ตรวจสอบระดับสต็อก สแกนรับเข้า และดูประวัติการเบิกจ่าย</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <a href="index.php?page=guide" class="card flex flex-col justify-between hover:border-[var(--c-info)] transition-colors group text-inherit no-underline">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 rounded-xl bg-[var(--c-info)] text-white flex items-center justify-center shadow-btn group-hover:scale-105 transition-transform" style="--shadow-btn: 0 4px 14px rgba(59,130,246, 0.40);"><i data-lucide="book-open"></i></div>
                                <i data-lucide="arrow-up-right" class="text-[var(--c-text-3)] group-hover:text-[var(--c-info)] transition-colors"></i>
                            </div>
                            <div class="mt-6">
                                <h3 class="text-base font-bold text-[var(--c-text-1)]">คู่มือการใช้งาน</h3>
                                <p class="text-sm text-[var(--c-text-3)]">ดูคู่มือแยกตามบทบาท พร้อมคำแนะนำสำหรับการใช้งานระบบ</p>
                            </div>
                        </a>
                        <?php if (hasRole('super_admin')): ?>
                        <a href="index.php?page=site_settings" class="card flex flex-col justify-between hover:border-[var(--c-slate-600)] transition-colors group text-inherit no-underline">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-btn group-hover:scale-105 transition-transform" style="--shadow-btn: 0 4px 14px rgba(15,23,42, 0.40);"><i data-lucide="settings"></i></div>
                                <i data-lucide="arrow-up-right" class="text-[var(--c-text-3)] group-hover:text-slate-900 transition-colors"></i>
                            </div>
                            <div class="mt-6">
                                <h3 class="text-base font-bold text-[var(--c-text-1)]">ตั้งค่าระบบเว็บไซต์</h3>
                                <p class="text-sm text-[var(--c-text-3)]">จัดการประกาศป๊อปอัปและรูปประกาศสำหรับผู้เข้าชม</p>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (hasRole('super_admin') && !empty($realtimeFeed)): ?>
                    <div class="mt-8">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                            กิจกรรมล่าสุดแบบเรียลไทม์
                        </h2>
                        <div class="marquee-wrapper !bg-slate-900 !shadow-none animate__animated animate__fadeInUp">
                            <div class="marquee-badge !bg-slate-800">
                                <i data-lucide="activity" class="w-4 h-4 mr-2 text-red-400"></i> LIVE FEED
                            </div>
                            <div class="marquee-content !duration-[40s]">
                                <?php foreach ($realtimeFeed as $feed): ?>
                                    <span class="mx-6 text-sm text-slate-300">
                                        <span class="font-bold text-white"><?= htmlspecialchars($feed['full_name']) ?></span> 
                                        <?= htmlspecialchars($feed['detail']) ?> 
                                        <span class="text-slate-500 text-xs ml-2"><?= date('H:i', strtotime($feed['action_time'])) ?></span>
                                    </span>
                                    <span class="text-slate-700">•</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

            <?php else: ?>
                <div class="page-view">
                <?php
                // ผูกหน้าให้ตรงกับตัวแปร URL
                $routes = [
                    'oil' => hasRole(['technician']) ? 'views/modules/oil_form.php' : 'views/modules/oil_report.php',
                    'start_day' => 'views/modules/start_day.php',
                    'system_history' => 'views/modules/system_history.php',
                    'customer_info' => 'views/modules/customer_info.php',
                    'job_close_history' => 'views/modules/job_close_history.php',
                    'dispatch' => 'views/modules/dispatch_map.php',
                    'ma_summary' => 'views/modules/ma_summary.php',
                    'guide' => 'views/modules/user_guide.php',
                    'inventory' => 'views/modules/inventory_app.php',
                    'tech_bag' => 'views/modules/tech_bag.php',
                    'site_settings' => 'views/modules/site_settings.php',
                    'users' => 'views/modules/user_settings.php',
                    'checkin' => 'views/modules/checkin.php',
                    'issues' => 'views/modules/issues.php',
                    'work_records' => 'views/modules/work_records.php',
                    'leave_requests' => 'views/modules/leave_requests.php',
                ];

                $accessDenied = false;
                
                // สิทธิ์เซลห้ามเข้าหน้านี้
                if (in_array($page, ['oil', 'dispatch', 'start_day'], true) && hasRole('sales')) {
                    $accessDenied = true;
                }
                // ช่าง MA เฉพาะงาน MA — เข้าได้แค่ dispatch, checkin และ leave_requests สำหรับ admin
                if (isMaTechnicianOnly() && !in_array($page, ['dispatch', 'checkin'], true)) {
                    $accessDenied = true;
                }
                // สิทธิ์อื่นๆห้ามเข้าหน้าประวัติรวม ยกเว้นแอดมิน
                if ($page === 'system_history' && !hasRole(['admin', 'super_admin'])) {
                    $accessDenied = true;
                }
                if ($page === 'site_settings' && !hasRole('super_admin')) {
                    $accessDenied = true;
                }
                if ($page === 'ma_summary' && !hasRole('super_admin')) {
                    $accessDenied = true;
                }
                if ($page === 'job_close_history' && !hasRole('technician')) {
                    $accessDenied = true;
                }
                if ($page === 'dispatch' && !canAccessDispatch()) {
                    $accessDenied = true;
                }
                if ($page === 'issues' && !hasRole(['admin', 'super_admin'])) {
                    $accessDenied = true;
                }
                if ($page === 'work_records' && !hasRole('intern')) {
                    $accessDenied = true;
                }
                if ($page === 'leave_requests' && !hasRole(['admin', 'super_admin'])) {
                    $accessDenied = true;
                }

                if (!$accessDenied && array_key_exists($page, $routes) && file_exists($routes[$page])) {
                    include $routes[$page];
                } elseif ($accessDenied) {
                    echo '<div class="card text-center py-16">
                            <div class="w-20 h-20 bg-[var(--c-surface-2)] rounded-full flex items-center justify-center mx-auto mb-6"><i data-lucide="slash" class="w-10 h-10 text-[var(--c-text-3)]"></i></div>
                            <h2 class="text-xl font-bold text-[var(--c-text-1)] mb-2">ไม่มีสิทธิ์เข้าถึงหน้านี้</h2>
                            <p class="text-sm text-[var(--c-text-3)] max-w-sm mx-auto">คุณไม่มีสิทธิ์ดูหน้านี้ด้วยบทบาทปัจจุบัน</p>
                            <a href="index.php?page=home" class="btn-primary mt-8 inline-block w-auto">กลับสู่หน้าแรก</a>
                          </div>';
                } else {
                    echo '<div class="card text-center py-16">
                            <div class="w-20 h-20 bg-[var(--c-surface-2)] rounded-full flex items-center justify-center mx-auto mb-6"><i data-lucide="settings" class="w-10 h-10 text-[var(--c-text-3)] animate-spin-slow" style="animation-duration: 4s;"></i></div>
                            <h2 class="text-xl font-bold text-[var(--c-text-1)] mb-2">กำลังปรับปรุงระบบ</h2>
                            <p class="text-sm text-[var(--c-text-3)] max-w-sm mx-auto">ส่วนนี้กำลังได้รับการอัปเดตให้เข้ากับรูปแบบดีไซน์ใหม่ กรุณากลับมาใช้งานในภายหลัง</p>
                            <a href="index.php?page=home" class="btn-primary mt-8 inline-block w-auto">กลับสู่หน้าแรก</a>
                          </div>';
                }
                ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <div id="toast-container" class="fixed top-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>
    
    <script>
        // Init Lucide
        lucide.createIcons();

        // Mobile Drawer Logic
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileDrawer = document.getElementById('mobileDrawer');
        const mobileDrawerBackdrop = document.getElementById('mobileDrawerBackdrop');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');

        function openDrawer() {
            mobileDrawerBackdrop.classList.add('visible');
            mobileDrawer.classList.add('open');
        }

        function closeDrawer() {
            mobileDrawer.classList.remove('open');
            mobileDrawerBackdrop.classList.remove('visible');
        }

        if(mobileMenuBtn) mobileMenuBtn.addEventListener('click', openDrawer);
        if(closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);
        if(mobileDrawerBackdrop) mobileDrawerBackdrop.addEventListener('click', closeDrawer);

        // Toast System (Level 5 Shadow)
        const AppToast = {
            show(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `flex items-center gap-3 p-4 bg-[var(--c-surface)] rounded-xl border border-[var(--c-border)] pointer-events-auto`;
                toast.style.boxShadow = 'var(--shadow-5)';
                toast.style.width = '320px';
                toast.style.animation = 'toastIn var(--dur-normal) var(--ease-spring) both';
                
                const icon = type === 'success' ? 'check-circle-2' : 'alert-circle';
                const iconColor = type === 'success' ? 'text-[var(--c-success)]' : 'text-[var(--c-danger)]';

                toast.innerHTML = `
                    <div class="shrink-0"><i data-lucide="${icon}" class="${iconColor} w-5 h-5"></i></div>
                    <div class="flex-1 text-sm font-medium text-[var(--c-text-1)]">${message}</div>
                    <button onclick="this.parentElement.remove()" class="shrink-0 text-[var(--c-text-3)] hover:text-[var(--c-text-1)] transition-colors"><i data-lucide="x" class="w-4 h-4"></i></button>
                `;
                container.appendChild(toast);
                lucide.createIcons();
                
                setTimeout(() => {
                    toast.style.animation = 'toastOut var(--dur-normal) var(--ease-in-out) both';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            },
            success(msg) { this.show(msg, 'success'); },
            error(msg) { this.show(msg, 'error'); }
        };

        // Auto-inject data-label for Mobile Table Card view
        function enhanceTablesForMobile() {
            document.querySelectorAll('table').forEach(table => {
                table.classList.add('data-table');
                const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
                table.querySelectorAll('tbody tr').forEach(row => {
                    row.querySelectorAll('td').forEach((cell, index) => {
                        if(headers[index] && !cell.hasAttribute('data-label') && !cell.hasAttribute('colspan')) {
                            cell.setAttribute('data-label', headers[index]);
                        }
                    });
                });
            });
        }
        
        enhanceTablesForMobile();
        const observer = new MutationObserver((mutations) => {
            let shouldEnhance = false;
            mutations.forEach(m => { if(m.addedNodes.length > 0) shouldEnhance = true; });
            if(shouldEnhance) enhanceTablesForMobile();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.NOTIFICATIONS_CONFIG = {
            isAdmin: <?php echo hasRole(['admin', 'super_admin']) ? 'true' : 'false'; ?>
        };

        <?php if ($popupAnnouncement): ?>
        (function() {
            const announcementId = <?= json_encode($popupAnnouncement['id']) ?>;
            const announcementModal = document.getElementById('siteAnnouncementModal');
            const closeBtn = document.getElementById('closeAnnouncementBtn');
            const checkbox = document.getElementById('dontShowAnnouncementToday');
            if (!announcementModal || !closeBtn || !announcementId) return;
            const today = new Date().toISOString().slice(0, 10);
            const storageKey = `dismissedAnnouncement_${announcementId}`;
            const dismissed = localStorage.getItem(storageKey);
            if (dismissed === today) return;

            function showAnnouncement() {
                announcementModal.classList.remove('hidden');
            }

            function closeAnnouncement() {
                announcementModal.classList.add('hidden');
                if (checkbox && checkbox.checked) {
                    localStorage.setItem(storageKey, today);
                }
            }

            closeBtn.addEventListener('click', closeAnnouncement);
            announcementModal.addEventListener('click', (event) => {
                if (event.target === announcementModal) closeAnnouncement();
            });
            document.addEventListener('DOMContentLoaded', showAnnouncement);
        })();
        <?php endif; ?>
    </script>
    <script src="assets/js/common.js"></script>
    <script src="assets/js/datepicker.js"></script>
    <script src="assets/js/notifications.js"></script>

    <?php if (!hasRole('intern')): ?>
    <script>
    // ========== LEAVE MODAL LOGIC ==========
    (function() {
        const modal   = document.getElementById('leaveRequestModal');
        if (!modal) return;

        const openBtns = [
            document.getElementById('openLeaveModalBtn'),
            document.getElementById('openLeaveModalBtnMobile')
        ];
        const closeBtn  = document.getElementById('closeLeaveModal');
        const cancelBtn = document.getElementById('cancelLeaveBtn');
        const submitBtn = document.getElementById('submitLeaveBtn');
        const startInput = document.getElementById('leaveStartDate');
        const endInput   = document.getElementById('leaveEndDate');
        const daysDisplay = document.getElementById('leaveDaysDisplay');
        const daysCount   = document.getElementById('leaveDaysCount');
        const reasonInput = document.getElementById('leaveReason');

        // Set default date to today
        const today = new Date().toISOString().slice(0, 10);

        function openLeaveModal() {
            startInput.value = today;
            endInput.value   = today;
            reasonInput.value = '';
            updateDaysCount();
            modal.classList.remove('hidden');
            lucide.createIcons();
        }

        function closeLeaveModal() {
            modal.classList.add('hidden');
        }

        openBtns.forEach(btn => btn?.addEventListener('click', openLeaveModal));
        closeBtn?.addEventListener('click', closeLeaveModal);
        cancelBtn?.addEventListener('click', closeLeaveModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeLeaveModal(); });

        function updateDaysCount() {
            const s = startInput.value;
            const e = endInput.value;
            if (s && e && e >= s) {
                const start = new Date(s);
                const end   = new Date(e);
                const diff  = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
                daysCount.textContent = diff;
                daysDisplay.classList.remove('hidden');
            } else {
                daysDisplay.classList.add('hidden');
            }
        }

        startInput?.addEventListener('change', () => {
            if (endInput.value < startInput.value) endInput.value = startInput.value;
            updateDaysCount();
        });
        endInput?.addEventListener('change', updateDaysCount);

        // Submit leave request
        submitBtn?.addEventListener('click', async () => {
            const start  = startInput.value;
            const end    = endInput.value;
            const reason = reasonInput.value.trim();

            if (!start || !end) {
                if(window.Toast) Toast.error('กรุณาเลือกวันที่ลา');
                else alert('กรุณาเลือกวันที่ลา');
                return;
            }
            if (!reason) {
                if(window.Toast) Toast.error('กรุณาระบุเหตุผลการลา');
                else alert('กรุณาระบุเหตุผลการลา');
                reasonInput.focus();
                return;
            }
            if (end < start) {
                if(window.Toast) Toast.error('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> กำลังส่ง...';

            try {
                const res = await fetch('api/leave/submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ start_date: start, end_date: end, reason })
                });
                const data = await res.json();

                if (data.success) {
                    closeLeaveModal();
                    if(window.Toast) Toast.success(`✅ ส่งคำขอลา ${data.days} วัน เรียบร้อยแล้ว!`);
                    else alert(`ส่งคำขอลา ${data.days} วัน เรียบร้อยแล้ว!`);
                } else {
                    if(window.Toast) Toast.error(data.error || 'เกิดข้อผิดพลาด');
                    else alert(data.error);
                }
            } catch (e) {
                if(window.Toast) Toast.error('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> ยืนยันคำขอลา';
            }
        });
    })();

    function switchLeaveTab(tab) {
        const panels = ['request', 'history'];
        panels.forEach(p => {
            document.getElementById(`leavePanel${p.charAt(0).toUpperCase()+p.slice(1)}`)?.classList.add('hidden');
            const tabEl = document.getElementById(`leaveTab${p.charAt(0).toUpperCase()+p.slice(1)}`);
            if (tabEl) {
                tabEl.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-bold');
                tabEl.classList.add('text-slate-500', 'border-transparent', 'font-medium');
            }
        });
        document.getElementById(`leavePanel${tab.charAt(0).toUpperCase()+tab.slice(1)}`)?.classList.remove('hidden');
        const activeTab = document.getElementById(`leaveTab${tab.charAt(0).toUpperCase()+tab.slice(1)}`);
        if (activeTab) {
            activeTab.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-bold');
            activeTab.classList.remove('text-slate-500', 'border-transparent', 'font-medium');
        }
        if (tab === 'history') loadMyLeaveHistory();
    }

    async function loadMyLeaveHistory() {
        const container = document.getElementById('myLeaveHistoryList');
        if (!container) return;
        container.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm">กำลังโหลด...</div>';

        try {
            const res = await fetch('api/leave/get_my_leaves.php');
            const data = await res.json();

            if (!data.success || data.data.length === 0) {
                container.innerHTML = '<div class="text-center py-10 text-slate-400 italic text-sm">ยังไม่มีประวัติการลา</div>';
                return;
            }

            container.innerHTML = '';
            data.data.forEach(row => {
                const startDate = new Date(row.start_date).toLocaleDateString('th-TH');
                const endDate   = new Date(row.end_date).toLocaleDateString('th-TH');
                const dateRange = row.start_date === row.end_date ? startDate : `${startDate} – ${endDate}`;

                let statusBadge = '';
                if (row.status === 'pending') {
                    statusBadge = '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">⏳ รอดำเนินการ</span>';
                } else if (row.status === 'approved') {
                    statusBadge = '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">✅ อนุมัติ</span>';
                } else {
                    statusBadge = '<span class="px-2 py-0.5 bg-rose-100 text-rose-700 text-xs font-bold rounded-full">❌ ปฏิเสธ</span>';
                }

                const card = document.createElement('div');
                card.className = 'bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2';
                card.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-slate-800 text-sm">${dateRange}</div>
                        ${statusBadge}
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="bg-indigo-100 text-indigo-700 font-bold px-2 py-0.5 rounded-full">${row.days} วัน</span>
                        ${row.reviewed_by_name ? `<span>· ตรวจสอบโดย ${row.reviewed_by_name}</span>` : ''}
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">${row.reason}</p>
                `;
                container.appendChild(card);
            });
        } catch(e) {
            container.innerHTML = '<div class="text-center py-8 text-red-400 text-sm">โหลดข้อมูลไม่ได้</div>';
        }
    }
    </script>
    <?php endif; ?>

    <script>
    (function() {


        // ─── Guide Modal: Role-Based ───
        const GUIDE_FILE = <?= json_encode($guideInfo['file']) ?>;

        const guideBtn       = document.getElementById('guideModalBtn');
        const guideModal     = document.getElementById('guideModal');
        const closeGuideBtn  = document.getElementById('closeGuideModal');
        const guideContent   = document.getElementById('guideContent');
        const guideScrollArea= document.getElementById('guideScrollArea');
        const progressBar    = document.getElementById('guideProgressBar');
        const tocList        = document.getElementById('guideTocList');

        let guideLoaded = false;

        async function loadGuide() {
            if (guideLoaded) return;
            try {
                const res  = await fetch(GUIDE_FILE + '?v=' + Date.now());
                const html = await res.text();
                guideContent.innerHTML = html;
                guideLoaded = true;
                if (window.lucide) lucide.createIcons();
                buildToc();
            } catch (e) {
                guideContent.innerHTML = '<div style="padding:32px;text-align:center;color:#94A3B8;"><p>ไม่สามารถโหลดคู่มือได้ กรุณารีเฟรชหน้า</p></div>';
            }
        }

        function buildToc() {
            if (!tocList) return;
            tocList.innerHTML = '';
            // Look for sa-step-title or g-card-title elements
            const titles = guideContent.querySelectorAll('.sa-step-title, .g-card-title');
            if (titles.length === 0) return;
            titles.forEach((el, i) => {
                // Give each section an ID for scroll targeting
                const sectionId = 'guide-section-' + i;
                const card = el.closest('.sa-step, .g-card');
                if (card) card.id = sectionId;

                const btn = document.createElement('button');
                btn.className = 'guide-section-link';
                btn.innerHTML = '<span class="guide-section-dot"></span>' + el.textContent.trim();
                btn.addEventListener('click', () => {
                    const target = document.getElementById(sectionId);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    // Mark active
                    tocList.querySelectorAll('.guide-section-link').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
                tocList.appendChild(btn);
            });
        }

        // Reading progress bar
        function updateProgress() {
            if (!guideScrollArea || !progressBar) return;
            const el = guideScrollArea;
            const scrolled = el.scrollTop;
            const total = el.scrollHeight - el.clientHeight;
            const pct = total > 0 ? Math.round((scrolled / total) * 100) : 0;
            progressBar.style.width = pct + '%';

            // Update TOC active based on scroll
            if (!tocList) return;
            const sections = guideContent.querySelectorAll('.sa-step, .g-card');
            let activeIdx = 0;
            sections.forEach((sec, i) => {
                const rect = sec.getBoundingClientRect();
                const containerRect = guideScrollArea.getBoundingClientRect();
                if (rect.top - containerRect.top < 120) activeIdx = i;
            });
            const links = tocList.querySelectorAll('.guide-section-link');
            links.forEach((l, i) => l.classList.toggle('active', i === activeIdx));
        }

        function openGuide() {
            guideModal.classList.add('show');
            document.body.style.overflow = 'hidden';
            loadGuide();
        }

        function closeGuide() {
            guideModal.classList.remove('show');
            document.body.style.overflow = '';
        }

        guideBtn.addEventListener('click', openGuide);
        closeGuideBtn.addEventListener('click', closeGuide);
        guideModal.addEventListener('click', (e) => {
            if (e.target === guideModal) closeGuide();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && guideModal.classList.contains('show')) closeGuide();
        });
        if (guideScrollArea) {
            guideScrollArea.addEventListener('scroll', updateProgress, { passive: true });
        }
    })();
    </script>

    <!-- ═══ USER PROFILE MODAL ═══ -->
    <div id="userProfileModal" class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300" id="userProfileModalInner">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-indigo-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900">โปรไฟล์ผู้ใช้งาน</h3>
                        <p class="text-xs text-slate-500">จัดการข้อมูลส่วนตัวและรหัสผ่าน</p>
                    </div>
                </div>
                <button id="closeUserProfileBtn" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <!-- Profile Image & Info (ID Card Style) -->
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 mb-6 text-center text-white shadow-lg relative overflow-hidden">
                    <!-- Decorative elements -->
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute -bottom-10 -left-10 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                    
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="relative group cursor-pointer mb-3" id="profileImageContainer">
                            <div class="w-24 h-24 rounded-full bg-white/20 border-4 border-white/50 shadow-lg flex items-center justify-center overflow-hidden backdrop-blur-sm">
                                <img id="upmProfileImage" src="" class="w-full h-full object-cover hidden" alt="Profile">
                                <span id="upmProfileInitials" class="text-3xl font-bold text-white drop-shadow-md">U</span>
                            </div>
                            <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <i data-lucide="camera" class="w-6 h-6 text-white"></i>
                            </div>
                            <input type="file" id="upmImageInput" class="hidden" accept="image/*">
                        </div>
                        
                        <button type="button" id="upmSaveImageBtn" class="hidden bg-white text-indigo-600 text-xs font-bold px-4 py-1.5 rounded-full mb-3 shadow-sm hover:bg-indigo-50 transition-colors">
                            บันทึกรูปโปรไฟล์
                        </button>
                        
                        <h3 id="upmFullName" class="text-xl font-bold mb-1 drop-shadow-sm">-</h3>
                        <p id="upmTeamName" class="text-sm text-indigo-100 font-medium mb-3">-</p>
                        
                        <div class="flex justify-center gap-2">
                            <span id="upmRoleBadge" class="px-3 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur-md uppercase tracking-wider">-</span>
                            <span id="upmStatusBadge" class="px-3 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur-md uppercase tracking-wider">-</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" id="upmUsername" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm bg-slate-50 text-slate-500 outline-none" readonly>
                    </div>

                    <hr class="border-slate-100 my-4">

                    <h4 class="font-bold text-slate-800 text-sm mb-3">เปลี่ยนรหัสผ่าน</h4>
                    <form id="upmPasswordForm" class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">รหัสผ่านเดิม</label>
                            <input type="password" name="old_password" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all" required minlength="6">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all" required minlength="6">
                        </div>
                        <button type="submit" id="upmSavePasswordBtn" class="w-full mt-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> บันทึกรหัสผ่าน
                        </button>
                    </form>
                    
                    <button type="button" class="w-full mt-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-bold py-2.5 rounded-xl transition-colors" onclick="document.getElementById('closeUserProfileBtn').click()">
                        ปิดหน้าต่าง
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ REPORT ISSUE MODAL ═══ -->
    <div id="issueReportModal" class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300" id="issueReportModalInner">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-rose-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600">
                        <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900">รายงานปัญหา</h3>
                        <p class="text-xs text-slate-500">แจ้งปัญหาการใช้งานไปยังผู้ดูแลระบบ</p>
                    </div>
                </div>
                <button id="closeIssueReportBtn" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="issueReportForm" class="p-6 flex flex-col gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">รายละเอียดปัญหา <span class="text-rose-500">*</span></label>
                    <textarea name="message" id="issueMessage" rows="4" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none transition-all resize-none" placeholder="อธิบายปัญหาที่คุณพบ..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">แนบรูปภาพ (ถ้ามี)</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="issueImage" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors relative overflow-hidden group">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6" id="issueImagePlaceholder">
                                <i data-lucide="image-plus" class="w-8 h-8 text-slate-400 mb-2 group-hover:text-rose-500 transition-colors"></i>
                                <p class="text-xs text-slate-500"><span class="font-semibold text-rose-500">คลิกเพื่ออัปโหลด</span> หรือลากไฟล์มาวาง</p>
                                <p class="text-[10px] text-slate-400 mt-1">PNG, JPG, WEBP (สูงสุด 5MB)</p>
                            </div>
                            <img id="issueImagePreview" class="absolute inset-0 w-full h-full object-cover hidden" />
                            <input id="issueImage" name="image" type="file" class="hidden" accept="image/png, image/jpeg, image/webp, image/gif" />
                        </label>
                    </div>
                    <div class="flex justify-end mt-2 hidden" id="issueImageRemoveContainer">
                        <button type="button" id="removeIssueImageBtn" class="text-xs text-rose-500 hover:text-rose-700 font-medium">ลบรูปภาพ</button>
                    </div>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancelIssueReportBtn" class="px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">ยกเลิก</button>
                    <button type="submit" id="submitIssueReportBtn" class="px-5 py-2.5 text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-md shadow-rose-200 transition-all flex items-center gap-2">
                        <i data-lucide="send" class="w-4 h-4"></i> ส่งรายงาน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('issueReportModal');
        const modalInner = document.getElementById('issueReportModalInner');
        const openBtn = document.getElementById('openIssueReportBtn');
        const openBtnMobile = document.getElementById('openIssueReportBtnMobile');
        const closeBtn = document.getElementById('closeIssueReportBtn');
        const cancelBtn = document.getElementById('cancelIssueReportBtn');
        const form = document.getElementById('issueReportForm');
        
        const imageInput = document.getElementById('issueImage');
        const imagePreview = document.getElementById('issueImagePreview');
        const imagePlaceholder = document.getElementById('issueImagePlaceholder');
        const removeImageBtn = document.getElementById('removeIssueImageBtn');
        const removeImageContainer = document.getElementById('issueImageRemoveContainer');
        const submitBtn = document.getElementById('submitIssueReportBtn');
        
        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Trigger reflow
            void modal.offsetWidth;
            modalInner.classList.remove('scale-95', 'opacity-0');
            modalInner.classList.add('scale-100', 'opacity-100');
        }
        
        function closeModal() {
            modalInner.classList.remove('scale-100', 'opacity-100');
            modalInner.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                form.reset();
                resetImagePreview();
            }, 300);
        }
        
        if (openBtn) openBtn.addEventListener('click', openModal);
        if (openBtnMobile) openBtnMobile.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        
        // Image preview logic
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                    imagePlaceholder.classList.add('opacity-0');
                    removeImageContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        function resetImagePreview() {
            imageInput.value = '';
            imagePreview.src = '';
            imagePreview.classList.add('hidden');
            imagePlaceholder.classList.remove('opacity-0');
            removeImageContainer.classList.add('hidden');
        }
        
        if (removeImageBtn) removeImageBtn.addEventListener('click', resetImagePreview);
        
        // Submit logic
        if (form) form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = document.getElementById('issueMessage').value.trim();
            if (!message && !imageInput.files[0]) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณากรอกข้อความหรือแนบรูปภาพ',
                    confirmButtonColor: '#F59E0B'
                });
                return;
            }
            
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div> กำลังส่ง...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(form);
                const res = await fetch('api/issues/submit.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: data.message || 'ส่งรายงานปัญหาเรียบร้อยแล้ว',
                        confirmButtonColor: '#10B981'
                    });
                    closeModal();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message || 'เกิดข้อผิดพลาดในการส่งรายงาน',
                        confirmButtonColor: '#EF4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'เชื่อมต่อล้มเหลว',
                    text: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์',
                    confirmButtonColor: '#EF4444'
                });
            } finally {
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;
                lucide.createIcons();
            }
        });
    });
    </script>
    <!-- User Profile Modal Logic -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const profileModal = document.getElementById('userProfileModal');
        const profileModalInner = document.getElementById('userProfileModalInner');
        const closeProfileBtn = document.getElementById('closeUserProfileBtn');
        
        const imgContainer = document.getElementById('profileImageContainer');
        const imgInput = document.getElementById('upmImageInput');
        const pwdForm = document.getElementById('upmPasswordForm');
        
        function openProfileModal() {
            profileModal.classList.remove('hidden');
            profileModal.classList.add('flex');
            // Animate in
            requestAnimationFrame(() => {
                profileModalInner.classList.remove('scale-95', 'opacity-0');
                profileModalInner.classList.add('scale-100', 'opacity-100');
            });
            fetchUserProfile();
        }
        
        function closeProfileModal() {
            profileModalInner.classList.remove('scale-100', 'opacity-100');
            profileModalInner.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                profileModal.classList.add('hidden');
                profileModal.classList.remove('flex');
                pwdForm.reset();
                if (selectedProfileFile) {
                    selectedProfileFile = null;
                    document.getElementById('upmSaveImageBtn').classList.add('hidden');
                    fetchUserProfile();
                }
            }, 300);
        }
        
        closeProfileBtn.addEventListener('click', closeProfileModal);
        profileModal.addEventListener('click', (e) => {
            if (e.target === profileModal) closeProfileModal();
        });
        
        window.openUserProfile = openProfileModal; // Expose globally
        
        async function fetchUserProfile() {
            try {
                const res = await fetch('api/users/get_profile.php');
                const data = await res.json();
                if (data.success) {
                    const u = data.data;
                    document.getElementById('upmFullName').textContent = u.full_name;
                    document.getElementById('upmUsername').value = u.username;
                    document.getElementById('upmTeamName').textContent = u.team_name ? 'ทีม: ' + u.team_name : 'ไม่มีทีม';
                    document.getElementById('upmRoleBadge').textContent = u.role;
                    document.getElementById('upmStatusBadge').textContent = u.status === 'approved' ? 'อนุมัติแล้ว' : (u.status === 'pending' ? 'รออนุมัติ' : u.status);
                    
                    const imgEl = document.getElementById('upmProfileImage');
                    const initEl = document.getElementById('upmProfileInitials');
                    if (u.profile_image) {
                        imgEl.src = u.profile_image + '?t=' + new Date().getTime();
                        imgEl.classList.remove('hidden');
                        initEl.classList.add('hidden');
                    } else {
                        imgEl.classList.add('hidden');
                        initEl.classList.remove('hidden');
                        initEl.textContent = (u.full_name || 'U').substring(0, 2).toUpperCase();
                    }
                } else {
                    Swal.fire({icon: 'error', title: 'โหลดโปรไฟล์ไม่สำเร็จ', text: data.message || data.error || 'Unknown error'});
                }
            } catch(e) { 
                console.error('Failed to fetch profile', e);
                Swal.fire({icon: 'error', title: 'การเชื่อมต่อผิดพลาด', text: 'ไม่สามารถโหลดข้อมูลโปรไฟล์ได้: ' + e.message});
            }
        }
        
        const saveImageBtn = document.getElementById('upmSaveImageBtn');
        let selectedProfileFile = null;
        
        imgContainer.addEventListener('click', () => imgInput.click());
        imgInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                selectedProfileFile = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgEl = document.getElementById('upmProfileImage');
                    const initEl = document.getElementById('upmProfileInitials');
                    imgEl.src = e.target.result;
                    imgEl.classList.remove('hidden');
                    initEl.classList.add('hidden');
                    saveImageBtn.classList.remove('hidden');
                }
                reader.readAsDataURL(selectedProfileFile);
            }
        });
        
        saveImageBtn.addEventListener('click', async function() {
            if (!selectedProfileFile) return;
            
            const originalHtml = this.innerHTML;
            this.innerHTML = 'กำลังบันทึก...';
            this.disabled = true;
            
            const fd = new FormData();
            fd.append('profile_image', selectedProfileFile);
            
            try {
                const res = await fetch('api/users/update_profile_image.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#10B981'});
                    saveImageBtn.classList.add('hidden');
                    selectedProfileFile = null;
                    fetchUserProfile();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire({icon: 'error', title: 'ผิดพลาด', text: data.message, confirmButtonColor: '#EF4444'});
                    fetchUserProfile();
                }
            } catch(e) {
                Swal.fire({icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', confirmButtonColor: '#EF4444'});
                fetchUserProfile();
            } finally {
                this.innerHTML = originalHtml;
                this.disabled = false;
            }
        });
        
        pwdForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('upmSavePasswordBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '...กำลังบันทึก';
            btn.disabled = true;
            
            try {
                const fd = new FormData(pwdForm);
                const res = await fetch('api/users/change_password.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#10B981'});
                    pwdForm.reset();
                } else {
                    Swal.fire({icon: 'error', title: 'ผิดพลาด', text: data.message, confirmButtonColor: '#EF4444'});
                }
            } catch(e) {
                Swal.fire({icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', confirmButtonColor: '#EF4444'});
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                lucide.createIcons();
            }
        });
    });
    </script>
</body>
</html>