<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

$isAdmin = hasRole(['admin', 'super_admin']);
?>

<div class="h-[calc(100vh-120px)] flex flex-col space-y-4">
    
    <!-- Top Controls (Admin Only) -->
    <?php if ($isAdmin): ?>
    <div class="bg-white/70 backdrop-blur-md p-5 rounded-[2rem] shadow-sm border border-white flex flex-wrap gap-4 items-center justify-between z-20 relative">
        <div class="flex items-center space-x-4">
            <div class="p-3 bg-indigo-100 text-indigo-600 rounded-2xl shadow-inner">🗺️</div>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">ระบบจัดส่งอัจฉริยะ</h2>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">จัดการทีมและจ่ายงาน</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-5 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center">
                📥 นำเข้า Excel
            </button>

            <button id="dispatchModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-2xl text-xs font-black shadow-lg shadow-indigo-100 transition-all flex items-center">
                🤖 จ่ายงานอัตโนมัติ
            </button>

            <button id="optimizeRouteBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-2xl text-xs font-black shadow-lg shadow-emerald-100 transition-all flex items-center">
                📍 เรียงคิวเส้นทาง
            </button>
        </div>
    </div>

    <!-- Team Management Area (Simplified for adding/listing teams) -->
    <div class="bg-white/50 backdrop-blur-sm p-4 rounded-[2rem] border border-white flex flex-col space-y-4">
        <div class="flex justify-between items-center px-4">
            <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest flex items-center">
                <span class="mr-2">👥</span> รายชื่อทีมทั้งหมด
            </h3>
            <div class="flex items-center space-x-2">
                <input type="text" id="newTeamName" placeholder="ชื่อทีมใหม่..." class="px-4 py-2 rounded-xl border-transparent focus:ring-2 focus:ring-indigo-500/20 text-xs font-bold shadow-sm">
                <button id="addTeamBtn" class="bg-indigo-600 text-white w-8 h-8 rounded-xl font-black flex items-center justify-center hover:bg-indigo-700 transition-all">+</button>
            </div>
        </div>
        
        <div id="teamListContainer" class="flex flex-wrap gap-3 px-2 min-h-[50px]">
            <!-- Team items -->
        </div>
    </div>

    <!-- Dispatch Distribution Modal -->
    <div id="dispatchModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4">
        <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-lg overflow-hidden animate__animated animate__zoomIn">
            <div class="p-8 bg-gradient-to-br from-indigo-600 to-violet-700 text-white">
                <h3 class="text-xl font-black italic tracking-tight">ตั้งค่าการจ่ายงานอัตโนมัติ</h3>
                <p class="text-indigo-100 text-xs mt-1 font-bold">ระบุจำนวนงานที่ต้องการให้แต่ละทีม (งานที่รอจ่าย: <span id="unassignedCount">0</span> งาน)</p>
            </div>
            
            <div class="p-8 max-h-[60vh] overflow-y-auto" id="dispatchTeamList">
                <!-- Teams for distribution will be injected here -->
            </div>

            <div class="p-8 bg-slate-50 flex space-x-3">
                <button onclick="closeDispatchModal()" class="flex-1 py-4 bg-white text-slate-500 rounded-2xl font-bold text-sm border border-slate-200">ยกเลิก</button>
                <button id="confirmDispatchBtn" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl font-black shadow-lg shadow-indigo-100">🚀 เริ่มจ่ายงานทันที</button>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Technician Header -->
    <div class="bg-white/70 backdrop-blur-md p-5 rounded-[2rem] shadow-sm border border-white flex justify-between items-center">    
        <div class="flex items-center space-x-4">
            <div class="p-3 bg-emerald-100 text-emerald-600 rounded-2xl shadow-inner">🗺️</div>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">รายการคิวงานของฉัน</h2>
        </div>
        <a id="techMapLink" href="#" target="_blank" class="hidden bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-2xl text-xs font-black shadow-lg transition-all">
            🚀 เริ่มนำทาง (Google Maps)
        </a>
    </div>
    <?php endif; ?>

    <!-- Main Content: Map & List -->
    <div class="flex flex-col lg:flex-row flex-1 gap-4 min-h-0">

        <!-- Left: Job List -->
        <div class="w-full lg:w-1/3 bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-50 flex flex-col overflow-hidden h-full">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                <h3 class="font-black text-slate-700 tracking-tight flex items-center">
                    รายการงาน 
                    <span id="jobCountBadge" class="ml-2 bg-indigo-100 text-indigo-600 px-3 py-0.5 rounded-full text-[10px] font-black">0</span>
                </h3>
                <?php if ($isAdmin): ?>
                <select id="teamFilter" class="text-[10px] font-black uppercase tracking-wider border-none bg-white rounded-xl shadow-sm focus:ring-0 py-1.5 pl-3 pr-8 cursor-pointer">     
                    <option value="all">📍 งานทั้งหมด</option>
                    <option value="unassigned">⏳ ยังไม่จ่ายงาน</option>
                </select>
                <?php endif; ?>
            </div>

            <div id="jobListContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/30">
                <!-- Job Cards injected via JS -->
            </div>
        </div>

        <!-- Right: Map -->
        <div class="w-full lg:w-2/3 bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-50 overflow-hidden relative min-h-[300px] lg:h-auto">
            <div id="map" class="w-full h-full z-0"></div>
            <!-- Loading Overlay -->
            <div id="mapLoader" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center z-10 hidden">
                <div class="loader-spinner mb-4 w-10 h-10 border-4"></div>
                <p class="text-indigo-800 font-black text-xs uppercase tracking-widest animate-pulse">กำลังประมวลผลระบบแผนที่...</p>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/dispatch.js"></script>