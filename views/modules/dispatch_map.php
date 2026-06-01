<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<style>
    /* 🌟 Dashboard Animations */
    @keyframes fadeSlideUp { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
    .animate-dashboard { animation: fadeSlideUp 0.4s ease-out forwards; }
    .animate-row { opacity: 0; animation: fadeSlideUp 0.3s ease-out forwards; }

    /* 🌟 Scrollbar ที่เลื่อนง่ายขึ้น */
    .table-container { scroll-behavior: smooth; overflow-x: auto; overflow-y: auto; padding-bottom: 16px; }
    .table-container::-webkit-scrollbar { width: 8px; height: 8px; }
    .table-container::-webkit-scrollbar-track { background: transparent; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    
    /* 🌟 ตารางต้องกว้างอย่างน้อย 1200px ห้ามบีบเด็ดขาด */
    .job-table { border-collapse: separate; border-spacing: 0 8px; min-width: 1200px; width: 100%; }
    .job-table th { 
        position: sticky; top: 0; z-index: 20; background: #f8fafc; 
        text-transform: uppercase; font-size: 0.75rem; 
        padding: 12px 10px; font-weight: 800; color: #64748b; white-space: nowrap;
        border-bottom: 2px solid #e2e8f0; 
    }
    
    .job-table tbody tr { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
    .job-table tbody tr:hover { transform: translateY(-2px); z-index: 10; position: relative; box-shadow: 0 4px 12px -2px rgba(0,0,0,0.08); }
    
    .job-table td { 
        background-color: #ffffff;
        padding: 12px 10px; vertical-align: top; /* 🌟 ให้อ่านจากบนลงล่างง่ายๆ */
        border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
    }
    .job-table td:first-child { border-left: 1px solid #f1f5f9; border-radius: 8px 0 0 8px; }
    .job-table td:last-child { border-right: 1px solid #f1f5f9; border-radius: 0 8px 8px 0; }

    #map { background: linear-gradient(135deg, #f0f4f8 0%, #e8f0f8 100%); }
    .leaflet-container { border-radius: 0; }

    .dispatch-page { display: flex; flex-direction: column; gap: 1rem; min-height: 100vh; padding-bottom: 2rem; }
    .dispatch-page .card:hover { transform: none; }
    .dispatch-view-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; background: var(--c-surface); border: 1px solid var(--c-border); border-radius: 10px; padding: 6px; box-shadow: var(--shadow-1); }
    .dispatch-view-tab { min-height: 44px; border-radius: 8px; font-size: 13px; font-weight: 900; color: var(--c-text-2); display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: background .16s ease, color .16s ease, box-shadow .16s ease; }
    .dispatch-view-tab:hover { background: var(--c-surface-2); color: var(--c-text-1); }
    .dispatch-view-tab.is-active { background: var(--c-primary); color: white; box-shadow: var(--shadow-btn); }
    .dispatch-workspace { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; min-height: 0; }
    .dispatch-list-panel { min-height: 640px; overflow: hidden; }
    .dispatch-list-scroll { flex: 1; min-height: 0; overflow: auto; background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%); scroll-behavior: smooth; }
    .dispatch-job-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 14px; }
    .dispatch-job-card { border-radius: 8px; }
    .dispatch-job-card:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -20px rgba(15, 23, 42, 0.55); }
    .dispatch-stat { min-width: 0; border: 1px solid var(--c-border); background: var(--c-surface); border-radius: 8px; padding: 10px 12px; }
    .dispatch-stat-label { display: block; color: var(--c-text-3); font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; line-height: 1; }
    .dispatch-stat-value { display: block; margin-top: 5px; color: var(--c-text-1); font-size: 18px; font-weight: 900; line-height: 1; }
    .dispatch-map-panel { height: calc(100vh - 210px); min-height: 620px; overflow: hidden; }
    .dispatch-map-header { position: absolute; top: 12px; left: 12px; right: 12px; z-index: 500; pointer-events: none; }
    .dispatch-map-header > div { pointer-events: auto; }
    .dispatch-map-list { position: absolute; z-index: 500; top: 88px; right: 12px; bottom: 12px; width: min(360px, calc(100% - 24px)); pointer-events: auto; overflow: hidden; }
    .dispatch-map-list-body { max-height: 100%; overflow-y: auto; }
    .dispatch-marker {
        width: 34px; height: 34px; border-radius: 50% 50% 50% 8px;
        transform: rotate(-45deg); border: 2px solid #fff;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 900; font-size: 12px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.28);
    }
    .dispatch-marker span { transform: rotate(45deg); }
    .action-buttons button { display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
    @media (min-width: 768px) {
        .dispatch-job-list { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 16px; }
    }
    @media (min-width: 1024px) {
        .dispatch-page { height: calc(100vh - 100px); min-height: 0; padding-bottom: 0; }
        .dispatch-workspace { display: block; flex: 1; }
        .dispatch-list-panel { min-height: 0; }
        .dispatch-map-panel { height: calc(100vh - 180px); min-height: 620px; }
    }
    @media (max-width: 767px) {
        .dispatch-list-panel { min-height: 520px; }
        .dispatch-job-list { padding: 12px; }
        .dispatch-map-panel { height: calc(100vh - 170px); min-height: 560px; }
        .dispatch-map-list { left: 12px; right: 12px; top: auto; bottom: 12px; width: auto; max-height: 42%; }
        .dashboard-header { align-items: stretch; }
        .dashboard-header .action-buttons { width: 100%; }
        .action-buttons > button { min-height: 40px; }
    }
</style>

<div class="dispatch-page animate-dashboard">
    
    <?php if ($isAdmin): ?>
    <div class="card !p-4 flex flex-wrap gap-4 items-center justify-between z-20 dashboard-header shrink-0">
        <div class="flex items-center gap-3 min-w-[200px]">
            <div class="w-10 h-10 bg-[var(--c-primary)] text-white rounded-xl shadow-btn flex items-center justify-center text-sm"><i data-lucide="rocket" class="w-5 h-5"></i></div>
            <div>
                <h2 class="text-base font-black text-[var(--c-text-1)] tracking-tight leading-none mb-1">ระบบแจกจ่ายงาน</h2>
                <p class="text-[10px] font-bold text-[var(--c-primary)] uppercase tracking-widest leading-none">Smart Dispatch</p>
            </div>
        </div>

        <div class="action-buttons grid grid-cols-2 md:flex md:flex-row md:justify-end gap-2 flex-wrap">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" title="นำเข้าไฟล์ Excel" class="bg-[var(--c-surface-2)] hover:bg-[var(--c-border)] text-[var(--c-text-2)] px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all border border-[var(--c-border)]">
                <i data-lucide="download" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>นำเข้า</span>
            </button>
            <button id="exportExcelBtn" title="ส่งออกไฟล์ Excel" class="bg-[var(--c-info-bg)] text-[var(--c-info)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all border border-[var(--c-info-bg)]">
                <i data-lucide="bar-chart-2" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>ส่งออก</span>
            </button>
            <button id="dispatchModalBtn" title="จ่ายงานอัตโนมัติ" class="btn-primary !px-2 md:!px-4 !py-2 text-[10px] md:text-xs col-span-2 md:col-span-1">
                <i data-lucide="bot" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>จ่ายงาน</span>
            </button>
            <button id="optimizeRouteBtn" title="เรียงลำดับเส้นทาง" class="bg-[var(--c-success)] hover:opacity-80 text-white px-2 md:px-4 py-2 rounded-lg text-[10px] md:text-xs font-bold shadow-sm transition-all border border-transparent">
                <i data-lucide="map-pin" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>เรียงคิว</span>
            </button>
            <button id="clearAssignmentsBtn" title="ล้างการจ่ายงาน" class="bg-[var(--c-warning-bg)] text-[var(--c-warning-text)] border border-[var(--c-warning-bg)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all">
                <i data-lucide="refresh-cw" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>ล้าง</span>
            </button>
            <button id="deleteAllJobsBtn" title="ลบงานทั้งหมด" class="bg-[var(--c-danger-bg)] text-[var(--c-danger-text)] border border-[var(--c-danger-bg)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all">
                <i data-lucide="trash-2" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span>ลบ</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="dispatch-view-tabs shrink-0">
        <button type="button" id="dispatchViewJobsBtn" class="dispatch-view-tab is-active">
            <i data-lucide="list-checks" class="w-4 h-4"></i>
            <span>ดูงาน</span>
        </button>
        <button type="button" id="dispatchViewMapBtn" class="dispatch-view-tab">
            <i data-lucide="map" class="w-4 h-4"></i>
            <span>ดูแผนที่</span>
        </button>
    </div>

    <div class="dispatch-workspace">
    <section id="jobViewPanel" class="card !p-0 flex flex-col relative dispatch-list-panel">
        <div id="mapLoader" class="absolute inset-0 bg-[var(--c-surface)]/90 backdrop-blur-sm flex flex-col items-center justify-center z-[80] hidden transition-opacity duration-200">
            <div class="w-10 h-10 border-3 border-[var(--c-primary-faint)] border-t-[var(--c-primary)] rounded-full animate-spin mb-3"></div>
            <p id="loaderText" class="text-[var(--c-primary)] font-bold text-xs uppercase tracking-widest animate-pulse">กำลังโหลด...</p>
        </div>

        <div class="px-4 py-3 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex flex-col xl:flex-row gap-3 xl:items-center xl:justify-between shrink-0">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center bg-[var(--c-surface)] px-3 py-1.5 rounded-md border border-[var(--c-border)] shadow-sm cursor-pointer hover:border-[var(--c-primary)] transition-colors">
                    <input type="checkbox" id="selectAllJobs" class="w-4 h-4 rounded border-[var(--c-text-3)] text-[var(--c-primary)] focus:ring-0 cursor-pointer">
                    <label for="selectAllJobs" class="text-[10px] font-black text-[var(--c-text-2)] ml-2 uppercase cursor-pointer">เลือกทั้งหมด</label>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 flex-1 min-w-[240px]">
                    <div class="dispatch-stat"><span class="dispatch-stat-label">ทั้งหมด</span><span id="jobCountBadge" class="dispatch-stat-value">0</span></div>
                    <div class="dispatch-stat"><span class="dispatch-stat-label">มีพิกัด</span><span id="mappedCountBadge" class="dispatch-stat-value text-[var(--c-info)]">0</span></div>
                    <div class="dispatch-stat"><span class="dispatch-stat-label">จ่ายแล้ว</span><span id="assignedCountBadge" class="dispatch-stat-value text-[var(--c-success)]">0</span></div>
                    <div class="dispatch-stat"><span class="dispatch-stat-label">รอจ่าย</span><span id="unassignedCountBadgeMain" class="dispatch-stat-value text-[var(--c-warning)]">0</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap w-full xl:w-auto">
                <input type="date" id="dateFilter" class="text-xs font-bold input !py-2 !px-3 min-h-10 flex-1 sm:flex-none">
                <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-[var(--c-surface)] hover:bg-[var(--c-surface-3)] border border-[var(--c-border)] text-[var(--c-text-2)] px-3 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap min-h-10">
                    ทุกวัน
                </button>
                <select id="limitFilter" class="text-xs font-bold input !py-2 !px-3 min-h-10">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">ทั้งหมด</option>
                </select>

                <?php if ($isAdmin): ?>
                <select id="teamFilter" class="text-xs font-bold input !py-2 !px-3 !bg-[var(--c-primary-faint)] !text-[var(--c-primary)] !border-[var(--c-primary-faint)] min-h-10">
                    <option value="all">ทีม</option>
                    <option value="unassigned">รอจ่าย</option>
                </select>
                <div class="flex items-center bg-[var(--c-surface)] rounded-lg border border-[var(--c-border)] overflow-hidden h-10 hidden md:flex">
                    <input type="text" id="newTeamName" placeholder="ชื่อทีม" class="border-0 px-3 h-full text-xs font-bold focus:ring-0 w-28">
                    <button id="addTeamBtn" class="bg-[var(--c-primary-faint)] hover:bg-[var(--c-primary)] hover:text-white text-[var(--c-primary)] h-full px-3 font-black border-l border-[var(--c-border)] transition-colors">+</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div id="teamListContainer" class="flex flex-wrap gap-2 px-4 pb-3 pt-2 border-b border-[var(--c-border)] bg-[var(--c-surface-3)] shrink-0"></div>
        <?php endif; ?>

        <div id="selectionActions" class="px-4 py-2 bg-[var(--c-primary-faint)] border-b border-[var(--c-primary-faint)] flex items-center justify-between hidden transition-all shrink-0">
            <p class="text-[11px] font-bold text-[var(--c-primary)]">เลือกอยู่ <span id="selectedCount" class="font-black text-sm ml-1">0</span> งาน</p>
            <div class="flex gap-2">
                <button id="navigateSelectedBtn" class="text-[10px] font-black bg-[var(--c-primary)] hover:bg-[var(--c-primary-hover)] text-white px-3 py-1.5 rounded-lg uppercase transition-all shadow-sm flex items-center">
                    <i data-lucide="navigation" class="w-3 h-3 mr-1"></i> นำทาง
                </button>
                <?php if ($isAdmin): ?>
                <button id="bulkDeleteBtn" class="text-[10px] font-black bg-[var(--c-danger)] hover:bg-[#DC2626] text-white px-3 py-1.5 rounded-lg uppercase transition-all shadow-sm">ลบ</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="dispatch-list-scroll">
            <div id="jobTableBody" class="dispatch-job-list text-sm text-[var(--c-text-2)]"></div>
        </div>
    </section>

    <aside id="mapViewPanel" class="card !p-0 relative dispatch-map-panel hidden">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <div class="dispatch-map-header">
            <div class="bg-white/95 backdrop-blur rounded-lg border border-slate-200 shadow-sm px-3 py-2 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider leading-none">Map Overview</div>
                    <div class="text-xs font-black text-slate-800 mt-1">แผนที่งานติดตั้ง</div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <div class="text-right">
                        <div id="mapCountBadge" class="text-sm font-black text-[var(--c-primary)] leading-none">0</div>
                        <div class="text-[9px] font-bold text-slate-400 mt-1">มีพิกัด</div>
                    </div>
                    <div class="w-px bg-slate-200"></div>
                    <div class="text-right">
                        <div id="mapMissingBadge" class="text-sm font-black text-amber-600 leading-none">0</div>
                        <div class="text-[9px] font-bold text-slate-400 mt-1">ไม่มีพิกัด</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dispatch-map-list bg-white/95 backdrop-blur rounded-lg border border-slate-200 shadow-lg">
            <div class="px-3 py-2 border-b border-slate-100 flex items-center justify-between gap-3">
                <div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider leading-none">Assigned Jobs</div>
                    <div class="text-xs font-black text-slate-800 mt-1">งานที่มอบหมายแล้ว</div>
                </div>
                <div id="mapAssignedCountBadge" class="text-sm font-black text-[var(--c-primary)]">0</div>
            </div>
            <div id="mapJobList" class="dispatch-map-list-body"></div>
        </div>
        <div id="map" class="w-full h-full"></div>
    </aside>
    </div>
</div>

<div id="dispatchModal" class="fixed inset-0 z-[80] hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden animate__animated animate__zoomIn z-[90]">
        <div class="p-4 bg-indigo-600 text-white text-center">
            <h3 class="text-sm font-black uppercase">Auto-Dispatch</h3>
            <p class="text-[10px] mt-1 text-indigo-100">รอจ่าย: <span id="unassignedCount" class="font-bold">0</span> งาน</p>
        </div>
        <div class="p-3 max-h-[40vh] overflow-y-auto space-y-1.5" id="dispatchTeamList"></div>
        <div class="p-3 bg-slate-50 flex gap-2 border-t border-slate-100">
            <button onclick="closeDispatchModal()" class="flex-1 py-2 bg-white text-slate-600 rounded-lg font-bold text-[10px] border border-slate-200 hover:bg-slate-100">ยกเลิก</button>
            <button id="confirmDispatchBtn" class="btn-primary flex-[2] py-2 text-[10px]">ยืนยันการจ่าย <i data-lucide="rocket" class="w-5 h-5 inline-block"></i></button>
        </div>
    </div>
</div>

<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/common.js"></script>
<script src="assets/js/dispatch.js?v=<?= time() ?>"></script>

<style>
    /* ปรับแต่ง Scrollbar ของ Modal ให้ดูสะอาดตาและไม่ทับซ้อน */
    .complete-modal-scrollbar::-webkit-scrollbar { width: 6px; }
    .complete-modal-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
    .complete-modal-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    .complete-modal-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div id="completeJobModal" class="fixed inset-0 z-[9999] hidden bg-slate-900/70 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden z-[10000] max-h-[95vh] flex flex-col transform transition-transform scale-100">
        
        <div class="p-5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex justify-between items-center shrink-0">
            <h3 class="text-xl font-black tracking-tight flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                ยืนยันรายละเอียดการจบงาน
            </h3>
            <button onclick="closeCompleteJobModal()" class="text-emerald-100 hover:text-white text-3xl leading-none transition-colors focus:outline-none">&times;</button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50 complete-modal-scrollbar">
            <form id="completeJobForm" class="space-y-6">
                <input type="hidden" id="cj_job_id">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เลข Non</label>
                        <p id="cj_non" class="font-black text-indigo-600 text-base mt-1">-</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ทีม (Team)</label>
                        <p id="cj_team_name" class="font-bold text-slate-800 text-sm mt-1">-</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อลูกค้า</label>
                        <p id="cj_customer" class="font-bold text-slate-800 text-sm mt-1 truncate">-</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รหัสอ้างอิง</label>
                        <p id="cj_access_no" class="font-bold text-slate-800 text-sm mt-1">-</p>
                    </div>
                    <div class="sm:col-span-2 md:col-span-4">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ที่อยู่ติดตั้ง</label>
                        <p id="cj_address" class="font-bold text-slate-700 text-sm mt-1 break-words">-</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <h4 class="font-black text-slate-800 mb-4 flex items-center">
                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-[10px] mr-2 uppercase tracking-widest">Info</span>
                        ข้อมูลทางเทคนิค
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">วันที่และเวลาติดตั้ง <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" id="cj_install_date" required class="w-full border border-slate-300 rounded-xl font-bold text-sm p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">ระยะที่ขอ (เมตร)</label>
                            <input type="number" id="cj_distance" class="w-full border border-slate-300 rounded-xl font-bold text-sm p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" placeholder="เช่น 150">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Splitter</label>
                            <input type="text" id="cj_splitter" class="w-full border border-slate-300 rounded-xl font-bold text-sm p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" placeholder="ระบุ Splitter">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Code SOA</label>
                            <input type="text" id="cj_code_soa" class="w-full border border-slate-300 rounded-xl font-bold text-sm p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" placeholder="ระบุ Code SOA">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <h4 class="font-black text-slate-800 mb-4 flex items-center">
                        <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-[10px] mr-2 uppercase tracking-widest">Material</span>
                        วัสดุที่ใช้งานจริง
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Patch Cord ดำ</label>
                            <input type="number" id="cj_patch_black" min="0" class="w-full border border-slate-300 rounded-xl text-center font-bold text-base p-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Patch Cord เหลือง</label>
                            <input type="number" id="cj_patch_yellow" min="0" class="w-full border border-slate-300 rounded-xl text-center font-bold text-base p-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">ท่อดำ</label>
                            <input type="number" id="cj_tube_black" min="0" class="w-full border border-slate-300 rounded-xl text-center font-bold text-base p-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">ท่อหดขาว</label>
                            <input type="number" id="cj_tube_white" min="0" class="w-full border border-slate-300 rounded-xl text-center font-bold text-base p-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">เนมเพลท</label>
                            <input type="number" id="cj_nameplate" min="0" class="w-full border border-slate-300 rounded-xl text-center font-bold text-base p-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all" placeholder="0">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">หมายเหตุเพิ่มเติม (ถ้ามี)</label>
                    <textarea id="cj_remark" rows="2" class="w-full border border-slate-300 rounded-xl font-medium text-sm p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" placeholder="ระบุรายละเอียดเพิ่มเติมที่ต้องการบันทึก..."></textarea>
                </div>
            </form>
        </div>
        
        <div class="p-5 bg-white border-t border-slate-200 flex justify-end gap-3 shrink-0">
            <button type="button" onclick="closeCompleteJobModal()" class="px-6 py-3 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 focus:outline-none transition-colors">ยกเลิก</button>
            <button type="button" onclick="submitCompleteJobDetails()" class="px-6 py-3 rounded-xl font-bold bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 flex items-center focus:outline-none transition-all active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                บันทึกการจบงาน
            </button>
        </div>
    </div>
</div>

<script>
/**
 * ฟังก์ชันเปิด Modal พร้อมดึงข้อมูลจากตัวแปร Array ของแผนที่มาเติมในฟอร์ม
 * @param {number|string} jobId - ID ของงานที่คลิก
 */
function openCompleteJobModal(jobId) {
    // ดึงข้อมูลงานจาก allJobs (ตัวแปรเก็บงานของแผนที่)
    const job = (typeof allJobs !== 'undefined') ? allJobs.find(j => String(j.id) === String(jobId)) : null;
    
    document.getElementById('cj_job_id').value = jobId;
    
    if (job) {
        document.getElementById('cj_non').textContent = job.access_no || '-'; 
        document.getElementById('cj_access_no').textContent = job.order_no || job.id || '-';
        
        // เช็คชื่อทีม ถ้ามี team_name ให้ใช้ ถ้าไม่มีให้ใช้ team_id
        let teamDisplay = 'ไม่ได้มอบหมาย';
        if (job.team_name) teamDisplay = job.team_name;
        else if (job.team_id) teamDisplay = 'Team ' + job.team_id;
        document.getElementById('cj_team_name').textContent = teamDisplay;
        
        document.getElementById('cj_customer').textContent = job.customer || 'ไม่ระบุชื่อ';
        document.getElementById('cj_address').textContent = job.address || '-';
    } else {
        document.getElementById('cj_non').textContent = "ไม่ทราบข้อมูล";
        document.getElementById('cj_access_no').textContent = jobId;
    }

    // เซ็ตวันที่และเวลาปัจจุบัน (Local Time) ลงใน Input
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('cj_install_date').value = now.toISOString().slice(0, 16);

    // ล้างค่าข้อมูลการกรอกครั้งก่อนหน้า
    const inputsToClear = ['cj_splitter', 'cj_code_soa', 'cj_distance', 'cj_patch_black', 'cj_patch_yellow', 'cj_tube_black', 'cj_tube_white', 'cj_nameplate', 'cj_remark'];
    inputsToClear.forEach(id => {
        document.getElementById(id).value = '';
    });

    // แสดง Modal
    document.getElementById('completeJobModal').classList.remove('hidden');
}

/**
 * ฟังก์ชันปิด Modal
 */
function closeCompleteJobModal() {
    document.getElementById('completeJobModal').classList.add('hidden');
}

/**
 * ฟังก์ชันรวบรวมข้อมูลและส่ง API เพื่อบันทึกลงฐานข้อมูล
 */
async function submitCompleteJobDetails() {
    const installDate = document.getElementById('cj_install_date').value;
    
    // ตรวจสอบข้อมูลเบื้องต้น
    if (!installDate) {
        alert('กรุณาระบุวันที่และเวลาติดตั้งให้ครบถ้วน');
        return;
    }

    // เตรียม Data Payload
    const payload = {
        job_id: document.getElementById('cj_job_id').value,
        install_date: installDate,
        splitter: document.getElementById('cj_splitter').value,
        code_soa: document.getElementById('cj_code_soa').value,
        distance: document.getElementById('cj_distance').value || 0,
        patch_black: document.getElementById('cj_patch_black').value || 0,
        patch_yellow: document.getElementById('cj_patch_yellow').value || 0,
        tube_black: document.getElementById('cj_tube_black').value || 0,
        tube_white: document.getElementById('cj_tube_white').value || 0,
        nameplate: document.getElementById('cj_nameplate').value || 0,
        remark: document.getElementById('cj_remark').value
    };

    // เปลี่ยนปุ่มเป็นสถานะกำลังโหลด (ถ้าต้องการ)
    // document.querySelector('#completeJobModal button.bg-emerald-600').innerText = "กำลังบันทึก...";

    try {
        // ยิง API ไปที่ Backend
        const response = await fetch('api/dispatch/complete_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('บันทึกข้อมูลการจบงานสำเร็จเรียบร้อย');
            closeCompleteJobModal();
            
            // รีเฟรชข้อมูลบนแผนที่
            if (typeof loadJobs === 'function') {
                loadJobs(); // ถ้ามีฟังก์ชันโหลดข้อมูลแผนที่ใหม่ ให้เรียกใช้
            } else if (typeof fetchJobs === 'function') {
                fetchJobs();
            } else {
                location.reload(); // รีเฟรชหน้าต่างถ้าไม่พบฟังก์ชันอัปเดต
            }
        } else {
            alert('เกิดข้อผิดพลาด: ' + (result.message || 'ไม่สามารถบันทึกข้อมูลได้'));
        }
    } catch (error) {
        console.error('Error Complete Job:', error);
        alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาตรวจสอบอินเทอร์เน็ตหรือติดต่อผู้ดูแลระบบ');
    }
}
</script>