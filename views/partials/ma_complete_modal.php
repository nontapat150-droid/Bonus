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
            <div>
                <label class="block text-xs font-black text-slate-700 mb-2">รูปภาพหลักฐานการจบงาน <span class="text-rose-500">*</span></label>
                <input type="file" id="maProofImages" name="proof_images[]" accept="image/jpeg,image/png,image/webp" multiple required
                    class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold">
                <p class="text-[10px] text-slate-400 mt-1">อัปโหลดอย่างน้อย 1 รูป (JPG, PNG, WebP)</p>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-700 mb-2">หมายเหตุ (ถ้ามี)</label>
                <textarea id="maCompleteRemark" name="remark" rows="2" class="input w-full text-sm" placeholder="รายละเอียดเพิ่มเติม"></textarea>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeMaCompleteModal()" class="flex-1 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200">ยกเลิก</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl font-black text-white bg-emerald-600 hover:bg-emerald-700">ยืนยันปิดงาน</button>
            </div>
        </form>
    </div>
</div>
