// assets/js/ma_summary.js

document.addEventListener('DOMContentLoaded', () => {
    loadMaSummary();
    document.getElementById('maSummaryMonth')?.addEventListener('change', loadMaSummary);
});

async function loadMaSummary() {
    const month = document.getElementById('maSummaryMonth')?.value || new Date().toISOString().slice(0, 7);
    const cards = document.getElementById('maSummaryCards');
    const tbody = document.getElementById('maSummaryTableBody');

    if (cards) cards.innerHTML = '<div class="col-span-full text-center py-8 text-slate-400">กำลังโหลด...</div>';

    try {
        const res = await fetch(`api/dispatch/ma_summary.php?month=${encodeURIComponent(month)}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'โหลดไม่สำเร็จ');

        const s = data.summary;
        const c = data.conditions;

        if (cards) {
            cards.innerHTML = `
                <div class="card !p-4 border-l-4 border-indigo-500">
                    <div class="text-[10px] font-black text-slate-400 uppercase">งาน MA รวม (${month})</div>
                    <div class="text-2xl font-black mt-1 ${s.meets_job_quota ? 'text-emerald-600' : 'text-rose-600'}">${s.total_ma_jobs}</div>
                    <div class="text-xs font-bold mt-1 ${s.meets_job_quota ? 'text-emerald-600' : 'text-rose-500'}">${s.meets_job_quota ? '✓ ผ่านเกณฑ์ ≥' + c.min_ma_jobs : '✗ ต่ำกว่า ' + c.min_ma_jobs}</div>
                </div>
                <div class="card !p-4 border-l-4 border-emerald-500">
                    <div class="text-[10px] font-black text-slate-400 uppercase">ช่าง MA ผ่าน ≥26 วัน</div>
                    <div class="text-2xl font-black text-emerald-600 mt-1">${s.qualified_technicians}</div>
                    <div class="text-xs font-bold text-slate-500 mt-1">จาก ${s.total_technicians} คน (Check-in MA)</div>
                </div>
                <div class="card !p-4 border-l-4 border-amber-500">
                    <div class="text-[10px] font-black text-slate-400 uppercase">เวลาเข้างาน MA</div>
                    <div class="text-xl font-black text-amber-600 mt-1">ไม่เกิน ${c.deadline_time || '08:30'} น.</div>
                    <div class="text-xs font-bold text-slate-500 mt-1">เกิน = บันทึกสายทันที</div>
                </div>
                <div class="card !p-4 border-l-4 border-violet-500">
                    <div class="text-[10px] font-black text-slate-400 uppercase">เกณฑ์วันทำงาน</div>
                    <div class="text-xl font-black text-violet-600 mt-1">≥ ${c.min_work_days} วัน</div>
                    <div class="text-xs font-bold text-slate-500 mt-1">นับจาก Check-in MA</div>
                </div>`;
        }

        if (tbody) {
            const techs = data.technicians || [];
            if (techs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">ไม่พบช่าง MA ในระบบ</td></tr>';
                return;
            }
            tbody.innerHTML = techs.map(t => `
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-bold">${escapeHtml(t.full_name)} <span class="text-xs text-slate-400">@${escapeHtml(t.username)}</span></td>
                    <td class="px-6 py-3 text-center font-black">${t.work_days}</td>
                    <td class="px-6 py-3 text-center font-bold text-emerald-600">${t.on_time_checkins || 0}</td>
                    <td class="px-6 py-3 text-center font-bold ${t.late_checkins > 0 ? 'text-rose-600' : 'text-slate-400'}">${t.late_checkins || 0}</td>
                    <td class="px-6 py-3 text-center">${t.meets_work_days ? '<span class="text-emerald-600 font-black">✓ ผ่าน</span>' : '<span class="text-rose-500 font-bold">✗</span>'}</td>
                    <td class="px-6 py-3 text-center font-bold">${t.completed_ma_jobs || 0}</td>
                </tr>`).join('');
        }
    } catch (e) {
        if (cards) cards.innerHTML = `<div class="col-span-full text-center py-8 text-rose-500">${escapeHtml(e.message)}</div>`;
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-rose-500">${escapeHtml(e.message)}</td></tr>`;
    }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
