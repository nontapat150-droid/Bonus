<?php
// views/modules/checkin.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
$isSuperAdmin = hasRole('super_admin');
$canMaCheckin = hasRole('ma_technician');
$showRegularCheckin = !isMaTechnicianOnly();
$showMaCheckin = $canMaCheckin || $isAdmin;
?>

<script>
    window.USER_ROLE = '<?php echo $_SESSION['role'] ?? 'guest'; ?>';
    window.USER_ROLES = <?php echo json_encode(getUserRoles()); ?>;
    window.SHOW_REGULAR = <?php echo $showRegularCheckin ? 'true' : 'false'; ?>;
    window.SHOW_MA = <?php echo $showMaCheckin ? 'true' : 'false'; ?>;
    window.IS_SUPER_ADMIN = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<?php if ($showRegularCheckin && $showMaCheckin): ?>
<div class="flex gap-2 mb-4 p-1.5 bg-white rounded-2xl shadow-sm border border-slate-100 max-w-md">
    <button type="button" id="tabRegular" onclick="switchCheckinTab('regular')" class="checkin-tab flex-1 py-2.5 rounded-xl text-sm font-black transition-all bg-indigo-600 text-white">เช็คอินทั่วไป</button>
    <button type="button" id="tabMa" onclick="switchCheckinTab('ma')" class="checkin-tab flex-1 py-2.5 rounded-xl text-sm font-black transition-all text-violet-600 hover:bg-violet-50">เช็คอิน MA</button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate__animated animate__fadeIn">
    <!-- แผงเช็คอินทั่วไป -->
    <div id="panelRegular" class="lg:col-span-5 bg-white rounded-[2rem] shadow-xl overflow-hidden flex flex-col <?php echo !$showRegularCheckin ? 'hidden' : ''; ?>">
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
                        <p class="text-sm font-bold text-indigo-600">แตะเพื่อถ่ายรูป</p>
                    </div>
                    <img id="imagePreview" class="absolute inset-0 w-full h-full object-cover hidden" src="" alt="Preview">
                    <input id="checkin_image" name="checkin_image" type="file" class="hidden" accept="image/*" capture="environment" required />
                </label>
                
                <!-- เพิ่ม Grid แบ่ง 2 ปุ่ม: เข้างาน และ เลิกงาน -->
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button type="submit" id="submitBtn" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black shadow-lg shadow-indigo-200 transform transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        ✅ เข้างาน
                    </button>
                    <button type="button" id="checkoutBtn" class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl font-black shadow-lg shadow-rose-200 transform transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        🏁 เลิกงาน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- แผงเช็คอิน MA -->
    <div id="panelMa" class="lg:col-span-5 bg-white rounded-[2rem] shadow-xl overflow-hidden flex flex-col <?php echo (!$showMaCheckin || ($showRegularCheckin && $showMaCheckin)) ? 'hidden' : ''; ?>">
        <div class="bg-gradient-to-br from-violet-600 to-purple-700 px-6 py-6 text-center text-white">
            <h2 class="text-2xl font-black tracking-tight flex items-center justify-center gap-2">
                <span class="text-3xl">🔧</span> เช็คอิน MA
            </h2>
            <p class="text-violet-200 text-xs font-bold mt-2">สำหรับช่าง MA — มาหลังเวลาที่กำหนดจะบันทึกว่าสายทันที</p>
        </div>
        <div class="p-6 text-center space-y-6 flex-1 flex flex-col justify-center">
            <div class="bg-violet-50 p-4 rounded-2xl border border-violet-100">
                <p class="text-xs font-bold text-violet-400 uppercase tracking-widest mb-1">เวลาปัจจุบัน</p>
                <h3 id="maCurrentTime" class="text-4xl font-black text-violet-600 tracking-tighter">00:00:00</h3>
                <p class="text-[11px] font-bold text-violet-500 mt-2">เวลาเข้างาน MA: ไม่เกิน <span id="maDeadlineDisplay" class="font-black">--:--</span> น.</p>
            </div>
            <?php if ($canMaCheckin): ?>
                <form id="maCheckinForm" enctype="multipart/form-data">
                <label for="ma_checkin_image" class="block w-full h-40 border-2 border-violet-200 border-dashed rounded-[1.5rem] cursor-pointer bg-violet-50/50 hover:bg-violet-50 transition-colors relative overflow-hidden group">
                    <div id="maUploadPrompt" class="absolute inset-0 flex flex-col items-center justify-center">
                        <svg class="w-10 h-10 text-violet-400 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <p class="text-sm font-bold text-violet-600">แตะเพื่อถ่ายรูป MA</p>
                    </div>
                    <img id="maImagePreview" class="absolute inset-0 w-full h-full object-cover hidden" src="" alt="Preview">
                    <input id="ma_checkin_image" name="checkin_image" type="file" class="hidden" accept="image/*" capture="environment" required />
                </label>
                
                <!-- เพิ่ม Grid แบ่ง 2 ปุ่ม: เข้างาน MA และ เลิกงาน MA -->
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button type="submit" id="maSubmitBtn" class="w-full py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-2xl font-black shadow-lg shadow-violet-200 transform transition-all active:scale-95 disabled:opacity-50">
                        ✅ เข้างาน MA
                    </button>
                    <button type="button" id="maCheckoutBtn" class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl font-black shadow-lg shadow-rose-200 transform transition-all active:scale-95 disabled:opacity-50">
                        🏁 เลิกงาน MA
                    </button>
                </div>
              </form>
            <?php else: ?>
            <div class="py-8 text-slate-400 text-sm font-bold">บัญชีนี้ไม่มีบทบาทช่าง MA</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-7 flex flex-col gap-6">
        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                📊 สรุปการเข้างาน <span id="dashLabel" class="ml-2 text-sm text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">-</span>
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

        <?php if ($isSuperAdmin): ?>
        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-violet-100 mb-4">
            <div class="mb-4 border-b border-violet-100 pb-4">
                <h3 class="font-bold text-violet-800 flex items-center"><span class="mr-2">🔧</span> ตั้งค่าเวลาเช็คอิน MA</h3>
                <p class="text-xs text-violet-500 mt-1">เฉพาะผู้ดูแลระบบ — ช่าง MA ที่เช็คอินหลังเวลานี้จะถูกบันทึกว่า <strong>มาสาย</strong> ทันที</p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex-1">
                    <label class="block text-[10px] font-black text-violet-400 uppercase mb-1">เวลาเข้างาน MA (ไม่เกิน)</label>
                    <input type="time" id="maLateTimeInput" class="w-full px-4 py-2 border border-violet-200 rounded-xl focus:ring-2 focus:ring-violet-500 font-bold text-violet-800">
                </div>
                <button onclick="saveMaSettings()" class="bg-violet-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-violet-700 transition-colors shadow-md sm:mt-5 min-w-[120px]">บันทึก</button>
            </div>
        </div>
        <?php elseif ($showMaCheckin): ?>
        <div class="bg-violet-50 rounded-2xl p-4 border border-violet-100 mb-4 text-center">
            <p class="text-xs text-violet-600 font-bold">เวลาเข้างาน MA: ไม่เกิน <span id="maDeadlineDisplayRo" class="font-black text-violet-800">--:--</span> น. (ตั้งโดยผู้ดูแลระบบ)</p>
        </div>
        <?php endif; ?>

        <?php if($isAdmin): ?>
        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50 mb-4">
            <div class="mb-4 border-b border-gray-100 pb-4">
                <h3 class="font-bold text-gray-800 flex items-center"><span class="mr-2">⚙️</span> ตั้งค่าระบบเวลาเข้างาน</h3>
                <p class="text-xs text-gray-500 mt-1">กำหนดเวลาที่ถือว่า "มาสาย" โดยสามารถเลือกหลายบทบาทพร้อมกันได้</p>
            </div>
            
            <!-- กลุ่มที่ 1 -->
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
                <div class="flex flex-wrap gap-2 flex-1" id="roleGroup1">
                    <label class="cursor-pointer">
                        <input type="checkbox" value="admin" class="peer hidden role-cb-1">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 text-sm font-bold text-gray-600 transition-all select-none">แอดมิน</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="super_admin" class="peer hidden role-cb-1">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 text-sm font-bold text-gray-600 transition-all select-none">ซุปเปอร์แอดมิน</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="technician" class="peer hidden role-cb-1">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 text-sm font-bold text-gray-600 transition-all select-none">ช่าง</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="sales" class="peer hidden role-cb-1">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 text-sm font-bold text-gray-600 transition-all select-none">เซลส์</span>
                    </label>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <input type="time" id="lateTimeInput1" class="flex-1 md:w-auto px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold text-gray-700">
                    <button onclick="saveSettingsMulti(1)" class="bg-slate-800 text-white px-6 py-2 rounded-xl font-bold hover:bg-slate-900 transition-colors shadow-md min-w-[100px]">บันทึก</button>
                </div>
            </div>

            <!-- กลุ่มที่ 2 -->
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2 flex-1" id="roleGroup2">
                    <label class="cursor-pointer">
                        <input type="checkbox" value="admin" class="peer hidden role-cb-2">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 text-sm font-bold text-gray-600 transition-all select-none">แอดมิน</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="super_admin" class="peer hidden role-cb-2">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 text-sm font-bold text-gray-600 transition-all select-none">ซุปเปอร์แอดมิน</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="technician" class="peer hidden role-cb-2">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 text-sm font-bold text-gray-600 transition-all select-none">ช่าง</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="checkbox" value="sales" class="peer hidden role-cb-2">
                        <span class="px-3 py-1.5 rounded-xl border border-gray-200 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 text-sm font-bold text-gray-600 transition-all select-none">เซลส์</span>
                    </label>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <input type="time" id="lateTimeInput2" class="flex-1 md:w-auto px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 font-bold text-gray-700">
                    <button onclick="saveSettingsMulti(2)" class="bg-emerald-700 text-white px-6 py-2 rounded-xl font-bold hover:bg-emerald-800 transition-colors shadow-md min-w-[100px]">บันทึก</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-xl p-6 border border-gray-50 flex-1 flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                🕒 <span id="historyTitle">ประวัติเช็คอิน</span>
            </h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="date" id="filterDate" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <span class="text-sm text-gray-400 hidden md:inline">หรือ</span>
                    <input type="month" id="filterMonth" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
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

<div id="editCheckinModal" class="hidden fixed inset-0 bg-slate-900/60 z-50 flex justify-center items-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-[95%] md:max-w-sm overflow-hidden shadow-2xl animate__animated animate__zoomIn">
        <div class="bg-indigo-600 p-4 flex justify-between items-center text-white">
            <h3 class="font-bold">✏️ แก้ไขรูปภาพเช็คอิน</h3>
            <button onclick="closeEditCheckinModal()" class="text-white hover:text-rose-300 font-black text-xl">&times;</button>
        </div>
        <div class="p-6">
            <input type="hidden" id="edit_checkin_id">
            <div class="mb-2">
                <label class="block text-sm font-bold text-gray-700 mb-2">อัปโหลดรูปภาพใหม่</label>
                <div id="editImagePreviewWrapper" class="relative">
                    <img id="editImagePreview" class="w-full h-48 object-cover rounded-xl border border-slate-200 hidden" src="" alt="Preview">
                    <div id="editImagePlaceholder" class="w-full h-48 rounded-xl border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-sm text-slate-500">
                        ไม่มีรูปภาพแนบ หรือเลือกไฟล์ใหม่เพื่อแทนที่
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" onclick="document.getElementById('edit_checkin_image').click()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all">เลือกไฟล์รูปใหม่</button>
                    <button type="button" id="deleteImageBtn" onclick="deleteCheckinImage()" class="px-4 py-2 bg-rose-500 text-white rounded-xl font-bold hover:bg-rose-600 transition-all hidden">ลบรูปภาพ</button>
                </div>
                <input type="file" id="edit_checkin_image" name="checkin_image" accept="image/*" class="hidden">
            </div>
        </div>
        <div class="p-4 bg-slate-50 border-t flex justify-end gap-2">
            <button onclick="closeEditCheckinModal()" class="px-4 py-2 bg-white text-slate-600 rounded-xl font-bold border border-slate-200">ยกเลิก</button>
            <button onclick="saveEditCheckin()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold shadow-md hover:bg-indigo-700">อัปเดตรูปภาพ</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js?v=<?= filemtime('assets/js/common.js') ?>"></script>
<script src="assets/js/checkin.js?v=<?= filemtime('assets/js/checkin.js') ?>"></script>
