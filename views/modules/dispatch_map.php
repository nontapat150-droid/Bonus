<?php
// views/modules/dispatch_map.php
if (!defined('PDO::ATTR_ERRMODE')) exit('Direct access not permitted.');

$isAdmin = hasRole(['admin', 'super_admin']);
?>

<div class="h-[calc(100vh-100px)] flex flex-col space-y-4">
    
    <!-- Top Controls (Admin Only) -->
    <?php if ($isAdmin): ?>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between z-10 relative">
        <div class="flex items-center space-x-4">
            <h2 class="text-xl font-bold text-gray-800"><span class="mr-2">🗺️</span> Smart Dispatch</h2>
            
            <!-- Excel Import -->
            <input type="file" id="jobExcelFile" accept=".xlsx, .xls" class="hidden">
            <button onclick="document.getElementById('jobExcelFile').click()" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium border border-indigo-200 transition">
                📥 นำเข้าไฟล์งาน (Excel)
            </button>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Quota Config -->
            <div class="flex items-center space-x-2 border border-gray-200 rounded-lg p-1 bg-gray-50">
                <input type="text" id="teamQuotaName" placeholder="ชื่อทีม (เช่น tech1)" class="w-32 px-2 py-1 text-sm rounded border-gray-300">
                <input type="number" id="teamQuotaLimit" placeholder="โควตา" class="w-20 px-2 py-1 text-sm rounded border-gray-300" min="1">
                <button id="addQuotaBtn" class="bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 px-3 py-1 rounded shadow-sm text-sm font-bold">+</button>
            </div>
            
            <button id="autoDispatchBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md transition">
                🤖 จ่ายงานอัตโนมัติ
            </button>
            
            <button id="optimizeRouteBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md transition">
                📍 เรียงคิว (Optimize)
            </button>
        </div>
    </div>
    
    <!-- Quota Display Area -->
    <div id="quotaArea" class="hidden bg-white p-3 rounded-lg shadow-sm border border-gray-100 flex flex-wrap gap-2"></div>
    <?php else: ?>
    <!-- Technician Header -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800"><span class="mr-2">🗺️</span> คิวงานของฉัน</h2>
        <a id="techMapLink" href="#" target="_blank" class="hidden bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md transition">
            🚀 นำทางทั้งหมด (Google Maps)
        </a>
    </div>
    <?php endif; ?>

    <!-- Main Content: Map & List -->
    <div class="flex flex-col lg:flex-row flex-1 gap-4 min-h-0">
        
        <!-- Left: Job List -->
        <div class="w-full lg:w-1/3 bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col overflow-hidden h-full">
            <div class="p-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-700">รายการงาน <span id="jobCountBadge" class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full text-xs">0</span></h3>
                <?php if ($isAdmin): ?>
                <select id="teamFilter" class="text-sm border-gray-300 rounded focus:ring-indigo-500 py-1">
                    <option value="all">ทุกทีม</option>
                    <option value="unassigned">ยังไม่จ่ายงาน</option>
                </select>
                <?php endif; ?>
            </div>
            
            <div id="jobListContainer" class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50">
                <!-- Job Cards injected via JS -->
            </div>
        </div>

        <!-- Right: Map -->
        <div class="w-full lg:w-2/3 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden relative h-64 lg:h-auto">
            <div id="map" class="w-full h-full z-0"></div>
            <!-- Loading Overlay -->
            <div id="mapLoader" class="absolute inset-0 bg-white bg-opacity-70 flex flex-col items-center justify-center z-10 hidden">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                <p class="mt-2 text-indigo-800 font-bold">กำลังประมวลผล...</p>
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
