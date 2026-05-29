<?php
// views/layouts/sidebar.php
// Ultimate SaaS Sidebar (Collapsible Desktop + Drawer/Bottom Nav Mobile)
?>
<script src="https://unpkg.com/lucide@latest"></script>

<aside id="sidebar-desktop" class="sidebar">
    <div class="sidebar-logo">
        <div class="w-8 h-8 bg-[var(--c-primary)] rounded-lg flex items-center justify-center text-[var(--c-text-inv)] font-bold shadow-btn shrink-0">B</div>
        <span class="sidebar-logo-text text-xl font-bold tracking-tight text-[var(--c-text-1)]">Bonus<span class="text-[var(--c-primary)]">.</span></span>
    </div>

    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 custom-scrollbar">
        
        <a href="index.php?page=home" class="nav-item <?= $page === 'home' ? 'active' : '' ?>" data-label="หน้าแรก">
            <div class="icon"><i data-lucide="layout-dashboard"></i></div>
            <span class="nav-label">หน้าแรก</span>
        </a>

        <div class="nav-label px-3 py-2 text-[10px] font-bold text-[var(--c-text-3)] uppercase tracking-widest mt-2 whitespace-nowrap">เมนูหลัก</div>
        
        <a href="index.php?page=checkin" class="nav-item <?= $page === 'checkin' ? 'active' : '' ?>" data-label="ระบบเช็คอิน">
            <div class="icon"><i data-lucide="camera"></i></div>
            <span class="nav-label">ระบบเช็คอิน</span>
        </a>

        <?php if (!hasRole('sales')): ?>
        <a href="index.php?page=start_day" class="nav-item <?= $page === 'start_day' ? 'active' : '' ?>" data-label="ค่าแรกเข้า">
            <div class="icon"><i data-lucide="gauge"></i></div>
            <span class="nav-label">ค่าแรกเข้า</span>
        </a>
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

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <div class="nav-label px-3 py-2 text-[10px] font-bold text-[var(--c-text-3)] uppercase tracking-widest mt-2 whitespace-nowrap">ตั้งค่าระบบ</div>
        <a href="index.php?page=system_history" class="nav-item <?= $page === 'system_history' ? 'active' : '' ?>" data-label="ประวัติรวมทั้งหมด">
            <div class="icon"><i data-lucide="database"></i></div>
            <span class="nav-label">ประวัติรวมทั้งหมด</span>
        </a>
        <a href="index.php?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>" data-label="จัดการผู้ใช้">
            <div class="icon"><i data-lucide="users"></i></div>
            <span class="nav-label">จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-user flex items-center gap-3 group relative cursor-pointer interactive hover:bg-[var(--c-surface-2)] rounded-lg mx-2 mb-2 transition-colors">
        <div class="w-10 h-10 rounded-full bg-[var(--c-primary-faint)] flex items-center justify-center text-[var(--c-primary)] font-bold shrink-0">
            <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 2)) ?>
        </div>
        <div class="nav-label flex-1 overflow-hidden">
            <p class="text-sm font-bold text-[var(--c-text-1)] truncate"><?= htmlspecialchars($user['full_name']) ?></p>
            <p class="text-[10px] text-[var(--c-text-3)] font-medium uppercase tracking-tight"><?= htmlspecialchars($user['role']) ?></p>
        </div>
        <a href="logout.php" class="nav-label text-[var(--c-text-3)] hover:text-[var(--c-danger)] transition-colors shrink-0" title="ออกจากระบบ">
            <i data-lucide="log-out" class="w-5 h-5"></i>
        </a>
        
        <div class="hidden-tooltip absolute left-16 bg-[var(--c-text-1)] text-white text-xs px-2 py-1 rounded opacity-0 pointer-events-none transition-opacity whitespace-nowrap z-[60]">
            <?= htmlspecialchars($user['full_name']) ?> <br>
            <span class="text-[var(--c-danger)] text-[10px]"><a href="logout.php">ออกจากระบบ</a></span>
        </div>
    </div>

    <button id="sidebarToggle" class="sidebar-toggle">
        <i data-lucide="chevron-left" class="w-4 h-4 chevron text-[var(--c-text-3)]"></i>
    </button>
</aside>

<div id="mobileDrawerBackdrop" class="mobile-drawer-backdrop"></div>

<aside id="mobileDrawer" class="mobile-drawer flex flex-col">
    <div class="h-14 px-4 flex items-center justify-between border-b border-[var(--c-border)] shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-[var(--c-primary)] rounded-lg flex items-center justify-center text-white font-bold shadow-sm">B</div>
            <span class="text-xl font-bold tracking-tight text-[var(--c-text-1)]">Bonus<span class="text-[var(--c-primary)]">.</span></span>
        </div>
        <button id="closeDrawerBtn" class="p-2 text-[var(--c-text-3)] hover:text-[var(--c-text-1)]">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-2">
        <a href="index.php?page=home" class="nav-item <?= $page === 'home' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="layout-dashboard"></i></div>
            <span class="nav-label">หน้าแรก</span>
        </a>
        <a href="index.php?page=checkin" class="nav-item <?= $page === 'checkin' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="camera"></i></div>
            <span class="nav-label">ระบบเช็คอิน</span>
        </a>
        <?php if (!hasRole('sales')): ?>
        <a href="index.php?page=start_day" class="nav-item <?= $page === 'start_day' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="gauge"></i></div>
            <span class="nav-label">ค่าแรกเข้า</span>
        </a>
        <a href="index.php?page=oil" class="nav-item <?= $page === 'oil' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="fuel"></i></div>
            <span class="nav-label">น้ำมันและยานพาหนะ</span>
        </a>
        <a href="index.php?page=dispatch" class="nav-item <?= $page === 'dispatch' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="map"></i></div>
            <span class="nav-label">ระบบจัดส่งอัจฉริยะ</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="nav-item <?= $page === 'inventory' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="package"></i></div>
            <span class="nav-label">ระบบคลังสินค้า</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <div class="nav-label px-3 py-2 text-[10px] font-bold text-[var(--c-text-3)] uppercase tracking-widest mt-2">ตั้งค่าระบบ</div>
        <a href="index.php?page=system_history" class="nav-item <?= $page === 'system_history' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="database"></i></div>
            <span class="nav-label">ประวัติรวมทั้งหมด</span>
        </a>
        <a href="index.php?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>">
            <div class="icon"><i data-lucide="users"></i></div>
            <span class="nav-label">จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="p-4 border-t border-[var(--c-border)]">
        <a href="logout.php" class="flex items-center gap-3 text-[var(--c-danger)] font-medium p-2 hover:bg-[var(--c-danger-bg)] rounded-lg transition-colors">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<nav id="bottom-nav" class="bottom-tabs md:hidden">
    <a href="index.php?page=home" class="tab-item <?= $page === 'home' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></div>
        <span class="tab-label">Home</span>
    </a>
    <a href="index.php?page=checkin" class="tab-item <?= $page === 'checkin' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="camera" class="w-6 h-6"></i></div>
        <span class="tab-label">Scan</span>
    </a>
    <?php if (!hasRole('sales')): ?>
    <a href="index.php?page=start_day" class="tab-item <?= $page === 'start_day' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="gauge" class="w-6 h-6"></i></div>
        <span class="tab-label">แรกเข้า</span>
    </a>
    <a href="index.php?page=oil" class="tab-item <?= $page === 'oil' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="fuel" class="w-6 h-6"></i></div>
        <span class="tab-label">Oil</span>
    </a>
    <a href="index.php?page=dispatch" class="tab-item <?= $page === 'dispatch' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="map" class="w-6 h-6"></i></div>
        <span class="tab-label">Map</span>
    </a>
    <?php endif; ?>
    <?php if (hasRole(['admin', 'super_admin'])): ?>
    <a href="index.php?page=inventory" class="tab-item <?= $page === 'inventory' ? 'active' : '' ?>">
        <div class="tab-icon"><i data-lucide="package" class="w-6 h-6"></i></div>
        <span class="tab-label">คลัง</span>
    </a>
    <?php endif; ?>
</nav>

<script>
    // สั่งให้รอโหลดหน้าเว็บทั้งหมด (ทั้งจาก index.php และ sidebar.php) ก่อนค่อยทำงาน
    document.addEventListener('DOMContentLoaded', () => {
        
        // Desktop Sidebar Toggle Logic
        const sidebar = document.getElementById('sidebar-desktop');
        const toggleBtn = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('main-content-area');

        // ตอนนี้จะหาเจอแล้วแน่นอน
        if (sidebar && toggleBtn && mainContent) {
            
            // อ่านค่าจาก LocalStorage ว่าเคยพับไว้ไหม
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            }

            // เวลากดปุ่ม พับ/ขยาย ให้สลับ class ให้ครบทั้งสองฝั่ง
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }

        // Tooltip แสดงชื่อเวลาพับ Sidebar
        const userSect = document.querySelector('.sidebar-user');
        const userTooltip = document.querySelector('.hidden-tooltip');
        
        if (userSect && userTooltip && sidebar) {
            userSect.addEventListener('mouseenter', () => {
                if (sidebar.classList.contains('collapsed')) {
                    userTooltip.style.opacity = '1';
                    userTooltip.style.pointerEvents = 'auto';
                }
            });
            userSect.addEventListener('mouseleave', () => {
                userTooltip.style.opacity = '0';
                userTooltip.style.pointerEvents = 'none';
            });
        }
    });
</script>