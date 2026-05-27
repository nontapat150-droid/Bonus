<?php
// views/modules/checkin.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate__animated animate__fadeIn">
    <div class="lg:col-span-5 bg-white rounded-[2rem] shadow-xl overflow-hidden flex flex-col">
        <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-6 py-6 text-center text-white">
            <h2 class="text-2xl font-black tracking-tight flex items-center justify-center gap-2">
                <span class="text-3xl">📸</span> เช็คอินเข้างาน
            </h2>
        </div>
        <div class="p-6 text-center space-y-6 flex-1 flex flex-col justify-center">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">เวลาปัจจุบัน</p>
                <h3 id="currentTime" class="text-4xl font-black text-indigo-600 tracking-tighter">00:00:00</h3>
            </div>
            <form id="checkinForm" enctype="multipart/form-data">
                <label for="checkin_image" class="block w-full h-40 border-2 border-indigo-200 border-dashed rounded-[1.5rem] cursor-pointer bg-indigo-50/50 hover:bg-indigo-50 transition-colors relative overflow-hidden group">
                    <div id="uploadPrompt" class="absolute inset-0 flex flex-col items-center justify-center">
                        <svg class="w-10 h-10 text-indigo-400 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <p class="text-sm font-bold text-indigo-600">แตะเพื่อถ่ายรูปเช็คอิน</p>
                    </div>
                    <img id="imagePreview" class="absolute inset-0 w-full h-full object-cover hidden" src="" alt="Preview">
                    <input id="checkin_image" name="checkin_image" type="file" class="hidden" accept="image/*" capture="environment" required />
                </label>
                <button type="submit" id="submitBtn" class="mt-4 w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black shadow-lg shadow-indigo-200 transform transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    ✅ ยืนยันการเช็คอิน
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 flex flex-col gap-6">
        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                📊 สรุปการเข้างาน <span id="dashLabel" class="ml-2 text-sm text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">-</span>
            </h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-blue-600 font-bold mb-1">วันทำงานทั้งหมด</p>
                    <p class="text-3xl font-black text-blue-800" id="dashTotal">0</p>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-emerald-600 font-bold mb-1">มาตรงเวลา</p>
                    <p class="text-3xl font-black text-emerald-800" id="dashOntime">0</p>
                </div>
                <div class="bg-orange-50 border border-orange-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-orange-600 font-bold mb-1">มาสาย</p>
                    <p class="text-3xl font-black text-orange-800" id="dashLate">0</p>
                </div>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h3 class="font-bold text-gray-800 flex items-center"><span class="mr-2">⚙️</span> ตั้งค่าระบบ (แอดมิน)</h3>
                <p class="text-xs text-gray-500 mt-1">กำหนดเวลาที่ถือว่า "มาสาย"</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="time" id="lateTimeInput" class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-gray-700">
                <button onclick="saveSettings()" class="bg-slate-800 text-white px-6 py-2 rounded-xl font-bold hover:bg-slate-900 transition-colors">บันทึก</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50 flex-1 flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                <h3 class="font-bold text-gray-800">🕒 ประวัติเช็คอิน</h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="date" id="filterDate" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <span class="text-sm text-gray-400">หรือ</span>
                    <input type="month" id="filterMonth" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <button onclick="loadCheckinHistory()" class="bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-indigo-100">ค้นหา</button>
                    <?php if($isAdmin): ?>
                    <button onclick="exportCheckin()" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm hover:bg-emerald-600">Excel</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 rounded-lg">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">วันที่ - เวลา</th>
                            <th class="px-4 py-3 text-center">รูปถ่าย</th>
                            <th class="px-4 py-3">พนักงาน</th>
                            <th class="px-4 py-3 text-center rounded-r-lg">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-100">
                        <tr><td colspan="4" class="text-center py-8 text-gray-400">กำลังโหลด...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/checkin.js"></script>