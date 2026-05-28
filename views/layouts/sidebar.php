<?php
// views/layouts/sidebar.php
// Ultimate SaaS Sidebar (Collapsible Desktop + Drawer/Bottom Nav Mobile)
?>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- CSS is handled in index.php for global scope -->

<!-- === Sidebar Desktop === -->
<aside id="sidebar-desktop" class="sidebar">
    <!-- App Logo -->
    <div class="sidebar-logo">
        <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold shadow-btn shrink-0">B</div>
        <span class="sidebar-logo-text text-xl font-bold tracking-tight text-slate-900">Bonus<span class="text-indigo-600">.</span></span>
    </div>

    <!-- Nav Menu -->
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 custom-scrollbar">
        
        <a href="index.php?page=home" class="nav-item <?= $page === 'home' ? 'active' : '' ?>" data-label="หน้าแรก">
            <div class="icon"><i data-lucide="layout-dashboard"></i></div>
            <span class="nav-label">หน้าแรก</span>
        </a>

        <div class="nav-label px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 whitespace-nowrap">เมนูหลัก</div>
        
        <a href="index.php?page=checkin" class="nav-item <?= $page === 'checkin' ? 'active' : '' ?>" data-label="ระบบเช็คอิน">
            <div class="icon"><i data-lucide="camera"></i></div>
            <span class="nav-label">ระบบเช็คอิน</span>
        </a>

        <?php if (!hasRole('sales')): ?>
        <a href="index.php?page=oil" class="nav-item <?= $page === 'oil' ? 'active' : '' ?>" data-label="น้ำมันและยานพาหนะ">
            <div class="icon"><i data-lucide="fuel"></i></div>
            <span class="nav-label">น้ำมันและยานพาหนะ</span>
        </a>
        <a href="index.php?page=dispatch" class="nav-item <?= $page === 'dispatch' ? 'active' : '' ?>" data-label="ระบบจัดส่งอัจฉริยะ">
            <div class="icon"><i data-lucide="map"></i></div>
            <span class="nav-label">ระบบจัดส่งอัจฉริยะ</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="nav-item <?= $page === 'inventory' ? 'active' : '' ?>" data-label="ระบบคลังสินค้า">
            <div class="icon"><i data-lucide="package"></i></div>
            <span class="nav-label">ระบบคลังสินค้า</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="nav-label px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 whitespace-nowrap">ตั้งค่าระบบ</div>
        <a href="index.php?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>" data-label="จัดการผู้ใช้">
            <div class="icon"><i data-lucide="users"></i></div>
            <span class="nav-label">จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- User Profile Section -->
    <div class="sidebar-user flex items-center gap-3 group relative cursor-pointer interactive hover:bg-slate-50 rounded-lg mx-2 mb-2">
        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold shrink-0">
            <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 2)) ?>
        </div>
        <div class="nav-label flex-1 overflow-hidden">
            <p class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($user['full_name']) ?></p>
            <p class="text-[10px] text-slate-500 font-medium uppercase tracking-tight"><?= htmlspecialchars($user['role']) ?></p>
        </div>
        <a href="logout.php" class="nav-label text-slate-400 hover:text-red-500 transition-colors shrink-0" title="ออกจากระบบ">
            <i data-lucide="log-out" class="w-5 h-5"></i>
        </a>
        
        <!-- Hover tooltip for collapsed state -->
        <div class="hidden-tooltip absolute left-16 bg-slate-800 text-white text-xs px-2 py-1 rounded opacity-0 pointer-events-none transition-opacity whitespace-nowrap z-60">
            <?= htmlspecialchars($user['full_name']) ?> <br>
            <span class="text-red-400 text-[10px]"><a href="logout.php">ออกจากระบบ</a></span>
        </div>
    </div>

    <!-- Toggle Button -->
    <button id="sidebarToggle" class="sidebar-toggle">
        <i data-lucide="chevron-left" class="w-4 h-4 chevron text-slate-500"></i>
    </button>
</aside>

<!-- === Mobile Drawer Backdrop === -->
<div id="mobileDrawerBackdrop" class="mobile-drawer-backdrop"></div>

<!-- === Mobile Drawer === -->
<aside id="mobileDrawer" class="mobile-drawer flex flex-col">
    <!-- App Logo -->
    <div class="h-14 px-4 flex items-center justify-between border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold shadow-sm">B</div>
            <span class="text-xl font-bold tracking-tight text-slate-900">Bonus<span class="text-indigo-600">.</span></span>
        </div>
        <button id="closeDrawerBtn" class="p-2 text-slate-400 hover:text-slate-600">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Drawer Nav -->
    <nav class="flex-1 overflow-y-auto py-4 px-2">
        <a href="index.php?page=home" class="nav-item <?= $page === 'home' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="layout-dashboard"></i></div>
            <span class="nav-label">หน้าแรก</span>
        </a>
        
        <!-- Copy all links here as needed, omitting for brevity since Bottom Tabs cover core nav -->
        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="nav-item <?= $page === 'inventory' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="package"></i></div>
            <span class="nav-label">ระบบคลังสินค้า</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="nav-label px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">ตั้งค่าระบบ</div>
        <a href="index.php?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="users"></i></div>
            <span class="nav-label">จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="p-4 border-t border-gray-100">
        <a href="logout.php" class="flex items-center gap-3 text-red-500 font-medium p-2 hover:bg-red-50 rounded-lg transition-colors">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- === Bottom Nav Mobile === -->
<nav id="bottom-nav" class="bottom-tabs md:hidden">
    <a href="index.php?page=home" class="tab-item <?= $page === 'home' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></div>
        <span class="tab-label">Home</span>
    </a>
    <a href="index.php?page=checkin" class="tab-item <?= $page === 'checkin' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="camera" class="w-6 h-6"></i></div>
        <span class="tab-label">Scan</span>
    </a>
    <a href="index.php?page=oil" class="tab-item <?= $page === 'oil' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="fuel" class="w-6 h-6"></i></div>
        <span class="tab-label">Oil</span>
    </a>
    <a href="index.php?page=dispatch" class="tab-item <?= $page === 'dispatch' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="map" class="w-6 h-6"></i></div>
        <span class="tab-label">Map</span>
    </a>
</nav>

<script>
    lucide.createIcons();

    // Desktop Sidebar Toggle Logic
    const sidebar = document.getElementById('sidebar-desktop');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('main-content-area'); // Need to ensure this ID is in index.php

    if (sidebar && toggleBtn) {
        // Load state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            if(mainContent) mainContent.classList.add('sidebar-collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            if(mainContent) mainContent.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Tooltip logic for user profile when collapsed
    const userSect = document.querySelector('.sidebar-user');
    const userTooltip = document.querySelector('.hidden-tooltip');
    if(userSect && userTooltip) {
        userSect.addEventListener('mouseenter', () => {
            if(sidebar.classList.contains('collapsed')) {
                userTooltip.style.opacity = '1';
                userTooltip.style.pointerEvents = 'auto';
            }
        });
        userSect.addEventListener('mouseleave', () => {
            userTooltip.style.opacity = '0';
            userTooltip.style.pointerEvents = 'none';
        });
    }
</script>
