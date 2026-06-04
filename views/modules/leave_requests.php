<?php
// views/modules/leave_requests.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole(['admin', 'super_admin'])) {
    echo "<div class='p-8 text-center text-red-600 font-bold text-xl'>ไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
    exit;
}
?>

<div class="space-y-6 animate__animated animate__fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl shadow-sm border border-rose-100">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <span class="bg-rose-100 text-rose-600 p-2 rounded-xl">
                    <i data-lucide="calendar-x" class="w-6 h-6"></i>
                </span>
                จัดการคำขอลางาน
            </h2>
            <p class="text-slate-500 mt-1 text-sm">ดูและอนุมัติคำขอลาของพนักงาน</p>
        </div>
        <!-- Status Filter -->
        <div class="flex items-center gap-2">
            <select id="leaveStatusFilter" class="border-gray-300 rounded-xl py-2 pl-3 pr-8 text-sm focus:ring-rose-400 focus:border-rose-400 font-medium">
                <option value="all">ทั้งหมด</option>
                <option value="pending" selected>รอดำเนินการ</option>
                <option value="approved">อนุมัติแล้ว</option>
                <option value="rejected">ปฏิเสธ</option>
            </select>
            <button onclick="loadAllLeaves()" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-sm">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> รีเฟรช
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-center">
            <p class="text-3xl font-black text-amber-600" id="statPending">-</p>
            <p class="text-xs font-bold text-amber-500 mt-1">⏳ รอดำเนินการ</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-center">
            <p class="text-3xl font-black text-emerald-600" id="statApproved">-</p>
            <p class="text-xs font-bold text-emerald-500 mt-1">✅ อนุมัติแล้ว</p>
        </div>
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 text-center">
            <p class="text-3xl font-black text-rose-600" id="statRejected">-</p>
            <p class="text-xs font-bold text-rose-500 mt-1">❌ ปฏิเสธ</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm text-left text-slate-600">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-5 py-3">พนักงาน</th>
                        <th class="px-5 py-3">วันที่ลา</th>
                        <th class="px-5 py-3 text-center">จำนวน</th>
                        <th class="px-5 py-3">เหตุผล</th>
                        <th class="px-5 py-3 text-center">สถานะ</th>
                        <th class="px-5 py-3 text-center">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="allLeavesBody">
                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="assets/js/leave_requests.js?v=<?= time() ?>"></script>
<script>
    lucide.createIcons();
    loadAllLeaves();
</script>
