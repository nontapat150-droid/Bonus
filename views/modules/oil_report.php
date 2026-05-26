<?php
// views/modules/oil_report.php
if (!defined('PDO::ATTR_ERRMODE')) exit('Direct access not permitted.');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl">📊</span> รายงานการใช้น้ำมัน
            </h2>
            <p class="text-gray-500 text-sm mt-1">ตรวจสอบประวัติการเบิกค่าน้ำมันและดูสถิติ</p>
        </div>
        <div class="mt-4 md:mt-0">
            <!-- For testing, a button to go to the form -->
            <a href="index.php?page=oil_test_form" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                + บันทึกน้ำมัน (ทดสอบสำหรับแอดมิน)
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-gray-500 text-sm font-medium">ค่าใช้จ่ายเดือนนี้</h3>
            <p class="text-3xl font-bold text-gray-800 mt-2">฿ <span id="stat_total_cost">0.00</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-gray-500 text-sm font-medium">ปริมาณรวม (ลิตร)</h3>
            <p class="text-3xl font-bold text-gray-800 mt-2"><span id="stat_total_liters">0</span> L</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-gray-500 text-sm font-medium">รายการเบิกทั้งหมด</h3>
            <p class="text-3xl font-bold text-gray-800 mt-2"><span id="stat_total_records">0</span> รายการ</p>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-gray-700">ประวัติการเบิกล่าสุด</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-6 py-3">วันที่/เวลา</th>
                        <th class="px-6 py-3">ช่าง (ผู้เบิก)</th>
                        <th class="px-6 py-3">ทะเบียนรถ</th>
                        <th class="px-6 py-3 text-right">เลขไมล์</th>
                        <th class="px-6 py-3 text-right">จำนวนลิตร</th>
                        <th class="px-6 py-3 text-right">ยอดรวม (บาท)</th>
                        <th class="px-6 py-3 text-center">หลักฐาน</th>
                    </tr>
                </thead>
                <tbody id="oilTableBody">
                    <!-- Data injected via JS -->
                    <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Mock data loading for now to ensure UI works
document.addEventListener('DOMContentLoaded', () => {
    // In the next step, we will connect this to a real API
    document.getElementById('oilTableBody').innerHTML = `
        <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">ยังไม่มีข้อมูลในระบบ</td></tr>
    `;
});
</script>
