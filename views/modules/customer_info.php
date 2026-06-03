<?php
// views/modules/customer_info.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
requireRole(['admin', 'super_admin']);
?>

<div class="max-w-7xl mx-auto space-y-6 animate__animated animate__fadeIn">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center shadow-inner">
                    <i data-lucide="users-2" class="w-6 h-6"></i>
                </div>
                ข้อมูลลูกค้า (Customer Information)
            </h1>
            <p class="text-slate-500 mt-2 text-sm font-medium">ค้นหาประวัติการทำงานทั้งหมดของลูกค้าจากหมายเลข NON, Circuit ID หรือ Access No</p>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <form id="searchCustomerForm" onsubmit="return false;" class="flex gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                </div>
                <input type="text" id="searchInput" class="input !pl-11 !py-4 w-full text-lg font-bold" placeholder="กรอกหมายเลข NON, Circuit ID, หรือ Access No...">
            </div>
            <button type="button" id="searchBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 rounded-xl shadow-btn transition-all shrink-0 flex items-center gap-2">
                <i data-lucide="search" class="w-5 h-5"></i> ค้นหา
            </button>
            <button type="button" id="showAllBtn" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 rounded-xl transition-all shrink-0 flex items-center gap-2" title="แสดงข้อมูลลูกค้าทั้งหมดล่าสุด">
                <i data-lucide="list" class="w-5 h-5"></i> แสดงทั้งหมด
            </button>
        </form>
    </div>

    <!-- Results Area -->
    <div id="resultsArea" class="hidden space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-black text-slate-800">ผลการค้นหา</h2>
            <span id="resultCount" class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">พบ 0 รายการ</span>
        </div>
        
        <!-- Cards Container -->
        <div id="customerCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Cards will be injected here via JS -->
        </div>
    </div>
    
    <!-- Empty State / Welcome -->
    <div id="emptyState" class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
        <div class="w-24 h-24 bg-indigo-50 text-indigo-200 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="user-search" class="w-12 h-12"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 mb-2">ค้นหาประวัติลูกค้า</h3>
        <p class="text-slate-500 font-medium max-w-md mx-auto">กรอกหมายเลข NON หรือ Circuit ID ด้านบน เพื่อดูประวัติค่าแรกเข้า ประวัติการติดตั้ง และสถานะต่างๆ ของลูกค้า</p>
    </div>
</div>

<!-- Modal: Customer Detail -->
<div id="customerDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl animate__animated animate__zoomIn animate__faster">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50 rounded-t-2xl shrink-0">
            <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <i data-lucide="user" class="w-5 h-5 text-indigo-600"></i>
                รายละเอียดลูกค้า
            </h3>
            <div class="flex items-center gap-2">
                <button type="button" id="deleteCustomerBtn" class="p-2 hover:bg-rose-100 text-rose-500 rounded-lg transition-colors hidden" title="ลบข้อมูลลูกค้านี้ทั้งหมด">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                <button type="button" class="closeModalBtn p-2 hover:bg-slate-200 text-slate-500 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar space-y-6" id="modalContent">
            <!-- Modal Content will be injected here via JS -->
        </div>
        
        <div class="p-5 border-t border-slate-100 bg-slate-50 rounded-b-2xl shrink-0 flex justify-end gap-3">
            <button type="button" class="closeModalBtn px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 rounded-xl transition-colors">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<script src="assets/js/customer_info.js?v=<?= time() ?>"></script>
