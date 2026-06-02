<?php
// views/modules/tech_bag.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

// Protection: Technician Only (or Admin if they want to view it, but user requested Technician only)
// Note: Based on user request, only Technician sees this menu.
if (!hasRole(['technician', 'admin', 'super_admin'])) {
    echo "<div class='p-8 text-center text-red-600 font-bold text-xl'>ไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
    exit;
}
?>

<div class="space-y-6 animate__animated animate__fadeIn">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-yellow-100">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center">
                <span class="bg-yellow-100 text-yellow-600 p-2 rounded-xl mr-3">
                    <i data-lucide="briefcase" class="w-6 h-6"></i>
                </span>
                กระเป๋าช่าง
            </h2>
            <p class="text-slate-500 mt-1 text-sm">จัดการอุปกรณ์และวัสดุสิ้นเปลืองที่อยู่กับคุณ รวมถึงประวัติการใช้งาน</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex overflow-x-auto bg-white rounded-xl shadow-sm p-2 space-x-2 border border-slate-100">
        <button onclick="switchTechTab('bag')" id="tab-bag" class="tech-tab active px-6 py-2.5 rounded-lg text-sm font-bold bg-yellow-100 text-yellow-700 transition-colors whitespace-nowrap flex items-center">
            <i data-lucide="package" class="w-5 h-5 mr-2"></i> ของในกระเป๋า
            <span id="bagCountBadge" class="ml-2 bg-yellow-500 text-white text-xs rounded-full px-2 py-0.5 hidden">0</span>
        </button>
        <button onclick="switchTechTab('history')" id="tab-history" class="tech-tab px-6 py-2.5 rounded-lg text-sm font-medium text-slate-500 hover:bg-slate-50 transition-colors whitespace-nowrap flex items-center">
            <i data-lucide="clock" class="w-5 h-5 mr-2"></i> ประวัติของฉัน
        </button>
    </div>

    <!-- View: ของในกระเป๋า (Bag) -->
    <div id="view-bag" class="space-y-6">
        <!-- อุปกรณ์ที่มี SN -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-slate-50 p-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-700 flex items-center"><i data-lucide="cpu" class="w-5 h-5 mr-2 text-indigo-500"></i> อุปกรณ์ (มี SN)</h3>
                <div class="space-x-2">
                    <button onclick="useSelectedItems()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                        <i data-lucide="check-circle" class="w-4 h-4 inline-block mr-1"></i> ใช้งานที่เลือก
                    </button>
                    <button onclick="openTransferModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                        <i data-lucide="refresh-cw" class="w-4 h-4 inline-block mr-1"></i> โอนย้าย
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b">
                        <tr>
                            <th class="px-6 py-3 w-10 text-center">
                                <input type="checkbox" id="selectAllItems" class="rounded text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="px-6 py-3">SN</th>
                            <th class="px-6 py-3">สินค้า</th>
                            <th class="px-6 py-3">รุ่น</th>
                            <th class="px-6 py-3">วันที่รับมา</th>
                        </tr>
                    </thead>
                    <tbody id="techBagItemsBody">
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- วัสดุสิ้นเปลือง -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-slate-50 p-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-700 flex items-center"><i data-lucide="box" class="w-5 h-5 mr-2 text-yellow-500"></i> วัสดุสิ้นเปลือง</h3>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b">
                        <tr>
                            <th class="px-6 py-3">ชื่อวัสดุ</th>
                            <th class="px-6 py-3 text-right">จำนวนคงเหลือ</th>
                            <th class="px-6 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="techBagConsumablesBody">
                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View: ประวัติ (History) -->
    <div id="view-history" class="hidden space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50">
                <h3 class="font-bold text-lg text-slate-700 flex items-center"><span class="mr-2"><i data-lucide="clock" class="w-5 h-5 inline-block text-indigo-500"></i></span> ประวัติการทำรายการ</h3>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">วันที่/เวลา</th>
                            <th class="px-6 py-3">ประเภท</th>
                            <th class="px-6 py-3">SN / จำนวน</th>
                            <th class="px-6 py-3">สินค้า</th>
                            <th class="px-6 py-3">รายละเอียดการทำรายการ</th>
                        </tr>
                    </thead>
                    <tbody id="techHistoryBody">
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: โอนย้าย -->
<div id="transferModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-lg transform transition-all animate__animated animate__zoomIn z-[90]">
        <div class="bg-blue-600 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i> โอนย้ายอุปกรณ์ (SN)</h3>
            <button onclick="closeTransferModal()" class="text-blue-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6">
            <p class="text-sm text-slate-500 mb-4">คุณกำลังเลือกโอนย้ายอุปกรณ์จำนวน <span id="transferCount" class="font-bold text-blue-600 text-lg">0</span> รายการ</p>
            
            <label class="block text-sm font-bold text-slate-700 mb-2">เลือกช่างผู้รับโอน <span class="text-red-500">*</span></label>
            <select id="transferTargetUser" class="w-full border border-slate-300 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 text-sm mb-6">
                <option value="">-- กำลังโหลดรายชื่อช่าง --</option>
            </select>

            <div class="flex justify-end gap-3 mt-4">
                <button onclick="closeTransferModal()" class="px-5 py-2.5 rounded-xl text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 font-bold transition">ยกเลิก</button>
                <button id="submitTransferBtn" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-md transition flex items-center">
                    ยืนยันการโอนย้าย
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: ใช้งานวัสดุสิ้นเปลือง -->
<div id="useConsumableModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-md transform transition-all animate__animated animate__zoomIn z-[90]">
        <div class="bg-green-600 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> บันทึกการใช้งานวัสดุ</h3>
            <button onclick="closeUseConsumableModal()" class="text-green-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">วัสดุที่เลือก</label>
                <div id="useConsumableName" class="text-lg font-bold text-slate-800 bg-slate-50 p-3 rounded-lg border border-slate-200"></div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">ระบุจำนวนที่ใช้งาน <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="number" id="useConsumableQty" min="0.1" step="0.1" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-green-500 focus:ring-0 text-lg font-bold pr-16" placeholder="0">
                    <span id="useConsumableUnit" class="absolute right-4 top-3.5 text-slate-400 font-medium">ชิ้น</span>
                </div>
                <p id="useConsumableMax" class="text-xs text-slate-500 mt-2 font-medium">มีให้ใช้งานได้สูงสุด: 0</p>
            </div>

            <input type="hidden" id="useConsumableId">

            <div class="flex justify-end gap-3 pt-4">
                <button onclick="closeUseConsumableModal()" class="px-5 py-2.5 rounded-xl text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 font-bold transition">ยกเลิก</button>
                <button id="submitUseConsumableBtn" class="px-5 py-2.5 rounded-xl bg-green-600 text-white hover:bg-green-700 font-bold shadow-md transition flex items-center">
                    บันทึกการใช้งาน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: โอนย้ายวัสดุสิ้นเปลือง -->
<div id="transferConsumableModal" class="fixed inset-0 z-[80] hidden bg-black bg-opacity-60 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-md transform transition-all animate__animated animate__zoomIn z-[90]">
        <div class="bg-blue-600 p-4 border-b flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center"><i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i> โอนย้ายวัสดุสิ้นเปลือง</h3>
            <button onclick="closeTransferConsumableModal()" class="text-blue-200 hover:text-white text-2xl font-bold leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">วัสดุที่เลือก</label>
                <div id="transferConsName" class="text-lg font-bold text-slate-800 bg-slate-50 p-3 rounded-lg border border-slate-200"></div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">เลือกช่างผู้รับโอน <span class="text-red-500">*</span></label>
                <select id="transferConsTarget" class="w-full border border-slate-300 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">-- กำลังโหลดรายชื่อช่าง --</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">ระบุจำนวนที่โอนย้าย <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="number" id="transferConsQty" min="0.1" step="0.1" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-0 text-lg font-bold pr-16" placeholder="0">
                    <span id="transferConsUnit" class="absolute right-4 top-3.5 text-slate-400 font-medium">ชิ้น</span>
                </div>
                <p id="transferConsMax" class="text-xs text-slate-500 mt-2 font-medium">มีให้โอนได้สูงสุด: 0</p>
            </div>

            <input type="hidden" id="transferConsId">

            <div class="flex justify-end gap-3 pt-4">
                <button onclick="closeTransferConsumableModal()" class="px-5 py-2.5 rounded-xl text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 font-bold transition">ยกเลิก</button>
                <button id="submitTransferConsBtn" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-md transition flex items-center">
                    ยืนยันโอนย้าย
                </button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/common.js?v=<?= time() ?>"></script>
<script src="assets/js/tech_bag.js?v=<?= time() ?>"></script>
<script>lucide.createIcons();</script>
