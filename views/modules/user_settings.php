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
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2 justify-end">
            <button onclick="openIndividualSummaryModal()" class="px-4 py-2 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white rounded-xl font-bold text-sm shadow-[0_4px_10px_-2px_rgba(14,165,233,0.4)] transition-all flex items-center justify-center">
                <i data-lucide="pie-chart" class="w-4 h-4 mr-2"></i> สรุปรายบุคคล
            </button>
            <button onclick="openOverallSummaryModal()" class="px-4 py-2 bg-gradient-to-r from-fuchsia-500 to-purple-600 hover:from-fuchsia-400 hover:to-purple-500 text-white rounded-xl font-bold text-sm shadow-[0_4px_10px_-2px_rgba(217,70,239,0.4)] transition-all flex items-center justify-center">
                <i data-lucide="bar-chart-2" class="w-4 h-4 mr-2"></i> สรุปการมาทั้งหมด
            </button>
            <button onclick="loadPendingUsers()" class="px-4 py-2 relative flex items-center justify-center text-sm font-bold text-white rounded-xl transition-all" style="background: var(--c-warning); box-shadow: 0 4px 14px rgba(245,158,11, 0.40);">
                <span class="mr-2"><i data-lucide="hourglass" class="w-4 h-4"></i></span> รออนุมัติ
                <span id="pendingCountBadge" class="absolute -top-2 -right-2 bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full shadow-md hidden animate__animated animate__bounceIn">0</span>
            </button>
            <button onclick="openUserModal()" class="px-4 py-2 bg-[var(--c-primary)] hover:bg-[var(--c-primary-hover)] text-white rounded-xl font-bold text-sm shadow-[0_4px_14px_-2px_var(--c-primary)] transition-all flex items-center justify-center">
                <span class="mr-2 text-lg leading-none">+</span> เพิ่มพนักงาน
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden animate__animated animate__fadeIn">
        <div class="px-6 py-4 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex flex-col sm:flex-row justify-between items-center gap-4">
            <h3 class="font-black text-[var(--c-text-1)] tracking-tight">รายชื่อพนักงานทั้งหมด</h3>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <select id="roleFilter" class="w-full sm:w-auto px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm appearance-none pr-10 relative" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%207.5L10%2012.5L15%207.5%22%20stroke%3D%22%2364748B%22%20stroke-width%3D%221.67%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 10px center;">
                    <option value="">ทุกตำแหน่ง</option>
                    <option value="super_admin">ผู้ดูแลระบบ</option>
                    <option value="admin">แอดมิน</option>
                    <option value="technician">ช่าง Office</option>
                    <option value="ma_technician">ช่าง MA</option>
                    <option value="sales">เซล</option>
                    <option value="intern">เด็กฝึกงาน</option>
                </select>
                <div class="relative w-full sm:w-64">
                    <input type="text" id="searchUser" placeholder="ค้นหาชื่อ หรือ Username..." class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm">
                    <span class="absolute left-3 top-2.5 text-[var(--c-text-3)]"><i data-lucide="search" class="w-4 h-4"></i></span>
                </div>
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

                <div id="daysOffField" class="hidden space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 ml-1">
                        <i data-lucide="calendar-x" class="w-4 h-4 text-rose-500"></i> วันหยุดประจำสัปดาห์
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/60" id="daysOffCheckboxes">
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Monday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันจันทร์</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Tuesday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันอังคาร</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Wednesday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันพุธ</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Thursday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันพฤหัสบดี</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Friday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันศุกร์</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Saturday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันเสาร์</span>
                        </label>
                        <label class="group relative flex items-center gap-2 p-2 rounded-xl cursor-pointer transition-all hover:bg-white hover:shadow-sm">
                            <input type="checkbox" name="days_off[]" value="Sunday" class="dayoff-cb w-4 h-4 text-rose-600 bg-white border-slate-300 rounded focus:ring-rose-600 focus:ring-2">
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-700 transition-colors">วันอาทิตย์</span>
                        </label>
                    </div>
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
                        <th class="px-4 py-3">ตำแหน่งที่ขอ</th>
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

<!-- Overall Summary Modal -->
<div id="overallSummaryModal" class="fixed inset-0 z-[80] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
    <div class="bg-white rounded-[24px] w-full max-w-[95%] md:max-w-5xl flex flex-col max-h-[92vh] overflow-hidden animate__animated animate__zoomIn animate__faster z-[90] shadow-2xl border border-white/20">
        
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-fuchsia-500/10 rounded-full blur-2xl"></div>
            <div class="flex items-center gap-4 relative z-10 w-full">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-fuchsia-50 to-purple-50 border border-fuchsia-100/50 flex items-center justify-center text-fuchsia-600 shadow-sm">
                    <i data-lucide="bar-chart-2" class="w-6 h-6"></i>
                </div>
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">สรุปการเข้างานทั้งหมด</h3>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">ตารางเปรียบเทียบสถิติการมาตรง/มาสายของพนักงานทุกคน</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="month" id="overallSummaryMonth" class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-fuchsia-500 focus:ring-4 focus:ring-fuchsia-500/10 transition-all shadow-sm" onchange="loadOverallSummary()">
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeOverallSummaryModal()" class="relative z-10 w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors ml-4 shrink-0">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50">
            <!-- Summary Boxes -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl -mr-4 -mt-4"></div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">มาตรงเวลา (รวม)</p>
                        <p id="totalOnTimeBadge" class="text-2xl font-black text-slate-800">0</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-16 h-16 bg-rose-500/10 rounded-full blur-xl -mr-4 -mt-4"></div>
                    <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">มาสาย (รวม)</p>
                        <p id="totalLateBadge" class="text-2xl font-black text-slate-800">0</p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-16 h-16 bg-amber-500/10 rounded-full blur-xl -mr-4 -mt-4"></div>
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                        <i data-lucide="calendar-off" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">การลา (รวมวัน)</p>
                        <p id="totalLeavesBadge" class="text-2xl font-black text-slate-800">0</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600 whitespace-nowrap">
                        <thead class="text-xs text-slate-500 uppercase tracking-wider font-black bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4">อันดับ</th>
                                <th class="px-6 py-4">รายชื่อพนักงาน</th>
                                <th class="px-6 py-4 text-center">ตำแหน่ง</th>
                                <th class="px-6 py-4 text-center text-emerald-600">มาตรงเวลา</th>
                                <th class="px-6 py-4 text-center text-rose-600">มาสาย</th>
                                <th class="px-6 py-4 text-center text-slate-500">วันหยุด</th>
                            </tr>
                        </thead>
                        <tbody id="overallSummaryTableBody" class="divide-y divide-slate-100">
                            <!-- JS will populate -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Individual Summary List Modal -->
<div id="individualSummaryModal" class="fixed inset-0 z-[80] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
    <div class="bg-white rounded-[24px] w-full max-w-[95%] md:max-w-6xl flex flex-col max-h-[92vh] overflow-hidden animate__animated animate__zoomIn animate__faster z-[90] shadow-2xl border border-white/20">
        
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-sky-500/10 rounded-full blur-2xl"></div>
            <div class="flex items-center gap-4 relative z-10 w-full">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-50 to-blue-50 border border-sky-100/50 flex items-center justify-center text-sky-600 shadow-sm">
                    <i data-lucide="pie-chart" class="w-6 h-6"></i>
                </div>
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">สรุปสถิติรายบุคคล</h3>
                        <p class="text-xs font-medium text-slate-500 mt-0.5" id="individualSubtitle">เลือกพนักงานเพื่อดูรายละเอียด หรือเปลี่ยนเดือน</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="month" id="individualSummaryMonth" class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all shadow-sm" onchange="loadIndividualSummaryData()">
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeIndividualSummaryModal()" class="relative z-10 w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors ml-4 shrink-0">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <div class="flex flex-1 overflow-hidden min-h-0">
            <!-- Sidebar list of users -->
            <div class="w-full md:w-1/3 lg:w-1/4 bg-slate-50 border-r border-slate-200 flex flex-col min-h-0">
                <div class="p-4 border-b border-slate-200 shrink-0">
                    <div class="relative">
                        <input type="text" id="individualSearchUser" placeholder="ค้นหาชื่อ..." class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-2 transition-all shadow-sm" onkeyup="filterIndividualUsers()">
                        <span class="absolute left-3 top-2.5 text-slate-400"><i data-lucide="search" class="w-4 h-4"></i></span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar" id="individualUserList">
                    <!-- Populated by JS -->
                </div>
            </div>
            
            <!-- Dashboard details -->
            <div class="hidden md:flex flex-1 flex-col overflow-hidden bg-slate-50/30 min-h-0" id="individualDashboardArea">
                <div class="flex-1 flex items-center justify-center text-slate-400 font-bold flex-col gap-3" id="individualEmptyState">
                    <i data-lucide="users" class="w-16 h-16 text-slate-200"></i>
                    <p>กรุณาเลือกพนักงานจากเมนูด้านซ้ายเพื่อดูสถิติ</p>
                </div>
                
                <div class="flex-1 overflow-y-auto p-6 hidden" id="individualContentState">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-100 to-sky-100 border-2 border-white shadow-md flex items-center justify-center text-indigo-500 font-black text-2xl uppercase" id="indAvatar">U</div>
                        <div>
                            <h2 class="text-2xl font-black text-slate-800" id="indName">ชื่อ-นามสกุล</h2>
                            <p class="text-sm font-bold text-sky-600 mt-1" id="indRole">ตำแหน่ง</p>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">มาตรงเวลา (ครั้ง)</p>
                            <p class="text-2xl font-black text-emerald-600" id="indOnTime">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">มาสาย (ครั้ง)</p>
                            <p class="text-2xl font-black text-rose-600" id="indLate">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">วันหยุด (ครั้ง)</p>
                            <p class="text-2xl font-black text-slate-600" id="indDayOff">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">ลางาน (วัน)</p>
                            <p class="text-2xl font-black text-amber-600" id="indLeaves">0</p>
                        </div>
                        
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">งาน MA สำเร็จ</p>
                            <p class="text-2xl font-black text-indigo-600" id="indMaJobs">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">งานติดตั้งสำเร็จ</p>
                            <p class="text-2xl font-black text-blue-600" id="indInstallJobs">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">เติมน้ำมัน (ครั้ง)</p>
                            <p class="text-2xl font-black text-teal-600" id="indOil">0</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-500 mb-1">เบิกค่าแรกเข้า (ครั้ง)</p>
                            <p class="text-2xl font-black text-fuchsia-600" id="indStartDay">0</p>
                        </div>
                    </div>
                    
                    <!-- Calendar / History Table -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                            <h4 class="font-black text-slate-700">ประวัติการเข้างานเดือนที่เลือก</h4>
                        </div>
                        <div class="overflow-y-auto max-h-[300px]">
                            <table class="w-full text-sm text-left text-slate-600 whitespace-nowrap">
                                <thead class="text-[10px] text-slate-500 uppercase tracking-wider font-black bg-white sticky top-0 border-b border-slate-200 shadow-sm">
                                    <tr>
                                        <th class="px-5 py-3">วันที่</th>
                                        <th class="px-5 py-3">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="indHistoryTableBody" class="divide-y divide-slate-100">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/admin_summary.js"></script>
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
            
            const roleBadges = {
                'super_admin': '<span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded-full font-bold text-[10px] border border-rose-100">ผู้ดูแลระบบ</span>',
                'admin': '<span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full font-bold text-[10px] border border-indigo-100">แอดมิน</span>',
                'technician': '<span class="px-2 py-0.5 bg-slate-50 text-slate-500 rounded-full font-bold text-[10px] border border-slate-100">ช่าง Office</span>',
                'ma_technician': '<span class="px-2 py-0.5 bg-violet-50 text-violet-600 rounded-full font-bold text-[10px] border border-violet-100">ช่าง MA</span>',
                'sales': '<span class="px-2 py-0.5 bg-green-50 text-green-600 rounded-full font-bold text-[10px] border border-green-100">เซล</span>',
                'intern': '<span class="px-2 py-0.5 bg-cyan-50 text-cyan-600 rounded-full font-bold text-[10px] border border-cyan-100">เด็กฝึกงาน</span>'
            };

            tbody.innerHTML = data.data.map(user => {
                const userRoles = (user.roles || [user.role]).map(r => roleBadges[r] || `<span class="px-2 py-0.5 bg-slate-50 text-slate-500 rounded-full font-bold text-[10px] border border-slate-100">${r}</span>`).join('<span class="inline-block w-1"></span>');
                return `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-bold text-slate-700">${user.full_name}</td>
                    <td class="px-4 py-3">${user.username}</td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-1">${userRoles}</div></td>
                    <td class="px-4 py-3"><span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg text-[10px] font-black">${user.team_name || '-'}</span></td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="approveUser(${user.id}, 'approved')" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-emerald-600 shadow-sm">อนุมัติ</button>
                        <button onclick="approveUser(${user.id}, 'rejected')" class="bg-rose-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-rose-600 ml-1 shadow-sm">ปฏิเสธ</button>
                    </td>
                </tr>
            `}).join('');
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