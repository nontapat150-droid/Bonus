<?php
// index.php (Modern SaaS Dashboard)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus. | Smart Business Suite</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6C5CE7;
            --primary-hover: #5A4BD1;
            --bg-main: #F4F5FB;
            --surface: #FFFFFF;
            --text-primary: #1A1A2E;
            --text-secondary: #6B7280;
            --border: #E5E7EB;
        }

        body {
            font-family: 'Inter', 'Sarabun', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
        }

        .card {
            background: var(--surface);
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            padding: 24px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .top-bar {
            height: 64px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: background-color 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .search-input {
            background: #F9FAFB;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            width: 320px;
            font-size: 13px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,92,231,0.15);
        }

        .badge-k {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px 4px;
            font-size: 10px;
            color: var(--text-secondary);
            margin-left: 8px;
        }

        /* Responsive Mobile */
        @media (max-width: 767px) {
            .top-bar { padding: 0 16px; }
            .search-input { width: 40px; padding: 10px; border-radius: 50%; border: none; background: transparent; }
            .search-input::placeholder { color: transparent; }
            .search-input-box i { position: absolute; left: 12px; top: 12px; }
            .card { padding: 16px; }
            main { padding: 16px !important; }
            .badge-k { display: none; }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'views/layouts/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="flex items-center gap-4 flex-1">
                <div class="relative search-input-box">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <input type="text" placeholder="ค้นหาข้อมูล... (⌘K)" class="search-input pl-10">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 badge-k">⌘K</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="relative p-2 text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
                <a href="index.php?page=checkin" class="btn-primary">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>ทำรายการใหม่</span>
                </a>
            </div>
        </header>

        <main class="p-6 overflow-y-auto">
            
            <?php if ($page === 'home'): ?>
                <div class="max-w-7xl mx-auto space-y-8 animate__animated animate__fadeIn">
                    
                    <!-- Welcome Section -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">ยินดีต้อนรับ, <?= htmlspecialchars($user['full_name']) ?> 👋</h1>
                            <p class="text-slate-500 mt-1">นี่คือสรุปข้อมูลระบบงานประจำวันที่ <?= date('d M Y') ?></p>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="card relative overflow-hidden group">
                            <div class="absolute top-6 right-6 w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="zap"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900"><?= number_format($stats['jobs_today']) ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">งานที่ต้องทำวันนี้</p>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full">↑ 12%</span>
                                <span class="text-[10px] text-slate-400 font-medium">จากเมื่อวาน</span>
                            </div>
                        </div>

                        <div class="card relative overflow-hidden">
                            <div class="absolute top-6 right-6 w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="fuel"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900">฿<?= number_format($stats['oil_cost_today']) ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">ค่าน้ำมันวันนี้</p>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-700 text-[10px] font-bold rounded-full">↓ 5%</span>
                                <span class="text-[10px] text-slate-400 font-medium">ประหยัดขึ้น</span>
                            </div>
                        </div>

                        <div class="card relative overflow-hidden">
                            <div class="absolute top-6 right-6 w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="package"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900"><?= number_format($stats['total_stock']) ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">สินค้าคงคลัง</p>
                            <div class="mt-4 flex items-center gap-2 text-[10px] text-slate-400 font-medium">
                                <i data-lucide="clock" class="w-3 h-3"></i> อัปเดตล่าสุด 5 นาทีที่แล้ว
                            </div>
                        </div>

                        <div class="card relative overflow-hidden">
                            <div class="absolute top-6 right-6 w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="users"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900"><?= number_format($stats['total_staff']) ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">จำนวนบุคลากร</p>
                            <div class="mt-4 flex items-center gap-2 text-[10px] text-slate-400 font-medium">
                                <i data-lucide="check-circle" class="w-3 h-3 text-emerald-500"></i> ทุกคนพร้อมปฏิบัติงาน
                            </div>
                        </div>
                    </div>

                    <!-- Main Actions (Quick Access) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="card flex flex-col justify-between hover:border-indigo-200 transition-all cursor-pointer group">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-100">
                                    <i data-lucide="map-pin"></i>
                                </div>
                                <i data-lucide="arrow-up-right" class="text-slate-300 group-hover:text-indigo-600 transition-colors"></i>
                            </div>
                            <div class="mt-8">
                                <h3 class="text-lg font-bold text-slate-900">ระบบจัดส่งและแผนที่</h3>
                                <p class="text-sm text-slate-500 mt-1">ดูตำแหน่งงานของทีมช่างและวางแผนเส้นทางอัจฉริยะ</p>
                            </div>
                            <a href="index.php?page=dispatch" class="mt-6 text-indigo-600 font-bold text-xs uppercase tracking-widest flex items-center gap-2">
                                เข้าสู่ระบบแผนที่ <i data-lucide="chevron-right" class="w-3 h-3"></i>
                            </a>
                        </div>

                        <div class="card flex flex-col justify-between hover:border-emerald-200 transition-all cursor-pointer group">
                            <div class="flex items-start justify-between">
                                <div class="w-12 h-12 bg-emerald-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-emerald-100">
                                    <i data-lucide="box"></i>
                                </div>
                                <i data-lucide="arrow-up-right" class="text-slate-300 group-hover:text-emerald-600 transition-colors"></i>
                            </div>
                            <div class="mt-8">
                                <h3 class="text-lg font-bold text-slate-900">จัดการคลังสินค้า</h3>
                                <p class="text-sm text-slate-500 mt-1">ตรวจสอบสินค้า รับเข้า-เบิกออก และประวัติการทำรายการ</p>
                            </div>
                            <a href="index.php?page=inventory" class="mt-6 text-emerald-600 font-bold text-xs uppercase tracking-widest flex items-center gap-2">
                                ไปที่คลังสินค้า <i data-lucide="chevron-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                    </div>

                </div>

            <?php else: ?>
                <div class="animate__animated animate__fadeIn">
                <?php
                $routes = [
                    'oil' => hasRole(['technician']) ? 'views/modules/oil_form.php' : 'views/modules/oil_report.php',
                    'oil_test_form' => 'views/modules/oil_form.php',
                    'dispatch' => 'views/modules/dispatch_map.php',
                    'inventory' => 'views/modules/inventory_app.php',
                    'users' => 'views/modules/user_settings.php',
                    'checkin' => 'views/modules/checkin.php'
                ];
                if (array_key_exists($page, $routes) && file_exists($routes[$page])) {
                    include $routes[$page];
                } else {
                    echo '<div class="card p-12 text-center max-w-2xl mx-auto">
                            <div class="text-6xl mb-6">⚙️</div>
                            <h2 class="text-2xl font-bold text-slate-900 mb-2">อยู่ระหว่างการพัฒนา</h2>
                            <p class="text-slate-500">โมดูลนี้กำลังถูกปรับปรุงเพื่อประสิทธิภาพที่ดีที่สุดสำหรับคุณ</p>
                            <a href="index.php?page=home" class="mt-8 btn-primary">กลับหน้าแรก</a>
                          </div>';
                }
                ?>
                </div>
            <?php endif; ?>

            <footer class="mt-20 text-center pb-10">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
                    &copy; <?= date('Y') ?> Bonus. SMART BUSINESS SUITE • PRIVACY POLICY
                </p>
            </footer>
        </main>
    </div>

    <!-- Toast UI -->
    <div id="toast-container" class="fixed top-6 right-6 z-[100] flex flex-col gap-3"></div>
    
    <script>
        // Lucide init
        lucide.createIcons();

        const Toast = {
            show(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `card flex items-center justify-between gap-4 py-3 px-4 border-l-4 ${type === 'success' ? 'border-emerald-500' : 'border-red-500'} animate__animated animate__fadeInRight`;
                toast.style.width = '300px';
                
                const icon = type === 'success' ? 'check-circle' : 'alert-circle';
                const iconColor = type === 'success' ? 'text-emerald-500' : 'text-red-500';

                toast.innerHTML = `
                    <div class="flex items-center gap-3">
                        <i data-lucide="${icon}" class="${iconColor} w-5 h-5"></i>
                        <span class="font-semibold text-slate-800">${message}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-500">&times;</button>
                `;
                container.appendChild(toast);
                lucide.createIcons();
                
                setTimeout(() => {
                    toast.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                    setTimeout(() => toast.remove(), 500);
                }, 4000);
            },
            success(msg) { this.show(msg, 'success'); },
            error(msg) { this.show(msg, 'error'); }
        };
    </script>

</body>
</html>
