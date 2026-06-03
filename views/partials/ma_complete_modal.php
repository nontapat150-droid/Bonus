<div id="maCompleteModal" class="fixed inset-0 z-[9999] hidden bg-slate-900/70 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
        <div class="p-5 bg-emerald-600 text-white flex justify-between items-center">
            <h3 class="text-lg font-black">ปิดงาน MA</h3>
            <button type="button" onclick="closeMaCompleteModal()" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form id="maCompleteForm" class="p-5 space-y-4" onsubmit="event.preventDefault(); submitMaCompleteJob();">
            <input type="hidden" id="maCompleteJobId" name="job_id">
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <div class="text-[10px] font-black text-slate-400 uppercase">งาน</div>
                <div id="maCompleteJobLabel" class="text-sm font-black text-slate-800 mt-1">-</div>
            </div>

            <!-- Job Status Selection -->
            <div>
                <label class="block text-xs font-black text-slate-700 mb-2">สถานะงาน <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="ma_status" value="completed" class="peer sr-only" checked onchange="toggleMaCompleteFields()">
                        <div class="text-center px-3 py-2 rounded-xl border border-slate-200 bg-white font-bold text-sm text-slate-600 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 hover:bg-slate-50 transition-colors">
                            จบงาน
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="ma_status" value="failed" class="peer sr-only" onchange="toggleMaCompleteFields()">
                        <div class="text-center px-3 py-2 rounded-xl border border-slate-200 bg-white font-bold text-sm text-slate-600 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700 hover:bg-slate-50 transition-colors">
                            ไม่จบงาน
                        </div>
                    </label>
                </div>
            </div>

            <!-- Fields for Completed Job -->
            <div id="maCompletedFields" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black text-slate-700 mb-1">ค่าสัญญาณหลังออนไลน์ <span class="text-rose-500">*</span></label>
                        <input type="text" id="maSignalAfter" name="signal_after" class="input w-full text-sm" placeholder="เช่น -21.5">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-700 mb-1">ค่า Power RX <span class="text-rose-500">*</span></label>
                        <input type="text" id="maPowerRx" name="power_rx" class="input w-full text-sm" placeholder="เช่น -23.1">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-700 mb-1">สาเหตุของปัญหา <span class="text-rose-500">*</span></label>
                    <textarea id="maProblemCause" name="problem_cause" rows="2" class="input w-full text-sm" placeholder="ระบุสาเหตุของปัญหา"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-700 mb-1">วิธีการแก้ไข <span class="text-rose-500">*</span></label>
                    <textarea id="maSolution" name="solution" rows="2" class="input w-full text-sm" placeholder="ระบุวิธีการแก้ไข"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-700 mb-1">รูปภาพหลักฐานการจบงาน <span class="text-rose-500">*</span></label>
                    <input type="file" id="maProofImages" name="proof_images[]" accept="image/jpeg,image/png,image/webp" multiple
                        class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold">
                    <p class="text-[10px] text-slate-400 mt-1">อัปโหลดอย่างน้อย 1 รูป (JPG, PNG, WebP)</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black text-slate-700 mb-2">หมายเหตุ <span id="maRemarkStar" class="text-rose-500 hidden">*</span></label>
                <textarea id="maCompleteRemark" name="remark" rows="2" class="input w-full text-sm" placeholder="รายละเอียดเพิ่มเติม หรือ เหตุผลที่ไม่จบงาน"></textarea>
            </div>
            
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeMaCompleteModal()" class="flex-1 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200">ยกเลิก</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl font-black text-white bg-emerald-600 hover:bg-emerald-700">ยืนยันบันทึก</button>
            </div>
            
            <script>
                function toggleMaCompleteFields() {
                    const status = document.querySelector('input[name="ma_status"]:checked').value;
                    const completedFields = document.getElementById('maCompletedFields');
                    const remarkStar = document.getElementById('maRemarkStar');
                    
                    if (status === 'completed') {
                        completedFields.style.display = 'block';
                        remarkStar.classList.add('hidden');
                    } else {
                        completedFields.style.display = 'none';
                        remarkStar.classList.remove('hidden');
                    }
                }
            </script>
        </form>
    </div>
</div>
