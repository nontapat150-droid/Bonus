let summaryData = null;

function openOverallSummaryModal() {
    document.getElementById('overallSummaryModal').classList.remove('hidden');
    loadOverallSummary();
}

function closeOverallSummaryModal() {
    document.getElementById('overallSummaryModal').classList.add('hidden');
}

function openIndividualSummaryModal() {
    document.getElementById('individualSummaryModal').classList.remove('hidden');
    loadIndividualSummaryData();
}

function closeIndividualSummaryModal() {
    document.getElementById('individualSummaryModal').classList.add('hidden');
}

async function fetchSummaryData(month) {
    try {
        const res = await fetch(`api/users/get_overall_summary.php?month=${month}`);
        const data = await res.json();
        if (data.success) {
            return data;
        } else {
            console.error(data.error);
            return null;
        }
    } catch (e) {
        console.error(e);
        return null;
    }
}

async function loadOverallSummary() {
    const monthInput = document.getElementById('overallSummaryMonth');
    if (!monthInput.value) {
        const now = new Date();
        monthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }
    const month = monthInput.value;
    
    document.getElementById('overallSummaryTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-10">กำลังโหลดข้อมูล...</td></tr>';
    
    const data = await fetchSummaryData(month);
    if (!data) {
        document.getElementById('overallSummaryTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-10 text-rose-500">ไม่สามารถโหลดข้อมูลได้</td></tr>';
        return;
    }

    // Update Badges
    document.getElementById('totalOnTimeBadge').innerText = data.summary.on_time;
    document.getElementById('totalLateBadge').innerText = data.summary.late;
    document.getElementById('totalLeavesBadge').innerText = data.summary.leaves;

    // Render Table
    const tbody = document.getElementById('overallSummaryTableBody');
    if (data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-10">ไม่มีข้อมูลพนักงาน</td></tr>';
        return;
    }

    let html = '';
    const rolesMap = {
        'super_admin': '<span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded-full font-bold text-[10px] border border-rose-100">ผู้ดูแลระบบ</span>',
        'admin': '<span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full font-bold text-[10px] border border-indigo-100">แอดมิน</span>',
        'technician': '<span class="px-2 py-0.5 bg-slate-50 text-slate-500 rounded-full font-bold text-[10px] border border-slate-100">ช่าง Office</span>',
        'ma_technician': '<span class="px-2 py-0.5 bg-violet-50 text-violet-600 rounded-full font-bold text-[10px] border border-violet-100">ช่าง MA</span>',
        'sales': '<span class="px-2 py-0.5 bg-green-50 text-green-600 rounded-full font-bold text-[10px] border border-green-100">เซล</span>',
        'intern': '<span class="px-2 py-0.5 bg-cyan-50 text-cyan-600 rounded-full font-bold text-[10px] border border-cyan-100">เด็กฝึกงาน</span>'
    };

    data.users.forEach((u, idx) => {
        const roleBadge = rolesMap[u.role] || `<span class="px-2 py-0.5 bg-slate-50 text-slate-500 rounded-full font-bold text-[10px] border border-slate-100">${u.role}</span>`;
        
        let rankClass = "text-slate-600 font-bold";
        if (idx === 0) rankClass = "text-amber-500 font-black text-lg";
        else if (idx === 1) rankClass = "text-slate-400 font-black text-lg";
        else if (idx === 2) rankClass = "text-amber-700 font-black text-lg";

        html += `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 ${rankClass}">${idx + 1}</td>
                <td class="px-6 py-4 font-bold text-slate-800">${u.full_name}</td>
                <td class="px-6 py-4 text-center">${roleBadge}</td>
                <td class="px-6 py-4 text-center text-emerald-600 font-black">${u.on_time}</td>
                <td class="px-6 py-4 text-center text-rose-600 font-black">${u.late}</td>
                <td class="px-6 py-4 text-center text-slate-500 font-bold">${u.day_off}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

async function loadIndividualSummaryData() {
    const monthInput = document.getElementById('individualSummaryMonth');
    if (!monthInput.value) {
        const now = new Date();
        monthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }
    const month = monthInput.value;
    
    document.getElementById('individualUserList').innerHTML = '<div class="text-center py-5 text-slate-400 text-sm">กำลังโหลด...</div>';
    
    const data = await fetchSummaryData(month);
    if (!data) {
        document.getElementById('individualUserList').innerHTML = '<div class="text-center py-5 text-rose-400 text-sm">ไม่สามารถโหลดข้อมูลได้</div>';
        return;
    }

    summaryData = data.users;
    renderIndividualUserList();
    
    // reset view
    document.getElementById('individualEmptyState').classList.remove('hidden');
    document.getElementById('individualContentState').classList.add('hidden');
}

function renderIndividualUserList(filterText = '') {
    const listContainer = document.getElementById('individualUserList');
    if (!summaryData) return;

    let html = '';
    const filtered = summaryData.filter(u => u.full_name.toLowerCase().includes(filterText.toLowerCase()));
    
    if (filtered.length === 0) {
        listContainer.innerHTML = '<div class="text-center py-5 text-slate-400 text-sm">ไม่พบพนักงาน</div>';
        return;
    }

    const rolesMap = {
        'super_admin': 'ผู้ดูแลระบบ',
        'admin': 'แอดมิน',
        'technician': 'ช่าง Office',
        'ma_technician': 'ช่าง MA',
        'sales': 'เซล',
        'intern': 'เด็กฝึกงาน'
    };

    filtered.forEach(u => {
        const roleText = rolesMap[u.role] || u.role;
        const initial = u.full_name.charAt(0);
        html += `
            <div onclick="selectIndividualUser(${u.id})" class="p-3 bg-white border border-slate-100 hover:border-sky-300 rounded-xl cursor-pointer transition-all flex items-center gap-3 shadow-sm hover:shadow-md user-list-item" data-id="${u.id}">
                <div class="w-10 h-10 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center font-black shrink-0">${initial}</div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-slate-800 truncate">${u.full_name}</p>
                    <p class="text-[10px] font-bold text-slate-400">${roleText}</p>
                </div>
            </div>
        `;
    });
    listContainer.innerHTML = html;
}

function filterIndividualUsers() {
    const text = document.getElementById('individualSearchUser').value;
    renderIndividualUserList(text);
}

function selectIndividualUser(id) {
    // highlight selected
    document.querySelectorAll('.user-list-item').forEach(el => {
        el.classList.remove('ring-2', 'ring-sky-500', 'bg-sky-50');
    });
    const selectedEl = document.querySelector(`.user-list-item[data-id="${id}"]`);
    if (selectedEl) {
        selectedEl.classList.add('ring-2', 'ring-sky-500', 'bg-sky-50');
    }

    const u = summaryData.find(user => user.id === id);
    if (!u) return;

    document.getElementById('individualEmptyState').classList.add('hidden');
    document.getElementById('individualContentState').classList.remove('hidden');

    const rolesMap = {
        'super_admin': 'ผู้ดูแลระบบ',
        'admin': 'แอดมิน',
        'technician': 'ช่าง Office',
        'ma_technician': 'ช่าง MA',
        'sales': 'เซล',
        'intern': 'เด็กฝึกงาน'
    };

    document.getElementById('indAvatar').innerText = u.full_name.charAt(0);
    document.getElementById('indName').innerText = u.full_name;
    document.getElementById('indRole').innerText = rolesMap[u.role] || u.role;

    document.getElementById('indOnTime').innerText = u.on_time;
    document.getElementById('indLate').innerText = u.late;
    document.getElementById('indDayOff').innerText = u.day_off;
    document.getElementById('indLeaves').innerText = u.leave_count;
    
    document.getElementById('indMaJobs').innerText = u.ma_job_count;
    document.getElementById('indInstallJobs').innerText = u.install_job_count;
    document.getElementById('indOil').innerText = u.oil_count;
    document.getElementById('indStartDay').innerText = u.start_day_count;

    // Render history table
    const tbody = document.getElementById('indHistoryTableBody');
    if (u.history && u.history.length > 0) {
        const statuses = {
            'on_time': '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold">มาตรงเวลา</span>',
            'late': '<span class="px-2 py-1 bg-rose-100 text-rose-700 rounded-lg text-xs font-bold">มาสาย</span>',
            'day_off': '<span class="px-2 py-1 bg-slate-100 text-slate-500 rounded-lg text-xs font-bold">วันหยุด</span>'
        };
        
        let hHtml = '';
        u.history.forEach(h => {
            const dateObj = new Date(h.date);
            const dateStr = dateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });
            
            hHtml += `
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-700">${dateStr}</td>
                    <td class="px-5 py-3">${statuses[h.status] || h.status}</td>
                </tr>
            `;
        });
        tbody.innerHTML = hHtml;
    } else {
        tbody.innerHTML = '<tr><td colspan="2" class="px-5 py-10 text-center text-slate-400">ไม่มีประวัติในเดือนที่เลือก</td></tr>';
    }
}
