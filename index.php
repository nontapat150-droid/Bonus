<?php
// index.php (Main Home Page Router)
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
        // 1. Jobs Today (Created today or plan_arrival_date is today)
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at) = CURDATE() OR plan_arrival_date = CURDATE()");
        $stats['jobs_today'] = $stmt->fetchColumn();

        // 2. Oil Cost Today
        $stmt = $pdo->query("SELECT SUM(total_price) FROM oil_records WHERE DATE(date_recorded) = CURDATE()");
        $stats['oil_cost_today'] = $stmt->fetchColumn() ?: 0;

        // 3. Total Stock (In stock items)
        $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'in_stock'");
        $stats['total_stock'] = $stmt->fetchColumn();

        // 4. Total Staff (Total users)
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $stats['total_staff'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Silently fail or log error
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการธุรกิจอัจฉริยะ</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #fdfdfd;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 30px 40px -15px rgba(0, 0, 0, 0.1);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #9333ea 100%);
        }

        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            right: -10%;
            bottom: -10%;
            width: 100px;
            height: 100px;
            background: white;
            opacity: 0.1;
            border-radius: 50%;
        }

        /* Toast & Loader */
        #toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { min-width: 280px; padding: 1rem; border-radius: 1rem; color: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; animation: slideInRight 0.3s ease-out; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        #global-loader { position: fixed; inset: 0; background: rgba(255,255,255,0.8); backdrop-filter: blur(4px); z-index: 10000; display: none; flex-direction: column; align-items: center; justify-content: center; }
        #global-loader.active { display: flex; }
        .loader-spinner { width: 40px; height: 40px; border: 4px solid #f3f4f6; border-top: 4px solid #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Mobile Adjustments */
        @media (max-width: 1024px) {
            main { padding-top: 5rem; }
        }
    </style>
</head>
<body class="text-slate-800 h-screen flex overflow-hidden">

    <div id="global-loader">
        <div class="loader-spinner mb-4"></div>
        <p class="text-indigo-600 font-black animate-pulse">กำลังประมวลผล...</p>
    </div>

    <div id="toast-container"></div>

    <?php include 'views/layouts/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 lg:p-10">

        <?php if ($page === 'home'): ?>
            <div class="max-w-6xl mx-auto space-y-10 animate__animated animate__fadeIn">
                
                <section class="hero-gradient rounded-[2.5rem] p-8 lg:p-12 text-white shadow-2xl shadow-indigo-200 relative overflow-hidden group">
                    <div class="relative z-10">
                        <span class="bg-white/20 backdrop-blur-md px-4 py-1 rounded-full text-xs font-black uppercase tracking-widest mb-4 inline-block border border-white/10 animate__animated animate__fadeInDown">
                            Dashboard • ประจำวันที่ <?php echo date('d/m/Y'); ?>
                        </span>
                        <h1 class="text-4xl lg:text-6xl font-black tracking-tighter mb-4 leading-tight">
                            ยินดีต้อนรับกลับมา,<br> 
                            <span class="text-yellow-300"><?php echo htmlspecialchars($user['full_name']); ?></span> ✨
                        </h1>
                        <p class="text-indigo-100 text-lg lg:text-xl font-medium max-w-xl opacity-90">
                            ยินดีที่ได้พบคุณอีกครั้ง! วันนี้เรามีงานที่รอให้คุณจัดการและสรุปข้อมูลที่น่าสนใจด้านล่างนี้
                        </p>
                    </div>
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all duration-700"></div>
                    <div class="absolute right-10 top-10 text-8xl opacity-20 group-hover:scale-110 transition-transform duration-700">🚀</div>
                </section>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                    <div class="stat-card bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-xl transition-all">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-4 text-2xl shadow-inner">⚡</div>
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest">งานวันนี้</p>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo number_format($stats['jobs_today']); ?> <span class="text-sm font-medium text-slate-400">งาน</span></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-xl transition-all">
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-4 text-2xl shadow-inner">⛽</div>
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest">ค่าน้ำมันวันนี้</p>
                        <h3 class="text-3xl font-black text-slate-800 mt-1">฿<?php echo number_format($stats['oil_cost_today']); ?></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-xl transition-all">
                        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center mb-4 text-2xl shadow-inner">📦</div>
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest">สินค้าในคลัง</p>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo number_format($stats['total_stock']); ?> <span class="text-sm font-medium text-slate-400">ชิ้น</span></h3>
                    </div>
                    <div class="stat-card bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-xl transition-all">
                        <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-4 text-2xl shadow-inner">👥</div>
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest">พนักงานทั้งหมด</p>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo number_format($stats['total_staff']); ?> <span class="text-sm font-medium text-slate-400">ท่าน</span></h3>
                    </div>
                </div>

                <div class="space-y-6">
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight flex items-center">
                        <span class="w-2 h-8 bg-indigo-600 rounded-full mr-3"></span>
                        เมนูการใช้งานหลัก
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                        
                        <a href="index.php?page=checkin" class="glass-card group p-8 rounded-[2.5rem] relative overflow-hidden">
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-orange-500 text-white rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-lg shadow-orange-200 group-hover:rotate-12 transition-transform">📸</div>
                                <h3 class="text-2xl font-black text-slate-800 mb-2">ระบบเช็คอิน</h3>
                                <p class="text-slate-500 text-sm leading-relaxed">ถ่ายรูปเพื่อบันทึกเวลาเข้างานแบบเรียลไทม์</p>
                                <div class="mt-6 flex items-center text-orange-600 font-black text-xs uppercase tracking-widest group-hover:translate-x-2 transition-transform">
                                    เช็คอินเข้างาน <span class="ml-2">➜</span>
                                </div>
                            </div>
                            <div class="absolute -right-4 -top-4 text-8xl opacity-[0.03] font-black group-hover:scale-125 transition-transform duration-700 uppercase">Time</div>
                        </a>

                        <a href="index.php?page=oil" class="glass-card group p-8 rounded-[2.5rem] relative overflow-hidden">
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-blue-600 text-white rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-lg shadow-blue-200 group-hover:rotate-12 transition-transform">⛽</div>
                                <h3 class="text-2xl font-black text-slate-800 mb-2">ระบบบันทึกน้ำมัน</h3>
                                <p class="text-slate-500 text-sm leading-relaxed">บันทึกเลขไมล์และหลักฐานการใช้น้ำมันเพื่อเบิกจ่าย</p>
                                <div class="mt-6 flex items-center text-blue-600 font-black text-xs uppercase tracking-widest group-hover:translate-x-2 transition-transform">
                                    เข้าใช้งานระบบ <span class="ml-2">➜</span>
                                </div>
                            </div>
                            <div class="absolute -right-4 -top-4 text-8xl opacity-[0.03] font-black group-hover:scale-125 transition-transform duration-700 uppercase">Oil</div>
                        </a>

                        <a href="index.php?page=dispatch" class="glass-card group p-8 rounded-[2.5rem] relative overflow-hidden">
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-emerald-600 text-white rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-lg shadow-emerald-200 group-hover:rotate-12 transition-transform">🗺️</div>
                                <h3 class="text-2xl font-black text-slate-800 mb-2">ระบบจัดส่ง</h3>
                                <p class="text-slate-500 text-sm leading-relaxed">จัดการแผนที่ เส้นทาง และจ่ายงานให้ทีมช่างอัตโนมัติ</p>
                                <div class="mt-6 flex items-center text-emerald-600 font-black text-xs uppercase tracking-widest group-hover:translate-x-2 transition-transform">
                                    เปิดแผนที่ <span class="ml-2">➜</span>
                                </div>
                            </div>
                            <div class="absolute -right-4 -top-4 text-8xl opacity-[0.03] font-black group-hover:scale-125 transition-transform duration-700 uppercase">Map</div>
                        </a>

                        <a href="index.php?page=inventory" class="glass-card group p-8 rounded-[2.5rem] relative overflow-hidden">
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-purple-600 text-white rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-lg shadow-purple-200 group-hover:rotate-12 transition-transform">📦</div>
                                <h3 class="text-2xl font-black text-slate-800 mb-2">คลังสินค้าอัจฉริยะ</h3>
                                <p class="text-slate-500 text-sm leading-relaxed">เช็คสต็อก นำเข้า-เบิกออกสินค้าด้วยระบบบาร์โค้ด</p>
                                <div class="mt-6 flex items-center text-purple-600 font-black text-xs uppercase tracking-widest group-hover:translate-x-2 transition-transform">
                                    จัดการสต็อก <span class="ml-2">➜</span>
                                </div>
                            </div>
                            <div class="absolute -right-4 -top-4 text-8xl opacity-[0.03] font-black group-hover:scale-125 transition-transform duration-700 uppercase">Stock</div>
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
                'checkin' => 'views/modules/checkin.php' // <-- ลงทะเบียนเส้นทาง checkin ที่นี่
            ];
            if (array_key_exists($page, $routes) && file_exists($routes[$page])) {
                include $routes[$page];
            } else {
                echo '<div class="glass-card p-12 rounded-[2.5rem] text-center max-w-2xl mx-auto shadow-2xl">
                        <div class="text-8xl mb-6">⚙️</div>
                        <h2 class="text-3xl font-black text-slate-800 mb-4 tracking-tighter italic">อยู่ระหว่างการพัฒนา</h2>
                        <p class="text-slate-400 font-medium">โมดูลนี้กำลังถูกปรับปรุงเพื่อประสิทธิภาพที่ดีที่สุดสำหรับคุณ</p>
                        <a href="index.php?page=home" class="mt-8 inline-block bg-indigo-600 text-white px-8 py-3 rounded-2xl font-black shadow-lg">กลับหน้าหลัก</a>
                      </div>';
            }
            ?>
            </div>
        <?php endif; ?>

        <footer class="mt-20 text-center border-t border-slate-100 pt-10 pb-10">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-300">
                &copy; <?php echo date('Y'); ?> ระบบบริหารจัดการธุรกิจอัจฉริยะ • SMART BUSINESS SUITE
            </p>
        </footer>
    </main>

    <script>
        const Toast = {
            show(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                const icon = type === 'success' ? '✅' : '❌';
                toast.innerHTML = `<div class="flex items-center font-bold text-sm"><span class="mr-3 text-lg">${icon}</span> ${message}</div><button onclick="this.parentElement.remove()" class="ml-4 opacity-50 hover:opacity-100">&times;</button>`;
                container.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'fadeOut 0.4s ease forwards';
                    setTimeout(() => toast.remove(), 400);
                }, 3000);
            },
            success(msg) { this.show(msg, 'success'); },
            error(msg) { this.show(msg, 'error'); },
            info(msg) { this.show(msg, 'info'); }
        };
        const Loader = {
            show() { document.getElementById('global-loader').classList.add('active'); },
            hide() { document.getElementById('global-loader').classList.remove('active'); }
        };
    </script>

</body>
</html>