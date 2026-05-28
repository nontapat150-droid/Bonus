<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

$isAdmin = hasRole(['admin', 'super_admin']);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Table Custom Scrollbar */
    .table-container::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-container::-webkit-scrollbar-track { background: #f8fafc; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .table-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Table Layout Fixes */
    .job-table { border-collapse: separate; border-spacing: 0; min-width: 1000px; }
    .job-table th { position: sticky; top: 0; z-index: 20; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .job-table td { border-bottom: 1px solid #f1f5f9; padding: 12px 16px; }
    
    /* Column Widths */
    .col-checkbox { width: 40px; }
    .col-seq { width: 50px; }
    .col-access { width: 100px; }
    .col-customer { width: 150px; }
    .col-phone { width: 120px; }
    .col-address { min-width: 250px; }
    .col-date { width: 90px; }
    .col-team { width: 110px; }

    /* SweetAlert Custom Styles */
    .swal2-popup { border-radius: 2rem !important; font-family: 'Sarabun', sans-serif !important; padding: 2rem !important; }
    .swal2-title { font-weight: 800 !important; color: #1e293b !important; }
    .swal2-confirm { border-radius: 1rem !important; padding: 0.8rem 2rem !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
    .swal2-cancel { border-radius: 1rem !important; padding: 0.8rem 2rem !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
</style>

<div class="h-[calc(100vh-120px)] flex flex-col space-y-4">
    
    <?php if ($isAdmin): ?>
    <div class="bg-white/70 backdrop-blur-md p-5 rounded-[2rem] shadow-sm border border-white flex flex-wrap gap-4 items-center justify-between z-20 relative">
        <div class="flex items-center space-x-4">
            <div class="p-3 bg-indigo-100 text-indigo-600 rounded-2xl shadow-inner">📋</div>
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">ระบบจัดส่งอัจฉริยะ</h2>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">จัดการทีมและจ่ายงาน</p>
            </div>
        </div>

        <div class="flex items-center space-x-2 flex-wrap gap-y-2">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-2xl text-[10px] font-black transition-all flex items-center shadow-sm">
                📥 นำเข้า Excel
            </button>

            <button id="exportExcelBtn" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2.5 rounded-2xl text-[10px] font-black shadow-lg shadow-sky-100 transition-all flex items-center">
                📊 นำออก Excel
            </button>

            <button id="dispatchModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-2xl text-[10px] font-black shadow-lg shadow-indigo-100 transition-all flex items-center">
                🤖 จ่ายงานอัตโนมัติ
            </button>

            <button id="optimizeRouteBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2.5 rounded-2xl text-[10px] font-black shadow-lg shadow-emerald-100 transition-all flex items-center">
                📍 เรียงคิวเส้นทาง (ลำดับใกล้สุด)
            </button>

            <div class="w-px h-8 bg-slate-200 mx-2 hidden sm:block"></div>

            <button id="clearAssignmentsBtn" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-2xl text-[10px] font-black shadow-lg shadow-amber-100 transition-all flex items-center">
                🔄 ล้างการจ่ายงาน
            </button>

            <button id="deleteAllJobsBtn" class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2.5 rounded-2xl text-[10px] font-black shadow-lg shadow-rose-100 transition-all flex items-center">
                🗑️ ลบงานทั้งหมด
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col flex-1 gap-4 min-h-0">
        <div class="w-full bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-50 flex flex-col overflow-hidden h-full">
            
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-700 tracking-tight flex items-center text-sm">
                        📦 รายละเอียดงานที่ได้รับมอบหมาย
                        <span id="jobCountBadge" class="ml-2 bg-indigo-100 text-indigo-600 px-3 py-0.5 rounded-full text-[10px] font-black">0</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="selectAllJobs" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="selectAllJobs" class="text-[9px] font-black text-slate-500 uppercase tracking-widest cursor-pointer">เลือกทั้งหมด</label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <input type="date" id="dateFilter" class="flex-1 text-[10px] font-black border-slate-100 rounded-xl focus:ring-indigo-500/20 px-3 py-2 cursor-pointer text-indigo-600 shadow-sm bg-white">
                    <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-2 rounded-xl text-[10px] font-black shadow-sm transition-colors cursor-pointer">
                        🔄 ดูทุกงาน
                    </button>
                    <select id="limitFilter" class="text-[10px] font-black border-slate-100 rounded-xl focus:ring-indigo-500/20 py-2 pl-3 pr-8 cursor-pointer bg-white text-indigo-600 shadow-sm">
                        <option value="20">20 รายการ</option>
                        <option value="50">50 รายการ</option>
                        <option value="100">100 รายการ</option>
                        <option value="all">ทั้งหมด</option>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                <div class="flex gap-2">
                    <select id="teamFilter" class="flex-1 text-[10px] font-black uppercase tracking-wider border-none bg-indigo-50 text-indigo-600 rounded-xl shadow-sm focus:ring-0 py-2 pl-4 pr-10 cursor-pointer">
                        <option value="all">📍 งานทั้งหมด</option>
                        <option value="unassigned">⏳ ยังไม่จ่ายงาน</option>
                    </select>
                    <div class="flex items-center space-x-1">
                        <input type="text" id="newTeamName" placeholder="ชื่อทีม..." class="px-3 py-2 rounded-xl border-slate-100 focus:ring-2 focus:ring-indigo-500/20 text-[9px] font-bold shadow-sm w-32">       
                        <button id="addTeamBtn" class="bg-indigo-600 text-white w-8 h-8 rounded-lg font-black flex items-center justify-center hover:bg-indigo-700 transition-all text-xs">+</button>
                    </div>
                </div>
                <div id="teamListContainer" class="flex flex-wrap gap-2 pt-2"></div>
                <?php endif; ?>
            </div>

            <div id="selectionActions" class="px-5 py-2 bg-slate-900 text-white flex items-center justify-between hidden">
                <p class="text-[9px] font-black uppercase tracking-widest">เลือกอยู่ <span id="selectedCount">0</span> รายการ</p>
                <button id="bulkDeleteBtn" class="text-[9px] font-black bg-rose-500 hover:bg-rose-600 px-3 py-1 rounded-lg uppercase transition-all">ลบข้อมูล</button>
            </div>

            <div class="flex-1 overflow-hidden flex flex-col bg-slate-50/20 relative">
                <div id="mapLoader" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center z-10 hidden">
                    <div class="loader-spinner mb-4 w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-indigo-800 font-black text-xs uppercase tracking-widest animate-pulse">กำลังประมวลผลข้อมูล...</p>
                </div>

                <div class="table-container flex-1 overflow-auto w-full">
                    <table class="job-table w-full whitespace-nowrap">
                        <thead>
                            <tr class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <th class="col-checkbox px-4 py-3 text-center">#</th>
                                <th class="col-seq px-4 py-3">ลำดับ(ใกล้สุด)</th>
                                <th class="col-access px-4 py-3">รหัสงาน</th>
                                <th class="col-customer px-4 py-3">ลูกค้า</th>
                                <th class="col-phone px-4 py-3">เบอร์โทร</th>
                                <th class="col-address px-4 py-3">ที่อยู่</th>
                                <th class="col-date px-4 py-3">วันที่</th>
                                <th class="col-team px-4 py-3 text-right">ทีมช่าง</th>
                            </tr>
                        </thead>
                        <tbody id="jobTableBody" class="text-[11px] font-bold text-slate-600">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="dispatchModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
    <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-[95%] md:max-w-lg overflow-hidden animate__animated animate__zoomIn">
        <div class="p-8 bg-gradient-to-br from-indigo-600 to-violet-700 text-white">
            <h3 class="text-xl font-black italic tracking-tight uppercase">Smart Auto-Dispatch</h3>
            <p class="text-indigo-100 text-xs mt-1 font-bold">ระบุจำนวนงานที่ต้องการกระจายให้แต่ละทีม (งานที่รอจ่าย: <span id="unassignedCount">0</span>)</p>
        </div>
        <div class="p-8 max-h-[60vh] overflow-y-auto" id="dispatchTeamList"></div>
        <div class="p-8 bg-slate-50 flex space-x-3">
            <button onclick="closeDispatchModal()" class="flex-1 py-4 bg-white text-slate-500 rounded-2xl font-black text-xs uppercase tracking-widest border border-slate-200 hover:bg-slate-50 transition-all">ยกเลิก</button>
            <button id="confirmDispatchBtn" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">เริ่มจ่ายงานทันที 🚀</button>
        </div>
    </div>
</div>

<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/dispatch.js"></script>