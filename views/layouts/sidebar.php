<?php
// views/layouts/sidebar.php
?>
<style>
    /* CSS สำหรับ Desktop Sidebar */
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

    /* CSS สำหรับ Mobile Drawer */
    .drawer-open {
        transform: translateX(0) !important;
    }

    /* 🟢 เพิ่มเติม: ดันเนื้อหาลงมาไม่ให้ Navbar มือถือบัง */
    @media (max-width: 767px) {
        main {
            /* ความสูง Navbar คือ 5rem + ระยะห่าง 1.5rem = 6.5rem */
            padding-top: 6.5rem !important; 
        }
    }
</style>

<aside id="desktopSidebar" class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col h-full sticky top-0 shadow-sm relative z-40" style="height: 100vh;">
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

<div class="md:hidden bg-white border-b border-slate-100 p-4 flex justify-between items-center fixed top-0 left-0 w-full z-[40] shadow-sm h-[5rem]">
    <h2 class="text-xl font-black text-indigo-600 tracking-tighter">สมาร์ทสูท</h2>
    <button id="mobileMenuBtn" class="p-2 text-slate-500 hover:text-indigo-600 focus:outline-none transition-colors bg-slate-50 rounded-xl">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/> 
        </svg>
    </button>
</div>

<div id="mobileOverlay" class="md:hidden fixed inset-0 bg-slate-900/60 z-[45] hidden backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

<div id="mobileDrawer" class="md:hidden fixed top-0 left-0 w-[80%] max-w-sm h-full bg-white z-[50] transform -translate-x-full shadow-2xl flex flex-col transition-transform duration-300">
    
    <div class="p-6 border-b border-slate-100 bg-indigo-50/50 flex justify-between items-start">
        <div class="flex flex-col">
            <div class="w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center text-white font-black mb-4 text-2xl shadow-lg shadow-indigo-200">
                <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <h3 class="font-black text-slate-800 text-lg leading-tight"><?php echo htmlspecialchars($user['full_name'] ?? 'ผู้ใช้งาน'); ?></h3>
            <p class="text-[10px] font-bold text-indigo-600 mt-1 uppercase tracking-wider bg-indigo-100 inline-block px-2 py-0.5 rounded w-max">
                <?php echo htmlspecialchars($role_map[$user['role'] ?? 'Guest'] ?? ucfirst($user['role'] ?? 'Guest')); ?>
            </p>
        </div>
        <button id="closeDrawerBtn" class="p-2 text-slate-400 hover:text-rose-500 bg-white rounded-full shadow-sm border border-slate-100">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    
    <nav class="flex-1 overflow-y-auto p-4 space-y-2">
        <a href="index.php?page=home" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-colors <?php echo ($page === 'home') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'font-medium'; ?>">
            <span class="mr-4 text-xl opacity-80">🏠</span> หน้าแรก
        </a>
        
        <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">โมดูลหลัก</div>
        
        <a href="index.php?page=checkin" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-orange-50 hover:text-orange-600 rounded-xl transition-colors <?php echo ($page === 'checkin') ? 'bg-orange-50 text-orange-600 font-bold' : 'font-medium'; ?>">
            <span class="mr-4 text-xl opacity-80">📸</span> ระบบเช็คอิน
        </a>
        
        <a href="index.php?page=oil" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors <?php echo ($page === 'oil') ? 'bg-blue-50 text-blue-600 font-bold' : 'font-medium'; ?>">
            <span class="mr-4 text-xl opacity-80">⛽</span> น้ำมันและยานพาหนะ
        </a>
        
        <a href="index.php?page=dispatch" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors <?php echo ($page === 'dispatch') ? 'bg-emerald-50 text-emerald-600 font-bold' : 'font-medium'; ?>">
            <span class="mr-4 text-xl opacity-80">🗺️</span> ระบบจัดส่งอัจฉริยะ
        </a>
        
        <?php if (hasRole(['admin', 'super_admin'])): ?>
            <a href="index.php?page=inventory" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-purple-50 hover:text-purple-600 rounded-xl transition-colors <?php echo ($page === 'inventory') ? 'bg-purple-50 text-purple-600 font-bold' : 'font-medium'; ?>">
                <span class="mr-4 text-xl opacity-80">📦</span> ระบบคลังสินค้า
            </a>
        <?php endif; ?>
        
        <?php if (hasRole('super_admin')): ?>
            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">ตั้งค่าระบบ</div>
            <a href="index.php?page=users" class="flex items-center px-4 py-3.5 text-slate-600 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-colors <?php echo ($page === 'users') ? 'bg-rose-50 text-rose-600 font-bold' : 'font-medium'; ?>">
                <span class="mr-4 text-xl opacity-80">👥</span> จัดการผู้ใช้
            </a>
        <?php endif; ?>
    </nav>
    
    <div class="p-6 border-t border-slate-100 mt-auto bg-slate-50">
        <a href="logout.php" class="flex items-center justify-center w-full px-4 py-3.5 bg-rose-100 text-rose-600 hover:bg-rose-500 hover:text-white rounded-xl transition-colors font-black text-sm shadow-sm">
            <span class="mr-2 text-xl">🚪</span> ออกจากระบบ
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ==========================================
        // ส่วนควบคุม Mobile Drawer
        // ==========================================
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const mobileDrawer = document.getElementById('mobileDrawer');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function openDrawer() {
            mobileOverlay.classList.remove('hidden');
            // หน่วงเวลาเล็กน้อยเพื่อให้ CSS Render ทันก่อนปรับ opacity
            setTimeout(() => {
                mobileOverlay.classList.remove('opacity-0');
                mobileDrawer.classList.add('drawer-open');
            }, 10);
        }

        function closeDrawer() {
            mobileDrawer.classList.remove('drawer-open');
            mobileOverlay.classList.add('opacity-0');
            setTimeout(() => {
                mobileOverlay.classList.add('hidden');
            }, 300); // รอให้ Animation เล่นจบ (0.3s)
        }

        if (mobileMenuBtn && mobileDrawer) {
            mobileMenuBtn.addEventListener('click', openDrawer);
            closeDrawerBtn.addEventListener('click', closeDrawer);
            mobileOverlay.addEventListener('click', closeDrawer);
        }

        // ==========================================
        // ส่วนควบคุม Desktop Sidebar (พับ/กาง)
        // ==========================================
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