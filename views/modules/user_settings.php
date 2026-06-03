<?php
// views/modules/user_settings.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole(['admin', 'super_admin'])) {
    echo "<div class='p-12 text-center'><h2 class='text-2xl font-bold text-rose-500'>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2><p class='text-slate-500 mt-2'>เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถจัดการพนักงานได้</p></div>";
    exit;
}
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-3xl font-black text-[var(--c-text-1)] tracking-tight flex items-center">
                <span class="mr-3 p-2 bg-[var(--c-primary-faint)] text-[var(--c-primary)] rounded-xl shadow-inner text-2xl"><i data-lucide="users" class="w-6 h-6"></i></span>
                จัดการพนักงาน
            </h2>
            <p class="text-[var(--c-text-3)] text-sm mt-1 font-medium">เพิ่ม แก้ไข และกำหนดสิทธิ์การใช้งานของพนักงาน</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-3">
            <button onclick="loadPendingUsers()" class="btn-primary relative flex items-center justify-center w-full sm:w-auto" style="background: var(--c-warning); box-shadow: 0 4px 14px rgba(245,158,11, 0.40);">
                <span class="mr-2"><i data-lucide="hourglass" class="w-4 h-4"></i></span> รออนุมัติ
                <span id="pendingCountBadge" class="absolute -top-2 -right-2 bg-rose-500 text-white text-xs font-black px-2 py-0.5 rounded-full shadow-md hidden animate__animated animate__bounceIn">0</span>
            </button>
            <button onclick="openUserModal()" class="btn-primary flex items-center justify-center w-full sm:w-auto">
                <span class="mr-2 text-lg">+</span> เพิ่มพนักงานใหม่
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden animate__animated animate__fadeIn">
        <div class="px-6 py-4 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex flex-col sm:flex-row justify-between items-center gap-4">
            <h3 class="font-black text-[var(--c-text-1)] tracking-tight">รายชื่อพนักงานทั้งหมด</h3>
            <div class="relative w-full sm:w-64">
                <input type="text" id="searchUser" placeholder="ค้นหาชื่อ หรือ Username..." class="w-full pl-10 pr-4 py-2 input text-sm font-bold transition-all">
                <span class="absolute left-3 top-2.5 text-[var(--c-text-3)]"><i data-lucide="search" class="w-4 h-4"></i></span>
            </div>
        </div>
        
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm text-left text-[var(--c-text-2)] whitespace-nowrap">
                <thead class="text-[10px] text-[var(--c-text-3)] uppercase tracking-[0.1em] font-black bg-[var(--c-surface-3)]">
                    <tr>
                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                        <th class="px-6 py-4">Username</th>
                        <th class="px-6 py-4">รหัสผ่าน</th>
                        <th class="px-6 py-4">ตำแหน่ง / สิทธิ์</th>
                        <th class="px-6 py-4">วันที่เข้าร่วม</th>
                        <th class="px-6 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="userTableBody" class="divide-y divide-[var(--c-border)]">
                    <tr><td colspan="6" class="px-8 py-20 text-center"><div class="loader-spinner mx-auto mb-4 w-8 h-8"></div><p class="font-bold text-[var(--c-text-3)]">กำลังโหลดรายชื่อ...</p></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="userModal" class="fixed inset-0 z-[80] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
    <div class="bg-white rounded-[24px] w-full max-w-[95%] md:max-w-[480px] flex flex-col max-h-[92vh] overflow-hidden animate__animated animate__zoomIn animate__faster z-[90] shadow-2xl border border-white/20 ring-1 ring-slate-900/5">
        
        <!-- Premium Header -->
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl"></div>
            <div class="absolute bottom-0 left-0 -ml-8 -mb-8 w-24 h-24 bg-violet-500/10 rounded-full blur-xl"></div>
            
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 border border-indigo-100/50 flex items-center justify-center text-indigo-600 shadow-sm">
                    <i data-lucide="user-plus" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-xl font-black text-slate-800 tracking-tight">เพิ่มพนักงานใหม่</h3>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">กรอกรายละเอียดข้อมูลพนักงาน</p>
                </div>
            </div>
            <button type="button" onclick="closeUserModal()" class="relative z-10 w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <!-- Form Area -->
        <form id="userForm" class="flex flex-col flex-1 overflow-hidden relative">
            <input type="hidden" id="userId" name="id">
            
            <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar flex-1 bg-slate-50/30">
                
                <!-- Group 1: General Info -->
                <div class="space-y-4">
                    <div>
                        <label class="flex justify-between text-xs font-bold text-slate-700 mb-1.5 ml-1">
                            <span>ชื่อ-นามสกุลจริง <span class="text-rose-500">*</span></span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <input type="text" id="full_name" name="full_name" required class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm" placeholder="เช่น นายสมชาย ยอดรัก">
                        </div>
                    </div>

                    <div>
                        <label class="flex justify-between text-xs font-bold text-slate-700 mb-1.5 ml-1">
                            <span>ชื่อผู้ใช้ (Username) <span class="text-rose-500">*</span></span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="at-sign" class="w-4 h-4"></i>
                            </div>
                            <input type="text" id="username_field" name="username" required class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm" placeholder="สำหรับใช้เข้าสู่ระบบ">
                        </div>
                    </div>

                    <div>
                        <label class="flex justify-between text-xs font-bold text-slate-700 mb-1.5 ml-1">
                            <span>รหัสผ่าน</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <input type="password" id="password" name="password" class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
                        </div>
                        <p id="passwordHelp" class="text-[11px] text-indigo-500 mt-2 ml-1 hidden font-medium flex items-center gap-1"><i data-lucide="info" class="w-3 h-3"></i> รหัสเดิมถูกเข้ารหัสไว้ หากไม่ต้องการเปลี่ยนให้เว้นว่าง</p>
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- Group 2: Roles -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2 ml-1">ตำแหน่งในระบบ <span class="text-slate-400 font-normal">(เลือกได้มากกว่า 1)</span></label>
                    <div id="rolesCheckboxes" class="grid grid-cols-2 gap-2 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/60">
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="technician" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">ช่าง Office</span>
                        </label>
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="ma_technician" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">ช่าง MA</span>
                        </label>
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="admin" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">แอดมิน</span>
                        </label>
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="super_admin" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">ผู้ดูแลระบบ</span>
                        </label>
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="intern" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">เด็กฝึกงาน</span>
                        </label>
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="roles[]" value="sales" class="role-cb w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-600 focus:ring-2">
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-700 transition-colors">เซล</span>
                        </label>
                    </div>
                    <select id="role" name="role" class="hidden">
                        <option value="technician">technician</option>
                    </select>
                </div>

                <!-- Group 3: Settings -->
                <div id="lateTimeField" class="hidden space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 ml-1">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i> เวลามาสายที่อนุมัติ
                    </label>
                    <input type="time" id="allow_late_time" name="allow_late_time" value="08:30" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm">
                    <p class="text-[11px] text-slate-500 font-medium ml-1">ระบบจะถือว่าเข้างานตรงเวลา หากเช็คอินก่อนเวลาที่กำหนด</p>
                </div>

                <div class="space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 ml-1">
                        <i data-lucide="car" class="w-4 h-4 text-sky-500"></i> กำหนดทีม / ป้ายทะเบียน
                    </label>
                    <div class="relative">
                        <select id="team_id" name="team_id" class="w-full pl-4 pr-10 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 appearance-none focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm">
                            <option value="">-- ไม่มีทีม (ไม่ได้สังกัดทีมใด) --</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Premium Footer Action -->
            <div class="p-5 bg-white border-t border-slate-100 shrink-0 z-10">
                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white rounded-xl font-bold shadow-[0_8px_20px_-6px_rgba(99,102,241,0.5)] hover:shadow-[0_12px_24px_-8px_rgba(99,102,241,0.6)] transform hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i> บันทึกข้อมูลพนักงาน
                </button>
            </div>
        </form>
    </div>
</div>

<div id="pendingModal" class="fixed inset-0 z-[80] hidden bg-[var(--c-overlay)] backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-[var(--c-surface)] rounded-2xl w-full max-w-[95%] md:max-w-3xl overflow-hidden animate__animated animate__zoomIn z-[90]" style="box-shadow: var(--shadow-4);">
        <div class="p-6 bg-[var(--c-warning)] text-white flex justify-between items-center">
            <h3 class="text-xl font-bold tracking-tight">รายการรออนุมัติเข้าใช้งาน</h3>
            <button onclick="document.getElementById('pendingModal').classList.add('hidden')" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 max-h-[60vh] overflow-y-auto w-full overflow-x-auto">
            <table class="w-full text-sm text-left text-[var(--c-text-2)] whitespace-nowrap">
                <thead class="text-[10px] text-[var(--c-text-3)] uppercase font-black bg-[var(--c-surface-3)]">
                    <tr>
                        <th class="px-4 py-3">ชื่อ-นามสกุล</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">ทะเบียนรถ/ทีม</th>
                        <th class="px-4 py-3 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="pendingTableBody" class="divide-y divide-[var(--c-border)]">
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
async function updatePendingBadge() {
    try {
        const res = await fetch('api/users/get_pending.php');
        const data = await res.json();
        if (data.success) {
            const count = data.data.length;
            const badge = document.getElementById('pendingCountBadge');
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    } catch (e) {}
}

document.addEventListener('DOMContentLoaded', updatePendingBadge);

async function loadPendingUsers() {
    document.getElementById('pendingModal').classList.remove('hidden');
    const tbody = document.getElementById('pendingTableBody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-10">กำลังโหลด...</td></tr>';
    
    try {
        const res = await fetch('api/users/get_pending.php');
        const data = await res.json();
        
        if (data.success) {
            if(data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-10 text-slate-400 font-bold">ไม่มีรายการรออนุมัติ</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.data.map(user => `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-bold text-slate-700">${user.full_name}</td>
                    <td class="px-4 py-3">${user.username}</td>
                    <td class="px-4 py-3"><span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg text-[10px] font-black">${user.team_name || '-'}</span></td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="approveUser(${user.id}, 'approved')" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-emerald-600 shadow-sm">อนุมัติ</button>
                        <button onclick="approveUser(${user.id}, 'rejected')" class="bg-rose-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-rose-600 ml-1 shadow-sm">ปฏิเสธ</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-10 text-rose-500">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
    }
}

async function approveUser(id, status) {
    const actionText = status === 'approved' ? 'อนุมัติการเข้าใช้งาน' : 'ปฏิเสธคำขอ';
    const confirmColor = status === 'approved' ? '#10B981' : '#EF4444';

    const result = await Swal.fire({
        title: `ยืนยันการ${actionText}?`,
        text: `คุณต้องการ${actionText}ของผู้ใช้นี้ใช่หรือไม่`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#94a3b8',
        confirmButtonText: `ใช่, ${actionText}`,
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    });

    if(!result.isConfirmed) return;
    
    // เรียก Loading SweetAlert2
    Swal.fire({
        title: 'กำลังดำเนินการ...',
        text: 'กรุณารอสักครู่',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        customClass: { popup: 'rounded-3xl' }
    });

    try {
        const res = await fetch('api/users/approve.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id, status })
        });
        const data = await res.json();

        // ดีเลย์ 0.6 วิ ให้ผู้ใช้เห็นการโหลด
        await new Promise(resolve => setTimeout(resolve, 600)); 

        if(data.success) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: `ทำรายการ${actionText}เรียบร้อยแล้ว`,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });

            loadPendingUsers(); 
            updatePendingBadge(); 
            if (typeof loadUsers === "function") loadUsers(); 
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch(e) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
}
</script>
<script src="assets/js/common.js"></script>
<script src="assets/js/users.js"></script>