<?php
// views/modules/checkin.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="max-w-md mx-auto bg-white rounded-[2rem] shadow-xl overflow-hidden animate__animated animate__fadeIn">
    <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-6 py-8 text-center text-white">
        <div class="text-5xl mb-3">📸</div>
        <h2 class="text-3xl font-black tracking-tight">เช็คอินเข้างาน</h2>
        <p class="text-indigo-100 text-sm mt-1">อัปโหลดรูปภาพเพื่อบันทึกเวลาปัจจุบัน</p>
    </div>

    <div class="p-8 text-center space-y-6">
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">เวลาปัจจุบัน</p>
            <h3 id="currentTime" class="text-4xl font-black text-indigo-600 tracking-tighter">00:00:00</h3>
        </div>

        <form id="checkinForm" enctype="multipart/form-data">
            <label for="checkin_image" class="block w-full h-48 border-2 border-indigo-200 border-dashed rounded-[1.5rem] cursor-pointer bg-indigo-50/50 hover:bg-indigo-50 transition-colors relative overflow-hidden group">
                <div id="uploadPrompt" class="absolute inset-0 flex flex-col items-center justify-center">
                    <svg class="w-10 h-10 text-indigo-400 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <p class="text-sm font-bold text-indigo-600">แตะเพื่อถ่ายรูป / เลือกรูปภาพ</p>
                </div>
                <img id="imagePreview" class="absolute inset-0 w-full h-full object-cover hidden" src="" alt="Preview">
                <input id="checkin_image" name="checkin_image" type="file" class="hidden" accept="image/*" capture="environment" required />
            </label>

            <button type="submit" id="submitBtn" class="mt-6 w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black shadow-lg shadow-indigo-200 transform transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                ✅ ยืนยันการเช็คอิน
            </button>
        </form>
    </div>
</div>

<?php if (hasRole(['super_admin', 'admin'])): ?>
<div class="max-w-md mx-auto mt-6 bg-white rounded-[2rem] shadow-xl overflow-hidden animate__animated animate__fadeIn">
    <div class="p-6 border-t border-slate-100 bg-slate-50">
        <h3 class="text-lg font-black text-slate-800 mb-4 flex items-center">
            <span class="mr-2">👑</span> สำหรับผู้ดูแลระบบ
        </h3>
        <div class="flex flex-col space-y-3">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">เลือกวันที่ต้องการส่งออก</label>
            <input type="date" id="exportCheckinDate" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700" value="<?php echo date('Y-m-d'); ?>">
            <button id="exportCheckinBtn" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3.5 rounded-xl font-black shadow-md shadow-emerald-200 transition-transform active:scale-95 flex items-center justify-center">
                <span class="mr-2">📊</span> ส่งออกข้อมูลลงเวลา (Excel)
            </button>
        </div>
    </div>
</div>

<!-- Load SheetJS for Excel Export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<?php endif; ?>

<script src="assets/js/checkin.js?v=<?php echo time(); ?>"></script>