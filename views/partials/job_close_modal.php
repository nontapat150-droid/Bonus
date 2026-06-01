<?php if (!defined('PDO::ATTR_ERRMODE')) exit; ?>
<div id="completeJobModal" class="fixed inset-0 z-[9999] hidden bg-slate-900/70 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden z-[10000] max-h-[95vh] flex flex-col">
        <div class="p-5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex justify-between items-center shrink-0">
            <h3 id="cj_modal_title" class="text-lg sm:text-xl font-black tracking-tight flex items-center gap-2">
                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
                ปิดงานติดตั้ง
            </h3>
            <button type="button" onclick="closeCompleteJobModal()" class="text-emerald-100 hover:text-white text-3xl leading-none">&times;</button>
        </div>

        <div class="p-4 sm:p-6 overflow-y-auto flex-1 bg-slate-50 complete-modal-scrollbar">
            <p id="cj_deadline_hint" class="text-xs font-bold text-slate-500 mb-3">แก้ไขข้อมูลได้ถึง 12:00 น. ของวันถัดไปจากวันมอบหมายงาน</p>
            <form id="completeJobForm" class="space-y-5" onsubmit="event.preventDefault(); submitCompleteJob3BB();">
                <input type="hidden" id="cj_job_id">
                <input type="hidden" id="cj_close_id" value="">
                <input type="hidden" id="cj_mode" value="create">

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ประเภทงานติดตั้ง <span class="text-rose-500">*</span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cj-provider-btn cursor-pointer">
                                <input type="radio" name="cj_install_provider" id="cj_provider_ais" value="AIS">
                                <span>AIS</span>
                            </label>
                            <label class="cj-provider-btn cursor-pointer">
                                <input type="radio" name="cj_install_provider" id="cj_provider_3bb" value="3BB" checked>
                                <span>3BB</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ข้อมูลจากงานที่มอบหมาย</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="cj_install_date" class="block text-[10px] font-bold text-slate-500 mb-1">วันที่ติดตั้ง <span class="text-rose-500">*</span></label>
                            <input type="date" id="cj_install_date" required class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-bold text-emerald-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500">ปิดเคสงาน (เลข Non)</label>
                            <p id="cj_close_case" class="font-black text-indigo-600 text-sm mt-0.5">-</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500">ชื่อ-สกุล</label>
                            <p id="cj_customer" class="font-bold text-slate-800 text-sm mt-0.5">-</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500">แพ็กเกจ</label>
                            <p id="cj_package" class="font-bold text-slate-800 text-sm mt-0.5">-</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[10px] font-bold text-slate-500">แพ็กเกจหลัก</label>
                            <p id="cj_main_package" class="font-bold text-slate-800 text-sm mt-0.5">-</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">กรอกข้อมูลเพิ่มเติม (ไม่บังคับ)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Order No</label>
                            <input type="text" id="cj_order_no" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-bold outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ Order No">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">อุปกรณ์ปิด SOA</label>
                            <input type="text" id="cj_equipment_soa" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Sn Playbox</label>
                            <input type="text" id="cj_sn_playbox" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ SN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Sn ONU</label>
                            <input type="text" id="cj_sn_onu" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ SN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Sn Mesh</label>
                            <input type="text" id="cj_sn_mesh" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ SN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Sn Sim</label>
                            <input type="text" id="cj_sn_sim" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ SN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Sn IP Camera</label>
                            <input type="text" id="cj_sn_ip_camera" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 font-mono outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ SN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Splitter</label>
                            <input type="text" id="cj_splitter" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ใช้ Port</label>
                            <input type="text" id="cj_port_used" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ใช้ #L3 (ชื่อ)</label>
                            <input type="text" id="cj_l3_name" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ระยะสายจริง (เมตร)</label>
                            <input type="number" id="cj_actual_cable_length" min="0" step="0.01" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="เช่น 120">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Ref ID 3BB</label>
                            <input type="text" id="cj_ref_id_3bb" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ตัวต่อ SC สีฟ้า</label>
                            <input type="text" id="cj_sc_connector_blue" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุ">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ค่าแรกเข้า (บาท)</label>
                            <input type="number" id="cj_initial_fee" min="0" step="0.01" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="0">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">หมายเหตุ</label>
                            <textarea id="cj_remark" rows="2" class="w-full border border-slate-300 rounded-xl text-sm p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="p-4 sm:p-5 bg-white border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-2 shrink-0">
            <button type="button" onclick="closeCompleteJobModal()" class="px-5 py-2.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200">ยกเลิก</button>
            <button type="button" id="cj_submit_btn" onclick="submitCompleteJob3BB()" class="px-5 py-2.5 rounded-xl font-bold bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                ยืนยันปิดงาน
            </button>
        </div>
    </div>
</div>
