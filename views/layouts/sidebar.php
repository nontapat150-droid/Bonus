<?php
// views/layouts/sidebar.php
// Modern SaaS Sidebar with Bottom Tab Bar for Mobile
?>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    :root {
        --primary: #6C5CE7;
        --primary-hover: #5A4BD1;
        --bg-main: #F4F5FB;
        --sidebar-bg: #FFFFFF;
        --text-main: #1A1A2E;
        --text-muted: #6B7280;
        --border: #E5E7EB;
    }

    body {
        font-family: 'Inter', 'Sarabun', sans-serif;
        background-color: var(--bg-main);
        color: var(--text-main);
    }

    /* Sidebar Desktop */
    #sidebar-desktop {
        width: 240px;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 50;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        margin: 4px 12px;
        border-radius: 8px;
        color: var(--text-muted);
        font-weight: 500;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .nav-link:hover {
        background-color: #F9FAFB;
        color: var(--text-main);
    }

    .nav-link.active {
        background-color: var(--primary);
        color: #FFFFFF;
    }

    .nav-link i {
        margin-right: 12px;
        width: 20px;
        height: 20px;
    }

    /* Bottom Nav Mobile */
    #bottom-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #FFFFFF;
        border-top: 1px solid var(--border);
        padding: 8px 12px;
        z-index: 100;
        justify-content: space-around;
        align-items: center;
    }

    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        color: var(--text-muted);
        font-size: 10px;
        font-weight: 600;
        text-decoration: none;
    }

    .bottom-nav-item.active {
        color: var(--primary);
    }

    .bottom-nav-item i {
        width: 24px;
        height: 24px;
        margin-bottom: 2px;
    }

    @media (max-width: 767px) {
        #sidebar-desktop { display: none; }
        #bottom-nav { display: flex; }
        main { padding-bottom: 80px !important; margin-left: 0 !important; }
    }

    @media (min-width: 768px) {
        main { margin-left: 240px; }
    }
</style>

<!-- Sidebar Desktop -->
<aside id="sidebar-desktop">
    <!-- App Logo -->
    <div class="p-6 mb-2">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold shadow-lg">B</div>
            <span class="text-xl font-bold tracking-tight text-slate-900">Bonus<span class="text-indigo-600">.</span></span>
        </div>
    </div>

    <!-- Nav Menu -->
    <nav class="flex-1 overflow-y-auto">
        <a href="index.php?page=home" class="nav-link <?= $page === 'home' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> <span>หน้าแรก</span>
        </a>

        <div class="px-7 py-3 text-[11px] font-bold text-gray-400 uppercase tracking-widest mt-4">เมนูหลัก</div>
        
        <a href="index.php?page=checkin" class="nav-link <?= $page === 'checkin' ? 'active' : '' ?>">
            <i data-lucide="camera"></i> <span>เช็คอิน</span>
        </a>

        <?php if (!hasRole('sales')): ?>
        <a href="index.php?page=oil" class="nav-link <?= $page === 'oil' ? 'active' : '' ?>">
            <i data-lucide="fuel"></i> <span>น้ำมัน/รถ</span>
        </a>
        <a href="index.php?page=dispatch" class="nav-link <?= $page === 'dispatch' ? 'active' : '' ?>">
            <i data-lucide="map"></i> <span>แผนที่งาน</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="nav-link <?= $page === 'inventory' ? 'active' : '' ?>">
            <i data-lucide="package"></i> <span>คลังสินค้า</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="px-7 py-3 text-[11px] font-bold text-gray-400 uppercase tracking-widest mt-4">ตั้งค่า</div>
        <a href="index.php?page=users" class="nav-link <?= $page === 'users' ? 'active' : '' ?>">
            <i data-lucide="users"></i> <span>จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- User Profile Section -->
    <div class="p-4 border-t border-gray-100">
        <div class="flex items-center gap-3 p-2">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border-2 border-white shadow-sm">
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 2)) ?>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($user['full_name']) ?></p>
                <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight"><?= htmlspecialchars($user['role']) ?></p>
            </div>
            <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors" title="ออกจากระบบ">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Bottom Nav Mobile -->
<nav id="bottom-nav">
    <a href="index.php?page=home" class="bottom-nav-item <?= $page === 'home' ? 'active' : '' ?>">
        <i data-lucide="layout-dashboard"></i>
        <span>Home</span>
    </a>
    <a href="index.php?page=checkin" class="bottom-nav-item <?= $page === 'checkin' ? 'active' : '' ?>">
        <i data-lucide="camera"></i>
        <span>Scan</span>
    </a>
    <a href="index.php?page=oil" class="bottom-nav-item <?= $page === 'oil' ? 'active' : '' ?>">
        <i data-lucide="fuel"></i>
        <span>Oil</span>
    </a>
    <a href="index.php?page=dispatch" class="bottom-nav-item <?= $page === 'dispatch' ? 'active' : '' ?>">
        <i data-lucide="map"></i>
        <span>Map</span>
    </a>
    <a href="logout.php" class="bottom-nav-item text-red-400">
        <i data-lucide="log-out"></i>
        <span>Exit</span>
    </a>
</nav>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();
</script>
