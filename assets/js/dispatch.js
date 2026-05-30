// assets/js/dispatch.js

let allJobs = [];
let currentTeams = []; 
let selectedJobIds = new Set();
let activeDispatchView = 'jobs';

// Leaflet map and markers (free, no API key)
let map = null;
let markersGroup = null;
let jobMarkerMap = new Map();

// Clean latitude/longitude from unwanted characters (like $)
function cleanCoordinate(value) {
    if (value === null || value === undefined || value === '') return null;
    const cleaned = String(value).replace(/[^0-9.-]/g, '').trim();
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : null;
}

function getJobLatLng(job) {
    const lat = cleanCoordinate(job?.lat);
    const lng = cleanCoordinate(job?.lng);
    if (lat === null || lng === null) return null;
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
    return { lat, lng };
}

function hasValue(value) {
    if (value === null || value === undefined) return false;
    const text = String(value).trim();
    return text !== '' && text !== '-' && text.toLowerCase() !== 'null';
}

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function displayValue(value, fallback = '-') {
    return hasValue(value) ? escapeHTML(String(value).trim()) : fallback;
}

function rawValue(value, fallback = '-') {
    return hasValue(value) ? String(value).trim() : fallback;
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function refreshLucideIcons() {
    if (window.lucide?.createIcons) window.lucide.createIcons();
}

function initMap() {
    try {
        if (!document.getElementById('map')) return;
        map = L.map('map', { zoomControl: false }).setView([13.736717, 100.523186], 6);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        markersGroup = L.layerGroup().addTo(map);
    } catch (e) {
        console.warn('Leaflet init failed', e);
    }
}

const teamColors = [
    '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#4f46e5', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

function updateMapMarkers(jobs) {
    if (!window.L || !map || !markersGroup) return;
    markersGroup.clearLayers();
    jobMarkerMap.clear();

    const valid = (jobs || []).map(job => {
        const coords = getJobLatLng(job);
        return coords ? { ...coords, job } : null;
    }).filter(Boolean);

    setText('mapCountBadge', valid.length);
    setText('mapMissingBadge', Math.max((jobs || []).length - valid.length, 0));

    if (valid.length === 0) {
        map.setView([13.736717, 100.523186], 6);
        setTimeout(() => { if (map) map.invalidateSize(); }, 300);
        return;
    }

    valid.forEach((v, idx) => {
        try {
            const teamIdx = currentTeams.findIndex(t => t.id == v.job.team_id);
            const color = v.job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
            const label = displayValue(v.job.seq || idx + 1);
            const icon = L.divIcon({
                className: '',
                html: `<div class="dispatch-marker" style="background-color:${color};"><span>${label}</span></div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -30]
            });

            const marker = L.marker([v.lat, v.lng], { icon });
            marker.bindTooltip(`${rawValue(v.job.seq || idx + 1)}. ${rawValue(v.job.access_no, 'N/A')}`, {
                direction: 'top',
                offset: [0, -28],
                opacity: 0.9
            });
            marker.on('click', () => {
                focusMapOnJob(v.job.id);
                showJobPopup(v.job, color);
            });
            markersGroup.addLayer(marker);
            jobMarkerMap.set(String(v.job.id), marker);
        } catch (e) { console.warn('marker failed', e); }
    });

    if (valid.length === 1) {
        map.setView([valid[0].lat, valid[0].lng], 14);
    } else {
        const bounds = markersGroup.getBounds();
        if (bounds && bounds.isValid && bounds.isValid()) {
            try { map.fitBounds(bounds.pad(0.18), { maxZoom: 13 }); } catch (e) { }
        }
    }

    setTimeout(() => { if (map) map.invalidateSize(); }, 300);
}

function focusMapOnJob(jobId) {
    const marker = jobMarkerMap.get(String(jobId));
    if (!marker || !map) return false;
    const latLng = marker.getLatLng();
    map.setView(latLng, Math.max(map.getZoom(), 14), { animate: true });
    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadJobs();

    document.getElementById('dispatchViewJobsBtn')?.addEventListener('click', () => switchDispatchView('jobs'));
    document.getElementById('dispatchViewMapBtn')?.addEventListener('click', () => switchDispatchView('map'));
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

function switchDispatchView(view) {
    activeDispatchView = view === 'map' ? 'map' : 'jobs';
    const jobsPanel = document.getElementById('jobViewPanel');
    const mapPanel = document.getElementById('mapViewPanel');
    const jobsBtn = document.getElementById('dispatchViewJobsBtn');
    const mapBtn = document.getElementById('dispatchViewMapBtn');

    jobsPanel?.classList.toggle('hidden', activeDispatchView !== 'jobs');
    mapPanel?.classList.toggle('hidden', activeDispatchView !== 'map');
    jobsBtn?.classList.toggle('is-active', activeDispatchView === 'jobs');
    mapBtn?.classList.toggle('is-active', activeDispatchView === 'map');

    renderUI();
    if (activeDispatchView === 'map') {
        setTimeout(() => { if (map) map.invalidateSize(); }, 80);
    }
}

function handleNavigateSelected() {
    if (selectedJobIds.size === 0) return;
    
    const selectedIdsArray = Array.from(selectedJobIds);
    let jobsToNav = allJobs.filter(j => selectedIdsArray.includes(String(j.id)));
    
    // เรียงตามลำดับคิว (seq) ถ้ามี
    jobsToNav.sort((a, b) => (a.seq || 999) - (b.seq || 999));
    
    const validJobs = jobsToNav.filter(j => getJobLatLng(j));
    
    if (validJobs.length === 0) {
        return Swal.fire('ไม่พบพิกัด', 'งานที่เลือกไม่มีข้อมูลพิกัดละติจูด/ลองจิจูด', 'warning');
    }
    
    if (validJobs.length === 1) {
        const { lat, lng } = getJobLatLng(validJobs[0]);
        const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
        window.open(url, '_blank');
        return;
    }

    const destination = validJobs[validJobs.length - 1];
    const { lat: destLat, lng: destLng } = getJobLatLng(destination);
    const waypoints = validJobs.slice(0, validJobs.length - 1).map(j => {
        const { lat, lng } = getJobLatLng(j);
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
    e.target.indeterminate = false;
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
    const filteredJobs = getFilteredJobs();
    const mapJobs = getMapJobs(filteredJobs);

    const totalCount = filteredJobs.length;
    const mappedCount = filteredJobs.filter(j => getJobLatLng(j)).length;
    const assignedCount = filteredJobs.filter(j => j.team_id || hasValue(j.team_name)).length;
    const unassignedCount = totalCount - assignedCount;

    setText('jobCountBadge', totalCount);
    setText('mappedCountBadge', mappedCount);
    setText('assignedCountBadge', assignedCount);
    setText('unassignedCountBadgeMain', unassignedCount);

    renderJobList(container, filteredJobs);
    renderMapJobList(mapJobs);

    try { updateMapMarkers(mapJobs); } catch (e) { console.warn(e); }
    updateSelectionUI();
    refreshLucideIcons();
}

function getFilteredJobs() {
    let teamVal = 'all';
    const teamEl = document.getElementById('teamFilter');
    if (IS_ADMIN && teamEl) teamVal = teamEl.value;

    const dateVal = document.getElementById('dateFilter')?.value;
    let filteredJobs = [...allJobs];

    if (teamVal === 'unassigned') filteredJobs = filteredJobs.filter(j => !j.team_id);
    else if (teamVal !== 'all') filteredJobs = filteredJobs.filter(j => j.team_id == teamVal);
    if (dateVal) filteredJobs = filteredJobs.filter(j => j.plan_arrival_date === dateVal);

    return filteredJobs;
}

function getLimitedJobs(jobs) {
    const limitVal = document.getElementById('limitFilter')?.value;
    if (limitVal && limitVal !== 'all') return jobs.slice(0, parseInt(limitVal));
    return jobs;
}

function getMapJobs(jobs) {
    return jobs
        .filter(job => (job.team_id || hasValue(job.team_name)) && getJobLatLng(job))
        .sort((a, b) => {
            const teamA = rawValue(a.team_name, '');
            const teamB = rawValue(b.team_name, '');
            if (teamA !== teamB) return teamA.localeCompare(teamB, 'th');
            return (parseInt(a.seq || 9999) - parseInt(b.seq || 9999)) || String(a.access_no || '').localeCompare(String(b.access_no || ''), 'th');
        });
}

function renderJobList(container, filteredJobs) {
    if (!container) return;
    container.innerHTML = '';

    const visibleJobs = getLimitedJobs(filteredJobs);

    if (visibleJobs.length === 0) {
        container.innerHTML = `
            <div class="col-span-full min-h-[320px] flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
                <div class="w-12 h-12 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center mb-3"><i data-lucide="inbox" class="w-6 h-6"></i></div>
                <div class="text-slate-500 font-black">ไม่พบข้อมูลงาน</div>
                <div class="text-xs text-slate-400 font-bold mt-1">ลองเปลี่ยนวันที่ ทีม หรือจำนวนรายการที่แสดง</div>
            </div>`;
        syncVisibleSelection([]);
        return;
    }

    const fragment = document.createDocumentFragment();
    visibleJobs.forEach((job, index) => {
        const card = createJobRow(job, index);
        card.style.animationDelay = `${(index % 25) * 0.025}s`;
        fragment.appendChild(card);
    });
    container.appendChild(fragment);

    syncVisibleSelection(visibleJobs);
}

function syncVisibleSelection(visibleJobs) {
    const selectAll = document.getElementById('selectAllJobs');
    if (selectAll) {
        const visibleIds = visibleJobs.map(j => String(j.id));
        const selectedVisible = visibleIds.filter(id => selectedJobIds.has(id)).length;
        selectAll.checked = visibleIds.length > 0 && selectedVisible === visibleIds.length;
        selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleIds.length;
    }
}

function renderMapJobList(mapJobs) {
    const container = document.getElementById('mapJobList');
    if (!container) return;

    setText('mapAssignedCountBadge', mapJobs.length);

    if (mapJobs.length === 0) {
        container.innerHTML = `
            <div class="p-4 text-center">
                <div class="w-10 h-10 mx-auto rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center mb-2"><i data-lucide="map-pin-off" class="w-5 h-5"></i></div>
                <div class="text-xs font-black text-slate-500">ยังไม่มีงานที่มอบหมายพร้อมพิกัด</div>
                <div class="text-[10px] font-bold text-slate-400 mt-1">เลือกทีม/วันที่อื่น หรือกดจ่ายงานอัตโนมัติก่อน</div>
            </div>`;
        return;
    }

    container.innerHTML = mapJobs.map((job, index) => {
        const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
        const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
        const coords = getJobLatLng(job);
        const isCompleted = job.status && (job.status.toLowerCase() === 'completed' || job.status.toLowerCase() === 'failed');
        const actionButtons = !isCompleted ? `
            <div class="grid grid-cols-2 gap-1.5 mt-2 pt-2 border-t border-slate-100">
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'completed')">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>จบงาน
                </button>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-rose-500 text-white hover:bg-rose-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'failed')">
                    <i data-lucide="x-circle" class="w-3 h-3"></i>ไม่สำเร็จ
                </button>
            </div>` : '';
        return `
            <div class="p-3 hover:bg-slate-50 border-b border-slate-100 last:border-b-0 transition-colors space-y-2">
                <button type="button" class="w-full text-left" onclick="showMapJobDetail('${escapeHTML(job.id)}')">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg text-white flex items-center justify-center text-xs font-black shrink-0" style="background:${color};">${displayValue(job.seq || index + 1)}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs font-black text-slate-900 truncate">${displayValue(job.access_no, 'N/A')}</div>
                                <div class="text-[10px] font-black whitespace-nowrap" style="color:${color};">${displayValue(job.team_name, 'ทีม')}</div>
                            </div>
                            <div class="text-[11px] font-bold text-slate-600 truncate mt-1">${displayValue(job.customer, 'ไม่ระบุลูกค้า')}</div>
                            <div class="text-[10px] font-bold text-slate-400 truncate mt-1">${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)}</div>
                        </div>
                    </div>
                </button>
                ${actionButtons}
            </div>`;
    }).join('');
}


function detailItem(label, value) {
    return `
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-2 min-w-0">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-wide">${label}</div>
            <div class="text-[11px] font-bold text-slate-700 mt-1 break-words">${displayValue(value)}</div>
        </div>`;
}

function statusBadge(status) {
    const value = rawValue(status, 'Pending').toLowerCase();
    if (value === 'completed') return '<span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100">เสร็จแล้ว</span>';
    if (value === 'failed') return '<span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-100">ไม่สำเร็จ</span>';
    return '<span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-100">รอดำเนินการ</span>';
}

function createJobRow(job, index) {
    const div = document.createElement('article');
    div.className = 'dispatch-job-card bg-white border border-slate-200 shadow-sm hover:border-indigo-300 transition-all duration-200 cursor-pointer flex flex-col p-4 animate-row relative group';

    const isSelected = selectedJobIds.has(String(job.id));
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    const coords = getJobLatLng(job);
    const jobId = escapeHTML(job.id);
    const queueLabel = displayValue(job.seq || index + 1);

    const teamBadge = job.team_name
        ? `<div class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[10px] font-bold whitespace-nowrap" style="background-color:${color}15; color:${color}; border:1px solid ${color}30">
             <span class="w-2 h-2 rounded-full mr-1.5" style="background-color:${color}"></span>${displayValue(job.team_name)}
           </div>`
        : `<div class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[10px] font-bold text-slate-500 bg-slate-100 border border-slate-200 whitespace-nowrap">
             <i data-lucide="clock-3" class="w-3 h-3 mr-1"></i>รอจ่ายงาน
           </div>`;

    const coordText = coords ? `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}` : 'ไม่มีพิกัด';
    const mapButtonClass = coords
        ? 'bg-[var(--c-primary)] text-white hover:bg-[var(--c-primary-hover)]'
        : 'bg-slate-100 text-slate-400 cursor-not-allowed';

    div.innerHTML = `
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <div class="pt-1" onclick="event.stopPropagation()">
                    <input type="checkbox" class="job-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        data-id="${jobId}" ${isSelected ? 'checked' : ''} onchange="toggleJobSelection('${jobId}')">
                </div>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-[12px] font-black text-white shadow-sm shrink-0" style="background-color:${color}">
                    ${queueLabel}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-black text-slate-900 text-sm leading-tight break-words">${displayValue(job.access_no, 'N/A')}</h3>
                        ${statusBadge(job.status)}
                    </div>
                    <div class="text-[11px] font-bold text-slate-500 mt-1">${displayValue(job.plan_arrival_date)}</div>
                </div>
            </div>
            ${teamBadge}
        </div>

        <div class="mt-3 space-y-2">
            <div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-wide">ลูกค้า</div>
                <div class="text-sm font-black text-slate-800 leading-snug break-words">${displayValue(job.customer, 'ไม่ระบุชื่อลูกค้า')}</div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-2 min-w-0">
                    <div class="text-[9px] font-black text-emerald-500 uppercase tracking-wide">โทรศัพท์</div>
                    <div class="text-[12px] font-black text-emerald-700 mt-1 break-words">${displayValue(job.phone, 'ไม่ระบุเบอร์โทร')}</div>
                </div>
                <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-2 min-w-0">
                    <div class="text-[9px] font-black text-indigo-500 uppercase tracking-wide">พิกัด</div>
                    <div class="text-[11px] font-bold ${coords ? 'text-indigo-700' : 'text-amber-700'} mt-1 break-words">${coordText}</div>
                </div>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-wide mb-1">สถานที่ติดตั้ง</div>
                <div class="text-[12px] text-slate-700 font-bold leading-relaxed break-words">${displayValue(job.address)}</div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                ${detailItem('แพ็กเกจ', job.package)}
                ${detailItem('สินค้า', job.product)}
                ${detailItem('Order No.', job.order_no)}
                ${detailItem('Task Order', job.task_order)}
                ${detailItem('Task Type', job.task_type)}
                ${detailItem('สร้างเมื่อ', job.created_at)}
            </div>
            ${hasValue(job.remark) ? `
                <div class="rounded-lg bg-rose-50 border border-rose-100 p-3">
                    <div class="text-[9px] font-black text-rose-500 uppercase tracking-wide mb-1">หมายเหตุ</div>
                    <div class="text-[12px] text-rose-700 font-bold leading-relaxed break-words">${displayValue(job.remark)}</div>
                </div>` : ''}
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center gap-1" onclick="event.stopPropagation(); showJobPopupById('${jobId}')">
                <i data-lucide="file-text" class="w-4 h-4"></i>รายละเอียด
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black flex items-center justify-center gap-1 ${mapButtonClass}" ${coords ? '' : 'disabled'} onclick="event.stopPropagation(); openJobNavigationById('${jobId}')">
                <i data-lucide="navigation" class="w-4 h-4"></i>นำทาง
            </button>
        </div>
        ${job.team_id && (!job.status || (job.status.toLowerCase() !== 'completed' && job.status.toLowerCase() !== 'failed')) ? `
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'completed')">
                <i data-lucide="check-circle" class="w-4 h-4"></i>จบงาน
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'failed')">
                <i data-lucide="x-circle" class="w-4 h-4"></i>ไม่สำเร็จ
            </button>
        </div>` : ''}
    `;

    div.onclick = () => {
        focusMapOnJob(job.id);
        showJobPopup(job, color);
    };

    return div;
}

function showJobPopupById(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    focusMapOnJob(job.id);
    showJobPopup(job, color);
}

function openJobNavigationById(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    const coords = getJobLatLng(job);
    if (!coords) return Swal.fire('ไม่พบพิกัด', 'งานนี้ยังไม่มีละติจูด/ลองจิจูดที่ถูกต้อง', 'warning');
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${coords.lat},${coords.lng}`, '_blank');
}

function showMapJobDetail(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    focusMapOnJob(job.id);
    showJobPopup(job, color);
}

function toggleJobSelection(id) {
    const strId = String(id);
    if (selectedJobIds.has(strId)) selectedJobIds.delete(strId);
    else selectedJobIds.add(strId);
    syncSelectAllState();
    updateSelectionUI();
}

function syncSelectAllState() {
    const selectAll = document.getElementById('selectAllJobs');
    if (!selectAll) return;
    const boxes = Array.from(document.querySelectorAll('.job-checkbox'));
    const checked = boxes.filter(cb => cb.checked).length;
    selectAll.checked = boxes.length > 0 && checked === boxes.length;
    selectAll.indeterminate = checked > 0 && checked < boxes.length;
}

function showJobPopup(job, color) {
    const coords = getJobLatLng(job);
    const gmapsLink = coords ? `https://www.google.com/maps/dir/?api=1&destination=${coords.lat},${coords.lng}` : null;

    let actionButtons = '';
    if (!IS_ADMIN) {
        actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'completed')" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ปิดจ๊อบ
                </button>
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'failed')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ทำไม่สำเร็จ
                </button>
            </div>
        `;
    }

    Swal.fire({
        title: `<div class="text-left"><div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รายละเอียดงาน</div><div class="font-black text-lg" style="color:${color};">${displayValue(job.access_no, 'N/A')}</div></div>`,
        html: `
            <div class="text-left mt-1 font-sans space-y-3">
                <div class="bg-white border border-slate-100 p-4 rounded-lg shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase">ลูกค้า</p>
                            <p class="text-sm font-black text-slate-800">${displayValue(job.customer, 'ไม่ระบุชื่อลูกค้า')}</p>
                        </div>
                        ${statusBadge(job.status)}
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">สถานที่ติดตั้ง</p>
                        <p class="text-xs text-slate-700 font-bold leading-relaxed">${displayValue(job.address)}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        ${detailItem('วันที่', job.plan_arrival_date)}
                        ${detailItem('ทีม', job.team_name || 'รอจ่าย')}
                        ${detailItem('โทรศัพท์', job.phone)}
                        ${detailItem('พิกัด', coords ? `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}` : 'ไม่มีพิกัด')}
                        ${detailItem('แพ็กเกจ', job.package)}
                        ${detailItem('สินค้า', job.product)}
                        ${detailItem('Order No.', job.order_no)}
                        ${detailItem('Task Order', job.task_order)}
                        ${detailItem('Task Type', job.task_type)}
                        ${detailItem('สร้างเมื่อ', job.created_at)}
                    </div>
                    ${hasValue(job.remark) ? `
                    <div class="bg-rose-50 p-3 rounded-lg border border-rose-100">
                        <p class="text-[9px] font-bold text-rose-500 uppercase mb-1">หมายเหตุ</p>
                        <p class="text-xs font-bold text-rose-700 leading-relaxed">${displayValue(job.remark)}</p>
                    </div>` : ''}
                </div>
                ${actionButtons}
            </div>
        `,
        showCancelButton: true,
        showCloseButton: true,
        showConfirmButton: !!gmapsLink,
        confirmButtonColor: color,
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: 'นำทางด้วย Google Maps',
        cancelButtonText: '<span class="text-slate-500 font-bold">ปิด</span>',
        customClass: {
            popup: 'rounded-2xl p-4 shadow-xl z-[9999]',
            title: 'text-left pb-2 border-b border-slate-100',
            confirmButton: 'rounded-lg px-4 py-2.5 font-bold w-full mt-2 text-xs',
            cancelButton: 'rounded-lg px-4 py-2.5 font-bold w-full mt-2 text-xs hover:bg-slate-200',
            actions: 'flex-col w-full px-2'
        },
        didOpen: refreshLucideIcons
    }).then((result) => {
        if (result.isConfirmed && gmapsLink) window.open(gmapsLink, '_blank');
    });
}

window.updateJobStatus = async function(jobId, status) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;

    let remark = '';
    
    if (status === 'failed') {
        const { value: text } = await Swal.fire({
            title: 'ระบุเหตุผลที่ไม่สำเร็จ', 
            html: `<p class="text-sm text-slate-600 mb-3">งาน: <strong>${escapeHTML(job.access_no)}</strong></p>`,
            input: 'textarea', 
            inputPlaceholder: 'เขียนหมายเหตุเกี่ยวกับปัญหาที่เกิดขึ้น...',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ยืนยัน', 
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
        });
        if (!text) { 
            if(text !== undefined) Swal.fire('แจ้งเตือน', 'กรุณาระบุเหตุผล', 'warning'); 
            return; 
        }
        remark = text;
    } else if (status === 'completed') {
        const { isConfirmed } = await Swal.fire({
            title: 'ยืนยันปิดจ๊อบ',
            html: `<p class="text-sm text-slate-600 mb-2">งาน: <strong>${escapeHTML(job.access_no)}</strong></p>
                   <p class="text-sm text-slate-600">ลูกค้า: <strong>${escapeHTML(job.customer || 'ไม่ระบุ')}</strong></p>
                   <p class="text-xs text-slate-500 mt-3">ท้องถิ่นที่: ${escapeHTML(job.address || '-')}</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'ยืนยันจบงาน',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
        });
        if (!isConfirmed) return;
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
            const icon = status === 'completed' ? 'success' : 'info';
            const message = status === 'completed' ? 'บันทึกการจบงานเรียบร้อย' : 'บันทึกการไม่สำเร็จเรียบร้อย';
            Swal.fire({ 
                title: 'สำเร็จ', 
                text: message, 
                icon: icon, 
                timer: 1500, 
                showConfirmButton: false,
                didClose: () => {
                    loadJobs();
                    refreshLucideIcons();
                }
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'เกิดข้อผิดพลาด', 'error');
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
