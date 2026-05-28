// assets/js/dispatch.js

let allJobs = [];
let currentTeams = []; 
let selectedJobIds = new Set();

const teamColors = [
    '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#4f46e5', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

document.addEventListener('DOMContentLoaded', () => {
    loadJobs();

    if (IS_ADMIN) {
        document.getElementById('jobExcelFile')?.addEventListener('change', handleExcelUpload);
        document.getElementById('exportExcelBtn')?.addEventListener('click', handleExportExcel);
        document.getElementById('addTeamBtn')?.addEventListener('click', handleAddTeam);
        document.getElementById('dispatchModalBtn')?.addEventListener('click', openDispatchModal);
        document.getElementById('confirmDispatchBtn')?.addEventListener('click', runAutoDispatch);
        document.getElementById('optimizeRouteBtn')?.addEventListener('click', runOptimizeRoute);
        document.getElementById('teamFilter')?.addEventListener('change', renderUI);
        document.getElementById('bulkDeleteBtn')?.addEventListener('click', handleBulkDelete);
        
        document.getElementById('deleteAllJobsBtn')?.addEventListener('click', handleDeleteAllJobs);
        document.getElementById('clearAssignmentsBtn')?.addEventListener('click', handleClearAssignments);
    }

    document.getElementById('dateFilter')?.addEventListener('change', renderUI);
    document.getElementById('limitFilter')?.addEventListener('change', renderUI);
    document.getElementById('selectAllJobs')?.addEventListener('change', handleSelectAll);
});

// 🌟 ระบบ Dynamic Loader เปลี่ยนข้อความได้
function showLoader(message = 'กำลังโหลดข้อมูล...') { 
    const loader = document.getElementById('mapLoader');
    const textEl = document.getElementById('loaderText');
    if(textEl) textEl.textContent = message;
    if(loader) {
        loader.classList.remove('hidden');
        loader.style.opacity = '1';
    }
}

function hideLoader() { 
    const loader = document.getElementById('mapLoader');
    if(loader) {
        loader.style.opacity = '0';
        setTimeout(() => loader.classList.add('hidden'), 300); // Smooth fade out
    }
}

async function loadJobs() {
    showLoader('กำลังซิงค์ข้อมูลงาน...');
    try {
        const res = await fetch('api/dispatch/get_jobs.php?_=' + new Date().getTime());
        const data = await res.json();

        if (data.success) {
            allJobs = data.data;
            currentTeams = data.teams || [];

            if (IS_ADMIN) {
                const filter = document.getElementById('teamFilter');
                if (filter) {
                    filter.innerHTML = '<option value="all">📍 แสดงงานทุกทีม</option><option value="unassigned">⏳ เฉพาะที่ยังไม่จ่ายงาน</option>';
                    currentTeams.forEach(t => {
                        filter.innerHTML += `<option value="${t.id}">👥 ทีม ${t.team_name}</option>`;
                    });
                }
            }
            renderTeamList();
            renderUI();
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

function renderTeamList() {
    const container = document.getElementById('teamListContainer');
    if (!container) return;
    container.innerHTML = '';
    currentTeams.forEach((t, i) => {
        const div = document.createElement('div');
        div.className = 'flex items-center bg-white border border-slate-100 px-3 py-2 rounded-xl shadow-sm space-x-2 animate__animated animate__fadeInUp';
        div.innerHTML = `
            <div class="w-3 h-3 rounded-full shadow-inner" style="background-color: ${getColor(i)}"></div>
            <span class="text-[10px] md:text-xs font-black text-slate-700 uppercase tracking-tight">${t.team_name}</span>
            <button onclick="handleDeleteTeam(${t.id})" class="text-slate-300 hover:text-rose-500 transition-colors pl-2">✕</button>
        `;
        container.appendChild(div);
    });
}

function openDispatchModal() {
    const unassignedJobs = allJobs.filter(j => !j.team_id).length;
    if (unassignedJobs === 0) return Swal.fire('แจ้งเตือน', 'ไม่มีงานที่รอจ่ายในระบบ', 'info');

    document.getElementById('unassignedCount').textContent = unassignedJobs;
    const container = document.getElementById('dispatchTeamList');
    container.innerHTML = '';

    if (currentTeams.length === 0) {
        container.innerHTML = '<p class="text-center text-slate-500 py-6 font-bold text-sm">กรุณาสร้างทีมก่อนทำการจ่ายงาน</p>';
        document.getElementById('confirmDispatchBtn').disabled = true;
    } else {
        document.getElementById('confirmDispatchBtn').disabled = false;
        currentTeams.forEach((t, i) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-4 bg-white rounded-2xl mb-2 border border-slate-100 shadow-sm';
            div.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md" style="background-color: ${getColor(i)}">${t.team_name.charAt(0)}</div>
                    <span class="font-black text-slate-700 text-xs md:text-sm uppercase">${t.team_name}</span>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="number" id="dist-quota-${t.id}" value="0" min="0" max="${unassignedJobs}" class="w-20 px-3 py-2.5 rounded-xl border border-slate-200 text-center font-black text-indigo-600 focus:ring-2 focus:ring-indigo-500 bg-slate-50">     
                    <span class="text-[10px] md:text-xs font-black text-slate-400 uppercase">งาน</span>
                </div>
            `;
            container.appendChild(div);
        });
    }

    const modal = document.getElementById('dispatchModal');
    modal.classList.remove('hidden');
}

function closeDispatchModal() {
    document.getElementById('dispatchModal').classList.add('hidden');
}

async function handleAddTeam() {
    const input = document.getElementById('newTeamName');
    if (!input) return;
    const name = input.value.trim();
    if (!name) return Swal.fire('แจ้งเตือน', 'กรุณาระบุชื่อทีม', 'warning');

    showLoader('กำลังสร้างทีมใหม่...');
    try {
        const res = await fetch('api/dispatch/teams/save_team.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ team_name: name })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'สร้างทีมใหม่เรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            input.value = '';
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function handleDeleteTeam(id) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบทีม?',
        text: 'งานที่จ่ายให้ทีมนี้ไปแล้วจะกลับไปรอจ่ายใหม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    showLoader('กำลังลบทีม...');
    try {
        const res = await fetch('api/dispatch/teams/delete_team.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'ลบทีมเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function runAutoDispatch() {
    const quotas = [];
    currentTeams.forEach(t => {
        const el = document.getElementById(`dist-quota-${t.id}`);
        if (el) {
            const val = parseInt(el.value) || 0;
            if (val > 0) quotas.push({ team_name: t.team_name, limit: val });
        }
    });

    if (quotas.length === 0) return Swal.fire('แจ้งเตือน', 'กรุณาระบุจำนวนงาน', 'warning');

    closeDispatchModal();
    showLoader('กำลังประมวลผลจ่ายงานอัตโนมัติ...');
    try {
        const res = await fetch('api/dispatch/auto_assign.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ quotas })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: `จ่ายงานให้ทีมสำเร็จ ${data.assigned} รายการ`, icon: 'success', timer: 2000, showConfirmButton: false });
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'ระบบจ่ายงานขัดข้อง', 'error');
    } finally {
        hideLoader();
    }
}

function handleSelectAll(e) {
    const checked = e.target.checked;
    document.querySelectorAll('.job-checkbox').forEach(cb => {
        cb.checked = checked;
        const id = cb.dataset.id;
        if (checked) selectedJobIds.add(id);
        else selectedJobIds.delete(id);
    });
    updateSelectionUI();
}

function updateSelectionUI() {
    const bar = document.getElementById('selectionActions');
    const countText = document.getElementById('selectedCount');
    if (bar && countText) {
        if (selectedJobIds.size > 0) {
            bar.classList.remove('hidden');
            countText.textContent = selectedJobIds.size;
        } else {
            bar.classList.add('hidden');
        }
    }
}

async function handleBulkDelete() {
    if (selectedJobIds.size === 0) return;
    
    const result = await Swal.fire({
        title: 'ลบข้อมูลที่เลือก?',
        text: `คุณกำลังลบงาน ${selectedJobIds.size} รายการ (ลบถาวร)`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบทิ้ง',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    showLoader('กำลังลบข้อมูลที่เลือก...');
    try {
        const ids = Array.from(selectedJobIds);
        const res = await fetch('api/dispatch/bulk_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ ids })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'ลบข้อมูลเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            selectedJobIds.clear();
            const selectAll = document.getElementById('selectAllJobs');
            if (selectAll) selectAll.checked = false;
            updateSelectionUI();
            loadJobs();
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'ลบข้อมูลล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function handleDeleteAllJobs() {
    const result = await Swal.fire({
        title: '🚨 ลบงานทั้งหมด?',
        text: 'ต้องการล้างระบบเพื่อนำเข้าใหม่ใช่หรือไม่?',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ใช่, ล้างระบบ',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    showLoader('กำลังล้างฐานข้อมูลงาน...');
    try {
        const res = await fetch('api/dispatch/delete_all_jobs.php');
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'ล้างระบบเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function handleClearAssignments() {
    const result = await Swal.fire({
        title: '🔄 ล้างการมอบหมายงาน?',
        text: 'งานทุกรายการจะถูกดึงกลับมารอจ่ายใหม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'ล้างการจ่าย',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    showLoader('กำลังดึงงานกลับเข้าระบบ...');
    try {
        const res = await fetch('api/dispatch/clear_assignments.php');
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'รีเซ็ตการมอบหมายเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function runOptimizeRoute() {
    showLoader('📍 กำลังคำนวณและจัดคิวเส้นทางอัจฉริยะ...');
    try {
        const res = await fetch('api/dispatch/optimize_route.php');
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'จัดลำดับงานที่ใกล้ที่สุดให้เรียบร้อยแล้ว!', icon: 'success', timer: 2000, showConfirmButton: false });
            loadJobs(); 
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

// 🌟 ฟังก์ชันเรนเดอร์ตาราง พร้อม Animation
function renderUI() {
    const tbody = document.getElementById('jobTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    let teamVal = 'all';
    const teamEl = document.getElementById('teamFilter');
    if (IS_ADMIN && teamEl) teamVal = teamEl.value;

    const dateVal = document.getElementById('dateFilter')?.value;
    const limitVal = document.getElementById('limitFilter')?.value;

    let filteredJobs = allJobs;

    if (teamVal === 'unassigned') {
        filteredJobs = filteredJobs.filter(j => !j.team_id);
    } else if (teamVal !== 'all') {
        filteredJobs = filteredJobs.filter(j => j.team_id == teamVal);
    }
    if (dateVal) {
        filteredJobs = filteredJobs.filter(j => j.plan_arrival_date === dateVal);
    }

    const totalCount = filteredJobs.length;
    if (limitVal && limitVal !== 'all') {
        filteredJobs = filteredJobs.slice(0, parseInt(limitVal));
    }

    const countBadge = document.getElementById('jobCountBadge');
    if (countBadge) countBadge.textContent = totalCount;

    if (filteredJobs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-20 text-slate-400 font-bold bg-slate-50/50">📭 ไม่พบข้อมูลงานในระบบ</td></tr>';
        return;
    }

    // สร้างแถวทีละแถว และหน่วงเวลา (Staggered animation) เล็กน้อย
    filteredJobs.forEach((job, index) => {
        const row = createJobRow(job, index);
        row.style.animationDelay = `${(index % 20) * 0.03}s`; // ดีเลย์เพื่อความสมูท 
        tbody.appendChild(row);
    });
}

function createJobRow(job, index) {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-indigo-50/40 transition-colors group cursor-pointer animate-row bg-white';
    const isSelected = selectedJobIds.has(String(job.id));

    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#94a3b8';

    tr.innerHTML = `
        <td class="text-center">
            <input type="checkbox" class="job-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer shadow-sm" 
                data-id="${job.id}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); toggleJobSelection('${job.id}')">
        </td>
        <td class="text-center">
            <div class="w-8 h-8 md:w-9 md:h-9 mx-auto rounded-xl flex items-center justify-center text-xs md:text-sm font-black text-white shadow-md transition-transform group-hover:scale-110" style="background-color: ${color}">
                ${job.seq || '-'}
            </div>
        </td>
        <td><span class="font-black text-slate-700 tracking-tight">${job.access_no}</span></td>
        <td>
            <div class="font-bold text-slate-800 line-clamp-1">${job.customer}</div>
            ${job.package ? `<div class="text-[9px] text-indigo-500 line-clamp-1 mt-0.5">${job.package}</div>` : ''}
        </td>
        <td>
            <span class="text-[10px] md:text-[11px] font-black text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100">📞 ${job.phone?.split(',')[0] || '-'}</span>
        </td>
        <td><p class="text-[10px] md:text-xs text-slate-500 line-clamp-1 md:line-clamp-2 max-w-[250px] leading-relaxed">📍 ${job.address}</p></td>
        <td><span class="text-[10px] md:text-xs font-black text-slate-400 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">${job.plan_arrival_date || '-'}</span></td>
        <td class="text-right pr-6">
            ${job.team_name 
                ? `<span class="text-[9px] md:text-[10px] font-black uppercase tracking-wider px-3 py-1.5 rounded-xl shadow-sm" style="background-color: ${color}15; color: ${color}; border: 1px solid ${color}30">${job.team_name}</span>`
                : `<span class="text-[9px] font-black text-slate-300 italic uppercase bg-slate-50 px-3 py-1 rounded-full">รอจัดทีม</span>`}
        </td>
    `;

    tr.onclick = () => showJobPopup(job, color);
    return tr;
}

function toggleJobSelection(id) {
    const strId = String(id);
    if (selectedJobIds.has(strId)) selectedJobIds.delete(strId);
    else selectedJobIds.add(strId);
    updateSelectionUI();
}

function showJobPopup(job, color) {
    const gmapsLink = `https://www.google.com/maps/dir/?api=1&destination=${job.lat},${job.lng}`;
    
    let actionButtons = '';
    if (!IS_ADMIN) {
        actionButtons = `
            <div class="grid grid-cols-2 gap-3 mt-4">
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'completed')" class="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-emerald-200 transition-all text-xs uppercase tracking-widest hover:-translate-y-1">
                    ✅ ปิดจ๊อบ
                </button>
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'failed')" class="bg-rose-500 hover:bg-rose-600 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-rose-200 transition-all text-xs uppercase tracking-widest hover:-translate-y-1">
                    ❌ ทำไม่สำเร็จ
                </button>
            </div>
        `;
    }

    Swal.fire({
        title: `<div class="text-indigo-600 font-black tracking-tight text-xl">${job.access_no}</div>`,
        html: `
            <div class="text-left mt-2 space-y-4 font-sans">
                <div class="flex items-center justify-between bg-gradient-to-r from-slate-50 to-white p-4 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-md" style="background-color: ${color}">
                            ${job.seq || '-'}
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">ทีมรับผิดชอบ</p>
                            <p class="text-sm font-black text-slate-700">${job.team_name || '⏳ ยังไม่จ่ายงาน'}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white border border-slate-100 p-5 rounded-3xl shadow-md shadow-slate-100/50 space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-50 rounded-bl-full -z-10 opacity-50"></div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ชื่อลูกค้า</p>
                        <p class="text-lg font-black text-slate-800 leading-tight">${job.customer}</p>
                    </div>
                    <div class="space-y-3">
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 flex items-start space-x-3">
                            <span class="opacity-60 text-lg mt-0.5">📍</span>
                            <p class="text-xs text-slate-600 leading-relaxed font-bold">${job.address}</p>
                        </div>
                        <div class="bg-emerald-50 p-3.5 rounded-2xl border border-emerald-100 flex items-center space-x-3">
                            <span class="opacity-80 text-lg">📞</span>
                            <p class="text-sm font-black text-emerald-700">${job.phone || 'ไม่ระบุเบอร์โทร'}</p>
                        </div>
                        
                        ${job.package ? `
                        <div class="bg-indigo-50/50 p-3.5 rounded-2xl border border-indigo-100/50">
                            <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-1">แพ็กเกจ</p>
                            <p class="text-xs font-bold text-indigo-700">${job.package}</p>
                        </div>` : ''}
                        
                        ${job.remark ? `
                        <div class="bg-rose-50/50 p-3.5 rounded-2xl border border-rose-100/50">
                            <p class="text-[9px] font-black text-rose-400 uppercase tracking-widest mb-1">หมายเหตุการทำงาน</p>
                            <p class="text-xs font-bold text-rose-700 leading-relaxed">${job.remark}</p>
                        </div>` : ''}
                    </div>
                </div>
                ${actionButtons}
            </div>
        `,
        showCancelButton: true,
        showCloseButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: '🚀 เริ่มนำทางเส้นทางนี้',
        cancelButtonText: '<span class="text-slate-500 font-bold">ปิดหน้าต่าง</span>',
        customClass: {
            popup: 'rounded-[2rem] md:rounded-[3rem] p-4 md:p-6 shadow-2xl z-[9999]',
            title: 'text-left pb-4 border-b border-slate-100',
            confirmButton: 'rounded-2xl px-6 py-4 font-black tracking-widest w-full mt-2 shadow-lg shadow-indigo-200 hover:-translate-y-1 transition-all text-xs',
            cancelButton: 'rounded-2xl px-6 py-4 font-black w-full mt-3 hover:bg-slate-200 transition-all text-xs',
            actions: 'flex-col w-full px-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(gmapsLink, '_blank');
        }
    });
}

window.updateJobStatus = async function(jobId, status) {
    let remark = '';

    if (status === 'failed') {
        const { value: text } = await Swal.fire({
            title: 'ระบุสาเหตุที่ไม่สำเร็จ',
            input: 'textarea',
            inputPlaceholder: 'พิมพ์สาเหตุที่นี่...',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-black px-6', cancelButton: 'rounded-xl font-black px-6' }
        });

        if (!text) {
            if(text !== undefined) Swal.fire('แจ้งเตือน', 'กรุณาระบุหมายเหตุ', 'warning');
            return;
        }
        remark = text;
    } else {
        const confirm = await Swal.fire({
            title: 'ยืนยันปิดจ๊อบ?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'ใช่, ปิดงานเลย',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-black px-6', cancelButton: 'rounded-xl font-black px-6' }
        });
        if (!confirm.isConfirmed) return;
    }

    showLoader('กำลังบันทึกสถานะงาน...');

    try {
        const res = await fetch('api/dispatch/update_job_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: jobId, status: status, remark: remark })
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: 'บันทึกสถานะเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
            loadJobs(); 
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
    } finally {
        hideLoader();
    }
};

// ==========================================
// IMPORT / EXPORT EXCEL FUNCTIONS
// ==========================================
function handleExcelUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    Swal.fire({
        title: 'นำเข้าข้อมูล Excel?',
        text: 'ระบบจะล้างงานเดิมที่รอจ่าย และนำข้อมูลชุดใหม่เข้าสู่ระบบ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'เริ่มนำเข้า',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) processExcel(file);
        else e.target.value = '';
    });
}

function processExcel(file) {
    showLoader('กำลังอ่านไฟล์และดึงพิกัดแผนที่...');
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(worksheet, {header: 1});

            if (rows.length < 2) throw new Error('ไฟล์ว่างเปล่า หรือรูปแบบไม่ถูกต้อง');

            const headerRow = rows[0].map(h => String(h).toLowerCase().replace(/\s/g, ''));
            const findCol = (keys) => headerRow.findIndex(h => keys.some(k => h.includes(k)));

            const phoneCols = [];
            headerRow.forEach((h, idx) => {
                if (h.includes('phone') || h.includes('tel') || h.includes('เบอร์') || h.includes('mobile')) phoneCols.push(idx);
            });

            const accessIdx = findCol(['access', 'รหัสงาน']);
            const latIdx = findCol(['lat', 'latitude', 'ละติจูด']);
            const lngIdx = findCol(['lng', 'long', 'longitude', 'ลองจิจูด']); 
            const custIdx = findCol(['customer', 'ชื่อลูกค้า']);
            const addrIdx = findCol(['address', 'ที่อยู่']);
            const dateIdx = findCol(['date', 'วัน', 'arrival']);
            const packageIdx = findCol(['package', 'แพ็กเกจ', 'แพคเกจ']);
            const remarkIdx = findCol(['remark', 'หมายเหตุ']);

            if (accessIdx === -1 || latIdx === -1 || lngIdx === -1) throw new Error('ไฟล์ Excel ขาดหัวคอลัมน์สำคัญ (รหัสงาน, ละติจูด, ลองจิจูด)');

            const parsedJobs = [];
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                if (row[accessIdx] && row[latIdx] && row[lngIdx]) {
                    let phones = [];
                    phoneCols.forEach(pIdx => { if (row[pIdx]) phones.push(String(row[pIdx]).trim()); });
                    let cleanPhone = phones.join(',').split(/[\/,|\s]+/).filter(p => p.length > 5).join(',');      

                    let planDate = row[dateIdx];
                    if (planDate && !isNaN(planDate) && String(planDate).indexOf('-') === -1 && String(planDate).indexOf('/') === -1) {
                        const dateObj = new Date((planDate - 25569) * 86400 * 1000);
                        planDate = dateObj.toISOString().split('T')[0];
                    } else if (planDate && typeof planDate === 'string') {
                        planDate = planDate.trim().split(' ')[0];
                        if (planDate.includes('/')) {
                            let parts = planDate.split('/');
                            if (parts.length === 3 && parts[2].length === 4) {
                                planDate = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
                            }
                        }
                    }

                    parsedJobs.push({
                        access_no: String(row[accessIdx]),
                        customer: row[custIdx] || 'ไม่ระบุชื่อ',
                        phone: cleanPhone,
                        address: row[addrIdx] || '-',
                        lat: String(row[latIdx]).replace(/[^0-9.-]/g, ''),
                        lng: String(row[lngIdx]).replace(/[^0-9.-]/g, ''),
                        plan_arrival_date: planDate || null,
                        package: packageIdx !== -1 ? row[packageIdx] : null,
                        remark: remarkIdx !== -1 ? row[remarkIdx] : null,
                        status: 'Pending'
                    });
                }
            }

            if (parsedJobs.length === 0) throw new Error('ไม่พบข้อมูลงานที่มีพิกัดถูกต้องในไฟล์นี้');

            showLoader('กำลังบันทึกข้อมูลเข้าระบบ...');
            const res = await fetch('api/dispatch/upload_jobs.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ jobs: parsedJobs })
            });
            const rData = await res.json();
            if (rData.success) {
                Swal.fire({ title: 'สำเร็จ', text: `นำเข้า ${rData.imported} งานเรียบร้อย!`, icon: 'success' });
                loadJobs();
            } else {
                throw new Error(rData.error);
            }
        } catch (err) {
            Swal.fire('ข้อผิดพลาด', err.message, 'error');
            hideLoader();
        } finally {
            if (document.getElementById('jobExcelFile')) document.getElementById('jobExcelFile').value = '';
        }
    };
    reader.readAsArrayBuffer(file);
}

async function handleExportExcel() {
    const today = new Date().toISOString().split('T')[0];
    const currentDate = document.getElementById('dateFilter')?.value || today;
    
    Swal.fire({
        title: '📊 ส่งออกไฟล์ Excel',
        html: `
            <div class="text-left mt-4 font-sans space-y-3">
                <button id="exportAllBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3.5 rounded-2xl font-black shadow-md shadow-indigo-100 transition-all text-xs uppercase tracking-wider">📥 โหลดทุกงานในระบบ</button>
                <button id="exportTodayBtn" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3.5 rounded-2xl font-black shadow-md shadow-emerald-100 transition-all text-xs uppercase tracking-wider">📅 โหลดเฉพาะงานวันนี้</button>
                
                <div class="border-t border-slate-100 mt-4 pt-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">หรือเลือกวันที่:</label>
                    <input type="date" id="exportDateInput" class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:ring-2 focus:ring-sky-500 text-slate-700 font-bold mb-3" value="${currentDate}">
                    
                    <button id="exportCustomBtn" class="w-full bg-sky-500 hover:bg-sky-600 text-white py-3 rounded-2xl font-black shadow-md shadow-sky-100 transition-all text-[11px] uppercase tracking-widest">โหลดตามวันที่เลือก</button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'rounded-3xl p-6 shadow-2xl', title: 'font-black text-slate-800' },
        didOpen: () => {
            document.getElementById('exportAllBtn').addEventListener('click', () => { Swal.close(); exportDataToExcel('all'); });
            document.getElementById('exportTodayBtn').addEventListener('click', () => { Swal.close(); exportDataToExcel('today'); });
            document.getElementById('exportCustomBtn').addEventListener('click', () => {
                const date = document.getElementById('exportDateInput').value;
                if (!date) return Swal.showValidationMessage('กรุณาระบุวันที่');
                Swal.close(); exportDataToExcel(date);
            });
        }
    });
}

function exportDataToExcel(filterType) {
    showLoader('กำลังเตรียมไฟล์ Excel...');
    setTimeout(() => {
        let filtered = [];
        const todayStr = new Date().toISOString().split('T')[0];
        let fileNameDate = '';

        if (filterType === 'all') { filtered = allJobs; fileNameDate = 'All'; }
        else if (filterType === 'today') { filtered = allJobs.filter(j => j.plan_arrival_date === todayStr); fileNameDate = todayStr; }
        else { filtered = allJobs.filter(j => j.plan_arrival_date === filterType); fileNameDate = filterType; }
        
        if (filtered.length === 0) {
            hideLoader();
            return Swal.fire({ title: 'ไม่พบข้อมูล', text: 'ไม่มีข้อมูลงานในช่วงเวลาที่เลือก', icon: 'info' });
        }

        const exportData = filtered.map(j => ({
            'รหัสงาน (Access No)': j.access_no || '',
            'ชื่อลูกค้า (Customer)': j.customer || '',
            'เบอร์โทร (Phone)': j.phone || '',
            'แพ็กเกจ (Package)': j.package || '',
            'ที่อยู่ (Address)': j.address || '',
            'ละติจูด (Lat)': j.lat || '',
            'ลองจิจูด (Lng)': j.lng || '',
            'หมายเหตุ (Remark)': j.remark || '',
            'วันที่ (Date)': j.plan_arrival_date || '',
            'สถานะ (Status)': j.status || '',
            'ทีมรับผิดชอบ (Team)': j.team_name || 'ยังไม่จ่ายงาน'
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        ws['!cols'] = [{wch: 20}, {wch: 25}, {wch: 15}, {wch: 15}, {wch: 40}, {wch: 15}, {wch: 15}, {wch: 20}, {wch: 15}, {wch: 15}, {wch: 20}];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Dispatch_Jobs");
        XLSX.writeFile(wb, `Dispatch_Jobs_${fileNameDate}.xlsx`);
        
        hideLoader();
    }, 500); // ดีเลย์เล็กน้อยเพื่อให้ Loader โชว์
}