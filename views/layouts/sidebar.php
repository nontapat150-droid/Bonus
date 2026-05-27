<?php
// views/layouts/sidebar.php
?>
<style>
    #desktopSidebar {
        transition: width 0.3s ease-in-out;
    }
    .sidebar-collapsed {
        width: 5.5rem !important;
    }
    .sidebar-collapsed .sidebar-text,
    .sidebar-collapsed .sidebar-brand-text,
    .sidebar-collapsed .user-info {
        display: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease-in-out;
    }
    .sidebar-collapsed .sidebar-header-box,
    .sidebar-collapsed .nav-item,
    .sidebar-collapsed .module-title {
        overflow: hidden;
    }
    .sidebar-collapsed .user-avatar {
        margin-right: 0 !important;
    }
    .sidebar-collapsed .sidebar-header-box {
        padding: 1.5rem 0.75rem;
        text-align: center;
    }
    .sidebar-collapsed .nav-item {
        justify-content: center;
        padding: 0.75rem;
    }
    .sidebar-collapsed .nav-icon {
        margin-right: 0 !important;
    }
    .sidebar-collapsed .sidebar-section-short {
        color: transparent;
        position: relative;
    }
    .sidebar-collapsed .sidebar-section-short::after {
        content: 'ระบบ';
        color: #94a3b8;
        position: absolute;
        left: 0;
        right: 0;
        text-align: center;
    }
    .sidebar-collapsed #sidebarToggle {
        transform: rotate(180deg);
    }
</style>
<aside id="desktopSidebar" class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col h-full sticky top-0 shadow-sm relative" style="height: 100vh;">
    <button id="sidebarToggle" class="absolute -right-3 top-8 bg-white border border-slate-200 rounded-full p-1.5 text-slate-400 hover:text-indigo-600 shadow-sm z-50 transition-transform duration-300 flex items-center justify-center" aria-label="พับ/ขยายเมนู" title="พับ/ขยายเมนู">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </button>
    <div class="sidebar-header-box p-8 border-b border-slate-50 transition-all duration-300">
        <h2 class="sidebar-brand-text text-2xl font-black text-indigo-600 tracking-tighter">สมาร์ทสูท</h2>
        <div class="mt-4 flex items-center transition-all duration-300">
            <div class="user-avatar w-10 h-10 rounded-full bg-indigo-100 flex-shrink-0 flex items-center justify-center text-indigo-700 font-bold mr-3 transition-all duration-300">
                <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-info whitespace-nowrap overflow-hidden">
                <p class="text-sm font-bold text-slate-800 leading-none"><?php echo htmlspecialchars($user['full_name'] ?? 'ผู้ใช้งาน'); ?></p>
                <span class="inline-block mt-1.5 px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded uppercase tracking-wider">
                    <?php 
                        $role_map = [
                            'super_admin' => 'ผู้ดูแลระบบสูงสุด',
                            'admin' => 'ผู้ดูแลระบบ',
                            'technician' => 'ช่างเทคนิค',
                            'user' => 'ผู้ใช้งานทั่วไป'
                        ];
                        $role = $user['role'] ?? 'Guest';
                        echo htmlspecialchars($role_map[$role] ?? ucfirst($role)); 
                    ?>
                </span>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <a href="index.php?page=home" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-all <?php echo ($page === 'home') ? 'bg-indigo-50 text-indigo-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">🏠</span>
            <span class="sidebar-text whitespace-nowrap">หน้าแรก</span>
        </a>

        <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">โมดูลหลัก</div>

        <a href="index.php?page=checkin" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-orange-50 hover:text-orange-600 rounded-xl transition-all <?php echo ($page === 'checkin') ? 'bg-orange-50 text-orange-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">📸</span>
            <span class="sidebar-text whitespace-nowrap">ระบบเช็คอิน</span>
        </a>

        <a href="index.php?page=oil" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-all <?php echo ($page === 'oil') ? 'bg-blue-50 text-blue-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">⛽</span>
            <span class="sidebar-text whitespace-nowrap">น้ำมันและยานพาหนะ</span>
        </a>

        <a href="index.php?page=dispatch" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-all <?php echo ($page === 'dispatch') ? 'bg-emerald-50 text-emerald-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">🗺️</span>
            <span class="sidebar-text whitespace-nowrap">ระบบจัดส่งอัจฉริยะ</span>
        </a>

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-purple-50 hover:text-purple-600 rounded-xl transition-all <?php echo ($page === 'inventory') ? 'bg-purple-50 text-purple-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">📦</span>
            <span class="sidebar-text whitespace-nowrap">ระบบคลังสินค้า</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest sidebar-section-short">ตั้งค่าระบบ</div>
        <a href="index.php?page=users" class="nav-item flex items-center px-4 py-3 text-slate-600 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-all <?php echo ($page === 'users') ? 'bg-rose-50 text-rose-600 font-bold' : ''; ?>">
            <span class="nav-icon mr-3 text-xl opacity-70">👥</span>
            <span class="sidebar-text whitespace-nowrap">จัดการผู้ใช้</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-slate-50">
        <a href="logout.php" class="nav-item flex items-center justify-center w-full px-4 py-2.5 bg-slate-50 text-slate-500 hover:bg-rose-500 hover:text-white rounded-xl transition-all font-bold text-sm">
            <span class="nav-icon text-xl opacity-70">🚪</span>
            <span class="sidebar-text ml-2 whitespace-nowrap">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<div class="md:hidden bg-white border-b border-slate-100 p-4 flex justify-between items-center sticky top-0 z-40 shadow-sm">
    <h2 class="text-xl font-black text-indigo-600 tracking-tighter">สมาร์ทสูท</h2>
    <button id="mobileMenuBtn" class="p-2 text-slate-400 hover:text-indigo-600 focus:outline-none transition-colors">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/> 
        </svg>
    </button>
</div>

<div id="mobileMenu" class="md:hidden hidden bg-white border-b border-slate-100 absolute w-full z-30 shadow-xl animate__animated animate__fadeInDown"> 
    <nav class="flex flex-col p-6 space-y-1">
        <a href="index.php?page=home" class="px-4 py-3 text-slate-600 hover:bg-indigo-50 rounded-xl font-bold">🏠 หน้าแรก</a>
        
        <a href="index.php?page=checkin" class="px-4 py-3 text-slate-600 hover:bg-orange-50 rounded-xl font-bold">📸 ระบบเช็คอิน</a>
        
        <a href="index.php?page=oil" class="px-4 py-3 text-slate-600 hover:bg-blue-50 rounded-xl font-bold">⛽ น้ำมันและยานพาหนะ</a>
        <a href="index.php?page=dispatch" class="px-4 py-3 text-slate-600 hover:bg-emerald-50 rounded-xl font-bold">🗺️ ระบบจัดส่งอัจฉริยะ</a>
        
        <?php if (hasRole(['admin', 'super_admin'])): ?>
            <a href="index.php?page=inventory" class="px-4 py-3 text-slate-600 hover:bg-purple-50 rounded-xl font-bold">📦 ระบบคลังสินค้า</a>
        <?php endif; ?>
        
        <?php if (hasRole('super_admin')): ?>
            <a href="index.php?page=users" class="px-4 py-3 text-slate-600 hover:bg-rose-50 rounded-xl font-bold">👥 จัดการผู้ใช้</a>
        <?php endif; ?>
        
        <hr class="my-4 border-slate-100">
        <a href="logout.php" class="px-4 py-3 text-rose-600 hover:bg-rose-50 rounded-xl font-bold">🚪 ออกจากระบบ</a>
    </nav>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');

        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }

        const sidebarToggle = document.getElementById('sidebarToggle');
        const desktopSidebar = document.getElementById('desktopSidebar');

        if (sidebarToggle && desktopSidebar) {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                desktopSidebar.classList.add('sidebar-collapsed');
            }

            sidebarToggle.addEventListener('click', function() {
                desktopSidebar.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', desktopSidebar.classList.contains('sidebar-collapsed'));
            });
        }
    });
</script>