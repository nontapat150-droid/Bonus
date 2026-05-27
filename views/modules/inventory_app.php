<?php
// views/modules/inventory_app.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

// Protection: Only Admin and Super Admin
if (!hasRole(['admin', 'super_admin'])) {
    echo "<div class='p-8 text-center text-red-600 font-bold text-xl'>ไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
    exit;
}
?>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl">📦</span> ระบบคลังสินค้า
            </h2>
            <p class="text-gray-500 text-sm mt-1">จัดการสต็อก นำเข้า เบิกจ่าย และดูประวัติ</p>
        </div>
    </div>

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

    <div id="view-overview" class="inv-view block space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <h3 class="font-bold text-gray-700">รายการสินค้าคงเหลือ</h3>
                    <button onclick="deleteAllInventory()" class="text-xs bg-red-50 hover:bg-red-600 hover:text-white text-red-600 border border-red-200 font-bold px-3 py-1.5 rounded-lg transition-colors flex items-center shadow-sm">
                        <span class="mr-1">🗑️</span> ล้างคลังทั้งหมด
                    </button>
                </div>
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
                            <th class="px-6 py-3 text-center">คงเหลือ</th>
                            <th class="px-6 py-3 text-center">หมายเลขซีเรียล</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody" class="divide-y divide-gray-100">
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="view-inbound" class="inv-view hidden space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 border-t-4 border-t-emerald-500">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4 border-b pb-4">
                <h3 class="font-bold text-gray-700 text-lg flex items-center"><span class="text-emerald-500 mr-2">📥</span> สแกนและรับเข้าสต็อก</h3>
                <div class="inline-flex bg-gray-100 rounded-full p-1 flex-wrap justify-center gap-1">
                    <button id="btnModeSn" onclick="setInboundMode('SN')" class="px-4 py-2 rounded-full text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all flex items-center">
                        🏷️ มี SN (สแกนทีละชิ้น)
                    </button>
                    <button id="btnModeQty" onclick="setInboundMode('QTY')" class="px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700 transition-all flex items-center">
                        📦 นับจำนวน (วัสดุสิ้นเปลือง)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">ชื่อสินค้า</label>
                    <select id="mainProductSelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-bold text-gray-700" onchange="handleMainProductChange()"></select>
                    <input type="text" id="mainProductInput" class="w-full px-4 py-3 border border-gray-300 rounded-lg mt-2 hidden focus:ring-2 focus:ring-emerald-500" placeholder="พิมพ์ชื่อสินค้าใหม่...">
                </div>

                <div id="areaModelSelect">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">รุ่น (Model)</label>
                    <select id="mainModelSelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-bold text-gray-700" onchange="handleMainModelChange()"></select>
                    <input type="text" id="mainModelInput" class="w-full px-4 py-3 border border-gray-300 rounded-lg mt-2 hidden focus:ring-2 focus:ring-emerald-500" placeholder="พิมพ์รุ่นใหม่...">
                </div>

                <div id="areaInputSn" class="md:col-span-2 mt-4">
                    <label class="block text-xs font-bold text-emerald-600 uppercase tracking-widest mb-2">สแกนบาร์โค้ด SN</label>
                    <input type="text" id="scanInput" class="w-full h-16 text-center text-2xl font-bold font-mono tracking-widest border-2 border-dashed border-gray-300 rounded-xl focus:border-emerald-500 focus:bg-emerald-50 focus:text-emerald-700 transition-all disabled:bg-gray-100 disabled:cursor-not-allowed" placeholder="เลือกสินค้าและรุ่นก่อนสแกน..." autocomplete="off" disabled>
                </div>

                <div id="areaInputQty" class="md:col-span-2 mt-4 hidden">
                    <label class="block text-xs font-bold text-yellow-600 uppercase tracking-widest mb-2">ระบุจำนวนที่รับเข้า (รวมในสต็อกเดียว)</label>
                    <div class="flex gap-2">
                        <input type="number" id="inboundQty" class="w-1/2 h-16 text-center text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-yellow-500 focus:ring-0" placeholder="จำนวน" min="1">
                        <input type="text" id="inboundUnit" class="w-1/4 h-16 text-center font-bold border-2 border-gray-300 rounded-xl focus:border-yellow-500 focus:ring-0" placeholder="หน่วย (เช่น ชิ้น)">
                        <button onclick="saveInboundQty()" class="w-1/4 h-16 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-xl shadow-md text-lg transition-colors">บันทึก</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center">
            <h3 class="font-bold text-gray-700 mb-2">นำเข้าสินค้าทีละหลายรายการ (Excel)</h3>  
            <p class="text-sm text-gray-500 mb-4">ไฟล์ต้องมีคอลัมน์เรียงตามลำดับ: <b>รหัสสินค้า (Code) | ชื่อสินค้า | รุ่น | ซีเรียล (SN)</b></p>
            <input type="file" id="excelImport" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('excelImport').click()" class="bg-slate-100 text-slate-600 hover:bg-slate-200 font-bold py-2 px-6 rounded-lg text-sm transition-colors flex items-center">
                <span class="mr-2 text-lg">📄</span> เลือกไฟล์ Excel
            </button>
            <div id="excelPreview" class="mt-4 w-full hidden max-w-md">
                <p class="text-sm text-green-600 font-bold text-center mb-2" id="excelCount"></p>
                <button id="confirmExcelBtn" class="w-full bg-emerald-600 text-white py-2 rounded-lg font-bold hover:bg-emerald-700 transition-colors">ยืนยันนำเข้าข้อมูลทั้งหมด</button>
            </div>
        </div>
    </div>

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
                            <th class="px-6 py-3">รายละเอียดผู้เบิก-ผู้รับ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-100">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-4">
                    <label class="block text-sm font-bold text-blue-800 mb-2">📦 ระบุผู้รับของ (เบิกให้ใคร / ทีมไหน?)</label>
                    <select id="outboundTargetSelect" class="w-full px-4 py-3 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-bold text-slate-700 shadow-sm cursor-pointer">
                        <option value="">-- กำลังโหลดรายชื่อผู้รับ... --</option>
                    </select>
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
            <button onclick="closeOutboundModal()" class="px-6 py-2 rounded-lg text-gray-600 bg-white border border-gray-300 hover:bg-gray-100 font-medium transition-colors">ปิด</button>
            <button id="finalSubmitOutbound" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-md flex items-center transition-colors">
                <span class="mr-2">✅</span> ยืนยันตัดสต็อกทันที
            </button>
        </div>
    </div>
</div>

<div id="snListModal" class="fixed inset-0 z-[100] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-lg w-full flex flex-col max-h-[90vh] transform transition-all animate__animated animate__zoomIn">
        <div class="bg-indigo-600 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><span class="mr-2">🏷️</span> รายการหมายเลขซีเรียล</h3>
            <button onclick="closeSnModal()" class="text-indigo-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-5 border-b bg-slate-50">
            <h4 id="snModalProductName" class="text-lg font-black text-slate-800">ชื่อสินค้า</h4>
            <p id="snModalModelName" class="text-sm text-slate-500 mb-4">รุ่น: -</p>
            <div class="relative w-full">
                <input type="text" id="searchSnInModal" placeholder="ค้นหาหมายเลข SN ภายในรุ่นนี้..." class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
            </div>
        </div>
        <div class="p-5 overflow-y-auto bg-white flex-1 custom-scrollbar">
            <div id="snModalListContainer" class="grid grid-cols-2 gap-3">
                </div>
        </div>
        <div class="p-4 border-t bg-slate-50 flex justify-between items-center">
            <span id="snModalCount" class="text-sm font-bold text-slate-500">รวม 0 รายการ</span>
            <button onclick="closeSnModal()" class="px-6 py-2 rounded-lg text-slate-600 bg-white border border-slate-300 hover:bg-slate-100 font-medium transition-colors">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<script src="assets/js/inventory.js"></script>