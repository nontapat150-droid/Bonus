<?php if (!defined('PDO::ATTR_ERRMODE')) exit; ?>
<div id="editStartDayModal" class="fixed inset-0 z-[9999] hidden bg-slate-900/70 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden z-[10000] max-h-[95vh] flex flex-col">
        <div class="p-5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white flex justify-between items-center shrink-0">
            <h3 class="text-lg sm:text-xl font-black tracking-tight flex items-center gap-2">
                <i data-lucide="edit" class="w-6 h-6"></i>
                แก้ไขประวัติค่าแรกเข้า
            </h3>
            <button type="button" onclick="closeEditStartDayModal()" class="text-indigo-100 hover:text-white text-3xl leading-none">&times;</button>
        </div>

        <div class="p-4 sm:p-6 overflow-y-auto flex-1 bg-slate-50 complete-modal-scrollbar">
            <form id="editStartDayForm" class="space-y-5" enctype="multipart/form-data">
                <input type="hidden" id="edit_sd_id" name="id">

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อลูกค้า <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_sd_customer" name="customer_name" required class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="ระบุชื่อลูกค้า">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">เลข Non <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_sd_non" name="non_number" required class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono text-indigo-700 font-bold outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="ระบุเลข Non">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ค่าแรกเข้า <span class="text-red-500">*</span></label>
                        <select id="edit_sd_fee" name="has_initial_fee" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 font-bold text-slate-700">
                            <option value="1">✅ มีค่าแรกเข้า</option>
                            <option value="2">💵 จ่ายหน้างาน</option>
                            <option value="0">❌ ไม่มีค่าแรกเข้า</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <label class="block text-sm font-bold text-slate-700 mb-2">📸 แนบรูปภาพใหม่ (ถ้าต้องการเปลี่ยน)</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="edit_sd_images" class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-indigo-50 transition-colors group">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i data-lucide="image-plus" class="w-8 h-8 mb-3 text-indigo-400 group-hover:scale-110 transition-transform"></i>
                                <p class="mb-1 text-sm text-indigo-600 font-bold">คลิกเพื่ออัปโหลดรูปภาพใหม่</p>
                                <p class="text-xs text-slate-500">(หากไม่เลือกรูปใหม่ จะใช้รูปเดิม)</p>
                            </div>
                            <input id="edit_sd_images" type="file" class="sr-only" multiple accept="image/*" />
                        </label>
                    </div>
                    <div id="editImagePreviewContainer" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-4"></div>
                </div>
            </form>
        </div>

        <div class="p-4 sm:p-5 bg-white border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-2 shrink-0">
            <button type="button" onclick="closeEditStartDayModal()" class="px-5 py-2.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200">ยกเลิก</button>
            <button type="submit" form="editStartDayForm" class="px-5 py-2.5 rounded-xl font-bold bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-5 h-5"></i>
                บันทึกการแก้ไข
            </button>
        </div>
    </div>
</div>
