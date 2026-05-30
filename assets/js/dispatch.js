// assets/js/dispatch.js

let allJobs = [];
let currentTeams = []; 
let selectedJobIds = new Set();

// Leaflet map and markers (free, no API key)
let map = null;
let markersGroup = null;

// Clean latitude/longitude from unwanted characters (like $)
function cleanCoordinate(value) {
    if (!value) return null;
    const cleaned = String(value).replace(/[^0-9.-]/g, '').trim();
    const num = parseFloat(cleaned);
    return isNaN(num) ? null : num;
}

function initMap() {
    try {
        if (!document.getElementById('map')) return;
        map = L.map('map').setView([13.736717, 100.523186], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        markersGroup = L.layerGroup().addTo(map);
    } catch (e) {
        console.warn('Leaflet init failed', e);
    }
}

function updateMapMarkers(jobs) {
    if (!window.L || !map || !markersGroup) return;
    markersGroup.clearLayers();
    const valid = (jobs || []).filter(j => {
        const lat = cleanCoordinate(j.lat);
        const lng = cleanCoordinate(j.lng);
        return lat && lng;
    }).map(j => ({
        lat: cleanCoordinate(j.lat),
        lng: cleanCoordinate(j.lng),
        job: j
    }));
    if (valid.length === 0) return;
    
    valid.forEach((v, idx) => {
        try {
            const teamIdx = currentTeams.findIndex(t => t.id == v.job.team_id);
            const color = v.job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
            
            // Create custom marker icon with team color
            const icon = L.divIcon({
                html: `<div style="background-color: ${color}; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; border: 2px solid white; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">📍</div>`,
                iconSize: [32, 32],
                popupAnchor: [0, -16]
            });
            
            const marker = L.marker([v.lat, v.lng], { icon });
            const popup = `
                <div style="min-width:200px; font-family: 'Segoe UI', sans-serif;">
                    <div style="background: ${color}; color: white; padding: 8px; border-radius: 4px 4px 0 0; font-weight: 700; margin: -4px -4px 8px -4px;">${v.job.access_no || 'N/A'}</div>
                    <div style="padding: 8px;">
                        <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">${v.job.customer || 'ไม่ระบุชื่อ'}</div>
                        <div style="font-size: 11px; color: #6b7280; margin-bottom: 8px; display: flex; gap: 4px;"><span>📍</span><span>${v.job.address || '-'}</span></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11px;">
                            <div style="background: #f3f4f6; padding: 6px; border-radius: 4px;"><div style="color: #9ca3af; font-size: 10px;">วันที่</div><div style="font-weight: 600; color: #374151;">${v.job.plan_arrival_date || '-'}</div></div>
                            <div style="background: #f3f4f6; padding: 6px; border-radius: 4px;"><div style="color: #9ca3af; font-size: 10px;">ทีม</div><div style="font-weight: 600; color: ${color};">${v.job.team_name || 'รอจ่าย'}</div></div>
                        </div>
                        <div style="margin-top: 8px;"><a style="display: inline-block; background: ${color}; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600;" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=${v.lat},${v.lng}">🚗 นำทาง</a></div>
                    </div>
                </div>`;
            marker.bindPopup(popup);
            markersGroup.addLayer(marker);
        } catch (e) { console.warn('marker failed', e); }
    });
    
    const bounds = markersGroup.getBounds();
    if (bounds && bounds.isValid && bounds.isValid()) {
        try { map.fitBounds(bounds.pad(0.15)); } catch (e) { }
    }
    
    // Handle map resize
    setTimeout(() => { if (map) map.invalidateSize(); }, 300);
}

const teamColors = [
    '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#4f46e5', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadJobs();

    document.getElementById('navigateSelectedBtn')?.addEventListener('click', handleNavigateSelected);

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

function handleNavigateSelected() {
    if (selectedJobIds.size === 0) return;
    
    const selectedIdsArray = Array.from(selectedJobIds);
    let jobsToNav = allJobs.filter(j => selectedIdsArray.includes(String(j.id)));
    
    // เรียงตามลำดับคิว (seq) ถ้ามี
    jobsToNav.sort((a, b) => (a.seq || 999) - (b.seq || 999));
    
    const validJobs = jobsToNav.filter(j => {
        const lat = cleanCoordinate(j.lat);
        const lng = cleanCoordinate(j.lng);
        return lat && lng;
    });
    
    if (validJobs.length === 0) {
        return Swal.fire('ไม่พบพิกัด', 'งานที่เลือกไม่มีข้อมูลพิกัดละติจูด/ลองจิจูด', 'warning');
    }
    
    if (validJobs.length === 1) {
        const lat = cleanCoordinate(validJobs[0].lat);
        const lng = cleanCoordinate(validJobs[0].lng);
        const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
        window.open(url, '_blank');
        return;
    }
    
    const destination = validJobs[validJobs.length - 1];
    const destLat = cleanCoordinate(destination.lat);
    const destLng = cleanCoordinate(destination.lng);
    const waypoints = validJobs.slice(0, validJobs.length - 1).map(j => {
        const lat = cleanCoordinate(j.lat);
        const lng = cleanCoordinate(j.lng);
        return `${lat},${lng}`;
    }).join('|');
    
    const url = `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}&waypoints=${waypoints}&travelmode=driving`;
    window.open(url, '_blank');
}

function showLoader(message = 'กำลังโหลด...') { 
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
        setTimeout(() => loader.classList.add('hidden'), 200); 
    }
}

async function loadJobs() {
    showLoader('ซิงค์ข้อมูล...');
    try {
        const res = await fetch('api/dispatch/get_jobs.php?_=' + new Date().getTime());
        const data = await res.json();

        if (data.success) {
            allJobs = data.data;
            currentTeams = data.teams || [];

            if (IS_ADMIN) {
                const filter = document.getElementById('teamFilter');
                if (filter) {
                    filter.innerHTML = '<option value="all">📍 ทุกทีม</option><option value="unassigned">⏳ ยังไม่จ่าย</option>';
                    currentTeams.forEach(t => { 
                        filter.innerHTML += `<option value="${t.id}">${t.team_name}</option>`; 
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

// 🌟 เรนเดอร์ปุ่มทีมแบบ Compact (เล็กและกระชับ)
function renderTeamList() {
    const container = document.getElementById('teamListContainer');
    if (!container) return;
    container.innerHTML = '';
    currentTeams.forEach((t, i) => {
        const div = document.createElement('div');
        div.className = 'flex items-center bg-white border border-slate-200 px-2 py-1 rounded text-[9px] shadow-sm animate__animated animate__fadeIn space-x-1.5';
        div.innerHTML = `
            <div class="w-2 h-2 rounded-full" style="background-color: ${getColor(i)}"></div>
            <span class="font-bold text-slate-700">${t.team_name}</span>
            <button onclick="handleDeleteTeam(${t.id})" class="text-slate-300 hover:text-rose-500 pl-1 font-black">✕</button>
        `;
        container.appendChild(div);
    });
}

function openDispatchModal() {
    const unassignedJobs = allJobs.filter(j => !j.team_id).length;
    if (unassignedJobs === 0) return Swal.fire('แจ้งเตือน', 'ไม่มีงานรอจ่าย', 'info');

    document.getElementById('unassignedCount').textContent = unassignedJobs;
    const container = document.getElementById('dispatchTeamList');
    container.innerHTML = '';

    if (currentTeams.length === 0) {
        container.innerHTML = '<p class="text-center text-slate-500 py-4 text-[10px] font-bold">ไม่มีทีมในระบบ กรุณาสร้างทีมก่อน</p>';
        document.getElementById('confirmDispatchBtn').disabled = true;
    } else {
        document.getElementById('confirmDispatchBtn').disabled = false;
        currentTeams.forEach((t, i) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 bg-white rounded border border-slate-100';
            div.innerHTML = `
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 rounded flex items-center justify-center text-white font-bold text-[10px]" style="background-color: ${getColor(i)}">${t.team_name.charAt(0)}</div>
                    <span class="font-bold text-slate-700 text-[11px]">${t.team_name}</span>
                </div>
                <div class="flex items-center space-x-1">
                    <input type="number" id="dist-quota-${t.id}" value="0" min="0" max="${unassignedJobs}" class="w-14 px-1 py-1 rounded border border-slate-200 text-center font-bold text-indigo-600 text-[11px] h-6 focus:ring-1 focus:ring-indigo-500">     
                </div>
            `;
            container.appendChild(div);
        });
    }
    document.getElementById('dispatchModal').classList.remove('hidden');
}

function closeDispatchModal() { 
    document.getElementById('dispatchModal').classList.add('hidden'); 
}

async function handleAddTeam() {
    const input = document.getElementById('newTeamName');
    if (!input) return;
    const name = input.value.trim();
    if (!name) return;

    showLoader('เพิ่มทีม...');
    try {
        const res = await fetch('api/dispatch/teams/save_team.php', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ team_name: name })
        });
        const data = await res.json();
        if (data.success) { 
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
    if (!confirm('ลบทีมนี้? งานที่จ่ายไปแล้วจะกลับไปรอจ่ายใหม่')) return;
    
    showLoader('ลบทีม...');
    try {
        const res = await fetch('api/dispatch/teams/delete_team.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ id }) 
        });
        const data = await res.json();
        if (data.success) {
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
        if (el && parseInt(el.value) > 0) quotas.push({ team_name: t.team_name, limit: parseInt(el.value) });
    });

    if (quotas.length === 0) return alert('กรุณาระบุจำนวนงานที่ต้องการจ่าย');

    closeDispatchModal();
    showLoader('กำลังกระจายงาน...');
    try {
        const res = await fetch('api/dispatch/auto_assign.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ quotas }) 
        });
        const data = await res.json();
        if (data.success) {
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) { 
        Swal.fire('ข้อผิดพลาด', 'ระบบทำงานผิดพลาด', 'error');
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
    if (!confirm(`ยืนยันการลบงานที่เลือกจำนวน ${selectedJobIds.size} รายการ? (ไม่สามารถกู้คืนได้)`)) return;
    
    showLoader('ลบข้อมูล...');
    try {
        const ids = Array.from(selectedJobIds);
        const res = await fetch('api/dispatch/bulk_delete.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ ids }) 
        });
        const data = await res.json();
        if (data.success) {
            selectedJobIds.clear();
            const selectAll = document.getElementById('selectAllJobs');
            if (selectAll) selectAll.checked = false;
            updateSelectionUI();
            loadJobs();
        }
    } catch (e) { 
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally { 
        hideLoader(); 
    }
}

async function handleDeleteAllJobs() {
    if (!confirm('ล้างข้อมูลงานทั้งหมดในระบบ? (สำหรับเตรียมนำเข้าใหม่)')) return;
    
    showLoader('ล้างข้อมูล...');
    try {
        const res = await fetch('api/dispatch/delete_all_jobs.php');
        const data = await res.json();
        if (data.success) loadJobs();
    } catch (e) { 
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally { 
        hideLoader(); 
    }
}

async function handleClearAssignments() {
    if (!confirm('ยกเลิกการจ่ายงานทั้งหมด? (งานจะกลับไปสถานะรอจ่าย)')) return;
    
    showLoader('ดึงงานกลับ...');
    try {
        const res = await fetch('api/dispatch/clear_assignments.php');
        const data = await res.json();
        if (data.success) loadJobs();
    } catch (e) { 
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally { 
        hideLoader(); 
    }
}

async function runOptimizeRoute() {
    showLoader('คำนวณและจัดคิวเส้นทาง...');
    try {
        const res = await fetch('api/dispatch/optimize_route.php');
        const data = await res.json();
        if (data.success) loadJobs(); 
    } catch (e) { 
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally { 
        hideLoader(); 
    }
}

function renderUI() {
    const container = document.getElementById('jobTableBody');
    if (!container) return;
    container.innerHTML = '';

    let teamVal = 'all';
    const teamEl = document.getElementById('teamFilter');
    if (IS_ADMIN && teamEl) teamVal = teamEl.value;

    const dateVal = document.getElementById('dateFilter')?.value;
    const limitVal = document.getElementById('limitFilter')?.value;

    let filteredJobs = allJobs;

    if (teamVal === 'unassigned') filteredJobs = filteredJobs.filter(j => !j.team_id);
    else if (teamVal !== 'all') filteredJobs = filteredJobs.filter(j => j.team_id == teamVal);
    
    if (dateVal) filteredJobs = filteredJobs.filter(j => j.plan_arrival_date === dateVal);

    const totalCount = filteredJobs.length;
    if (limitVal && limitVal !== 'all') filteredJobs = filteredJobs.slice(0, parseInt(limitVal));

    const countBadge = document.getElementById('jobCountBadge');
    if (countBadge) countBadge.textContent = totalCount;

    // 🌟 ถ้าไม่มีข้อมูล ให้แสดงกล่องข้อความเปล่าๆ
    if (filteredJobs.length === 0) {
        container.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-dashed border-slate-300">
                <div class="text-4xl mb-3 opacity-50">📭</div>
                <div class="text-slate-400 font-bold">ไม่พบข้อมูลงาน</div>
            </div>`;
        updateMapMarkers(filteredJobs);
        return;
    }

    filteredJobs.forEach((job, index) => {
        const card = createJobRow(job, index);
        card.style.animationDelay = `${(index % 25) * 0.03}s`; // Stagger animation
        container.appendChild(card);
    });

    try { updateMapMarkers(filteredJobs); } catch (e) { console.warn(e); }
}

// 🌟 ตัวแปรสำคัญ: บีบ Padding ลง และจำกัดข้อความให้อยู่ใน 1 บรรทัดด้วย truncate-text
// 🌟 ฟังก์ชันเรนเดอร์ตาราง (UI/UX อัปเดตใหม่)
// 🌟 ฟังก์ชันเรนเดอร์ตาราง (แก้ไข UI/UX สมบูรณ์แบบ)
// 🌟 ฟังก์ชันเรนเดอร์ตารางแบบ ข้อมูลมาครบ 100% ไม่มีการซ่อนข้อความ
// 🌟 ฟังก์ชันเรนเดอร์ตารางที่แสดงข้อมูลครบ 100% จัดเรียงสวยงาม
function createJobRow(job, index) {
    const div = document.createElement('div');
    div.className = 'bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-1 hover:border-indigo-300 transition-all duration-300 cursor-pointer flex flex-col p-4 animate-row relative group';
    
    const isSelected = selectedJobIds.has(String(job.id));
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#94a3b8';

    const teamBadge = job.team_name 
        ? `<div class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[10px] font-bold whitespace-nowrap" style="background-color: ${color}15; color: ${color}; border: 1px solid ${color}30">
             <span class="w-2 h-2 rounded-full mr-1.5" style="background-color: ${color}"></span>
             ${job.team_name}
           </div>`
        : `<div class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[10px] font-bold text-slate-500 bg-slate-100 border border-slate-200 whitespace-nowrap">
             <svg class="w-3 h-3 mr-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> รอจ่ายงาน
           </div>`;

    const phoneVal = job.phone ? job.phone.split(',')[0] : '-';

    const packageHtml = (job.package && job.package !== '-' && job.package !== 'null') 
        ? `<div class="inline-flex items-center px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-black border border-indigo-100 uppercase">📦 ${job.package}</div>`
        : '';

    const remarkHtml = (job.remark && job.remark !== '-' && job.remark !== 'null')
        ? `<div class="mt-3 bg-rose-50/80 p-2.5 rounded-xl border border-rose-100">
             <div class="text-[10px] font-black text-rose-500 mb-0.5 flex items-center">หมายเหตุ</div>
             <div class="text-[11px] text-rose-700 font-bold leading-relaxed break-words">${job.remark}</div>
           </div>`
        : '';

    // 🌟 โครงสร้าง HTML ภายในการ์ด (Card Layout)
    div.innerHTML = `
        <div class="flex justify-between items-start mb-3">
            <div class="flex items-center gap-2.5">
                <div class="p-1 -ml-1 rounded-md hover:bg-slate-100" onclick="event.stopPropagation()">
                    <input type="checkbox" class="job-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" 
                        data-id="${job.id}" ${isSelected ? 'checked' : ''} onchange="toggleJobSelection('${job.id}')">
                </div>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-[12px] font-black text-white shadow-sm" style="background-color: ${color}">
                    ${job.seq || '-'}
                </div>
                <div class="font-black text-slate-800 text-[14px] tracking-tight group-hover:text-indigo-600 transition-colors">${job.access_no}</div>
            </div>
            <div class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-1.5 rounded-lg border border-slate-100 flex items-center shadow-sm">
                📅 ${job.plan_arrival_date || '-'}
            </div>
        </div>

        <div class="mb-3">
            <div class="font-bold text-slate-700 text-[13px] leading-snug break-words mb-2">${job.customer}</div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="inline-flex items-center text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    📞 ${phoneVal}
                </div>
                ${packageHtml}
            </div>
        </div>

        <div class="flex-1 bg-slate-50/80 p-3 rounded-xl border border-slate-100 group-hover:bg-indigo-50/40 transition-colors flex flex-col justify-center">
            <div class="flex items-start">
                <div class="text-[11px] text-slate-600 font-medium leading-relaxed break-words whitespace-normal line-clamp-2" title="${job.address}">
                    📍 ${job.address}
                </div>
            </div>
        </div>
        
        ${remarkHtml}

        <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between items-center">
            <div class="text-[10px] font-bold text-slate-400 uppercase">ทีมรับผิดชอบ</div>
            ${teamBadge}
        </div>
    `;

    // กดที่การ์ดเพื่อเปิด Popup เหมือนเดิม
    div.onclick = () => showJobPopup(job, color);
    
    return div;
}

function toggleJobSelection(id) {
    const strId = String(id);
    if (selectedJobIds.has(strId)) selectedJobIds.delete(strId);
    else selectedJobIds.add(strId);
    updateSelectionUI();
}

function showJobPopup(job, color) {
    // Clean and validate coordinates before creating link
    const lat = cleanCoordinate(job.lat);
    const lng = cleanCoordinate(job.lng);
    const gmapsLink = lat && lng ? `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}` : null;
    
    let actionButtons = '';
    if (!IS_ADMIN) {
        actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'completed')" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2.5 rounded-lg shadow-sm text-[11px] uppercase">
                    ✅ ปิดจ๊อบ
                </button>
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'failed')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-2.5 rounded-lg shadow-sm text-[11px] uppercase">
                    ❌ ทำไม่สำเร็จ
                </button>
            </div>
        `;
    }

    Swal.fire({
        title: `<div class="text-indigo-600 font-black text-lg">${job.access_no}</div>`,
        html: `
            <div class="text-left mt-1 font-sans">
                <div class="bg-white border border-slate-100 p-4 rounded-xl shadow-sm space-y-3">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">ชื่อลูกค้า</p>
                        <p class="text-sm font-black text-slate-800">${job.customer}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="bg-slate-50 p-2 rounded flex items-start space-x-2 border border-slate-100">
                            <span class="text-xs mt-0.5">📍</span>
                            <p class="text-[11px] text-slate-600 font-bold">${job.address}</p>
                        </div>
                        <div class="bg-emerald-50 p-2 rounded flex items-center space-x-2 border border-emerald-100">
                            <span class="text-xs">📞</span>
                            <p class="text-xs font-black text-emerald-700">${job.phone || 'ไม่ระบุเบอร์โทร'}</p>
                        </div>
                        <div class="flex gap-2">
                            <div class="bg-indigo-50 flex-1 p-2 rounded border border-indigo-100">
                                <p class="text-[9px] font-bold text-indigo-400 uppercase">แพ็กเกจ</p>
                                <p class="text-[11px] font-bold text-indigo-700">${job.package || '-'}</p>
                            </div>
                            <div class="bg-violet-50 flex-1 p-2 rounded border border-violet-100">
                                <p class="text-[9px] font-bold text-violet-400 uppercase">ทีมช่าง</p>
                                <p class="text-[11px] font-bold text-violet-700">${job.team_name || 'รอจ่ายงาน'}</p>
                            </div>
                        </div>
                        ${job.remark ? `
                        <div class="bg-rose-50 p-2 rounded border border-rose-100">
                            <p class="text-[9px] font-bold text-rose-400 uppercase">หมายเหตุ</p>
                            <p class="text-[11px] font-bold text-rose-700">${job.remark}</p>
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
        confirmButtonText: '🚀 นำทาง Map',
        cancelButtonText: '<span class="text-slate-500 font-bold">ปิด</span>',
        customClass: {
            popup: 'rounded-2xl p-4 shadow-xl z-[9999]',
            title: 'text-left pb-2 border-b border-slate-100',
            confirmButton: 'rounded-lg px-4 py-2.5 font-bold w-full mt-2 text-[11px]',
            cancelButton: 'rounded-lg px-4 py-2.5 font-bold w-full mt-2 text-[11px] hover:bg-slate-200',
            actions: 'flex-col w-full px-2'
        }
    }).then((result) => {
        if (result.isConfirmed) window.open(gmapsLink, '_blank');
    });
}

window.updateJobStatus = async function(jobId, status) {
    let remark = '';
    if (status === 'failed') {
        const { value: text } = await Swal.fire({
            title: 'ระบุสาเหตุ', 
            input: 'textarea', 
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน', 
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
        });
        if (!text) { 
            if(text !== undefined) Swal.fire('แจ้งเตือน', 'กรุณาระบุหมายเหตุ', 'warning'); 
            return; 
        }
        remark = text;
    } else {
        if (!confirm('ยืนยันปิดจ๊อบ?')) return;
    }
    
    showLoader('บันทึกสถานะ...');
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
// IMPORT EXCEL
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
        confirmButtonText: 'นำเข้า', 
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) processExcel(file);
        else e.target.value = '';
    });
}

function processExcel(file) {
    showLoader('กำลังอ่านไฟล์ Excel...');
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

            showLoader('บันทึกข้อมูลเข้าระบบ...');
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

// ==========================================
// EXPORT EXCEL
// ==========================================
async function handleExportExcel() {
    exportDataToExcel('all');
}

function exportDataToExcel(filterType) {
    showLoader('เตรียมไฟล์ Excel...');
    setTimeout(() => {
        let filtered = allJobs; // ดึงงานทั้งหมดมาออกรายงาน
        const ws = XLSX.utils.json_to_sheet(filtered.map(j => ({
            'รหัสงาน': j.access_no || '', 
            'ลูกค้า': j.customer || '', 
            'เบอร์โทร': j.phone || '', 
            'แพ็กเกจ': j.package || '', 
            'ที่อยู่': j.address || '',
            'ละติจูด': j.lat || '', 
            'ลองจิจูด': j.lng || '', 
            'หมายเหตุ': j.remark || '', 
            'วันที่': j.plan_arrival_date || '', 
            'ทีม': j.team_name || ''
        })));
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Dispatch");
        XLSX.writeFile(wb, `Dispatch_Jobs.xlsx`);
        hideLoader();
    }, 500);
}