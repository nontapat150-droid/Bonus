<?php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
if (!hasRole('technician')) exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
?>

<style>
    .complete-modal-scrollbar::-webkit-scrollbar { width: 6px; }
    .swal2-container { z-index: 10100 !important; }
    .cj-provider-btn input { position: absolute; opacity: 0; pointer-events: none; }
    .cj-provider-btn span {
        display: block; padding: 0.625rem 1rem; border-radius: 0.75rem; font-weight: 800; font-size: 0.875rem;
        border: 2px solid #e2e8f0; background: #fff; color: #64748b; text-align: center;
    }
    .cj-provider-btn input:checked + span { border-color: #059669; background: #ecfdf5; color: #047857; }
</style>

<div class="max-w-6xl mx-auto space-y-6 animate__animated animate__fadeIn">
    <div class="bg-emerald-600 rounded-3xl px-8 py-6 shadow-lg text-white">
        <h2 class="text-2xl font-black flex items-center gap-3">
            <i data-lucide="clipboard-list" class="w-8 h-8"></i>
            ประวัติการปิดงานของฉัน
        </h2>
        <p class="text-emerald-100 mt-2 text-sm">แก้ไขได้ถึง 12:00 น. ของวันถัดไปจากวันมอบหมายงาน (แอดมินแก้ไขได้ตลอด)</p>
    </div>

    <div id="jobCloseAlertBanner" class="hidden"></div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="flex items-center gap-2 font-bold text-slate-700">
            <i data-lucide="calendar-search" class="w-5 h-5 text-emerald-500"></i>
            ตัวกรอง <span id="jchCount" class="text-xs bg-slate-100 px-2 py-0.5 rounded-md text-slate-600">0 รายการ</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <input type="date" id="jchFilterDate" class="input py-2 text-sm">
            <input type="month" id="jchFilterMonth" class="input py-2 text-sm">
            <button type="button" onclick="loadJobCloseHistory()" class="btn-primary py-2 px-5 text-sm">ค้นหา</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase bg-slate-50 text-slate-500 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left">วันที่ปิดงาน</th>
                        <th class="px-4 py-3 text-left">ประเภท</th>
                        <th class="px-4 py-3 text-left">Non</th>
                        <th class="px-4 py-3 text-left">ลูกค้า</th>
                        <th class="px-4 py-3 text-left">วันติดตั้ง</th>
                        <th class="px-4 py-3 text-left">แก้ไขได้ถึง</th>
                        <th class="px-4 py-3 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="jchTableBody" class="divide-y divide-slate-100">
                    <tr><td colspan="7" class="text-center py-10 text-slate-400">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/job_close_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/job_close.js?v=<?= time() ?>"></script>
<script src="assets/js/job_close_history.js?v=<?= time() ?>"></script>
