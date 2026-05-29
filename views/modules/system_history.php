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
            <p class="text-indigo-100 mt-2">ดูประวัติการทำรายการของพนักงานทุกคนในระบบ แยกตามหมวดหมู่</p>
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
</div>

<style>
    .active-tab { background-color: #eef2ff !important; color: #4338ca !important; }
</style>
<script src="assets/js/common.js"></script>
<script src="assets/js/system_history.js"></script>