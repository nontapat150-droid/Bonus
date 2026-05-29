<?php
// views/modules/inventory_app.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

// Protection: Admin, Super Admin and Technician
if (!hasRole(['admin', 'super_admin', 'technician'])) {
    echo "<div class='p-8 text-center text-red-600 font-bold text-xl'>ไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
    exit;
}

$isAdmin = hasRole(['admin', 'super_admin']);
?>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>

<style>
    /* SweetAlert Custom Styles */
    .swal2-popup { border-radius: 2rem !important; font-family: 'Sarabun', sans-serif !important; padding: 2rem !important; }
    .swal2-title { font-weight: 800 !important; color: #1e293b !important; }
    .swal2-confirm { border-radius: 1rem !important; padding: 0.8rem 2rem !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
    .swal2-cancel { border-radius: 1rem !important; padding: 0.8rem 2rem !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
</style>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between card">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <span class="mr-2 text-3xl"><i data-lucide="package" class="w-5 h-5 inline-block"></i></span> ระบบคลังสินค้า
            </h2>
            <p class="text-gray-500 text-sm mt-1">จัดการสต็อก นำเข้า เบิกจ่าย และดูประวัติ</p>
        </div>
    </div>

    <div class="flex overflow-x-auto card space-x-2">       
        <?php if ($isAdmin): ?>
        <button onclick="invTab('overview')" id="tab-overview" class="inv-tab active px-6 py-2 rounded-lg text-sm font-bold bg-purple-100 text-purple-700 transition-colors whitespace-nowrap">
            <i data-lucide="bar-chart-2" class="w-5 h-5 inline-block"></i> คลังสินค้า
        </button>
        <button onclick="invTab('inbound')" id="tab-inbound" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap">
            <i data-lucide="download" class="w-5 h-5 inline-block"></i> นำเข้า
        </button>
        <button onclick="invTab('outbound')" id="tab-outbound" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap relative">
            <i data-lucide="upload" class="w-5 h-5 inline-block"></i> เบิกออก
            <span id="outboundBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
        </button>
        <?php endif; ?>
        <button onclick="invTab('transfer')" id="tab-transfer" class="inv-tab <?php echo !$isAdmin ? 'active px-6 py-2 rounded-lg text-sm font-bold bg-purple-100 text-purple-700' : 'px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50'; ?> transition-colors whitespace-nowrap relative">
            <i data-lucide="refresh-cw" class="w-5 h-5 inline-block"></i> โอนย้าย (ยืมของ)
            <span id="transferBadge" class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
        </button>
        <button onclick="invTab('history')" id="tab-history" class="inv-tab px-6 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors whitespace-nowrap">
            <i data-lucide="clock" class="w-5 h-5 inline-block"></i> ประวัติ
        </button>
    </div>

    <?php if ($isAdmin): ?>
    <div id="view-overview" class="inv-view block space-y-4">
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <h3 class="font-bold text-gray-700">รายการสินค้าคงเหลือ</h3>
                    <button onclick="deleteAllInventory()" class="text-xs bg-red-50 hover:bg-red-600 hover:text-white text-red-600 border border-red-200 font-bold px-3 py-1.5 rounded-lg transition-colors flex items-center shadow-sm">
                        <span class="mr-1"><i data-lucide="trash-2" class="w-5 h-5 inline-block"></i></span> ล้างคลังทั้งหมด
                    </button>
                </div>
                <div class="relative w-full sm:w-64">
                    <input type="text" id="searchStock" placeholder="ค้นหาสินค้า หรือ รุ่น..." class="w-full pl-10 pr-4 py-2 input">
                    <span class="absolute left-3 top-2.5 text-gray-400"><i data-lucide="search" class="w-5 h-5 inline-block"></i></span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">รหัสสินค้า</th>
                            <th class="px-6 py-3">ชื่อสินค้า</th>
                            <th class="px-6 py-3">รุ่น (Model)</th>
                            <th class="px-6 py-3 text-center">คงเหลือ</th>
                            <th class="px-6 py-3 text-center">หมายเลขซีเรียล</th>
                            <th class="px-6 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody" class="divide-y divide-gray-100">
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="view-inbound" class="inv-view hidden space-y-6">
        <div class="card border-t-4 border-t-emerald-500">
            <h3 class="font-bold text-gray-700 text-lg flex items-center mb-6"><span class="text-emerald-500 mr-2"><i data-lucide="download" class="w-5 h-5 inline-block"></i></span> นำเข้าสินค้าด้วยการสแกน (ทีละรายการ)</h3>
            
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-4">
                    <h4 class="font-bold text-slate-700 flex items-center"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 1</span> เลือกระบบการรับเข้า</h4>
                    <div class="inline-flex bg-white rounded-lg p-1 border border-slate-200 shadow-sm flex-wrap gap-1">
                        <button id="btnModeSn" onclick="setInboundMode('SN')" class="px-4 py-2 rounded-md text-sm font-bold bg-emerald-50 text-emerald-700 transition-all flex items-center">
                            <i data-lucide="tag" class="w-4 h-4 inline-block mr-1"></i> มี SN (สแกนทีละชิ้น)
                        </button>
                        <button id="btnModeQty" onclick="setInboundMode('QTY')" class="px-4 py-2 rounded-md text-sm font-medium text-slate-500 hover:text-slate-700 transition-all flex items-center">
                            <i data-lucide="package" class="w-4 h-4 inline-block mr-1"></i> นับจำนวน (วัสดุสิ้นเปลือง)
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <div class="md:col-span-2">
                        <h4 class="font-bold text-slate-700 flex items-center mb-3"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 2</span> ค้นหาสินค้าและรุ่น</h4>
                    </div>
                    <div class="relative mt-0">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อสินค้า</label>
                        <input type="text" id="mainProductInput" class="w-full input font-bold" placeholder="คลิกเพื่อเลือก หรือพิมพ์ชื่อสินค้าใหม่..." autocomplete="off">
                        <div id="productDropdown" class="absolute z-[50] w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto hidden mt-1 custom-scrollbar"></div>
                    </div>

                    <div id="areaModelSelect" class="relative">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">รุ่น (Model)</label>
                        <input type="text" id="mainModelInput" class="w-full input font-bold" placeholder="คลิกเพื่อเลือก หรือพิมพ์รุ่นใหม่..." autocomplete="off">
                        <div id="modelDropdown" class="absolute z-[50] w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto hidden mt-1 custom-scrollbar"></div>
                    </div>

                    <div id="areaInputSn" class="md:col-span-2 mt-4 pt-4 border-t border-slate-200">
                        <h4 class="font-bold text-slate-700 flex items-center mb-3"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 3</span> สแกนบาร์โค้ด</h4>
                        <input type="text" id="scanInput" class="w-full h-16 text-center text-2xl font-bold font-mono tracking-widest border-2 border-dashed border-emerald-300 rounded-xl focus:border-emerald-500 focus:bg-emerald-50 focus:text-emerald-700 transition-all disabled:bg-slate-100 disabled:border-slate-200 disabled:cursor-not-allowed" placeholder="เลือกสินค้าและรุ่นก่อนสแกน..." autocomplete="off" disabled>
                    </div>

                    <div id="areaInputQty" class="md:col-span-2 mt-4 pt-4 border-t border-slate-200 hidden">
                        <h4 class="font-bold text-slate-700 flex items-center mb-3"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 3</span> ระบุจำนวนที่รับเข้า</h4>
                        <div class="flex gap-2">
                            <input type="number" id="inboundQty" class="w-1/2 h-16 text-center text-2xl font-bold border-2 border-slate-300 rounded-xl focus:border-yellow-500 focus:ring-0" placeholder="จำนวน" min="1">
                            <input type="text" id="inboundUnit" class="w-1/4 h-16 text-center font-bold border-2 border-slate-300 rounded-xl focus:border-yellow-500 focus:ring-0" placeholder="หน่วย (เช่น ชิ้น)">
                            <button onclick="saveInboundQty()" class="w-1/4 h-16 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-xl shadow-md text-lg transition-colors">บันทึก</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card flex flex-col items-center">
            <h3 class="font-bold text-gray-700 mb-4 text-lg"><i data-lucide="bar-chart-2" class="w-5 h-5 inline-block"></i> นำเข้าสินค้าทีละหลายรายการ (Excel)</h3>  
            
            <div class="w-full max-w-md mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm font-semibold text-blue-900 mb-2"><i data-lucide="clipboard" class="w-4 h-4 inline-block"></i> รูปแบบไฟล์ที่ถูกต้อง:</p>
                <ul class="text-xs text-blue-800 space-y-1 ml-4 list-disc">
                    <li>ไฟล์ต้องเป็น Excel (.xlsx หรือ .xls)</li>
                    <li>คอลัมน์ที่ 1: <b>ชื่อสินค้า</b> (บังคับ)</li>
                    <li>คอลัมน์ที่ 2: <b>รุ่น / Model</b> (บังคับ)</li>
                    <li>คอลัมน์ที่ 3: <b>ซีเรียล / SN</b> (ตัวเลือก - ถ้าไม่มีจะสร้างอัตโนมัติ)</li>
                    <li>คอลัมน์ที่ 4: รหัสสินค้า (ตัวเลือก)</li>
                </ul>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-md">
                <input type="file" id="excelImport" accept=".xlsx, .xls" class="hidden">
                <button onclick="document.getElementById('excelImport').click()" class="flex-1 bg-slate-100 text-slate-600 hover:bg-slate-200 font-bold py-3 px-6 rounded-lg text-sm transition-colors flex items-center justify-center">
                    <span class="mr-2 text-lg"><i data-lucide="folder" class="w-5 h-5 inline-block"></i></span> เลือกไฟล์ Excel
                </button>
                <button onclick="downloadTemplate()" class="flex-1 bg-blue-100 text-blue-600 hover:bg-blue-200 font-bold py-3 px-6 rounded-lg text-sm transition-colors flex items-center justify-center">
                    <span class="mr-2 text-lg"><i data-lucide="arrow-down" class="w-5 h-5 inline-block"></i></span> ดาวน์โหลด Template
                </button>
            </div>

            <div id="excelPreview" class="mt-4 w-full hidden max-w-md">
                <p class="text-sm text-green-600 font-bold text-center mb-2" id="excelCount"></p>
                <button id="confirmExcelBtn" class="w-full bg-emerald-600 text-white py-3 rounded-lg font-bold hover:bg-emerald-700 transition-colors shadow-btn"><i data-lucide="check-circle" class="w-5 h-5 inline-block"></i> ยืนยันนำเข้าข้อมูลทั้งหมด</button>
            </div>
        </div>
    </div>

    <div id="view-outbound" class="inv-view hidden space-y-6">
        <div class="card border-t-4 border-t-rose-500">
            <h3 class="font-bold text-gray-700 mb-6 flex items-center text-lg"><span class="text-rose-500 mr-2 text-xl"><i data-lucide="upload" class="w-5 h-5 inline-block"></i></span> สแกนเบิกสินค้าออก</h3>
            
            <h4 class="font-bold text-slate-700 flex items-center mb-3"><span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 1</span> เพิ่มสินค้าลงคิวเบิก</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">เบิกสินค้ามี SN (ทีละชิ้น)</label>
                    <div class="flex gap-2">
                        <input type="text" id="out_sn" placeholder="ยิงบาร์โค้ด SN ตรงนี้..." class="flex-1 px-4 py-3 input font-mono">
                        <button id="addOutboundBtn" class="bg-slate-800 text-white px-5 rounded-xl font-bold hover:bg-slate-900 transition-colors text-sm">เพิ่ม</button>
                    </div>
                </div>

                <div class="bg-yellow-50 p-5 rounded-2xl border border-yellow-200">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-yellow-700 mb-2">เบิกวัสดุสิ้นเปลือง (นับจำนวน)</label>
                    <div class="flex gap-2">
                        <select id="outboundConsumableSelect" class="flex-1 px-3 py-3 input border-yellow-300 focus:ring-yellow-500 font-bold text-slate-700 cursor-pointer">
                            <option value="">-- เลือกวัสดุ --</option>
                        </select>
                        <input type="number" id="outboundConsumableQty" placeholder="จำนวน" min="0.1" step="0.1" class="w-24 px-3 py-3 input border-yellow-300 focus:ring-yellow-500 font-bold text-center">
                        <button id="addOutboundConsumableBtn" class="bg-yellow-600 text-white px-5 rounded-xl font-bold hover:bg-yellow-700 transition-colors text-sm">เพิ่ม</button>
                    </div>
                </div>
            </div>

            <h4 class="font-bold text-slate-700 flex items-center mb-3"><span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 2</span> ตรวจสอบและยืนยัน</h4>
            <div class="border rounded-xl overflow-hidden w-full overflow-x-auto shadow-sm">
                <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                    <thead class="text-xs text-gray-700 uppercase bg-slate-100">
                        <tr>
                            <th class="px-6 py-4">SN / รหัส</th>
                            <th class="px-6 py-4">ชื่อสินค้า - รุ่น</th>
                            <th class="px-6 py-3 text-center">จำนวน (หน่วย)</th>
                            <th class="px-6 py-3 text-center">นำออก</th>
                        </tr>
                    </thead>
                    <tbody id="stagingTableBody" class="divide-y divide-gray-100">
                        <tr id="emptyStaging">
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3">
                                        <i data-lucide="package-open" class="w-8 h-8 text-slate-300"></i>
                                    </div>
                                    <p class="font-bold text-slate-500 text-base">ยังไม่มีรายการในคิว</p>
                                    <p class="text-xs text-slate-400 mt-1">กรุณาสแกน SN หรือเลือกวัสดุเพื่อเริ่มการเบิกออก</p>
                                </div>
                            </td>
                        </tr>
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

    <div id="view-transfer" class="inv-view hidden space-y-6">
        <div class="card border-t-4 border-t-blue-500">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2 flex items-center"><span class="text-blue-500 mr-2 text-xl"><i data-lucide="refresh-cw" class="w-5 h-5 inline-block"></i></span> โอนย้าย / ยืมของระหว่างช่าง</h3>
            
            <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                <p class="text-sm text-gray-500">เลือกสินค้าหรือระบุจำนวนวัสดุ เพื่อทำการโอนย้ายไปยังช่างคนอื่น</p>
                <button onclick="loadMyTodayItems()" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 font-bold px-3 py-1.5 rounded-lg transition-colors flex items-center border border-blue-200">
                    <span class="mr-1"><i data-lucide="refresh-cw" class="w-5 h-5 inline-block"></i></span> รีเฟรชรายการ
                </button>
            </div>

            <div class="mb-6">
                <h4 class="font-bold text-slate-700 mb-2">1. อุปกรณ์แบบมีหมายเลขซีเรียล (รับมาวันนี้)</h4>
                <div class="border rounded-lg overflow-hidden w-full overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 w-10 text-center">
                                    <input type="checkbox" id="selectAllTransfer" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer">
                                </th>
                                <th class="px-6 py-3">SN</th>
                                <th class="px-6 py-3">ชื่อสินค้า - รุ่น</th>
                                <th class="px-6 py-3">เวลาที่รับของ</th>
                            </tr>
                        </thead>
                        <tbody id="transferItemsTableBody" class="divide-y divide-gray-100">
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400"><div class="loader-spinner w-6 h-6 mx-auto mb-2"></div>กำลังโหลดรายการ...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="font-bold text-slate-700 mb-2">2. วัสดุสิ้นเปลือง (ของในคลังส่วนตัว)</h4>
                <div class="border rounded-lg overflow-hidden w-full overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-6 py-3">ชื่อวัสดุ</th>
                                <th class="px-6 py-3 text-center">คงเหลือที่ตัวคุณ</th>
                                <th class="px-6 py-3 text-center w-48">จำนวนที่ต้องการโอนยืม</th>
                            </tr>
                        </thead>
                        <tbody id="transferConsumablesTableBody" class="divide-y divide-gray-100">
                            <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">ไม่มีสต็อกวัสดุสิ้นเปลือง</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex flex-col md:flex-row items-center gap-4 justify-between bg-blue-50 p-4 rounded-xl border border-blue-100">
                <div class="w-full md:w-1/2">
                    <label class="block text-sm font-bold text-blue-800 mb-2"><i data-lucide="user" class="w-5 h-5 inline-block"></i> เลือกช่างผู้รับของ (ยืมไป)</label>
                    <select id="transferTargetSelect" class="w-full px-4 py-3 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-bold text-slate-700 shadow-sm cursor-pointer">
                        <option value="">-- โหลดรายชื่อ... --</option>
                    </select>
                </div>
                <button id="confirmTransferBtn" class="w-full md:w-auto h-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition-transform transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    ยืนยันการโอนย้าย (0 รายการ)
                </button>
            </div>
        </div>
    </div>

    <div id="view-history" class="inv-view <?php echo !$isAdmin ? 'block' : 'hidden'; ?> space-y-4">
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center flex-wrap gap-4">       
                <h3 class="font-bold text-gray-700">ประวัติการรับเข้า - เบิกออก</h3>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <?php if ($isAdmin): ?>
                    <select id="filterHistoryTeam" class="text-sm border-gray-300 rounded-lg py-2 pl-3 pr-8 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">ทุกทีม</option>
                    </select>
                    <select id="filterHistoryUser" class="text-sm border-gray-300 rounded-lg py-2 pl-3 pr-8 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">ทุกคน</option>
                    </select>
                    <?php endif; ?>
                    
                    <button id="exportHistoryBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center whitespace-nowrap">
                        <span class="mr-2"><i data-lucide="bar-chart-2" class="w-5 h-5 inline-block"></i></span> ส่งออก Excel
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">วันที่/เวลา</th>
                            <th class="px-6 py-3">ประเภท</th>
                            <th class="px-6 py-3">SN</th>
                            <th class="px-6 py-3">สินค้า - รุ่น</th>
                            <th class="px-6 py-3">รายละเอียดผู้เบิก-ผู้รับ</th>
                            <th class="px-6 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-100">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="outboundModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-[95%] md:max-w-2xl flex flex-col max-h-[90vh] transform transition-all z-[90]">
        <div class="bg-gray-800 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><span class="mr-2"><i data-lucide="file-text" class="w-5 h-5 inline-block"></i></span> บิลสรุปการเบิกสินค้า</h3>
            <button onclick="closeOutboundModal()" class="text-gray-400 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto">
            <div id="printBillArea" class="space-y-4">
                <div class="text-center mb-6 border-b pb-4 border-dashed border-gray-300">
                    <h2 class="text-xl font-bold uppercase text-gray-800">คลังสินค้า </h2>
                    <p class="text-gray-500 text-sm">ใบเบิกสินค้า (Outbound Slip)</p>    
                    <p class="text-gray-500 text-sm mt-1" id="billDate"></p>
                </div>

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-4">
                    <label class="block text-sm font-bold text-blue-800 mb-2"><i data-lucide="package" class="w-5 h-5 inline-block"></i> ระบุผู้รับของ (เบิกให้ใคร / ทีมไหน?)</label>
                    <select id="outboundTargetSelect" class="w-full px-4 py-3 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-bold text-slate-700 shadow-sm cursor-pointer">
                        <option value="">-- กำลังโหลดรายชื่อผู้รับ... --</option>
                    </select>
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="w-full text-sm text-left whitespace-nowrap">
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
        </div>
        <div class="p-4 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeOutboundModal()" class="px-6 py-2 rounded-lg text-gray-600 bg-white border border-gray-300 hover:bg-gray-100 font-medium transition-colors">ปิด</button>
            <button id="finalSubmitOutbound" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-md flex items-center transition-colors">
                <span class="mr-2"><i data-lucide="check-circle" class="w-5 h-5 inline-block"></i></span> ยืนยันตัดสต็อกทันที
            </button>
        </div>
    </div>
</div>

<div id="snListModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-[95%] md:max-w-4xl flex flex-col max-h-[90vh] transform transition-all animate__animated animate__zoomIn z-[90]">
        <div class="bg-indigo-600 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><span class="mr-2"><i data-lucide="tag" class="w-5 h-5 inline-block"></i></span> รายการหมายเลขซีเรียล</h3>
            <button onclick="closeSnModal()" class="text-indigo-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-5 border-b bg-slate-50">
            <h4 id="snModalProductName" class="text-lg font-black text-slate-800">ชื่อสินค้า</h4>
            <p id="snModalModelName" class="text-sm text-slate-500 mb-4">รุ่น: -</p>
            <div class="relative w-full">
                <input type="text" id="searchSnInModal" placeholder="ค้นหาหมายเลข SN ภายในรุ่นนี้..." class="w-full pl-10 pr-4 py-3 border-2 border-indigo-100 rounded-xl focus:border-indigo-500 focus:ring-0 font-mono text-base transition-colors shadow-sm">
                <span class="absolute left-4 top-3.5 text-indigo-400"><i data-lucide="search" class="w-5 h-5 inline-block"></i></span>
            </div>
        </div>
        <div class="p-5 overflow-y-auto bg-white flex-1 custom-scrollbar">
            <div id="snModalListContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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

<script src="assets/js/common.js"></script>
<script src="assets/js/inventory.js"></script>