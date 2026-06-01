// assets/js/job_close.js — ฟอร์มปิดงาน / แก้ไข / ตรวจสอบข้อมูล

const CJ_OPTIONAL_INPUTS = [
    'cj_order_no', 'cj_equipment_soa', 'cj_sn_playbox', 'cj_sn_onu', 'cj_sn_mesh',
    'cj_sn_sim', 'cj_sn_ip_camera', 'cj_splitter', 'cj_port_used', 'cj_l3_name',
    'cj_actual_cable_length', 'cj_ref_id_3bb', 'cj_sc_connector_blue', 'cj_initial_fee', 'cj_remark'
];

const CJ_REQUIRED_RULES = [
    { id: 'cj_install_provider', label: 'ประเภทงานติดตั้ง (AIS / 3BB)', check: () => ['AIS', '3BB'].includes(getSelectedInstallProvider()) },
    { id: 'cj_install_date', label: 'วันที่ติดตั้ง (ต้องเลือกทุกครั้ง)', check: () => !!(document.getElementById('cj_install_date')?.value?.trim()) }
];

function cjEscape(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

function getSelectedInstallProvider() {
    const el = document.querySelector('input[name="cj_install_provider"]:checked');
    return el ? el.value : '';
}

function updateCompleteJobModalTitle() {
    const provider = getSelectedInstallProvider();
    const mode = document.getElementById('cj_mode')?.value || 'create';
    const titleEl = document.getElementById('cj_modal_title');
    if (!titleEl) return;
    const action = mode === 'edit' ? 'แก้ไขปิดงาน' : 'ปิดงานติดตั้ง';
    titleEl.innerHTML = `<i data-lucide="clipboard-check" class="w-6 h-6"></i>${action}${provider ? ' ' + cjEscape(provider) : ''}`;
    if (window.lucide?.createIcons) window.lucide.createIcons();
}

function bindCompleteJobProviderListeners() {
    document.querySelectorAll('input[name="cj_install_provider"]').forEach(r => {
        r.onchange = updateCompleteJobModalTitle;
    });
}

function formatPlanDateHint(job) {
    if (!job?.plan_arrival_date) return '';
    const d = new Date(String(job.plan_arrival_date).slice(0, 10) + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
}

function validateJobCloseForm() {
    const missing = [];
    CJ_REQUIRED_RULES.forEach(rule => {
        if (!rule.check()) missing.push(rule.label);
    });
    return missing;
}

function showJobCloseValidationError(missing) {
    const list = missing.map(m => `<li class="text-left text-sm text-slate-700">${cjEscape(m)}</li>`).join('');
    return Swal.fire({
        title: 'กรุณาระบุข้อมูลให้ครบ',
        html: `<p class="text-sm text-slate-600 mb-2">ต้องกรอกข้อมูลที่จำเป็นก่อนยืนยันปิดงาน:</p><ul class="list-disc pl-5 space-y-1">${list}</ul>`,
        icon: 'warning',
        confirmButtonColor: '#10b981',
        confirmButtonText: 'ตกลง'
    });
}

function collectJobClosePayload(job) {
    const val = id => document.getElementById(id)?.value?.trim() ?? '';
    return {
        install_provider: getSelectedInstallProvider(),
        install_date: document.getElementById('cj_install_date')?.value?.trim() ?? '',
        close_case_no: job?.access_no ?? '',
        order_no: val('cj_order_no'),
        customer_name: job?.customer ?? '',
        package_name: job?.package ?? '',
        main_package: job?.product ?? '',
        equipment_soa: val('cj_equipment_soa'),
        sn_playbox: val('cj_sn_playbox'),
        sn_onu: val('cj_sn_onu'),
        sn_mesh: val('cj_sn_mesh'),
        sn_sim: val('cj_sn_sim'),
        sn_ip_camera: val('cj_sn_ip_camera'),
        splitter: val('cj_splitter'),
        port_used: val('cj_port_used'),
        l3_name: val('cj_l3_name'),
        actual_cable_length: val('cj_actual_cable_length'),
        ref_id_3bb: val('cj_ref_id_3bb'),
        sc_connector_blue: val('cj_sc_connector_blue'),
        initial_fee: val('cj_initial_fee'),
        remark: val('cj_remark')
    };
}

function fillJobCloseFormFromRecord(rec) {
    document.getElementById('cj_close_id').value = rec.id;
    document.getElementById('cj_mode').value = 'edit';
    document.getElementById('cj_job_id').value = rec.job_id;

    const ais = document.getElementById('cj_provider_ais');
    const bb = document.getElementById('cj_provider_3bb');
    if (rec.install_provider === 'AIS' && ais) ais.checked = true;
    else if (bb) bb.checked = true;

    document.getElementById('cj_install_date').value = rec.install_date ? String(rec.install_date).slice(0, 10) : '';
    document.getElementById('cj_close_case').textContent = rec.close_case_no || rec.access_no || '-';
    document.getElementById('cj_customer').textContent = rec.customer_name || '-';
    document.getElementById('cj_package').textContent = rec.package_name || '-';
    document.getElementById('cj_main_package').textContent = rec.main_package || '-';

    document.getElementById('cj_order_no').value = rec.order_no || '';
    const map = {
        cj_equipment_soa: 'equipment_soa', cj_sn_playbox: 'sn_playbox', cj_sn_onu: 'sn_onu',
        cj_sn_mesh: 'sn_mesh', cj_sn_sim: 'sn_sim', cj_sn_ip_camera: 'sn_ip_camera',
        cj_splitter: 'splitter', cj_port_used: 'port_used', cj_l3_name: 'l3_name',
        cj_actual_cable_length: 'actual_cable_length', cj_ref_id_3bb: 'ref_id_3bb',
        cj_sc_connector_blue: 'sc_connector_blue', cj_initial_fee: 'initial_fee', cj_remark: 'remark'
    };
    Object.entries(map).forEach(([elId, key]) => {
        const el = document.getElementById(elId);
        if (el) el.value = rec[key] != null && rec[key] !== '' ? rec[key] : '';
    });

    const btn = document.getElementById('cj_submit_btn');
    if (btn) btn.textContent = 'บันทึกการแก้ไข';
    const hint = document.getElementById('cj_deadline_hint');
    if (hint) {
        hint.textContent = rec.can_edit
            ? `แก้ไขได้ถึง ${rec.edit_deadline_label || '12:00 น. วันถัดไป'}`
            : 'หมดเวลาแก้ไขแล้ว';
        hint.className = rec.can_edit ? 'text-xs font-bold text-emerald-600' : 'text-xs font-bold text-rose-600';
    }
    updateCompleteJobModalTitle();
}

function fillJobCloseFormForCreate(job) {
    document.getElementById('cj_close_id').value = '';
    document.getElementById('cj_mode').value = 'create';
    document.getElementById('cj_job_id').value = job.id;
    const dateInput = document.getElementById('cj_install_date');
    if (dateInput) {
        dateInput.value = '';
        dateInput.removeAttribute('value');
    }
    const dateHint = document.getElementById('cj_install_date_hint');
    if (dateHint) {
        const planLabel = formatPlanDateHint(job);
        dateHint.textContent = planLabel
            ? `กรุณาเลือกวันที่ติดตั้งทุกครั้ง (วันมอบหมายงาน: ${planLabel})`
            : 'กรุณาเลือกวันที่ติดตั้งทุกครั้งก่อนบันทึก';
    }
    const bb = document.getElementById('cj_provider_3bb');
    if (bb) bb.checked = true;
    document.getElementById('cj_close_case').textContent = job.access_no || '-';
    document.getElementById('cj_customer').textContent = job.customer || 'ไม่ระบุ';
    document.getElementById('cj_package').textContent = job.package || '-';
    document.getElementById('cj_main_package').textContent = job.product || '-';
    document.getElementById('cj_order_no').value = job.order_no ? String(job.order_no).trim() : '';
    CJ_OPTIONAL_INPUTS.forEach(id => {
        if (id === 'cj_order_no') return;
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const btn = document.getElementById('cj_submit_btn');
    if (btn) btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> ยืนยันปิดงาน';
    const hint = document.getElementById('cj_deadline_hint');
    if (hint) hint.textContent = 'แก้ไขข้อมูลได้ถึง 12:00 น. ของวันถัดไปจากวันมอบหมายงาน';
    bindCompleteJobProviderListeners();
    updateCompleteJobModalTitle();
}

window.openCompleteJobModal = function(jobId) {
    const jobs = typeof allJobs !== 'undefined' ? allJobs : [];
    const job = jobs.find(j => String(j.id) === String(jobId));
    if (!job) {
        Swal.fire('ไม่พบงาน', 'ไม่พบข้อมูลงานที่เลือก', 'warning');
        return;
    }
    const modal = document.getElementById('completeJobModal');
    if (!modal) {
        Swal.fire('ข้อผิดพลาด', 'ไม่พบฟอร์มปิดงาน', 'error');
        return;
    }
    fillJobCloseFormForCreate(job);
    modal.classList.remove('hidden');
    if (window.lucide?.createIcons) window.lucide.createIcons();
};

window.openEditJobCloseModal = async function(closeId) {
    try {
        const cb = new Date().getTime();
        const res = await fetch(`api/dispatch/get_job_close_detail.php?id=${closeId}&_=${cb}`);
        const data = await res.json();
        
        if (!data.success) {
            Swal.fire('ข้อผิดพลาด', data.error || 'โหลดข้อมูลไม่สำเร็จ', 'error');
            return;
        }
        if (!data.data.can_edit) {
            Swal.fire('ไม่สามารถแก้ไข', `หมดเวลาแก้ไขแล้ว (กำหนดถึง ${data.data.edit_deadline_label})`, 'warning');
            return;
        }
        const modal = document.getElementById('completeJobModal');
        if (!modal) return;
        fillJobCloseFormFromRecord(data.data);
        modal.classList.remove('hidden');
        if (window.lucide?.createIcons) window.lucide.createIcons();
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ', 'error');
    }
};

window.closeCompleteJobModal = function() {
    document.getElementById('completeJobModal')?.classList.add('hidden');
};

window.submitCompleteJob3BB = async function() {
    const mode = document.getElementById('cj_mode')?.value || 'create';
    const jobId = document.getElementById('cj_job_id')?.value;
    const closeId = document.getElementById('cj_close_id')?.value;
    const jobs = typeof allJobs !== 'undefined' ? allJobs : [];
    const job = jobs.find(j => String(j.id) === String(jobId));

    const missing = validateJobCloseForm();
    if (missing.length) {
        await showJobCloseValidationError(missing);
        return;
    }

    const payload = collectJobClosePayload(job || { access_no: document.getElementById('cj_close_case')?.textContent });
    const installDateLabel = new Date(payload.install_date + 'T00:00:00').toLocaleDateString('th-TH', {
        year: 'numeric', month: 'long', day: 'numeric'
    });

    const { isConfirmed } = await Swal.fire({
        title: mode === 'edit' ? `ยืนยันบันทึกการแก้ไข ${payload.install_provider}?` : `ยืนยันปิดงาน ${payload.install_provider}?`,
        html: `<p class="text-sm text-slate-600">ประเภท: <strong>${cjEscape(payload.install_provider)}</strong></p>
               <p class="text-sm text-slate-600">วันที่ติดตั้ง: <strong>${cjEscape(installDateLabel)}</strong></p>
               <p class="text-sm text-slate-600">Non: <strong>${cjEscape(payload.close_case_no || '-')}</strong></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: mode === 'edit' ? 'บันทึกการแก้ไข' : 'ยืนยันปิดงาน',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
    });
    if (!isConfirmed) return;

    const loader = typeof showLoader === 'function' ? showLoader : () => {};
    const hide = typeof hideLoader === 'function' ? hideLoader : () => {};
    loader(mode === 'edit' ? 'กำลังบันทึก...' : 'กำลังบันทึกปิดงาน...');

    try {
        let res, data;
        if (mode === 'edit') {
            res = await fetch('api/dispatch/update_job_close.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ close_id: closeId, close_3bb: payload })
            });
        } else {
            if (!jobId || !job) {
                Swal.fire('ข้อผิดพลาด', 'ไม่พบข้อมูลงาน', 'error');
                return;
            }
            res = await fetch('api/dispatch/update_job_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: jobId, status: 'completed', close_3bb: payload })
            });
        }
        data = await res.json();
        if (data.success) {
            closeCompleteJobModal();
            Swal.fire({
                title: 'สำเร็จ',
                text: data.message || 'บันทึกเรียบร้อย',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                didClose: () => {
                    if (typeof loadJobs === 'function') loadJobs();
                    if (typeof loadJobCloseHistory === 'function') loadJobCloseHistory();
                    if (typeof applyFilter === 'function' && typeof currentType !== 'undefined' && currentType === 'job_close') applyFilter();
                    if (typeof loadJobCloseHistory === 'function') loadJobCloseHistory();
                    JobClose.checkAlerts();
                }
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
    } finally {
        hide();
    }
};

const JobClose = {
    async checkAlerts() {
        const banner = document.getElementById('jobCloseAlertBanner');
        if (!banner) return;
        try {
            const cb = new Date().getTime();
            const res = await fetch(`api/dispatch/get_close_alerts.php?_=${cb}`);
            const data = await res.json();
            if (!data.success || !data.alerts?.length) {
                banner.classList.add('hidden');
                banner.innerHTML = '';
                return;
            }
            const items = data.alerts.map(a =>
                `<li class="text-sm"><strong>${cjEscape(a.access_no)}</strong> — ${cjEscape(a.customer || '-')} (กำหนดปิด: ${cjEscape(a.deadline_label)}, เหลือ ~${a.hours_left} ชม.)</li>`
            ).join('');
            banner.className = 'rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 mb-3';
            banner.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="text-2xl">⚠️</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-black text-amber-800 text-sm">ใกล้ถึงกำหนดปิดงาน — ยังมี ${data.count} งานที่ยังไม่ปิด</p>
                        <p class="text-xs text-amber-700 mt-1">กรุณาปิดงานก่อน 12:00 น. ของวันถัดไปจากวันมอบหมาย</p>
                        <ul class="mt-2 space-y-1 list-disc pl-4 text-amber-900">${items}</ul>
                    </div>
                </div>`;
            banner.classList.remove('hidden');

            if (data.count > 0 && !sessionStorage.getItem('jobCloseUrgentSwal')) {
                sessionStorage.setItem('jobCloseUrgentSwal', '1');
                const first = data.alerts[0];
                Swal.fire({
                    title: 'แจ้งเตือน: ใกล้หมดเวลาปิดงาน',
                    html: `<p class="text-sm text-slate-600">มี <strong>${data.count}</strong> งานที่ยังไม่ปิด</p>
                           <p class="text-sm mt-2">ตัวอย่าง: <strong>${cjEscape(first.access_no)}</strong> — กำหนดปิด ${cjEscape(first.deadline_label)}</p>
                           <p class="text-xs text-amber-700 mt-3">กรุณาปิดงานก่อน 12:00 น. ของวันถัดไปจากวันมอบหมาย</p>`,
                    icon: 'warning',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'รับทราบ'
                });
            }
        } catch (e) {
            banner.classList.add('hidden');
        }
    }
};
window.deleteJobCloseRecord = async function(closeId) {
    const { isConfirmed } = await Swal.fire({
        title: 'ลบประวัติปิดงาน?',
        html: `<p class="text-sm text-slate-600">รายการนี้จะถูกลบถาวร</p>
               <p class="text-xs text-amber-700 mt-2">งานที่เกี่ยวข้องจะกลับเป็นสถานะรอปิดงาน (ช่างสามารถปิดงานใหม่ได้)</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ยืนยันลบ',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
    });
    if (!isConfirmed) return;

    try {
        const res = await fetch('api/dispatch/delete_job_close.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ close_id: closeId })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'ลบแล้ว', text: data.message || 'ลบประวัติเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false,
                didClose: () => {
                    if (typeof applyFilter === 'function' && typeof currentType !== 'undefined' && currentType === 'job_close') applyFilter();
                    if (typeof loadJobCloseHistory === 'function') loadJobCloseHistory();
                }
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'ลบไม่สำเร็จ', 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ', 'error');
    }
};

window.JobClose = JobClose;
