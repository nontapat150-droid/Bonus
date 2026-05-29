<?php
// views/modules/oil_report.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="space-y-6">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-3xl font-black text-[var(--c-text-1)] tracking-tight flex items-center">
                <span class="mr-3 p-2 bg-[var(--c-primary-faint)] text-[var(--c-primary)] rounded-xl shadow-inner text-2xl"><i data-lucide="bar-chart-2" class="w-6 h-6"></i></span>
                รายงานการใช้น้ำมัน   
            </h2>
            <p class="text-[var(--c-text-3)] text-sm mt-1 font-medium">ตรวจสอบประวัติการเบิกค่าน้ำมันและดูสถิติ</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-3">
            <div class="flex items-center space-x-2">
                <input type="date" id="start_date" class="input w-full sm:w-auto text-xs font-bold">
                <span class="text-[var(--c-text-3)] text-xs font-bold">ถึง</span>
                <input type="date" id="end_date" class="input w-full sm:w-auto text-xs font-bold">
            </div>
            <button id="filterBtn" class="btn-primary w-full sm:w-auto text-xs">
                ค้นหา
            </button>
            <input type="file" id="importOilExcel" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('importOilExcel').click()" class="btn-primary w-full sm:w-auto text-xs" style="background: var(--c-warning); box-shadow: 0 4px 14px rgba(245,158,11, 0.40);">
                <span class="mr-1"><i data-lucide="download" class="w-4 h-4"></i></span> นำเข้า Excel
            </button>
            <button onclick="exportOilExcel()" class="btn-primary w-full sm:w-auto text-xs" style="background: var(--c-success); box-shadow: 0 4px 14px rgba(16,185,129, 0.40);">
                <span class="mr-1"><i data-lucide="download" class="w-4 h-4"></i></span> ส่งออก Excel
            </button>
            
        </div>
    </div>

    <div class="kpi-grid">
        <div class="card relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-info-bg)] !text-[var(--c-info)]"><i data-lucide="dollar-sign" class="w-5 h-5"></i></div>
            </div>
            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ค่าใช้จ่ายรวม (บาท)</p>
            <h3 class="text-kpi"><span class="text-2xl text-[var(--c-text-3)]">฿</span><span id="stat_total_cost">0.00</span></h3>
        </div>
        <div class="card relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-success-bg)] !text-[var(--c-success)]"><i data-lucide="droplet" class="w-5 h-5"></i></div>
            </div>
            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">ปริมาณรวม (ลิตร)</p>
            <h3 class="text-kpi"><span id="stat_total_liters">0.00</span></h3>
        </div>
        <div class="card relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[#FDF2F8] !text-[#EC4899]"><i data-lucide="file-text" class="w-5 h-5"></i></div>
            </div>
            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">รายการเบิกทั้งหมด</p>
            <h3 class="text-kpi"><span id="stat_total_records">0</span></h3>
        </div>
        <div class="card relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="icon-box-primary group-hover:scale-110 transition-transform var(--dur-spring) !bg-[var(--c-warning-bg)] !text-[var(--c-warning)]"><i data-lucide="clipboard" class="w-5 h-5"></i></div>
            </div>
            <p class="text-xs font-semibold text-[var(--c-text-3)] uppercase tracking-wider mb-1">เคสงานทุกทีม</p>
            <h3 class="text-kpi"><span id="stat_total_jobs">0</span></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
            <h3 class="font-bold text-[var(--c-text-1)] mb-4">เปรียบเทียบค่าใช้จ่ายรายวัน (บาท)</h3>
            <div class="relative h-64 w-full"><canvas id="costChart"></canvas></div>
        </div>
        <div class="card">
            <h3 class="font-bold text-[var(--c-text-1)] mb-4">แนวโน้มปริมาณการใช้น้ำมัน (ลิตร)</h3>
            <div class="relative h-64 w-full"><canvas id="litersChart"></canvas></div>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex justify-between items-center">
            <h3 class="font-black text-[var(--c-text-1)] tracking-tight">ประวัติการเบิก และการคำนวณต้นทุน</h3>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm text-left text-[var(--c-text-2)] whitespace-nowrap">
                <thead class="text-[10px] text-[var(--c-text-3)] uppercase tracking-[0.1em] font-black bg-[var(--c-surface-3)]">
                    <tr>
                        <th class="px-4 py-4">วันที่/เวลา</th>
                        <th class="px-4 py-4">ช่าง (ผู้เบิก)</th>
                        <th class="px-4 py-4">ทีม/ป้ายทะเบียน</th>
                        <th class="px-4 py-4 text-center">ระยะทาง (กม.)</th>
                        <th class="px-4 py-4 text-center">เคสงาน (รอบ)</th>
                        <th class="px-4 py-4 text-right">ต้นทุน/กม.</th>
                        <th class="px-4 py-4 text-right">ต้นทุน/งาน</th>
                        <th class="px-4 py-4 text-right text-[var(--c-text-1)]">ยอดรวม (บาท)</th>
                        <th class="px-4 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="oilTableBody" class="divide-y divide-[var(--c-border)]">
                    <tr><td colspan="9" class="px-6 py-12 text-center text-[var(--c-text-3)] font-bold">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="imageModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-80 flex justify-center items-center p-4">
    <div class="bg-white rounded-xl overflow-hidden w-full max-w-[95%] md:max-w-4xl flex flex-col max-h-[90vh]">
        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">รูปภาพหลักฐานการเติมน้ำมัน</h3>
            <button onclick="closeImageModal()" class="text-gray-500 hover:text-red-500 text-xl font-bold">&times;</button>
        </div>
        <div id="modalImageGrid" class="p-4 grid grid-cols-2 md:grid-cols-3 gap-4 overflow-y-auto"></div>
    </div>
</div>

<div id="editOilModal" class="fixed inset-0 z-[80] hidden bg-slate-900 bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-[95%] md:max-w-md animate__animated animate__zoomIn z-[90]">
        <div class="bg-indigo-600 p-5 border-b flex justify-between items-center text-white">
            <h3 class="font-black text-lg"><i data-lucide="edit-2" class="w-5 h-5 inline-block"></i> แก้ไขข้อมูลผู้เติม / ทะเบียนรถ</h3>
            <button onclick="closeEditOilModal()" class="text-indigo-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-5 bg-slate-50">
            <input type="hidden" id="edit_record_id">
            
            <div>
                <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ชื่อผู้เติม (ช่างเทคนิค)</label>
                <select id="edit_tech_id" class="input"></select>
            </div>
            
            <div>
                <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ป้ายทะเบียนรถ (ทีม)</label>
                <select id="edit_license_plate" class="input"></select>
            </div>
        </div>
        <div class="p-4 border-t bg-white flex justify-end space-x-3">
            <button onclick="closeEditOilModal()" class="px-6 py-2.5 rounded-xl text-slate-600 font-bold bg-slate-100 hover:bg-slate-200 transition-colors">ยกเลิก</button>
            <button onclick="saveEditOil()" class="btn-primary">บันทึกการแก้ไข</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/oil_report.js"></script>