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
    .table-container { scroll-behavior: smooth; -webkit-overflow-scrolling: touch; overflow: auto; }
    .table-container::-webkit-scrollbar { width: 5px; height: 5px; }
    .table-container::-webkit-scrollbar-track { background: transparent; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* 🌟 COMPACT TABLE LAYOUT */
    .job-table { border-collapse: separate; border-spacing: 0; min-width: 900px; width: 100%; }
    .job-table th { 
        position: sticky; top: 0; z-index: 20; background: #f8fafc; 
        border-bottom: 2px solid #e2e8f0; text-transform: uppercase; 
        letter-spacing: 0.05em; font-size: 0.65rem; padding: 8px 10px; font-weight: 900; color: #64748b;
    }
    .job-table td { 
        border-bottom: 1px solid #f1f5f9; padding: 6px 10px; transition: background-color 0.15s; vertical-align: middle;
    }
    
    .truncate-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    @media (max-width: 768px) {
        .dashboard-header { flex-direction: column; align-items: stretch; gap: 0.5rem; }
        .action-buttons { overflow-x: auto; padding-bottom: 4px; white-space: nowrap; }
    }
</style>

<div class="flex flex-col gap-4 animate-dashboard h-[calc(100dvh-160px)] md:h-[calc(100vh-120px)]">
    
    <?php if ($isAdmin): ?>
    <div class="card !p-4 flex flex-wrap gap-4 items-center justify-between z-20 dashboard-header shrink-0">
        <div class="flex items-center gap-3 min-w-[200px]">
            <div class="w-10 h-10 bg-[var(--c-primary)] text-white rounded-xl shadow-btn flex items-center justify-center text-sm"><i data-lucide="rocket" class="w-5 h-5"></i></div>
            <div>
                <h2 class="text-base font-black text-[var(--c-text-1)] tracking-tight leading-none mb-1">ระบบแจกจ่ายงาน</h2>
                <p class="text-[10px] font-bold text-[var(--c-primary)] uppercase tracking-widest leading-none">Smart Dispatch</p>
            </div>
        </div>

        <div class="action-buttons flex items-center gap-2 flex-1 justify-end">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-[var(--c-surface-2)] hover:bg-[var(--c-border)] text-[var(--c-text-2)] px-3 py-2 rounded-lg text-xs font-bold transition-all border border-[var(--c-border)]">
                <i data-lucide="download" class="w-4 h-4 inline-block mr-1"></i> นำเข้า
            </button>
            <button id="exportExcelBtn" class="bg-[var(--c-info-bg)] text-[var(--c-info)] hover:opacity-80 px-3 py-2 rounded-lg text-xs font-bold transition-all border border-[var(--c-info-bg)]">
                <i data-lucide="bar-chart-2" class="w-4 h-4 inline-block mr-1"></i> ส่งออก
            </button>
            <div class="w-px h-6 bg-[var(--c-border)] mx-1 hidden md:block"></div>
            <button id="dispatchModalBtn" class="btn-primary !px-4 !py-2 text-xs">
                <i data-lucide="bot" class="w-4 h-4 inline-block mr-1"></i> จ่ายงานออโต้
            </button>
            <button id="optimizeRouteBtn" class="bg-[var(--c-success)] hover:opacity-80 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm transition-all border border-transparent">
                <i data-lucide="map-pin" class="w-4 h-4 inline-block mr-1"></i> เรียงคิว
            </button>
            <div class="w-px h-6 bg-[var(--c-border)] mx-1 hidden md:block"></div>
            <button id="clearAssignmentsBtn" class="bg-[var(--c-warning-bg)] text-[var(--c-warning-text)] border border-[var(--c-warning-bg)] hover:opacity-80 px-3 py-2 rounded-lg text-xs font-bold transition-all">
                <i data-lucide="refresh-cw" class="w-4 h-4 inline-block mr-1"></i> ล้าง
            </button>
            <button id="deleteAllJobsBtn" class="bg-[var(--c-danger-bg)] text-[var(--c-danger-text)] border border-[var(--c-danger-bg)] hover:opacity-80 px-3 py-2 rounded-lg text-xs font-bold transition-all">
                <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> ลบทั้งหมด
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="card !p-0 flex flex-col flex-1 overflow-hidden relative">
        
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

            <div class="flex items-center gap-2 flex-wrap">
                <input type="date" id="dateFilter" class="text-xs font-bold input !py-1.5">
                <button onclick="document.getElementById('dateFilter').value=''; renderUI();" class="bg-[var(--c-surface)] hover:bg-[var(--c-surface-3)] border border-[var(--c-border)] text-[var(--c-text-2)] px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                    ทุกวัน
                </button>
                <select id="limitFilter" class="text-xs font-bold input !py-1.5">
                    <option value="20">20 แถว</option>
                    <option value="50">50 แถว</option>
                    <option value="100">100 แถว</option>
                    <option value="all">ทั้งหมด</option>
                </select>

                <?php if ($isAdmin): ?>
                <div class="w-px h-5 bg-[var(--c-border)] mx-1"></div>
                <select id="teamFilter" class="text-xs font-bold input !py-1.5 !bg-[var(--c-primary-faint)] !text-[var(--c-primary)] !border-[var(--c-primary-faint)]">
                    <option value="all">ทุกทีม</option>
                    <option value="unassigned">ยังไม่จ่าย</option>
                </select>
                <div class="flex items-center bg-[var(--c-surface)] rounded-lg border border-[var(--c-border)] overflow-hidden h-[34px]">
                    <input type="text" id="newTeamName" placeholder="ชื่อทีมใหม่" class="border-0 px-3 h-full text-xs font-bold focus:ring-0 w-24">       
                    <button id="addTeamBtn" class="bg-[var(--c-primary-faint)] hover:bg-[var(--c-primary)] hover:text-white text-[var(--c-primary)] h-full px-3 font-black border-l border-[var(--c-border)] transition-colors">+</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div id="teamListContainer" class="flex flex-wrap gap-2 px-4 pb-3 pt-2 border-b border-[var(--c-border)] bg-[var(--c-surface-3)] shrink-0"></div>
        <?php endif; ?>

        <div id="selectionActions" class="px-4 py-2 bg-[var(--c-danger-bg)] border-b border-[var(--c-danger-bg)] flex items-center justify-between hidden transition-all shrink-0">
            <p class="text-[11px] font-bold text-[var(--c-danger-text)]">เลือกอยู่ <span id="selectedCount" class="font-black text-sm ml-1">0</span> งาน</p>
            <button id="bulkDeleteBtn" class="text-[10px] font-black bg-[var(--c-danger)] hover:opacity-80 text-white px-4 py-1.5 rounded-lg uppercase transition-all shadow-sm">ลบที่เลือก</button>
        </div>

        <div class="flex-1 overflow-hidden relative bg-[var(--c-surface)]">
            <div class="table-container absolute inset-0 w-full h-full overflow-auto">
                <table class="job-table">
                    <thead class="bg-[var(--c-surface-2)]">
                        <tr>
                            <th class="w-8 text-center !bg-[var(--c-surface-2)]">#</th>
                            <th class="w-12 text-center !bg-[var(--c-surface-2)]">คิว</th>
                            <th class="w-24 !bg-[var(--c-surface-2)]">รหัสงาน</th>
                            <th class="w-48 !bg-[var(--c-surface-2)]">ชื่อลูกค้า</th>
                            <th class="w-24 !bg-[var(--c-surface-2)]">เบอร์โทร</th>
                            <th class="min-w-[180px] !bg-[var(--c-surface-2)]">สถานที่</th>
                            <th class="w-20 !bg-[var(--c-surface-2)]">วันที่</th>
                            <th class="w-28 text-right pr-4 !bg-[var(--c-surface-2)]">ทีมช่าง</th>
                        </tr>
                    </thead>
                    <tbody id="jobTableBody" class="text-xs text-[var(--c-text-2)] divide-y divide-[var(--c-border)]">
                        </tbody>
                </table>
            </div>
        </div>
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
<script src="assets/js/dispatch.js"></script>