<?php
// views/modules/checkin.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>

<script>
    window.USER_ROLE = '<?php echo $_SESSION['role'] ?? 'guest'; ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate__animated animate__fadeIn">
    <div class="lg:col-span-5 card overflow-hidden flex flex-col z-[90]">
        <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-6 py-6 text-center text-white">
            <h2 class="text-2xl font-black tracking-tight flex items-center justify-center gap-2">
                <span class="text-3xl"><i data-lucide="camera" class="w-5 h-5 inline-block"></i></span> เช็คอินเข้างาน
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
                <button type="submit" id="submitBtn" class="mt-4 w-full py-3 btn-primary">
                    <i data-lucide="check-circle" class="w-5 h-5 inline-block"></i> ยืนยันการเช็คอิน
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 flex flex-col gap-6">
        <div class="card z-[90]">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i data-lucide="bar-chart-2" class="w-5 h-5 inline-block"></i> สรุปการเข้างาน <span id="dashLabel" class="ml-2 text-sm text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">-</span>
            </h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-blue-600 font-bold mb-1">วันทั้งหมด</p>
                    <p class="text-3xl md:text-4xl font-black text-blue-800" id="dashTotal">0</p>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-emerald-600 font-bold mb-1">ตรงเวลา</p>
                    <p class="text-3xl md:text-4xl font-black text-emerald-800" id="dashOntime">0</p>
                </div>
                <div class="bg-orange-50 border border-orange-100 p-4 rounded-2xl text-center">
                    <p class="text-xs text-orange-600 font-bold mb-1">มาสาย</p>
                    <p class="text-3xl md:text-4xl font-black text-orange-800" id="dashLate">0</p>
                </div>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <div class="card flex items-center justify-between gap-4 flex-wrap z-[90]">
            <div>
                <h3 class="font-bold text-gray-800 flex items-center"><span class="mr-2"><i data-lucide="settings" class="w-5 h-5 inline-block"></i></span> ตั้งค่าระบบ (แอดมิน)</h3>
                <p class="text-xs text-gray-500 mt-1">กำหนดเวลาที่ถือว่า "มาสาย"</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <input type="time" id="lateTimeInput" class="flex-1 sm:w-auto input">
                <button onclick="saveSettings()" class="bg-slate-800 text-white px-6 py-2 rounded-xl font-bold hover:bg-slate-900 transition-colors">บันทึก</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="card flex-1 flex flex-col z-[90]">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                <h3 class="font-bold text-gray-800"><i data-lucide="clock" class="w-5 h-5 inline-block"></i> ประวัติเช็คอิน</h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="date" id="filterDate" class="px-3 py-1.5 input">
                    <span class="text-sm text-gray-400 hidden md:inline">หรือ</span>
                    <input type="month" id="filterMonth" class="px-3 py-1.5 input">
                    <button onclick="loadCheckinHistory()" class="bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-indigo-100">ค้นหา</button>
                    <?php if($isAdmin): ?>
                    <button onclick="exportCheckin()" class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm hover:bg-emerald-100">Excel</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="w-full flex-1">
                <table class="w-full text-sm text-left block md:table">
                    <thead class="hidden md:table-header-group text-xs text-slate-500 uppercase bg-slate-50 rounded-lg">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">วันที่ - เวลา</th>
                            <th class="px-4 py-3 text-center">รูปถ่าย</th>
                            <th class="px-4 py-3">พนักงาน</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <th class="px-4 py-3 text-center rounded-r-lg">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="block md:table-row-group divide-y divide-gray-100">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="editCheckinModal" class="hidden fixed inset-0 bg-slate-900/60 z-[80] flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-[95%] md:max-w-sm overflow-hidden shadow-2xl animate__animated animate__zoomIn z-[90]">
        <div class="bg-indigo-600 p-4 flex justify-between items-center text-white">
            <h3 class="font-bold"><i data-lucide="edit-2" class="w-5 h-5 inline-block"></i> แก้ไขเวลาเช็คอิน</h3>
            <button onclick="closeEditCheckinModal()" class="text-white hover:text-rose-300 font-black text-xl">&times;</button>
        </div>
        <div class="p-6">
            <input type="hidden" id="edit_checkin_id">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-2">รูปภาพหลักฐาน</label>
                <div id="editImagePreviewWrapper" class="relative">
                    <img id="editImagePreview" class="w-full h-48 object-cover rounded-xl border border-slate-200 hidden" src="" alt="Preview">
                    <div id="editImagePlaceholder" class="w-full h-48 rounded-xl border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-sm text-slate-500">
                        ไม่มีรูปภาพแนบ หรือเลือกไฟล์ใหม่เพื่อแทนที่
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" onclick="document.getElementById('edit_checkin_image').click()" class="btn-primary px-4 py-2">เลือกไฟล์รูปใหม่</button>
                    <button type="button" id="deleteImageBtn" onclick="deleteCheckinImage()" class="px-4 py-2 bg-rose-500 text-white rounded-xl font-bold hover:bg-rose-600 transition-all hidden">ลบรูปภาพ</button>
                </div>
                <input type="file" id="edit_checkin_image" name="checkin_image" accept="image/*" class="hidden">
            </div>
            <label class="block text-sm font-bold text-gray-700 mb-2">เวลาเข้างานที่ต้องการแก้</label>
            <input type="datetime-local" id="edit_checkin_time" class="w-full input">
            <p class="text-xs text-slate-400 mt-2">* ระบบจะบันทึกเวลาใหม่ และคำนวณสถานะสาย/ตรงเวลา ใหม่อัตโนมัติ</p>
        </div>
        <div class="p-4 bg-slate-50 border-t flex justify-end gap-2">
            <button onclick="closeEditCheckinModal()" class="px-4 py-2 bg-white text-slate-600 rounded-xl font-bold border border-slate-200">ยกเลิก</button>
            <button onclick="saveEditCheckin()" class="px-4 py-2 btn-primary">บันทึกเวลา</button>
        </div>
    </div>
</div>

<script src="assets/js/checkin.js"></script>