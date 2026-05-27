<?php
// views/modules/oil_report.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="space-y-6">
    <!-- Header & Date Filter -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl">📊</span> รายงานการใช้น้ำมัน   
            </h2>
            <p class="text-gray-500 text-sm mt-1">ตรวจสอบประวัติการเบิกค่าน้ำมันและดูสถิติ</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-3">
            <div class="flex items-center space-x-2">
                <input type="date" id="start_date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <span class="text-gray-500">ถึง</span>
                <input type="date" id="end_date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <button id="filterBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                ค้นหา
            </button>
            <?php if (hasRole(['admin', 'super_admin'])): ?>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <input type="file" id="oilExcelImport" accept=".xlsx,.xls" class="hidden">
                <button type="button" id="oilImportBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                    📥 นำเข้า Excel
                </button>
                <button type="button" id="oilConfirmExcelBtn" class="bg-slate-100 text-slate-700 hover:bg-slate-200 px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm hidden">
                    ยืนยันนำเข้า
                </button>
                <button type="button" id="oilDeleteAllBtn" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                    🗑️ ลบทั้งหมด
                </button>
            </div>
            <?php endif; ?>
            <?php if (hasRole('super_admin')): ?>
            <a href="index.php?page=oil_test_form" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm text-center">
                + บันทึก (ทดสอบ)
            </a>
            <?php endif; ?>
        </div>
        <?php if (hasRole(['admin', 'super_admin'])): ?>
        <div id="oilExcelPreview" class="hidden mt-4 bg-emerald-50 border border-emerald-100 rounded-2xl p-4 text-sm text-emerald-700">
            <p id="oilExcelCount" class="font-bold"></p>
            <p class="mt-2 text-slate-600">ตรวจสอบข้อมูลใน Excel แล้วกดปุ่มยืนยันเพื่อนำเข้าข้อมูล</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-md p-6 text-white">
            <h3 class="text-blue-100 text-sm font-medium">ค่าใช้จ่ายรวม</h3>
            <p class="text-3xl font-bold mt-2">฿ <span id="stat_total_cost">0.00</span></p>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-md p-6 text-white">
            <h3 class="text-emerald-100 text-sm font-medium">ปริมาณรวม (ลิตร)</h3>
            <p class="text-3xl font-bold mt-2"><span id="stat_total_liters">0.00</span> ลิตร</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-md p-6 text-white">
            <h3 class="text-purple-100 text-sm font-medium">รายการเบิกทั้งหมด</h3>
            <p class="text-3xl font-bold mt-2"><span id="stat_total_records">0</span> รายการ</p>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl shadow-md p-6 text-white">
            <h3 class="text-amber-100 text-sm font-medium">📋 เคสงานทุกทีม</h3>
            <p class="text-3xl font-bold mt-2"><span id="stat_total_jobs">0</span> งาน</p>
        </div>
    </div>

    <!-- Charts Area -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cost Bar Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-bold text-gray-700 mb-4">เปรียบเทียบค่าใช้จ่ายรายวัน (บาท)</h3>
            <div class="relative h-64 w-full">
                <canvas id="costChart"></canvas>
            </div>
        </div>
        <!-- Liters Line Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-bold text-gray-700 mb-4">แนวโน้มปริมาณการใช้น้ำมัน (ลิตร)</h3>
            <div class="relative h-64 w-full">
                <canvas id="litersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-gray-700">ประวัติการเบิก</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-6 py-3">วันที่/เวลา</th>
                        <th class="px-6 py-3">ช่าง (ผู้เบิก)</th>
                        <th class="px-6 py-3">ทีม/ป้ายทะเบียน</th>
                        <th class="px-6 py-3 text-center">เคสงาน</th>
                        <th class="px-6 py-3 text-right">เลขไมล์</th>
                        <th class="px-6 py-3 text-right">จำนวนลิตร</th>
                        <th class="px-6 py-3 text-right">ราคา/ลิตร</th>
                        <th class="px-6 py-3 text-right text-gray-800">ยอดรวม (บาท)</th>
                        <th class="px-6 py-3 text-center">หลักฐาน</th>
                        <?php if (hasRole(['admin', 'super_admin'])): ?>
                        <th class="px-6 py-3 text-center">การจัดการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="oilTableBody" class="divide-y divide-gray-100">
                    <!-- Data injected via JS -->
                    <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 z-[100] hidden bg-black bg-opacity-80 flex justify-center items-center p-4">
    <div class="bg-white rounded-xl overflow-hidden max-w-4xl w-full flex flex-col max-h-[90vh]">
        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">รูปภาพหลักฐานการเติมน้ำมัน</h3>
            <button onclick="closeImageModal()" class="text-gray-500 hover:text-red-500 text-xl font-bold">&times;</button>
        </div>
        <div id="modalImageGrid" class="p-4 grid grid-cols-2 md:grid-cols-3 gap-4 overflow-y-auto">
            <!-- Images injected via JS -->
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if (hasRole(['admin', 'super_admin'])): ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<?php endif; ?>
<!-- Load Oil Report Logic -->
<script>window.IS_ADMIN = <?php echo hasRole(['admin','super_admin']) ? 'true' : 'false'; ?>;</script>
<script src="assets/js/oil_report.js"></script>