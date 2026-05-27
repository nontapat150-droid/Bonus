<?php
// views/layouts/sidebar.php
?>
<aside class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col h-full sticky top-0 shadow-sm" style="height: 100vh;">
    <div class="p-8 border-b border-slate-50">
        <h2 class="text-2xl font-black text-indigo-600 tracking-tighter">สมาร์ทสูท</h2>
        <div class="mt-4 flex items-center">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold mr-3">
                <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
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
        <a href="index.php?page=home" class="flex items-center px-4 py-3 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-all <?php echo ($page === 'home') ? 'bg-indigo-50 text-indigo-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">🏠</span> หน้าแรก
        </a>

        <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">โมดูลหลัก</div>

        <a href="index.php?page=checkin" class="flex items-center px-4 py-3 text-slate-600 hover:bg-orange-50 hover:text-orange-600 rounded-xl transition-all <?php echo ($page === 'checkin') ? 'bg-orange-50 text-orange-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">📸</span> ระบบเช็คอิน
        </a>

        <a href="index.php?page=oil" class="flex items-center px-4 py-3 text-slate-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-all <?php echo ($page === 'oil') ? 'bg-blue-50 text-blue-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">⛽</span> น้ำมันและยานพาหนะ
        </a>

        <a href="index.php?page=dispatch" class="flex items-center px-4 py-3 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-all <?php echo ($page === 'dispatch') ? 'bg-emerald-50 text-emerald-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">🗺️</span> ระบบจัดส่งอัจฉริยะ
        </a>

        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <a href="index.php?page=inventory" class="flex items-center px-4 py-3 text-slate-600 hover:bg-purple-50 hover:text-purple-600 rounded-xl transition-all <?php echo ($page === 'inventory') ? 'bg-purple-50 text-purple-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">📦</span> ระบบคลังสินค้า
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">ตั้งค่าระบบ</div>
        <a href="index.php?page=users" class="flex items-center px-4 py-3 text-slate-600 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-all <?php echo ($page === 'users') ? 'bg-rose-50 text-rose-600 font-bold' : ''; ?>">
            <span class="mr-3 text-xl opacity-70">👥</span> จัดการผู้ใช้
        </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-slate-50">
        <a href="logout.php" class="flex items-center justify-center w-full px-4 py-2.5 bg-slate-50 text-slate-500 hover:bg-rose-500 hover:text-white rounded-xl transition-all font-bold text-sm">
            🚪 ออกจากระบบ
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
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    });
</script>