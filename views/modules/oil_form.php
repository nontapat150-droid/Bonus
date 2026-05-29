<?php
// views/modules/oil_form.php
// Ensure this file is included within index.php
if (!defined('PDO::ATTR_ERRMODE')) {
    exit('เข้าถึงโดยตรงไม่ได้');
}

// ตรวจสอบสิทธิ์ว่าเป็น Admin หรือ Super Admin หรือไม่
$isAdmin = hasRole(['admin', 'super_admin']);
?>

<div class="max-w-2xl mx-auto card overflow-hidden">
    <div class="bg-blue-600 px-6 py-4">
        <h2 class="text-2xl font-bold text-white flex items-center">
            <span class="mr-2 text-3xl"><i data-lucide="fuel" class="w-5 h-5 inline-block"></i></span> บันทึกการใช้น้ำมัน
        </h2>
        <p class="text-blue-100 text-sm mt-1">กรอกข้อมูลการใช้รถและอัปเดตหลักฐาน (สูงสุด 10 รูป)</p>
    </div>

    <div class="p-6">
        <form id="oilForm" enctype="multipart/form-data" class="space-y-6">

            <div id="alertBox" class="hidden rounded-lg p-4 mb-4 text-sm"></div>

            <!-- Profile Info -->
            <div class="bg-gradient-to-r from-indigo-50 to-violet-50 rounded-2xl p-5 border border-indigo-100">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-3"><i data-lucide="user" class="w-4 h-4 inline-block"></i> ผู้บันทึกข้อมูล</p>
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center text-indigo-600 font-black text-lg" id="userAvatar">
                        <?= mb_substr($_SESSION['full_name'] ?? 'U', 0, 1) ?>
                    </div>
                    <div>
                        <p class="font-black text-slate-800 text-lg" id="displayUserName"><?= htmlspecialchars($_SESSION['full_name'] ?? '-') ?></p>
                        <p class="text-xs text-slate-400" id="displayUserTeam">กำลังโหลดข้อมูลทีม...</p>
                    </div>
                </div>
            </div>

            <!-- Group 1: Vehicle & Time -->
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-5">
                <h3 class="font-bold text-slate-700 flex items-center mb-2"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 1</span> ข้อมูลยานพาหนะและเวลา</h3>
                
                <?php if ($isAdmin): ?>
                <div class="bg-amber-50 p-4 rounded-xl border border-amber-200">
                    <label class="block text-sm font-bold text-amber-800 mb-1">📅 วันที่/เวลา เติมน้ำมัน (สำหรับแอดมินบันทึกย้อนหลัง)</label>
                    <div class="relative mt-2">
                        <input type="datetime-local" id="date_recorded" name="date_recorded"
                            class="input w-full pl-10">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-amber-500"><i data-lucide="clock" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                    <p class="text-xs text-amber-600 mt-2 font-medium">* หากปล่อยว่างไว้ ระบบจะใช้ <span class="font-bold underline">วันและเวลาปัจจุบัน</span> โดยอัตโนมัติ</p>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ทีม / ป้ายทะเบียนรถ <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="license_plate" name="license_plate" required class="input w-full pl-10 cursor-pointer">
                            <option value="">-- กำลังโหลดทีม/ป้ายทะเบียน... --</option>
                        </select>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400"><i data-lucide="car" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">* เลือกทีมหรือป้ายทะเบียนรถที่คุณใช้งานอยู่ เพื่อให้ระบบอ้างอิงเคสงานและทีมได้ถูกต้อง</p>
                    <div id="teamJobCount" class="hidden mt-2 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2 text-sm">
                        <span class="font-bold text-emerald-700"><i data-lucide="clipboard" class="w-5 h-5 inline-block"></i> เคสงานของทีมนี้:</span>
                        <span id="jobCountValue" class="font-black text-emerald-600 ml-1">0</span> <span class="text-emerald-600">งาน</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลขไมล์ปัจจุบัน <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" id="mileage" name="mileage" required min="0" class="input w-full pl-10" placeholder="ตัวอย่าง: 12500">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400"><i data-lucide="gauge" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group 2: Fuel Details -->
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                <h3 class="font-bold text-slate-700 flex items-center mb-4"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 2</span> ข้อมูลบิลน้ำมัน</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนลิตร <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" id="liters" name="liters" required min="0.01" step="0.01" class="input w-full pl-10 text-lg font-bold text-blue-700" placeholder="0.00">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                                <span class="text-blue-500"><i data-lucide="droplet" class="w-5 h-5 inline-block"></i></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ราคาต่อลิตร <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" id="price_per_liter" name="price_per_liter" required min="0.01" step="0.01" class="input w-full pl-10 text-lg font-bold text-slate-700" placeholder="0.00">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                                <span class="text-slate-400 font-bold">฿</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ราคารวมทั้งสิ้น (บาท)</label>
                        <div class="relative">
                            <input type="number" id="total_price" name="total_price" readonly class="input w-full pl-10 bg-gray-100 text-xl font-black text-rose-600 cursor-not-allowed border-dashed" placeholder="0.00">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                                <span class="text-rose-500"><i data-lucide="calculator" class="w-5 h-5 inline-block"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group 3: Upload Evidence -->
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                <h3 class="font-bold text-slate-700 flex items-center mb-4"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs mr-2 uppercase tracking-widest">Step 3</span> อัปโหลดรูปภาพหลักฐาน</h3>
                <label class="block text-sm font-medium text-gray-500 mb-2">ถ่ายรูปหน้าปัดไมล์, บิลน้ำมัน หรือป้ายทะเบียน (อัปโหลดได้สูงสุด 10 รูป) <span class="text-red-500">*</span></label>   
                <div class="flex items-center justify-center w-full">
                    <label for="oil_images" class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-indigo-50 transition-colors group">      
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i data-lucide="upload-cloud" class="w-8 h-8 mb-3 text-indigo-400 group-hover:scale-110 transition-transform"></i>
                            <p class="mb-1 text-sm text-indigo-600 font-bold">คลิกเพื่อถ่ายรูปหรือเลือกไฟล์</p>
                            <p class="text-[10px] text-slate-400 uppercase tracking-widest">PNG, JPG, JPEG</p>
                        </div>
                        <input id="oil_images" name="oil_images[]" type="file" class="hidden" multiple accept="image/*" capture="environment" required />
                    </label>
                </div>
                <div id="imagePreviewContainer" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-4"></div>
                <p id="imageCount" class="text-xs font-bold text-slate-500 mt-2 text-right">เลือกแล้ว: 0/10 รูป</p>
            </div>

            <div class="pt-2">
                <button type="submit" id="submitBtn" class="btn-primary w-full py-4 text-lg">
                    <i data-lucide="save" class="w-5 h-5 inline-block"></i> บันทึกข้อมูลและส่งรายงาน
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/oil.js"></script>