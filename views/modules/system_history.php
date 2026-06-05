<?php
// views/modules/system_history.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
if (!hasRole(['admin', 'super_admin'])) exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
?>

<div class="max-w-6xl mx-auto space-y-6 animate__animated animate__fadeIn">
    
    <div class="bg-indigo-600 rounded-3xl px-8 py-6 shadow-lg text-white flex flex-col md:flex-row items-center justify-between">
        <div>
            <h2 class="text-2xl md:text-3xl font-black flex items-center">
                <i data-lucide="database" class="w-8 h-8 mr-3"></i> ศูนย์ข้อมูลประวัติรวมทั้งหมด
            </h2>
            <p class="text-indigo-100 mt-2">ดูประวัติการทำรายการของพนักงานทุกคนในระบบ ค้นหาได้ทั้งรายวันและรายเดือน</p>
        </div>
    </div>

    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex overflow-x-auto gap-2 custom-scrollbar">
        <button onclick="loadHistory('checkin')" id="tab-checkin" class="hist-tab active-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all bg-indigo-50 text-indigo-700">
            📸 เช็คอินเข้างาน
        </button>
        <button onclick="loadHistory('start_day')" id="tab-start_day" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            🏁 ค่าแรกเข้า
        </button>
        <button onclick="loadHistory('oil')" id="tab-oil" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            ⛽ เติมน้ำมัน
        </button>
        <button onclick="loadHistory('inventory')" id="tab-inventory" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            📦 คลังสินค้า
        </button>
        <button onclick="loadHistory('job_close')" id="tab-job_close" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            ✅ ปิดงานติดตั้ง
        </button>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="flex items-center gap-2 font-bold text-slate-700">
            <i data-lucide="calendar-search" class="w-5 h-5 text-indigo-500"></i> ตัวกรองเวลา <span id="recordCountBadge" class="ml-2 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md text-xs">0 รายการ</span>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2 w-full sm:w-auto">
            <input type="date" id="filterDate" class="input py-2 text-sm w-full sm:w-auto">
            <span class="text-slate-400 text-sm hidden sm:inline">หรือ</span>
            <input type="month" id="filterMonth" class="input py-2 text-sm w-full sm:w-auto">
            <button onclick="applyFilter()" class="btn-primary py-2 px-6 w-full sm:w-auto text-sm shadow-md">
                <i data-lucide="search" class="w-4 h-4"></i> ค้นหา
            </button>
            <!-- 2. ปุ่มล้างประวัติ (ปุ่มเดิม เอากลับมาแล้วครับ!) -->
            <?php if (hasRole(['admin', 'super_admin'])): ?>
            <button onclick="clearHistory()" class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold border border-rose-200 py-2 px-6 rounded-xl w-full sm:w-auto text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4"></i> ล้างประวัติทั้งหมด
            </button>
            <?php endif; ?>

            <!-- 3. 🟢 ปุ่มสรุปการเช็คอิน (ปุ่มใหม่ ให้มาต่อท้ายอยู่ข้างหลังสุด) -->
            <button onclick="showCheckinSummary()" id="btnCheckinSummary" style="display: none;" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold border border-indigo-200 py-2 px-6 rounded-xl w-full sm:w-auto text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i> สรุปการเช็คอิน
            </button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto bg-white min-h-[400px]">
            <table class="w-full text-sm text-left block md:table">
                <thead id="tableHead" class="hidden md:table-header-group text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                    </thead>
                <tbody id="tableBody" class="block md:table-row-group divide-y divide-slate-100">
                    <tr><td class="text-center py-10 text-slate-400 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="checkinSummaryModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[10100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-indigo-50">
            <div class="flex items-center gap-2">
                <i data-lucide="clipboard-list" class="text-indigo-600 w-6 h-6"></i>
                <h3 class="text-xl font-black text-indigo-900">สรุปข้อมูลการเช็คอิน</h3>
            </div>
            <div class="flex items-center gap-3 justify-end">
                <button onclick="saveCheckinSummaryAsImage()" id="btnSaveCheckinSummaryImage" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold border border-emerald-200 py-2 px-4 rounded-xl text-sm shadow-sm transition-all flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> บันทึกเป็นรูปภาพ
                </button>
                <button onclick="closeCheckinSummary()" class="text-slate-400 hover:text-rose-500 transition-colors p-2 rounded-xl hover:bg-rose-50">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

            <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                <div id="checkinSummaryCaptureArea" class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-100 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 font-bold">พนักงาน</th>
                                <th class="px-4 py-3 font-bold text-center">ตรงเวลา (ครั้ง)</th>
                                <th class="px-4 py-3 font-bold text-center">มาสาย (ครั้ง)</th>
                            </tr>
                        </thead>
                        <tbody id="checkinSummaryBody" class="divide-y divide-slate-100">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button onclick="saveCheckinSummaryAsImage()" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold py-2 px-6 rounded-xl transition-all">บันทึกเป็นรูปภาพ</button>
                <button onclick="closeCheckinSummary()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2 px-6 rounded-xl transition-all">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<style>
    .swal2-container { z-index: 10100 !important; }
    .cj-provider-btn input { position: absolute; opacity: 0; pointer-events: none; }
    .cj-provider-btn span { display: block; padding: 0.5rem 1rem; border-radius: 0.75rem; font-weight: 800; font-size: 0.8rem; border: 2px solid #e2e8f0; text-align: center; }
    .cj-provider-btn input:checked + span { border-color: #059669; background: #ecfdf5; color: #047857; }
    .complete-modal-scrollbar::-webkit-scrollbar { width: 6px; }
</style>

<?php include __DIR__ . '/../partials/job_close_modal.php'; ?>
<?php include __DIR__ . '/../partials/edit_start_day_modal.php'; ?>

<script>
    window.USER_ROLE = '<?= $_SESSION['role'] ?? 'user' ?>';
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/job_close.js?v=<?= time() ?>"></script>
<script src="assets/js/system_history.js?v=<?= time() ?>"></script>