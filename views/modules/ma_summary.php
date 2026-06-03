<?php
// views/modules/ma_summary.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole('super_admin')) {
    echo "<div class='p-12 text-center'><h2 class='text-2xl font-bold text-rose-500'>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2></div>";
    exit;
}
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-[var(--c-text-1)] flex items-center gap-3">
                <span class="p-2 bg-[var(--c-primary-faint)] text-[var(--c-primary)] rounded-xl"><i data-lucide="bar-chart-3" class="w-6 h-6"></i></span>
                สรุปข้อมูลงาน MA
            </h2>
            <p class="text-sm text-[var(--c-text-3)] mt-1">เงื่อนไข: วันทำงาน ≥ 26 วัน/เดือน (จาก Check-in MA) · งาน MA รวม ≥ 130 งาน/เดือน · มาหลังเวลาที่กำหนด = สายทันที</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="month" id="maSummaryMonth" class="input !py-2 !px-3 text-sm font-bold" value="<?= date('Y-m') ?>">
            <button onclick="loadMaSummary()" class="btn-primary !py-2 !px-4 text-sm">โหลดข้อมูล</button>
        </div>
    </div>

    <div id="maSummaryCards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>

    <div class="card !p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--c-border)] bg-[var(--c-surface-2)]">
            <h3 class="font-black text-[var(--c-text-1)]">รายละเอียดช่าง MA (วันทำงานจาก Check-in MA)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-[10px] uppercase tracking-wider font-black text-[var(--c-text-3)] bg-[var(--c-surface-3)]">
                    <tr>
                        <th class="px-6 py-3 text-left">ชื่อ</th>
                        <th class="px-6 py-3 text-center">วันทำงาน</th>
                        <th class="px-6 py-3 text-center">ตรงเวลา</th>
                        <th class="px-6 py-3 text-center">มาสาย</th>
                        <th class="px-6 py-3 text-center">ผ่าน ≥26 วัน</th>
                        <th class="px-6 py-3 text-center">งาน MA ปิดสำเร็จ</th>
                    </tr>
                </thead>
                <tbody id="maSummaryTableBody" class="divide-y divide-[var(--c-border)]">
                    <tr><td colspan="6" class="px-6 py-12 text-center text-[var(--c-text-3)]">กดโหลดข้อมูลเพื่อดูสรุป</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="assets/js/ma_summary.js?v=<?= time() ?>"></script>
