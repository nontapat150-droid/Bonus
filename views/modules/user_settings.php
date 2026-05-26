<?php
// views/modules/user_settings.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole('super_admin')) {
    echo "<div class='p-12 text-center'><h2 class='text-2xl font-bold text-rose-500'>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2><p class='text-slate-500 mt-2'>เฉพาะผู้ดูแลระบบสูงสุดเท่านั้นที่สามารถจัดการพนักงานได้</p></div>";
    exit;
}
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white/60 backdrop-blur-md p-6 rounded-[2rem] shadow-sm border border-white">
        <div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight flex items-center">
                <span class="mr-3 p-2 bg-rose-100 text-rose-600 rounded-2xl shadow-inner text-2xl">👥</span>
                จัดการพนักงาน
            </h2>
            <p class="text-slate-500 text-sm mt-1 font-medium">เพิ่ม แก้ไข และกำหนดสิทธิ์การใช้งานของพนักงาน</p>
        </div>
        <button onclick="openUserModal()" class="mt-4 md:mt-0 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 flex items-center justify-center">
            <span class="mr-2 text-lg">+</span> เพิ่มพนักงานใหม่
        </button>
    </div>

    <!-- User List Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-50 overflow-hidden animate__animated animate__fadeIn">
        <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
            <h3 class="font-black text-slate-700 tracking-tight">รายชื่อพนักงานทั้งหมด</h3>
            <div class="relative w-full sm:w-64">
                <input type="text" id="searchUser" placeholder="ค้นหาชื่อ หรือ Username..." class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white border-transparent focus:border-indigo-500 focus:ring-0 text-sm font-bold shadow-sm transition-all">
                <span class="absolute left-4 top-3.5 text-slate-300">🔍</span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-500">
                <thead class="text-[10px] text-slate-400 uppercase tracking-[0.2em] font-black bg-slate-50/30">
                    <tr>
                        <th class="px-8 py-5">ชื่อ-นามสกุล</th>
                        <th class="px-8 py-5">Username</th>
                        <th class="px-8 py-5">ตำแหน่ง / สิทธิ์</th>
                        <th class="px-8 py-5">วันที่เข้าร่วม</th>
                        <th class="px-8 py-5 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="userTableBody" class="divide-y divide-slate-50">
                    <!-- Data via JS -->
                    <tr><td colspan="5" class="px-8 py-20 text-center"><div class="loader-spinner mx-auto mb-4 w-8 h-8"></div><p class="font-bold text-slate-400">กำลังโหลดรายชื่อ...</p></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
    <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-md overflow-hidden animate__animated animate__zoomIn">
        <div class="p-8 bg-gradient-to-br from-indigo-600 to-violet-700 text-white flex justify-between items-center">
            <h3 id="modalTitle" class="text-xl font-black italic tracking-tight">เพิ่มพนักงานใหม่</h3>
            <button onclick="closeUserModal()" class="text-white/50 hover:text-white text-3xl font-light">&times;</button>
        </div>
        
        <form id="userForm" class="p-8 space-y-5">
            <input type="hidden" id="userId" name="id">
            
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ชื่อ-นามสกุลจริง</label>
                <input type="text" id="full_name" name="full_name" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-bold transition-all" placeholder="ตัวอย่าง: นายสมชาย ยอดรัก">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ชื่อผู้ใช้ (Username)</label>
                <input type="text" id="username_field" name="username" required class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-bold transition-all" placeholder="สำหรับใช้ Login">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ตำแหน่งในระบบ</label>
                <select id="role" name="role" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-bold transition-all">
                    <option value="technician">ช่างเทคนิค (Technician)</option>
                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    <option value="super_admin">ผู้ดูแลระบบสูงสุด (Super Admin)</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="w-full px-5 py-3.5 rounded-2xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-bold transition-all" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
                <p id="passwordHelp" class="text-[10px] text-slate-400 mt-2 ml-1 hidden italic">* หากแก้ไขข้อมูล ไม่ต้องกรอกหากไม่ต้องการเปลี่ยนรหัสผ่าน</p>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black shadow-lg shadow-indigo-100 transform transition-all active:scale-95">
                    🚀 บันทึกข้อมูลพนักงาน
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/users.js"></script>