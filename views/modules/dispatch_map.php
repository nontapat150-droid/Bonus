<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* 🌟 Dashboard Animations */
    @keyframes fadeSlideUp {
        0% { opacity: 0; transform: translateY(15px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .animate-dashboard { animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .animate-row { opacity: 0; animation: fadeSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

    /* 🌟 Smooth Scrollbar สำหรับตาราง */
    .table-container { 
        scroll-behavior: smooth; 
        -webkit-overflow-scrolling: touch; 
    }
    .table-container::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-container::-webkit-scrollbar-track { background: transparent; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .table-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* 🌟 Responsive Table Layout */
    .job-table { border-collapse: separate; border-spacing: 0; min-width: 900px; width: 100%; }
    .job-table th { position: sticky; top: 0; z-index: 20; background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem; padding: 16px 12px; }
    .job-table td { border-bottom: 1px solid #f1f5f9; padding: 14px 12px; transition: background-color 0.2s; }
    
    /* 🌟 Mobile Optimization */
    @media (max-width: 768px) {
        .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .action-buttons { grid-template-columns: repeat(2, 1fr); width: 100%; display: grid; }
        .action-buttons button { width: 100%; justify-content: center; }
        .filter-bar { flex-direction: column; width: 100%; }
        .filter-bar > * { width: 100%; }
    }
</style>

<div class="h-[calc(100vh-90px)] md:h-[calc(100vh-120px)] flex flex-col space-y-4 animate-dashboard">
    
    <?php if ($isAdmin): ?>
    <div class="bg-white/80 backdrop-blur-xl p-4 md:p-5 rounded-3xl shadow-sm border border-white flex flex-wrap gap-4 items-center justify-between z-20 relative dashboard-header transition-all">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-2xl shadow-lg shadow-indigo-200 flex items-center justify-center text-xl">🚀</div>
            <div>
                <h2 class="text-lg md:text-xl font-black text-slate-800 tracking-tight">ระบบแจกจ่ายงาน</h2>
                <p class="text-[10px] md:text-xs font-bold text-indigo-500 uppercase tracking-widest">Smart Dispatch Dashboard</p>
            </div>
        </div>

        <div class="action-buttons flex items-center gap-2">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-slate-50 hover:bg-slate-100 text-slate-600 px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black transition-all flex items-center shadow-sm border border-slate-200">
                📥 นำเข้า
            </button>
            <button id="exportExcelBtn" class="bg-sky-50 text-sky-600 hover:bg-sky-100 border border-sky-100 px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black transition-all flex items-center shadow-sm">
                📊 ส่งออก
            </button>
            <button id="dispatchModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black shadow-lg shadow-indigo-200 transition-all flex items-center">
                🤖 จ่ายงานออโต้
            </button>
            <button id="optimizeRouteBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black shadow-lg shadow-emerald-200 transition-all flex items-center">
                📍 เรียงคิวงาน
            </button>
            <button id="clearAssignmentsBtn" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black shadow-lg shadow-amber-200 transition-all flex items-center">
                🔄 ล้างการจ่าย
            </button>
            <button id="deleteAllJobsBtn" class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black shadow-lg shadow-rose-200 transition-all flex items-center">
                🗑️ ลบทั้งหมด
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col flex-1 gap-4 min-h-0">
        <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/40 border border-slate-100 flex flex-col overflow-hidden h-full relative">
            
            <div id="mapLoader" class="absolute inset-0 bg-white/90 backdrop-blur-md flex flex-col items-center justify-center z-50 hidden transition-opacity duration-300">
                <div class="relative w-16 h-16 flex items-center justify-center mb-4">
                    <div class="absolute inset-0 border-4 border-indigo-100 rounded-full"></div>
                    <div class="absolute inset-0 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <div class="text-indigo-600 text-xl font-black">⚙️</div>
                </div>
                <p id="loaderText" class="text-indigo-800 font-black text-xs md:text-sm uppercase tracking-widest animate-pulse">กำลังโหลดข้อมูล...</p>
            </div>

            <div class="p-4 md:p-6 border-b border-slate-100 bg-slate-50/50 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-700 tracking-tight flex items-center text-sm md:text-base">
                        📋 รายการงานทั้งหมด
                        <span id="jobCountBadge" class="ml-2 bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-[10px] font-black shadow-inner">0</span>
                    </h3>
                    <div class="flex items-center space-x-2 bg-white px-3 py-1.5 rounded-xl shadow-sm border border-slate-100">
                        <input type="checkbox" id="selectAllJobs" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        <label for="selectAllJobs" class="text-[10px] font-black text-slate-500 uppercase tracking-widest cursor-pointer">เลือกทั้งหมด</label>
                    </div>
                </div>

                <div class="filter-bar flex gap-2">
                    <input type="date" id="dateFilter" class="flex-1 text-[11px] md:text-xs font-black border-slate-200 rounded-2xl focus:ring-indigo-500/20 px-4 py-3 cursor-pointer text-slate-600 shadow-sm bg-white">
                    <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-3 rounded-2xl text-[10px] md:text-xs font-black shadow-md transition-all cursor-pointer">
                        🔄 ทุกงาน
                    </button>
                    <select id="limitFilter" class="text-[11px] md:text-xs font-black border-slate-200 rounded-2xl focus:ring-indigo-500/20 py-3 pl-4 pr-8 cursor-pointer bg-white text-slate-600 shadow-sm w-full md:w-auto">
                        <option value="20">โชว์ 20 งาน</option>
                        <option value="50">โชว์ 50 งาน</option>
                        <option value="100">โชว์ 100 งาน</option>
                        <option value="all">โชว์ทั้งหมด</option>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                <div class="filter-bar flex gap-2 mt-2">
                    <select id="teamFilter" class="flex-1 text-[10px] md:text-xs font-black uppercase tracking-wider border border-indigo-100 bg-indigo-50/50 text-indigo-700 rounded-2xl shadow-sm focus:ring-0 py-3 pl-4 pr-10 cursor-pointer">
                        <option value="all">📍 แสดงงานทุกทีม</option>
                        <option value="unassigned">⏳ เฉพาะที่ยังไม่จ่ายงาน</option>
                    </select>
                    <div class="flex items-center space-x-1 w-full md:w-auto">
                        <input type="text" id="newTeamName" placeholder="สร้างทีมใหม่..." class="flex-1 px-4 py-3 rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500/20 text-[10px] md:text-xs font-bold shadow-sm md:w-40">       
                        <button id="addTeamBtn" class="bg-indigo-600 text-white w-12 h-12 rounded-2xl font-black flex items-center justify-center hover:bg-indigo-700 transition-all shadow-md">+</button>
                    </div>
                </div>
                <div id="teamListContainer" class="flex flex-wrap gap-2 pt-2"></div>
                <?php endif; ?>
            </div>

            <div id="selectionActions" class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between hidden transition-all animate-dashboard">
                <p class="text-[10px] font-black uppercase tracking-widest">เลือกอยู่ <span id="selectedCount" class="text-sky-400 text-sm">0</span> งาน</p>
                <button id="bulkDeleteBtn" class="text-[10px] font-black bg-rose-500 hover:bg-rose-600 px-4 py-2 rounded-xl uppercase transition-all shadow-md">ลบที่เลือก</button>
            </div>

            <div class="flex-1 overflow-hidden flex flex-col bg-white">
                <div class="table-container flex-1 w-full overflow-x-auto">
                    <table class="job-table whitespace-nowrap">
                        <thead>
                            <tr class="text-slate-400 text-left">
                                <th class="w-10 text-center"><span class="sr-only">เลือก</span></th>
                                <th class="w-16 text-center">คิว</th>
                                <th class="w-32">รหัสงาน</th>
                                <th class="w-48">ลูกค้า</th>
                                <th class="w-32">เบอร์โทร</th>
                                <th class="min-w-[200px]">สถานที่</th>
                                <th class="w-24">วันที่นัด</th>
                                <th class="w-32 text-right pr-6">ทีมช่าง</th>
                            </tr>
                        </thead>
                        <tbody id="jobTableBody" class="text-xs md:text-sm font-bold text-slate-700">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="dispatchModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-[95%] md:max-w-lg overflow-hidden animate__animated animate__zoomIn">
        <div class="p-6 md:p-8 bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-center">
            <h3 class="text-lg md:text-xl font-black italic tracking-tight uppercase">Smart Auto-Dispatch</h3>
            <p class="text-indigo-100 text-[10px] md:text-xs mt-2 font-bold bg-white/20 inline-block px-4 py-1.5 rounded-full">งานที่รอจ่าย: <span id="unassignedCount" class="text-white text-sm">0</span> งาน</p>
        </div>
        <div class="p-4 md:p-6 max-h-[50vh] overflow-y-auto space-y-2" id="dispatchTeamList"></div>
        <div class="p-4 md:p-6 bg-slate-50 flex space-x-3 border-t border-slate-100">
            <button onclick="closeDispatchModal()" class="flex-1 py-3.5 bg-white text-slate-500 rounded-2xl font-black text-[10px] md:text-xs uppercase tracking-widest border border-slate-200 hover:bg-slate-100 transition-all">ยกเลิก</button>
            <button id="confirmDispatchBtn" class="flex-[2] py-3.5 bg-indigo-600 text-white rounded-2xl font-black text-[10px] md:text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all hover:-translate-y-0.5">เริ่มจ่ายงาน 🚀</button>
        </div>
    </div>
</div>

<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/dispatch.js"></script>