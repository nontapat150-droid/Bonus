<?php
// views/modules/start_day.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
?>

<div class="max-w-2xl mx-auto card overflow-hidden animate__animated animate__fadeIn">
    <div class="bg-emerald-600 px-6 py-4">
        <h2 class="text-2xl font-bold text-white flex items-center">
            <span class="mr-2 text-3xl"><i data-lucide="gauge" class="w-6 h-6 inline-block"></i></span> บันทึกค่าแรกเข้า (เริ่มต้นวัน)
        </h2>
        <p class="text-emerald-100 text-sm mt-1">บันทึกเลขไมล์หน้าปัดรถก่อนเริ่มงานในแต่ละวัน</p>
    </div>

    <div class="p-6">
        <form id="startDayForm" enctype="multipart/form-data" class="space-y-6">
            <!-- ส่งค่าไปบอก API ว่านี่คือค่าแรกเข้า -->
            <input type="hidden" name="record_type" value="initial">

            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-5">
                <!-- วันที่และเวลา -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <label class="block text-sm font-bold text-slate-700 mb-1">📅 วันที่และเวลาเริ่มต้นงาน <span class="text-red-500">*</span></label>
                    <div class="relative mt-2">
                        <input type="datetime-local" id="date_recorded" name="date_recorded" required class="input w-full pl-10 font-bold">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-emerald-500"><i data-lucide="clock" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                </div>

                <!-- ป้ายทะเบียน -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ป้ายทะเบียนรถที่ใช้ <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="license_plate" name="license_plate" required class="input w-full pl-10 cursor-pointer">
                            <option value="">-- กำลังโหลดป้ายทะเบียน... --</option>
                        </select>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400"><i data-lucide="car" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                </div>
                
                <!-- เลขไมล์ -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลขไมล์หน้าปัดรถ <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" id="mileage" name="mileage" required min="0" class="input w-full pl-10 text-lg font-bold text-emerald-700" placeholder="ตัวอย่าง: 12500">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400"><i data-lucide="gauge" class="w-5 h-5 inline-block"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- อัปโหลดรูปภาพ -->
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                <label class="block text-sm font-bold text-slate-700 mb-2">📸 อัปโหลดรูปถ่ายหน้าปัดรถ <span class="text-red-500">*</span></label>   
                <div class="flex items-center justify-center w-full">
                    <label for="oil_images" class="flex flex-col items-center justify-center w-full h-32 border-2 border-emerald-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-emerald-50 transition-colors group">      
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i data-lucide="camera" class="w-8 h-8 mb-3 text-emerald-400 group-hover:scale-110 transition-transform"></i>
                            <p class="mb-1 text-sm text-emerald-600 font-bold">คลิกเพื่อถ่ายรูปหน้าปัดไมล์</p>
                        </div>
                        <input id="oil_images" name="oil_images[]" type="file" class="hidden" multiple accept="image/*" capture="environment" required />
                    </label>
                </div>
                <div id="imagePreviewContainer" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-4"></div>
            </div>

            <div class="pt-2">
                <button type="submit" id="submitBtn" class="w-full py-4 text-lg bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all">
                    <i data-lucide="check-circle" class="w-5 h-5 inline-block"></i> บันทึกค่าแรกเข้า
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/start_day.js"></script>