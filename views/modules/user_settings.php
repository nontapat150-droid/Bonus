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

<div id="userModal" class="fixed inset-0 z-[80] hidden bg-[var(--c-overlay)] backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-[var(--c-surface)] rounded-2xl w-full max-w-[95%] md:max-w-md overflow-hidden animate__animated animate__zoomIn z-[90]" style="box-shadow: var(--shadow-4);">
        <div class="p-6 bg-[var(--c-primary)] text-white flex justify-between items-center">
            <h3 id="modalTitle" class="text-xl font-bold tracking-tight">เพิ่มพนักงานใหม่</h3>
            <button onclick="closeUserModal()" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        
        <form id="userForm" class="p-6 space-y-4">
            <input type="hidden" id="userId" name="id">
            
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ชื่อ-นามสกุลจริง</label>
                <input type="text" id="full_name" name="full_name" required class="input" placeholder="ตัวอย่าง: นายสมชาย ยอดรัก">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ชื่อผู้ใช้ (Username)</label>
                <input type="text" id="username_field" name="username" required class="input" placeholder="สำหรับใช้ Login">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ตำแหน่งในระบบ</label>
                <select id="role" name="role" onchange="toggleLateTimeField()" class="input">
                    <option value="technician">ช่างเทคนิค (Technician)</option>
                    <option value="sales">เซล (Sales)</option>
                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    <option value="super_admin">ผู้ดูแลระบบสูงสุด (Super Admin)</option>
                </select>
            </div>

            <div id="lateTimeField" class="hidden">
                <label class="block text-[10px] font-black uppercase tracking-widest text-orange-600 mb-2 ml-1"><i data-lucide="clock" class="w-5 h-5 inline-block"></i> เวลามาสายที่อนุมัติ (สำหรับ Sales & Technician)</label>
                <input type="time" id="allow_late_time" name="allow_late_time" value="08:30" class="input">
                <p class="text-[10px] text-slate-400 mt-2 ml-1 italic">* ตั้งเวลาที่อนุญาตให้มาสายได้ (เช่น 08:30 = อนุญาตให้มาตั้งแต่ 08:30 ให้ถือว่า "มาตรงเวลา")</p>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-amber-500 mb-2 ml-1"><i data-lucide="car" class="w-5 h-5 inline-block"></i> ทีม / ป้ายทะเบียนรถ</label>
                <select id="team_id" name="team_id" class="input">
                    <option value="">-- ไม่มีทีม --</option>
                </select>
                <p class="text-[10px] text-slate-400 mt-2 ml-1 italic">* เลือกป้ายทะเบียนที่เคยลงทะเบียนในระบบ เพื่อย้ายทีม</p>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="input" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
                <p id="passwordHelp" class="text-[10px] text-slate-400 mt-2 ml-1 hidden italic">* หากแก้ไขข้อมูล ไม่ต้องกรอกหากไม่ต้องการเปลี่ยนรหัสผ่าน (รหัสเดิมถูกเข้ารหัสไว้)</p>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-4 btn-primary">
                    <i data-lucide="rocket" class="w-5 h-5 inline-block"></i> บันทึกข้อมูลพนักงาน
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