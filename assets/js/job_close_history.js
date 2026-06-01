// assets/js/job_close_history.js

document.addEventListener('DOMContentLoaded', () => {
    const d = document.getElementById('jchFilterDate');
    const m = document.getElementById('jchFilterMonth');
    if (m) m.value = new Date().toISOString().slice(0, 7);
    if (d) d.addEventListener('change', () => { if (m) m.value = ''; });
    if (m) m.addEventListener('change', () => { if (d) d.value = ''; });
    loadJobCloseHistory();
    if (window.JobClose?.checkAlerts) JobClose.checkAlerts();
});

async function loadJobCloseHistory() {
    const body = document.getElementById('jchTableBody');
    const badge = document.getElementById('jchCount');
    if (!body) return;

    body.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-slate-400">กำลังโหลด...</td></tr>';

    const date = document.getElementById('jchFilterDate')?.value || '';
    const month = document.getElementById('jchFilterMonth')?.value || '';

    try {
        const res = await fetch(`api/dispatch/get_job_close_list.php?date=${date}&month=${month}`);
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-rose-500">${data.error || 'โหลดไม่สำเร็จ'}</td></tr>`;
            if (badge) badge.textContent = '0 รายการ';
            return;
        }
        renderJobCloseHistoryTable(data.data);
        if (badge) badge.textContent = `${data.data.length} รายการ`;
    } catch (e) {
        body.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-rose-500">เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ</td></tr>';
    }
    if (window.lucide?.createIcons) window.lucide.createIcons();
}

function renderJobCloseHistoryTable(rows) {
    const body = document.getElementById('jchTableBody');
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="text-center py-12 text-slate-400 italic">ไม่มีประวัติในช่วงเวลานี้</td></tr>';
        return;
    }
    body.innerHTML = rows.map(row => {
        const created = row.created_at ? new Date(row.created_at).toLocaleString('th-TH') : '-';
        const install = row.install_date ? new Date(row.install_date + 'T00:00:00').toLocaleDateString('th-TH') : '-';
        const providerBadge = row.install_provider === 'AIS'
            ? '<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-bold">AIS</span>'
            : '<span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs font-bold">3BB</span>';
        const editBtn = row.can_edit
            ? `<button type="button" onclick="openEditJobCloseModal(${row.id})" class="text-xs font-bold text-emerald-600 hover:text-emerald-800 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200">แก้ไข</button>`
            : `<span class="text-xs text-slate-400">หมดเวลาแก้ไข</span>`;
        return `<tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs">${created}</td>
            <td class="px-4 py-3">${providerBadge}</td>
            <td class="px-4 py-3 font-bold text-indigo-600">${escapeHtml(row.close_case_no || row.access_no || '-')}</td>
            <td class="px-4 py-3">${escapeHtml(row.customer_name || '-')}</td>
            <td class="px-4 py-3 text-xs">${install}</td>
            <td class="px-4 py-3 text-xs text-slate-500">${escapeHtml(row.edit_deadline_label || '-')}</td>
            <td class="px-4 py-3 text-center">${editBtn}</td>
        </tr>`;
    }).join('');
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}
