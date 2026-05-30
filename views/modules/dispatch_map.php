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
</style>

<div class="flex flex-col gap-4 animate-dashboard min-h-screen pb-10 lg:h-[calc(100vh-100px)] lg:pb-0">
    
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
                <i data-lucide="download" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">นำเข้า</span>
            </button>
            <button id="exportExcelBtn" title="ส่งออกไฟล์ Excel" class="bg-[var(--c-info-bg)] text-[var(--c-info)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all border border-[var(--c-info-bg)]">
                <i data-lucide="bar-chart-2" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">ส่งออก</span>
            </button>
            <button id="dispatchModalBtn" title="จ่ายงานอัตโนมัติ" class="btn-primary !px-2 md:!px-4 !py-2 text-[10px] md:text-xs col-span-2 md:col-span-1">
                <i data-lucide="bot" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">จ่ายงาน</span>
            </button>
            <button id="optimizeRouteBtn" title="เรียงลำดับเส้นทาง" class="bg-[var(--c-success)] hover:opacity-80 text-white px-2 md:px-4 py-2 rounded-lg text-[10px] md:text-xs font-bold shadow-sm transition-all border border-transparent">
                <i data-lucide="map-pin" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">เรียงคิว</span>
            </button>
            <button id="clearAssignmentsBtn" title="ล้างการจ่ายงาน" class="bg-[var(--c-warning-bg)] text-[var(--c-warning-text)] border border-[var(--c-warning-bg)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all">
                <i data-lucide="refresh-cw" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">ล้าง</span>
            </button>
            <button id="deleteAllJobsBtn" title="ลบงานทั้งหมด" class="bg-[var(--c-danger-bg)] text-[var(--c-danger-text)] border border-[var(--c-danger-bg)] hover:opacity-80 px-2 md:px-3 py-2 rounded-lg text-[10px] md:text-xs font-bold transition-all">
                <i data-lucide="trash-2" class="w-3 h-3 md:w-4 md:h-4 inline-block mr-1"></i><span class="hidden sm:inline">ลบ</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table Section Card -->
    <div class="card !p-0 flex flex-col lg:flex-1 overflow-hidden relative min-h-[400px]">
        <div id="mapLoader" class="absolute inset-0 bg-[var(--c-surface)]/90 backdrop-blur-sm flex flex-col items-center justify-center z-[80] hidden transition-opacity duration-200">
            <div class="w-10 h-10 border-3 border-[var(--c-primary-faint)] border-t-[var(--c-primary)] rounded-full animate-spin mb-3"></div>
            <p id="loaderText" class="text-[var(--c-primary)] font-bold text-xs uppercase tracking-widest animate-pulse">กำลังโหลด...</p>
        </div>

        <div class="px-4 py-3 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex flex-wrap gap-3 items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
                <div class="flex items-center bg-[var(--c-surface)] px-3 py-1.5 rounded-md border border-[var(--c-border)] shadow-sm cursor-pointer hover:border-[var(--c-primary)] transition-colors">
                    <input type="checkbox" id="selectAllJobs" class="w-4 h-4 rounded border-[var(--c-text-3)] text-[var(--c-primary)] focus:ring-0 cursor-pointer">
                    <label for="selectAllJobs" class="text-[10px] font-black text-[var(--c-text-2)] ml-2 uppercase cursor-pointer">เลือกทั้งหมด</label>
                </div>
                <span class="text-xs font-bold text-[var(--c-text-3)]">จำนวน: <span id="jobCountBadge" class="text-[var(--c-primary)] font-black text-sm ml-1">0</span></span>
            </div>

            <div class="flex items-center gap-1 md:gap-2 flex-wrap">
                <input type="date" id="dateFilter" class="text-[10px] md:text-xs font-bold input !py-1.5 !px-2">
                <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-[var(--c-surface)] hover:bg-[var(--c-surface-3)] border border-[var(--c-border)] text-[var(--c-text-2)] px-2 md:px-3 py-1.5 rounded-lg text-[9px] md:text-[10px] font-bold transition-all whitespace-nowrap">
                    ทุกวัน
                </button>
                <select id="limitFilter" class="text-[10px] md:text-xs font-bold input !py-1.5 !px-2">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">ทั้งหมด</option>
                </select>

                <?php if ($isAdmin): ?>
                <select id="teamFilter" class="text-[10px] md:text-xs font-bold input !py-1.5 !px-2 !bg-[var(--c-primary-faint)] !text-[var(--c-primary)] !border-[var(--c-primary-faint)]">
                    <option value="all">ทีม</option>
                    <option value="unassigned">รอจ่าย</option>
                </select>
                <div class="flex items-center bg-[var(--c-surface)] rounded-lg border border-[var(--c-border)] overflow-hidden h-[34px] hidden md:flex">
                    <input type="text" id="newTeamName" placeholder="ชื่อทีม" class="border-0 px-2 h-full text-[10px] font-bold focus:ring-0 w-16 md:w-24">       
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

        <!-- Table Section -->
        <div class="w-full flex-1 overflow-hidden flex flex-col">
            <div class="flex-1 overflow-hidden relative bg-[var(--c-surface)]">
                <div class="table-container absolute inset-0 w-full h-full overflow-auto">
                    <table class="job-table pb-4">
    <thead class="bg-[#f8fafc]">
        <tr>
            <th class="text-center w-12"><i data-lucide="check-square" class="w-4 h-4 mx-auto text-slate-400"></i></th>
            <th class="text-center w-16">คิว</th>
            <th class="text-left w-32">รหัสงาน</th>
            <th class="text-left w-48">ชื่อลูกค้า</th>
            <th class="text-left w-32">เบอร์โทร</th>
            <th class="text-left auto">สถานที่ติดตั้ง</th>
            <th class="text-left w-28">วันที่</th>
            <th class="text-center w-36">ทีมรับผิดชอบ</th>
        </tr>
    </thead>
    <tbody id="jobTableBody" class="text-sm text-[var(--c-text-2)]">
        </tbody>
</table>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section Card -->
    <div class="card !p-0 overflow-hidden shrink-0 relative h-[400px] lg:h-[450px]">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <div id="map" class="w-full h-full"></div>
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
