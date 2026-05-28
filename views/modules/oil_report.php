<?php
// views/modules/oil_report.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl">📊</span> รายงานการใช้น้ำมัน   
            </h2>
            <p class="text-gray-500 text-sm mt-1">ตรวจสอบประวัติการเบิกค่าน้ำมันและดูสถิติ</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-3">
            <div class="flex items-center space-x-2">
                <input type="date" id="start_date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 w-full sm:w-auto">
                <span class="text-gray-500">ถึง</span>
                <input type="date" id="end_date" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 w-full sm:w-auto">
            </div>
            <button id="filterBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm w-full sm:w-auto">
                ค้นหา
            </button>
            <input type="file" id="importOilExcel" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('importOilExcel').click()" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm flex items-center justify-center w-full sm:w-auto">
                <span class="mr-2">📥</span> นำเข้า Excel
            </button>
            <button onclick="exportOilExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm flex items-center justify-center w-full sm:w-auto">
                <span class="mr-2">📥</span> ส่งออก Excel
            </button>
            
            <?php if (hasRole('super_admin')): ?>
            <a href="index.php?page=oil_test_form" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm text-center w-full sm:w-auto">
                + บันทึก (ทดสอบ)
            </a>
            <?php endif; ?>
        </div>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-bold text-gray-700 mb-4">เปรียบเทียบค่าใช้จ่ายรายวัน (บาท)</h3>
            <div class="relative h-64 w-full"><canvas id="costChart"></canvas></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-bold text-gray-700 mb-4">แนวโน้มปริมาณการใช้น้ำมัน (ลิตร)</h3>
            <div class="relative h-64 w-full"><canvas id="litersChart"></canvas></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-gray-700">ประวัติการเบิก และการคำนวณต้นทุน</h3>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">วันที่/เวลา</th>
                        <th class="px-4 py-3">ช่าง (ผู้เบิก)</th>
                        <th class="px-4 py-3">ทีม/ป้ายทะเบียน</th>
                        <th class="px-4 py-3 text-center">ระยะทาง (กม.)</th>
                        <th class="px-4 py-3 text-center">เคสงาน (รอบ)</th>
                        <th class="px-4 py-3 text-right">ต้นทุน/กม.</th>
                        <th class="px-4 py-3 text-right">ต้นทุน/งาน</th>
                        <th class="px-4 py-3 text-right text-gray-800">ยอดรวม (บาท)</th>
                        <th class="px-4 py-3 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="oilTableBody" class="divide-y divide-gray-100">
                    <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="imageModal" class="fixed inset-0 z-[100] hidden bg-black bg-opacity-80 flex justify-center items-center p-4">
    <div class="bg-white rounded-xl overflow-hidden w-full max-w-[95%] md:max-w-4xl flex flex-col max-h-[90vh]">
        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">รูปภาพหลักฐานการเติมน้ำมัน</h3>
            <button onclick="closeImageModal()" class="text-gray-500 hover:text-red-500 text-xl font-bold">&times;</button>
        </div>
        <div id="modalImageGrid" class="p-4 grid grid-cols-2 md:grid-cols-3 gap-4 overflow-y-auto"></div>
    </div>
</div>

<div id="editOilModal" class="fixed inset-0 z-[100] hidden bg-slate-900 bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-[95%] md:max-w-md animate__animated animate__zoomIn">
        <div class="bg-indigo-600 p-5 border-b flex justify-between items-center text-white">
            <h3 class="font-black text-lg">✏️ แก้ไขข้อมูลผู้เติม / ทะเบียนรถ</h3>
            <button onclick="closeEditOilModal()" class="text-indigo-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-5 bg-slate-50">
            <input type="hidden" id="edit_record_id">
            
            <div>
                <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ชื่อผู้เติม (ช่างเทคนิค)</label>
                <select id="edit_tech_id" class="w-full px-4 py-3 border border-slate-300 rounded-xl font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-sm"></select>
            </div>
            
            <div>
                <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">ป้ายทะเบียนรถ (ทีม)</label>
                <select id="edit_license_plate" class="w-full px-4 py-3 border border-slate-300 rounded-xl font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-sm"></select>
            </div>
        </div>
        <div class="p-4 border-t bg-white flex justify-end space-x-3">
            <button onclick="closeEditOilModal()" class="px-6 py-2.5 rounded-xl text-slate-600 font-bold bg-slate-100 hover:bg-slate-200 transition-colors">ยกเลิก</button>
            <button onclick="saveEditOil()" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 font-black shadow-lg shadow-indigo-200 transition-colors">บันทึกการแก้ไข</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/js/oil_report.js"></script>