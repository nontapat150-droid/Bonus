<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* 🌟 Dashboard Animations */
    @keyframes fadeSlideUp { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
    .animate-dashboard { animation: fadeSlideUp 0.4s ease-out forwards; }
    .animate-row { opacity: 0; animation: fadeSlideUp 0.3s ease-out forwards; }

    /* 🌟 Compact Scrollbar */
    .table-container { scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
    .table-container::-webkit-scrollbar { width: 5px; height: 5px; }
    .table-container::-webkit-scrollbar-track { background: transparent; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* 🌟 COMPACT TABLE LAYOUT (ตัวทำให้ตารางเล็กลง) */
    .job-table { border-collapse: separate; border-spacing: 0; min-width: 900px; width: 100%; }
    .job-table th { 
        position: sticky; top: 0; z-index: 20; background: #f8fafc; 
        border-bottom: 2px solid #e2e8f0; text-transform: uppercase; 
        letter-spacing: 0.05em; font-size: 0.65rem; padding: 8px 10px; font-weight: 900; color: #64748b;
    }
    .job-table td { 
        border-bottom: 1px solid #f1f5f9; padding: 6px 10px; transition: background-color 0.15s; vertical-align: middle;
    }
    
    /* บังคับตัดคำไม่ให้ขึ้นบรรทัดใหม่ */
    .truncate-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* 🌟 Mobile Optimization */
    @media (max-width: 768px) {
        .dashboard-header { flex-direction: column; align-items: stretch; gap: 0.5rem; }
        .action-buttons { overflow-x: auto; padding-bottom: 4px; white-space: nowrap; }
    }
</style>

<div class="h-[calc(100vh-80px)] flex flex-col space-y-2 animate-dashboard p-1 md:p-0">
    
    <?php if ($isAdmin): ?>
    <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-100 flex flex-wrap gap-3 items-center justify-between z-20 dashboard-header">
        <div class="flex items-center space-x-3 min-w-[200px]">
            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-lg shadow-sm flex items-center justify-center text-sm">🚀</div>
            <div>
                <h2 class="text-sm font-black text-slate-800 tracking-tight leading-none">ระบบแจกจ่ายงาน</h2>
                <p class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest mt-0.5">Smart Dispatch</p>
            </div>
        </div>

        <div class="action-buttons flex items-center gap-1.5 flex-1 justify-end">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-slate-50 hover:bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all border border-slate-200">
                📥 นำเข้า
            </button>
            <button id="exportExcelBtn" class="bg-sky-50 text-sky-600 hover:bg-sky-100 border border-sky-100 px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                📊 ส่งออก
            </button>
            <div class="w-px h-5 bg-slate-200 mx-1 hidden md:block"></div>
            <button id="dispatchModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm transition-all">
                🤖 จ่ายงานออโต้
            </button>
            <button id="optimizeRouteBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold shadow-sm transition-all">
                📍 เรียงคิว
            </button>
            <div class="w-px h-5 bg-slate-200 mx-1 hidden md:block"></div>
            <button id="clearAssignmentsBtn" class="bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                🔄 ล้างการจ่าย
            </button>
            <button id="deleteAllJobsBtn" class="bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-100 px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                🗑️ ลบทั้งหมด
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col flex-1 gap-2 min-h-0 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative">
        
        <div id="mapLoader" class="absolute inset-0 bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center z-50 hidden transition-opacity duration-200">
            <div class="w-10 h-10 border-3 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-2"></div>
            <p id="loaderText" class="text-indigo-800 font-bold text-xs uppercase tracking-widest animate-pulse">กำลังโหลด...</p>
        </div>

        <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/50 flex flex-wrap gap-2 items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="flex items-center bg-white px-2 py-1 rounded-md border border-slate-200 shadow-sm cursor-pointer">
                    <input type="checkbox" id="selectAllJobs" class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-0 cursor-pointer">
                    <label for="selectAllJobs" class="text-[9px] font-black text-slate-600 ml-1.5 uppercase cursor-pointer">เลือกทั้งหมด</label>
                </div>
                <span class="text-[10px] font-bold text-slate-500">จำนวน: <span id="jobCountBadge" class="text-indigo-600 font-black">0</span></span>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <input type="date" id="dateFilter" class="text-[10px] font-bold border-slate-200 rounded-lg px-2 py-1 h-7 focus:ring-0 text-slate-600">
                <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-2 py-1 h-7 rounded-lg text-[9px] font-bold transition-all">
                    ทุกวัน
                </button>
                <select id="limitFilter" class="text-[10px] font-bold border-slate-200 rounded-lg py-1 pl-2 pr-6 h-7 focus:ring-0 text-slate-600">
                    <option value="20">20 แถว</option>
                    <option value="50">50 แถว</option>
                    <option value="100">100 แถว</option>
                    <option value="all">ทั้งหมด</option>
                </select>

                <?php if ($isAdmin): ?>
                <div class="w-px h-4 bg-slate-200 mx-1"></div>
                <select id="teamFilter" class="text-[10px] font-bold border-indigo-100 bg-indigo-50 text-indigo-700 rounded-lg py-1 pl-2 pr-6 h-7 focus:ring-0">
                    <option value="all">📍 ทุกทีม</option>
                    <option value="unassigned">⏳ ยังไม่จ่าย</option>
                </select>
                <div class="flex items-center h-7 bg-white rounded-lg border border-slate-200 overflow-hidden">
                    <input type="text" id="newTeamName" placeholder="ชื่อทีมใหม่" class="border-0 px-2 h-full text-[10px] font-bold focus:ring-0 w-20 md:w-24">       
                    <button id="addTeamBtn" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 h-full px-2 font-black border-l border-slate-200">+</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div id="teamListContainer" class="flex flex-wrap gap-1 px-3 pb-2 pt-1 border-b border-slate-50 bg-slate-50/20"></div>
        <?php endif; ?>

        <div id="selectionActions" class="px-3 py-1.5 bg-rose-50 border-b border-rose-100 flex items-center justify-between hidden transition-all">
            <p class="text-[10px] font-bold text-rose-800">เลือกอยู่ <span id="selectedCount" class="font-black">0</span> งาน</p>
            <button id="bulkDeleteBtn" class="text-[9px] font-black bg-rose-500 hover:bg-rose-600 text-white px-3 py-1 rounded-md uppercase transition-all shadow-sm">ลบที่เลือก</button>
        </div>

        <div class="flex-1 overflow-hidden flex flex-col bg-white">
            <div class="table-container flex-1 w-full overflow-x-auto">
                <table class="job-table">
                    <thead>
                        <tr>
                            <th class="w-8 text-center">#</th>
                            <th class="w-12 text-center">คิว</th>
                            <th class="w-24">รหัสงาน</th>
                            <th class="w-48">ชื่อลูกค้า</th>
                            <th class="w-24">เบอร์โทร</th>
                            <th class="min-w-[180px]">สถานที่</th>
                            <th class="w-20">วันที่</th>
                            <th class="w-28 text-right pr-4">ทีมช่าง</th>
                        </tr>
                    </thead>
                    <tbody id="jobTableBody" class="text-[11px] text-slate-700">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="dispatchModal" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden animate__animated animate__zoomIn">
        <div class="p-4 bg-indigo-600 text-white text-center">
            <h3 class="text-sm font-black uppercase">Auto-Dispatch</h3>
            <p class="text-[10px] mt-1 text-indigo-100">รอจ่าย: <span id="unassignedCount" class="font-bold">0</span> งาน</p>
        </div>
        <div class="p-3 max-h-[40vh] overflow-y-auto space-y-1.5" id="dispatchTeamList"></div>
        <div class="p-3 bg-slate-50 flex gap-2 border-t border-slate-100">
            <button onclick="closeDispatchModal()" class="flex-1 py-2 bg-white text-slate-600 rounded-lg font-bold text-[10px] border border-slate-200 hover:bg-slate-100">ยกเลิก</button>
            <button id="confirmDispatchBtn" class="flex-[2] py-2 bg-indigo-600 text-white rounded-lg font-bold text-[10px] shadow-sm hover:bg-indigo-700">ยืนยันการจ่าย 🚀</button>
        </div>
    </div>
</div>

<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/dispatch.js"></script>