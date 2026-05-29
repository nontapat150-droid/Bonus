<?php
// index.php (Ultimate SaaS Dashboard)
require_once 'config/db.php';
require_once 'config/auth.php';

requireLogin();
$user = getCurrentUser();

$page = $_GET['page'] ?? 'home';

// Fetch Real-time Stats for Dashboard
$stats = [
    'jobs_today' => 0,
    'oil_cost_today' => 0,
    'total_stock' => 0,
    'total_staff' => 0
];

if ($page === 'home') {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at) = CURDATE() OR plan_arrival_date = CURDATE()");
        $stats['jobs_today'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT SUM(total_price) FROM oil_records WHERE DATE(date_recorded) = CURDATE()");
        $stats['oil_cost_today'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'in_stock'");
        $stats['total_stock'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $stats['total_staff'] = $stmt->fetchColumn();
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
            .data-table td:empty { display: none; } /* Hide empty cells */
            .data-table td > * { text-align: right; } /* Ensure content like buttons is right-aligned */
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
        
        <!-- Top Bar -->
        <header class="topbar">
            <!-- Mobile Menu Toggle -->
            <button id="mobileMenuBtn" class="md:hidden p-2 -ml-2 text-slate-500 hover:text-slate-800">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>

            

            <div class="flex items-center gap-3">
                <button id="notificationBell" class="relative p-2 text-[var(--c-text-2)] hover:bg-[var(--c-surface-2)] rounded-full transition-colors">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span id="notificationUnreadDot" class="absolute top-1 right-1 w-2.5 h-2.5 bg-[var(--c-danger)] rounded-full border-2 border-[var(--c-surface)]"></span>
                </button>
            </div>
        </header>

        <div id="notificationModal" class="hidden fixed inset-0 z-50 bg-black/40 p-4 backdrop-blur-sm">
            <div class="mx-auto w-full max-w-3xl rounded-[32px] bg-white shadow-2xl overflow-hidden border border-slate-200">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">แจ้งเตือนจากระบบ</h2>
                        <p class="text-slate-500 text-sm">ข้อความระบบและจากแอดมินให้ทีมของคุณ</p>
                    </div>
                    <button id="closeNotificationModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>
                <div class="px-5 py-4 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-slate-600 text-sm">แจ้งเตือนใหม่: <span id="notificationCount" class="font-semibold">0</span></div>
                        <?php if (hasRole(['admin', 'super_admin'])): ?>
                        <button id="openNotificationCreate" class="inline-flex items-center justify-center rounded-2xl bg-sky-600 text-white px-4 py-2 text-sm font-bold hover:bg-sky-700 transition">ส่งแจ้งเตือน</button>
                        <?php endif; ?>
                    </div>
                    <div id="notificationList" class="space-y-3 max-h-[calc(65vh-120px)] overflow-y-auto pr-2"></div>

                    <?php if (hasRole(['admin', 'super_admin'])): ?>
                    <div id="notificationCreateCard" class="hidden rounded-3xl bg-slate-50 border border-slate-200 p-5 space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">หัวเรื่อง</label>
                            <input id="notificationTitle" type="text" placeholder="กรอกหัวเรื่อง" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">ข้อความ</label>
                            <textarea id="notificationMessage" rows="4" placeholder="กรอกข้อความแจ้งเตือน" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700">ส่งถึงทีม</label>
                            <select id="notificationTeam" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                <option value="">ทุกทีม</option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-end gap-3">
                            <button id="cancelNotificationCreate" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">ยกเลิก</button>
                            <button id="sendNotificationBtn" class="rounded-2xl bg-sky-600 text-white px-4 py-3 text-sm font-bold hover:bg-sky-700 transition">ส่งแจ้งเตือน</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <main class="p-4 md:p-6 page-view">
            
            <?php if ($page === 'home'): ?>
                <div class="max-w-7xl mx-auto space-y-8">
                    
                    <!-- Welcome Section -->
                    <div class="flex flex-col gap-1">
                        <h1 class="text-2xl font-bold tracking-tight">Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋</h1>
                        <p class="text-sm text-[var(--c-text-3)]">Here's what's happening with your operations today, <?= date('d M Y') ?>.</p>
                    </div>

                    <!-- KPI Grid -->
                    <div class="kpi-grid">
                        <div class="card relative group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring)"><i data-lucide="zap" class="w-5 h-5"></i></div>
                                <span class="badge-success flex items-center gap-1"><i data-lucide="trending-up" class="w-3 h-3"></i> 12%</span>
                            </div>
                            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">Jobs Today</p>
                            <h3 class="text-kpi"><?= number_format($stats['jobs_today']) ?></h3>
                        </div>

                        <div class="card relative group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="icon-box-success group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-warning-bg)] !text-[var(--c-warning)]"><i data-lucide="fuel" class="w-5 h-5"></i></div>
                                <span class="badge-danger flex items-center gap-1"><i data-lucide="trending-down" class="w-3 h-3"></i> 5%</span>
                            </div>
                            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ค่าน้ำมันวันนี้</p>
                            <h3 class="text-kpi"><span class="text-2xl text-[var(--c-text-3)]">฿</span><?= number_format($stats['oil_cost_today']) ?></h3>
                        </div>

                        <div class="card relative group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-info-bg)] !text-[var(--c-info)]"><i data-lucide="package" class="w-5 h-5"></i></div>
                                <span class="text-[10px] text-[var(--c-text-3)] font-medium bg-[var(--c-surface-2)] px-2 py-1 rounded">Live</span>
                            </div>
                            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">สินค้าทั้งหมด</p>
                            <h3 class="text-kpi"><?= number_format($stats['total_stock']) ?></h3>
                        </div>

                        <div class="card relative group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[#FDF2F8] !text-[#EC4899]"><i data-lucide="users" class="w-5 h-5"></i></div>
                                <span class="text-[10px] text-[var(--c-text-3)] font-medium bg-[var(--c-surface-2)] px-2 py-1 rounded">Active</span>
                            </div>
                            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">พนักงานที่แอคทีฟ</p>
                            <h3 class="text-kpi"><?= number_format($stats['total_staff']) ?></h3>
                        </div>
                    </div>

                    <!-- Main Actions -->
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
                    </div>

                </div>

            <?php else: ?>
                <div class="page-view">
                <?php
                $routes = [
                    'oil' => hasRole(['technician']) ? 'views/modules/oil_form.php' : 'views/modules/oil_report.php',
                    'start_day' => 'views/modules/start_day.php', /* 👈 เพิ่มบรรทัดนี้ลงไป */
                    'dispatch' => 'views/modules/dispatch_map.php',
                    'inventory' => 'views/modules/inventory_app.php',
                    'users' => 'views/modules/user_settings.php',
                    'checkin' => 'views/modules/checkin.php'
                ];

                $accessDenied = false;
                if (in_array($page, ['oil', 'dispatch'], true) && hasRole('sales')) {
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

    <!-- Toast Container z-100 -->
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
        const Toast = {
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

        // --- UI Overlap Detector (Debug) ---
        window.checkUIOverlaps = function() {
            const all = Array.from(document.querySelectorAll('body *'));
            let warnings = 0;
            
            all.forEach(el => {
                const style = window.getComputedStyle(el);
                const zIndex = parseInt(style.zIndex);
                if (!isNaN(zIndex) && zIndex >= 50) {
                    // Ignore known high z-index containers
                    const isKnown = el.id === 'toast-container' || 
                                    el.closest('.mobile-drawer') || 
                                    el.closest('.mobile-drawer-backdrop') || 
                                    el.closest('[id$="Modal"]') ||
                                    el.classList.contains('topbar');
                    if (!isKnown && !el.classList.contains('hidden')) {
                        console.warn('⚠️ [Overlap Detector] พบ Z-Index สูงผิดปกติ:', zIndex, el);
                        el.style.outline = '3px dashed red';
                        el.style.outlineOffset = '-3px';
                        warnings++;
                    }
                }
            });
            
            if(warnings > 0) {
                console.log(`🚨 ตรวจพบ UI อาจทับซ้อนกัน ${warnings} จุด`);
                if(window.IS_ADMIN) Toast.show(`ดักจับพบ Z-Index ผิดปกติ ${warnings} จุด (ดู Console)`, 'error');
            }
        };
        
        // รันตรวจสอบหลักจากโหลดเสร็จ 2 วินาที
        setTimeout(window.checkUIOverlaps, 2000);

    </script>

    <script>
        window.NOTIFICATIONS_CONFIG = {
            isAdmin: <?php echo hasRole(['admin', 'super_admin']) ? 'true' : 'false'; ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/common.js"></script>
    <script src="assets/js/notifications.js"></script>
</body>
</html>
