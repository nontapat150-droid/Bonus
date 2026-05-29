<?php
// views/modules/oil_report.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="space-y-6">
    <div class="flex flex-col gap-6">
        <div class="card !p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="flex items-center">
                <div class="mr-4 p-3 bg-[var(--c-primary-faint)] text-[var(--c-primary)] rounded-2xl shadow-inner">
                    <i data-lucide="bar-chart-2" class="w-8 h-8"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-[var(--c-text-1)] tracking-tight">รายงานการใช้น้ำมัน</h2>
                    <p class="text-[var(--c-text-3)] text-xs font-medium uppercase tracking-wider">Fleet Oil Consumption & Analytics</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 bg-[var(--c-surface-2)] p-2 rounded-2xl border border-[var(--c-border)]">
                
                <div class="flex items-center gap-2 border-r border-[var(--c-border)] pr-3">
                    <i data-lucide="truck" class="w-4 h-4 text-[var(--c-text-3)] ml-2"></i>
                    <select id="filter_license_plate" onchange="document.getElementById('filterBtn').click()" class="bg-transparent border-none text-xs font-bold focus:ring-0 cursor-pointer py-1">
                        <option value="all">-- ทุกคัน --</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-[var(--c-text-3)] ml-2"></i>
                    <select id="dateRangePreset" class="bg-transparent border-none text-xs font-bold focus:ring-0 cursor-pointer py-1" onchange="applyDatePreset(this.value)">
                        <option value="custom">กำหนดเอง</option>
                        <option value="this_month">เดือนนี้</option>
                        <option value="last_month">เดือนที่แล้ว</option>
                    </select>
                </div>
                <div class="h-4 w-px bg-[var(--c-border)] hidden sm:block"></div>
                <div class="flex items-center gap-2">
                    <input type="date" id="start_date" class="bg-transparent border-none text-xs font-bold focus:ring-0 p-0 w-28">
                    <span class="text-[var(--c-text-3)] text-[10px] font-black uppercase">to</span>
                    <input type="date" id="end_date" class="bg-transparent border-none text-xs font-bold focus:ring-0 p-0 w-28">
                </div>
                <button id="filterBtn" class="bg-[var(--c-primary)] text-white px-4 py-1.5 rounded-xl text-xs font-bold hover:brightness-110 transition-all shadow-sm">
                    ตกลง
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div id="compareControls" class="flex flex-wrap items-center gap-3">
                <button onclick="toggleCompareMode()" id="compareBtn" class="flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-700 transition-all shadow-lg shadow-slate-200">
                    <i data-lucide="bar-chart-horizontal" class="w-4 h-4"></i>
                    <span id="compareBtnText">เปรียบเทียบรถ</span>
                </button>
                
                <div id="vehicleSelectorWrapper" class="hidden animate__animated animate__fadeInLeft">
                    <select id="vehicleCompareSelector" multiple class="input min-w-[200px] text-xs font-bold py-2.5 rounded-2xl" onchange="renderComparisonCharts()">
                        </select>
                    <p class="text-[10px] text-[var(--c-text-3)] mt-1 ml-2 font-bold uppercase tracking-tighter">* กด Ctrl ค้างเพื่อเลือกหลายคัน</p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-3 ml-auto">
                <button onclick="openAddOilModal()" class="flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-100">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>เพิ่มข้อมูล</span>
                </button>

                <div class="flex gap-2">
                    <input type="file" id="importOilExcel" accept=".xlsx, .xls" class="hidden">
                    <button onclick="document.getElementById('importOilExcel').click()" class="flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-amber-500 text-white text-xs font-bold hover:bg-amber-400 transition-all shadow-lg shadow-amber-100">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">นำเข้า</span>
                    </button>
                    <button onclick="exportOilExcel()" class="flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500 transition-all shadow-lg shadow-emerald-100">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">ส่งออก</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="compareSection" class="hidden animate__animated animate__fadeIn">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card !p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg"><i data-lucide="dollar-sign" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)]">ยอดเงินที่เติม (บาท)</h3>
                </div>
                <div class="h-64"><canvas id="compareCostChart"></canvas></div>
            </div>
            <div class="card !p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg"><i data-lucide="droplet" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)]">ปริมาณน้ำมัน (ลิตร)</h3>
                </div>
                <div class="h-64"><canvas id="compareLitersChart"></canvas></div>
            </div>
            <div class="card !p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-sky-50 text-sky-600 rounded-lg"><i data-lucide="navigation" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)]">ระยะทางวิ่งสะสม (กม.)</h3>
                </div>
                <div class="h-64"><canvas id="compareDistanceChart"></canvas></div>
            </div>
            <div class="card !p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-amber-50 text-amber-600 rounded-lg"><i data-lucide="briefcase" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)]">จำนวนรอบงาน (เคส)</h3>
                </div>
                <div class="h-64"><canvas id="compareJobsChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="kpi-grid">
        </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card !p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg"><i data-lucide="dollar-sign" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)] text-sm uppercase tracking-wider">ค่าใช้จ่ายรายวัน (บาท)</h3>
                </div>
                <select onchange="updateChartType('combinedTrendChart', this.value, 0)" class="input !py-1 !px-2 text-[10px] font-bold bg-slate-50 border-none focus:ring-0 w-24">
                    <option value="line" selected>กราฟเส้น</option>
                    <option value="bar">กราฟแท่ง</option>
                    <option value="area">พื้นที่ (Area)</option>
                </select>
            </div>
            <div class="h-64"><canvas id="combinedTrendChart"></canvas></div>
        </div>

        <div class="card !p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg"><i data-lucide="droplet" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)] text-sm uppercase tracking-wider">ปริมาณน้ำมัน (ลิตร)</h3>
                </div>
                <select onchange="updateChartType('litersTrendChart', this.value)" class="input !py-1 !px-2 text-[10px] font-bold bg-slate-50 border-none focus:ring-0 w-24">
                    <option value="line" selected>กราฟเส้น</option>
                    <option value="bar">กราฟแท่ง</option>
                    <option value="area">พื้นที่ (Area)</option>
                </select>
            </div>
            <div class="h-64"><canvas id="litersTrendChart"></canvas></div>
        </div>

        <div class="card !p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-sky-50 text-sky-600 rounded-lg"><i data-lucide="gauge" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)] text-sm uppercase tracking-wider">ระยะทางวิ่งรายวัน (กม.)</h3>
                </div>
                <select onchange="updateChartType('distanceTrendChart', this.value)" class="input !py-1 !px-2 text-[10px] font-bold bg-slate-50 border-none focus:ring-0 w-24">
                    <option value="bar" selected>กราฟแท่ง</option>
                    <option value="line">กราฟเส้น</option>
                    <option value="area">พื้นที่ (Area)</option>
                </select>
            </div>
            <div class="h-64"><canvas id="distanceTrendChart"></canvas></div>
        </div>

        <div class="card !p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-50 text-amber-600 rounded-lg"><i data-lucide="pie-chart" class="w-5 h-5"></i></div>
                    <h3 class="font-bold text-[var(--c-text-1)] text-sm uppercase tracking-wider">ประสิทธิภาพต้นทุนต่อรอบ</h3>
                </div>
                <select onchange="updateChartType('efficiencyChart', this.value)" class="input !py-1 !px-2 text-[10px] font-bold bg-slate-50 border-none focus:ring-0 w-24">
                    <option value="line" selected>กราฟเส้น</option>
                    <option value="bar">กราฟแท่ง</option>
                    <option value="area">พื้นที่ (Area)</option>
                </select>
            </div>
            <div class="h-64"><canvas id="efficiencyChart"></canvas></div>
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
                        <th>เลขไมล์</th>
                        <th class="px-4 py-4 text-center">ระยะทาง (กม.)</th>
                        <th class="px-4 py-4 text-center">เคสงาน (รอบ)</th>
                        <th class="px-4 py-4 text-right">ต้นทุน/กม.</th>
                        <th class="px-4 py-4 text-right">ต้นทุน/งาน</th>
                        <th class="px-4 py-4 text-right text-[var(--c-text-1)]">ยอดรวม (บาท)</th>
                        <th class="px-4 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="oilTableBody" class="divide-y divide-[var(--c-border)]">
                    <tr><td colspan="10" class="px-6 py-12 text-center text-[var(--c-text-3)] font-bold">กำลังโหลดข้อมูล...</td></tr>
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
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ชื่อผู้เติม (ช่างเทคนิค) <span class="text-rose-500">*</span></label>
                    <select id="manage_tech_id" class="input w-full" onchange="autoFillVehicle(this.value)"></select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ป้ายทะเบียนรถ (ทีม) <span class="text-rose-500">*</span></label>
                    <select id="manage_license_plate" class="input w-full"></select>
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
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ราคารวม (บาท) <span class="text-rose-500">*</span></label>
                    <input type="number" step="1" id="manage_total_price" class="input w-full font-bold text-indigo-700 bg-slate-50" placeholder="Ex: 1000">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">จำนวนเคสงาน (รอบ)</label>
                    <input type="number" id="manage_job_count" class="input w-full" placeholder="Ex: 5" value="0">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ระยะทาง (กม.)</label>
                    <input type="text" id="manage_distance" class="input w-full bg-slate-100 text-slate-400 cursor-not-allowed" readonly placeholder="คำนวณอัตโนมัติจากไมล์รอบก่อนหน้า">
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

<script src="assets/js/oil_report.js?v=<?= time(); ?>"></script>