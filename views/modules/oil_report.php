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
                <select id="dateRangePreset" class="input text-xs font-bold" onchange="applyDatePreset(this.value)">
                    <option value="custom">กำหนดเอง</option>
                    <option value="this_month">เดือนนี้</option>
                    <option value="last_month">เดือนที่แล้ว</option>
                </select>
                <input type="date" id="start_date" class="input w-full sm:w-auto text-xs font-bold">
                <span class="text-[var(--c-text-3)] text-xs font-bold">ถึง</span>
                <input type="date" id="end_date" class="input w-full sm:w-auto text-xs font-bold">
            </div>
            <button id="filterBtn" class="btn-primary w-full sm:w-auto text-xs">
                ค้นหา
            </button>
            <button onclick="toggleCompareMode()" id="compareBtn" class="btn-primary w-full sm:w-auto text-xs" style="background: var(--c-secondary); box-shadow: 0 4px 14px rgba(107, 114, 128, 0.40);">
                <span class="mr-1"><i data-lucide="users" class="w-4 h-4"></i></span> เปรียบเทียบรถ
            </button>
            <button onclick="openAddOilModal()" class="btn-primary w-full sm:w-auto text-xs" style="background: var(--c-info); box-shadow: 0 4px 14px rgba(59,130,246, 0.40);">
                <span class="mr-1"><i data-lucide="plus" class="w-4 h-4"></i></span> เพิ่มข้อมูลย้อนหลัง
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

    <!-- Comparison Section (Hidden by default) -->
    <div id="compareSection" class="hidden space-y-6">
        <div class="card">
            <h3 class="font-bold text-[var(--c-text-1)] mb-4 flex items-center">
                <i data-lucide="pie-chart" class="w-5 h-5 mr-2 text-indigo-500"></i>
                เปรียบเทียบสถิติระหว่างรถแต่ละคัน
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="h-80"><canvas id="compareCostChart"></canvas></div>
                <div class="h-80"><canvas id="compareLitersChart"></canvas></div>
                <div class="h-80"><canvas id="compareDistanceChart"></canvas></div>
                <div class="h-80"><canvas id="compareJobsChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="kpi-grid">
        <!-- ... existing KPIs ... -->
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
            <h3 class="font-bold text-[var(--c-text-1)] mb-4 flex items-center">
                <i data-lucide="trending-up" class="w-5 h-5 mr-2 text-indigo-500"></i>
                แนวโน้มค่าใช้จ่ายและปริมาณน้ำมัน
            </h3>
            <div class="relative h-64 w-full"><canvas id="combinedTrendChart"></canvas></div>
        </div>
        <div class="card">
            <h3 class="font-bold text-[var(--c-text-1)] mb-4 flex items-center">
                <i data-lucide="gauge" class="w-5 h-5 mr-2 text-sky-500"></i>
                แนวโน้มระยะทางวิ่ง (กม.)
            </h3>
            <div class="relative h-64 w-full"><canvas id="distanceTrendChart"></canvas></div>
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

<div id="manageOilModal" class="fixed inset-0 z-[80] hidden bg-slate-900 bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-[95%] md:max-w-2xl my-auto animate__animated animate__zoomIn z-[90]">
        <div class="bg-indigo-600 p-5 border-b flex justify-between items-center text-white">
            <h3 id="manageOilModalTitle" class="font-black text-lg"><i data-lucide="edit-2" class="w-5 h-5 inline-block"></i> จัดการข้อมูลน้ำมัน</h3>
            <button onclick="closeManageOilModal()" class="text-indigo-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4 bg-slate-50 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <input type="hidden" id="manage_record_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">วันที่บันทึก <span class="text-rose-500">*</span></label>
                    <input type="datetime-local" id="manage_date_recorded" class="input w-full">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ป้ายทะเบียนรถ (ทีม) <span class="text-rose-500">*</span></label>
                    <select id="manage_license_plate" class="input w-full"></select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ชื่อผู้เติม (ช่างเทคนิค) <span class="text-rose-500">*</span></label>
                    <select id="manage_tech_id" class="input w-full"></select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">เลขไมล์รถ <span class="text-rose-500">*</span></label>
                    <input type="number" id="manage_mileage" class="input w-full" placeholder="Ex: 75000">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">จำนวนลิตร <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" id="manage_liters" class="input w-full" placeholder="Ex: 30.50">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ราคาต่อลิตร <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" id="manage_price_per_liter" class="input w-full" placeholder="Ex: 35.50">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ระยะทาง (กม.)</label>
                    <input type="number" step="0.01" id="manage_distance" class="input w-full" placeholder="Ex: 300 (เว้นว่างไว้เพื่อคำนวณอัตโนมัติจากไมล์ก่อนหน้า)">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">จำนวนเคสงาน (รอบ)</label>
                    <input type="number" id="manage_job_count" class="input w-full" placeholder="Ex: 5" value="0">
                </div>
            </div>
            
            <div id="manage_image_section" class="mt-4 pt-4 border-t border-slate-200">
                <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">อัปโหลดสลิป/ใบเสร็จ (ถ้ามี)</label>
                <input type="file" id="manage_images" multiple accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="text-[10px] text-slate-400 mt-1">อัปโหลดรูปภาพใหม่เพื่อแนบกับรายการเพิ่มใหม่ (แก้ไขไม่รองรับอัปโหลดรูปใหม่ ณ ตอนนี้)</p>
            </div>
        </div>
        <div class="p-4 border-t bg-white flex justify-end space-x-3">
            <button onclick="closeManageOilModal()" class="px-6 py-2.5 rounded-xl text-slate-600 font-bold bg-slate-100 hover:bg-slate-200 transition-colors">ยกเลิก</button>
            <button onclick="saveManageOil()" class="btn-primary" id="btnSaveManageOil">บันทึกข้อมูล</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/oil_report.js"></script>