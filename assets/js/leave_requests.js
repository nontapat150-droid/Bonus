// assets/js/leave_requests.js
// Admin: จัดการคำขอลางานทั้งหมด

async function loadAllLeaves() {
    const status = document.getElementById('leaveStatusFilter')?.value || 'all';
    const tbody = document.getElementById('allLeavesBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center gap-2"><div class="loader-spinner w-8 h-8"></div><span>กำลังโหลด...</span></div></td></tr>';

    try {
        const res = await fetch(`api/leave/get_all_leaves.php?status=${status}`);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-red-400">${data.error}</td></tr>`;
            return;
        }

        // Update stats
        updateLeaveStats(data.data);

        if (data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">ไม่มีรายการ</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        data.data.forEach(row => {
            const startDate = new Date(row.start_date).toLocaleDateString('th-TH');
            const endDate   = new Date(row.end_date).toLocaleDateString('th-TH');
            const dateRange = row.start_date === row.end_date ? startDate : `${startDate} – ${endDate}`;
            const created   = new Date(row.created_at).toLocaleDateString('th-TH');

            let statusBadge = '';
            if (row.status === 'pending') {
                statusBadge = '<span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full border border-amber-200">⏳ รอดำเนินการ</span>';
            } else if (row.status === 'approved') {
                statusBadge = `<span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full border border-emerald-200">✅ อนุมัติ</span>`;
            } else {
                statusBadge = `<span class="px-2.5 py-1 bg-rose-100 text-rose-700 text-xs font-bold rounded-full border border-rose-200">❌ ปฏิเสธ</span>`;
            }

            let actionBtns = '';
            if (row.status === 'pending') {
                actionBtns = `
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="updateLeaveStatus(${row.id}, 'approved')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-1.5 rounded-lg font-bold transition">อนุมัติ</button>
                        <button onclick="updateLeaveStatus(${row.id}, 'rejected')" class="bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs px-3 py-1.5 rounded-lg font-bold transition">ปฏิเสธ</button>
                    </div>`;
            } else {
                const reviewedByText = row.reviewed_by_name ? `โดย ${row.reviewed_by_name}` : '';
                actionBtns = `<span class="text-xs text-slate-400 italic">${reviewedByText}</span>`;
            }

            const roleLabel = getRoleLabel(row.role);
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-50 hover:bg-slate-50/50 transition-colors';
            tr.innerHTML = `
                <td class="px-5 py-3">
                    <div class="font-semibold text-slate-800">${row.full_name}</div>
                    <div class="text-xs text-slate-400">${roleLabel} · ยื่นเมื่อ ${created}</div>
                </td>
                <td class="px-5 py-3 font-medium text-slate-700 whitespace-nowrap">${dateRange}</td>
                <td class="px-5 py-3 text-center">
                    <span class="bg-indigo-100 text-indigo-700 font-bold text-sm px-2.5 py-0.5 rounded-full">${row.days} วัน</span>
                </td>
                <td class="px-5 py-3 max-w-[240px]">
                    <p class="text-slate-600 text-sm line-clamp-2" title="${escapeHtml(row.reason)}">${escapeHtml(row.reason)}</p>
                </td>
                <td class="px-5 py-3 text-center">${statusBadge}</td>
                <td class="px-5 py-3">${actionBtns}</td>
            `;
            tbody.appendChild(tr);
        });

        lucide.createIcons();
    } catch (e) {
        console.error(e);
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-400">เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว</td></tr>';
    }
}

function updateLeaveStats(data) {
    const pending  = data.filter(r => r.status === 'pending').length;
    const approved = data.filter(r => r.status === 'approved').length;
    const rejected = data.filter(r => r.status === 'rejected').length;
    const elP = document.getElementById('statPending');
    const elA = document.getElementById('statApproved');
    const elR = document.getElementById('statRejected');
    if (elP) elP.textContent = pending;
    if (elA) elA.textContent = approved;
    if (elR) elR.textContent = rejected;
}

async function updateLeaveStatus(leaveId, status) {
    const label = status === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ';
    if (!confirm(`ยืนยันการ${label}คำขอลานี้?`)) return;

    try {
        const res = await fetch('api/leave/update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: leaveId, status })
        });
        const data = await res.json();
        if (data.success) {
            if (window.Toast) Toast.success(data.message);
            else alert(data.message);
            loadAllLeaves();
        } else {
            if (window.Toast) Toast.error(data.error);
            else alert(data.error);
        }
    } catch (e) {
        if (window.Toast) Toast.error('เชื่อมต่อล้มเหลว');
        else alert('เชื่อมต่อล้มเหลว');
    }
}

function getRoleLabel(role) {
    const map = {
        'super_admin':   '🛡️ Super Admin',
        'admin':         '⚙️ Admin',
        'technician':    '🔧 ช่าง Office',
        'ma_technician': '🔩 ช่าง MA',
        'sales':         '📊 Sales',
        'intern':        '🎓 เด็กฝึกงาน',
    };
    return map[role] || role;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Auto-reload when filter changes
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('leaveStatusFilter')?.addEventListener('change', loadAllLeaves);
});
