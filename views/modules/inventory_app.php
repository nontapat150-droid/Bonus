<?php
// views/modules/inventory_app.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

// Protection: Only Admin and Super Admin
if (!hasRole(['admin', 'super_admin'])) {
    echo "<div class='p-8 text-center text-red-600 font-bold text-xl'>ไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
    exit;
}
?>

<!-- Load SheetJS for Excel Import/Export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl">📦</span> ระบบคลังสินค้า
            </h2>
            <p class="text-gray-500 text-sm mt-1">จัดการสต็อก นำเข้า เบิกจ่าย และดูประวัติ</p>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100 p-2 space-x-2">       
        <button onclick="invTab('overview')" id="tab-overview" class="inv-tab active px-6 py-2 rounded-lg text-sm font-bold bg-purple-100 text-purple-700 transition-colors whitespace-nowrap">
            📊 คลังสินค้า
        </button>
        <button onclick="invTab('inbound')" id="tab-inbound" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap">
            📥 นำเข้า
        </button>
        <button onclick="invTab('outbound')" id="tab-outbound" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap relative">
            📤 เบิกออก
            <span id="outboundBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
        </button>
        <button onclick="invTab('history')" id="tab-history" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap">
            🕒 ประวัติ
        </button>
    </div>

    <!-- TAB 1: Stock Overview -->
    <div id="view-overview" class="inv-view block space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                <h3 class="font-bold text-gray-700">รายการสินค้าคงเหลือ</h3>
                <div class="relative w-full sm:w-64">
                    <input type="text" id="searchStock" placeholder="ค้นหาสินค้า หรือ รุ่น..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">รหัสสินค้า</th>
                            <th class="px-6 py-3">ชื่อสินค้า</th>
                            <th class="px-6 py-3">รุ่น (Model)</th>
                            <th class="px-6 py-3 text-center">คงเหลือ (ชิ้น/หน่วย)</th>
                            <th class="px-6 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody" class="divide-y divide-gray-100">
                        <!-- Data via JS -->
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Inbound -->
    <div id="view-inbound" class="inv-view hidden space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Manual Entry Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">สแกนรับเข้าทีละชิ้น</h3>
                <form id="inboundForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสินค้า <span class="text-red-500">*</span></label>
                        <input type="text" id="in_product_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">รุ่น (Model) <span class="text-red-500">*</span></label>
                        <input type="text" id="in_model_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">หมายเลขซีเรียล (SN) <span class="text-gray-400 font-normal">(เว้นว่างไว้เพื่อสร้างอัตโนมัติ)</span></label>
                        <input type="text" id="in_sn" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="ยิงบาร์โค้ด SN ตรงนี้">
                    </div>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg transition-colors">
                        บันทึกรับเข้า
                    </button>
                </form>
            </div>

            <!-- Excel Import -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-center items-center text-center">
                <h3 class="font-bold text-gray-700 mb-4">นำเข้าผ่านไฟล์ Excel</h3>  
                <p class="text-sm text-gray-500 mb-4">ไฟล์ต้องมีคอลัมน์: Product Name, Model Name, SN</p>
                <input type="file" id="excelImport" accept=".xlsx, .xls" class="hidden">
                <button onclick="document.getElementById('excelImport').click()" class="bg-purple-100 text-purple-700 hover:bg-purple-200 border border-purple-300 font-bold py-3 px-6 rounded-lg transition-colors flex items-center">
                    <span class="mr-2 text-xl">📄</span> เลือกไฟล์ Excel
                </button>
                <div id="excelPreview" class="mt-4 w-full hidden">
                    <p class="text-sm text-green-600 font-bold" id="excelCount">พบข้อมูล 0 รายการ</p>
                    <button id="confirmExcelBtn" class="mt-2 w-full bg-emerald-600 text-white py-2 rounded-lg">ยืนยันนำเข้าทั้งหมด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: Outbound -->
    <div id="view-outbound" class="inv-view hidden space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">สแกนเบิกสินค้าออก</h3>
            <div class="flex gap-4 mb-6">
                <input type="text" id="out_sn" placeholder="ยิงบาร์โค้ด SN ตรงนี้ แล้วกด Enter..." class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-red-500 font-mono">
                <button id="addOutboundBtn" class="bg-gray-800 text-white px-6 rounded-lg font-bold hover:bg-gray-900 transition-colors">เพิ่มลงคิว</button>
            </div>

            <div class="border rounded-lg overflow-hidden">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                        <tr>
                            <th class="px-6 py-3">SN</th>
                            <th class="px-6 py-3">ชื่อสินค้า - รุ่น</th>
                            <th class="px-6 py-3 text-center">นำออก</th>
                        </tr>
                    </thead>
                    <tbody id="stagingTableBody" class="divide-y divide-gray-100">
                        <!-- Staged items -->
                        <tr id="emptyStaging"><td colspan="3" class="px-6 py-8 text-center text-gray-400">ยังไม่มีรายการสแกน รอการเบิก</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button id="confirmOutboundBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition-transform transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    ยืนยันการเบิกออก (0 รายการ)
                </button>
            </div>
        </div>
    </div>

    <!-- TAB 4: History -->
    <div id="view-history" class="inv-view hidden space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">       
                <h3 class="font-bold text-gray-700">ประวัติการรับเข้า - เบิกออก</h3>
                <button id="exportHistoryBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center">
                    <span class="mr-2">📊</span> ส่งออก Excel
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">วันที่/เวลา</th>
                            <th class="px-6 py-3">ประเภท</th>
                            <th class="px-6 py-3">SN</th>
                            <th class="px-6 py-3">สินค้า - รุ่น</th>
                            <th class="px-6 py-3">เจ้าหน้าที่ผู้ทำรายการ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-100">
                        <!-- Data via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Floating Window (Modal) for Outbound Confirmation Summary -->
<div id="outboundModal" class="fixed inset-0 z-[100] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-2xl w-full flex flex-col max-h-[90vh] transform transition-all">
        <div class="bg-gray-800 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><span class="mr-2">📑</span> บิลสรุปการเบิกสินค้า</h3>
            <button onclick="closeOutboundModal()" class="text-gray-400 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto">
            <div id="printBillArea" class="space-y-4">
                <div class="text-center mb-6 border-b pb-4 border-dashed border-gray-300">
                    <h2 class="text-xl font-bold uppercase text-gray-800">คลังสินค้า สมาร์ทสูท</h2>
                    <p class="text-gray-500 text-sm">ใบเบิกสินค้า (Outbound Slip)</p>    
                    <p class="text-gray-500 text-sm mt-1" id="billDate"></p>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="border-b-2 border-gray-800">
                        <tr>
                            <th class="py-2">ลำดับ</th>
                            <th class="py-2">รายการ (สินค้า - รุ่น)</th>
                            <th class="py-2 text-right">SN</th>
                        </tr>
                    </thead>
                    <tbody id="billTableBody" class="divide-y divide-gray-200">
                        <!-- Bill Items -->
                    </tbody>
                    <tfoot class="border-t-2 border-gray-800 font-bold">
                        <tr>
                            <td colspan="2" class="py-3 text-right">รวมทั้งสิ้น:</td>     
                            <td class="py-3 text-right text-red-600 text-lg" id="billTotal">0 ชิ้น</td> 
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="p-4 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeOutboundModal()" class="px-6 py-2 rounded-lg text-gray-600 bg-white border border-gray-300 hover:bg-gray-100 font-medium">ปิด</button>
            <button id="finalSubmitOutbound" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-md flex items-center">
                <span class="mr-2">✅</span> ยืนยันตัดสต็อกทันที
            </button>
        </div>
    </div>
</div>

<script src="assets/js/inventory.js"></script>