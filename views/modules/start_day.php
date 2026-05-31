<?php
// views/modules/start_day.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
?>

<div class="max-w-4xl mx-auto space-y-6 animate__animated animate__fadeIn">
    
    <div class="flex justify-center mb-2">
        <div class="bg-white p-1.5 rounded-2xl border border-slate-200 inline-flex shadow-sm gap-1">
            <button id="tabFormBtn" type="button" class="px-6 py-2.5 text-sm font-bold rounded-xl transition-all bg-emerald-50 text-emerald-600 shadow-sm">
                <i data-lucide="clipboard-edit" class="w-4 h-4 inline-block mr-1"></i> บันทึกค่าแรกเข้า
            </button>
            <button id="tabHistBtn" type="button" class="px-6 py-2.5 text-sm font-bold rounded-xl transition-all text-slate-500 hover:bg-slate-50 hover:text-slate-700">
                <i data-lucide="history" class="w-4 h-4 inline-block mr-1"></i> ประวัติของฉัน
            </button>
        </div>
    </div>

    <div id="formSection" class="max-w-2xl mx-auto card overflow-hidden animate__animated animate__fadeIn">
        <div class="bg-emerald-600 px-6 py-4">
            <h2 class="text-2xl font-bold text-white flex items-center">
                <span class="mr-2 text-3xl"><i data-lucide="clipboard-check" class="w-6 h-6 inline-block"></i></span> บันทึกค่าแรกเข้า
            </h2>
            <p class="text-emerald-100 text-sm mt-1">ระบบบันทึกข้อมูลลูกค้าและค่าแรกเข้าสำหรับช่างเทคนิค</p>
        </div>

        <div class="p-6">
            <form id="startDayForm" enctype="multipart/form-data" class="space-y-6">

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600 font-black text-lg">
                        <?= mb_substr($_SESSION['full_name'] ?? 'U', 0, 1) ?>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase">ผู้ทำรายการ</p>
                        <p class="font-black text-slate-800 text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? '-') ?></p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-100 space-y-5 shadow-sm">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อลูกค้า <span class="text-red-500">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" required class="input w-full" placeholder="ระบุชื่อลูกค้า">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">เลข Non <span class="text-red-500">*</span></label>
                        <input type="text" id="non_number" name="non_number" required class="input w-full font-mono text-indigo-700 font-bold" placeholder="ระบุเลข Non">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ค่าแรกเข้า <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="has_initial_fee" value="1" checked class="peer sr-only">
                                <div class="p-3 text-center border-2 border-slate-200 rounded-xl peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 font-bold transition-all shadow-sm">
                                    ✅ มีค่าแรกเข้า
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="has_initial_fee" value="2" class="peer sr-only">
                                <div class="p-3 text-center border-2 border-slate-200 rounded-xl peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700 font-bold transition-all shadow-sm">
                                    💵 จ่ายหน้างาน
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="has_initial_fee" value="0" class="peer sr-only">
                                <div class="p-3 text-center border-2 border-slate-200 rounded-xl peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700 font-bold transition-all shadow-sm">
                                    ❌ ไม่มีค่าแรกเข้า
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                    <label class="block text-sm font-bold text-slate-700 mb-2">📸 แนบรูปภาพประกอบ <span class="text-red-500">*</span></label>
                    <div class="flex items-center justify-center w-full">
                        <label for="start_day_images" class="flex flex-col items-center justify-center w-full h-32 border-2 border-emerald-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-emerald-50 transition-colors group">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i data-lucide="camera" class="w-8 h-8 mb-3 text-emerald-400 group-hover:scale-110 transition-transform"></i>
                                <p class="mb-1 text-sm text-emerald-600 font-bold">คลิกเพื่อถ่ายรูปหรือแนบไฟล์</p>
                            </div>
                            <input id="start_day_images" name="start_day_images[]" type="file" class="sr-only" multiple accept="image/*" />
                        </label>
                    </div>
                    <div id="imagePreviewContainer" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-4"></div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="submitBtn" class="w-full py-4 text-lg bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all">
                        <i data-lucide="save" class="w-5 h-5 inline-block"></i> ยืนยันการบันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="historySection" class="card overflow-hidden hidden animate__animated animate__fadeIn">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-white">
            <h3 class="text-lg font-bold text-slate-800"><i data-lucide="history" class="w-5 h-5 inline-block mr-2 text-indigo-500"></i> ประวัติการบันทึกของฉัน</h3>
        </div>
        <div class="overflow-x-auto bg-white">
            <table class="w-full text-sm text-left block md:table">
                <thead class="hidden md:table-header-group text-xs text-slate-500 uppercase bg-slate-50">
                    <tr>
                        <th class="px-6 py-4">วันที่ - เวลา</th>
                        <th class="px-6 py-4">ชื่อลูกค้า</th>
                        <th class="px-6 py-4">เลข Non</th>
                        <th class="px-6 py-4 text-center">สถานะค่าแรกเข้า</th>
                        <th class="px-6 py-4 text-center">รูปถ่าย</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody" class="block md:table-row-group divide-y divide-slate-100">
                    <tr><td colspan="5" class="text-center py-8 text-slate-400">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js"></script>
<script src="assets/js/start_day.js"></script>