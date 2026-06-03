// assets/js/dispatch.js

let allJobs = [];
let currentTeams = []; 
let currentOfficeTechs = [];
let currentMaTechs = [];
let selectedJobIds = new Set();
let activeDispatchView = 'jobs';
let currentJobType = 'jobs';

// Leaflet map and markers (free, no API key)
let map = null;
let markersGroup = null;
let jobMarkerMap = new Map();

// Clean latitude/longitude from unwanted characters
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

// 🌟 ซ่อม Syntax Error ร้ายแรงตรงนี้แล้วครับ (สาเหตุที่ทำให้ปุ่มกดไม่ได้และจอดำ)
function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char] || char));
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
            attribution: '© OpenStreetMap contributors'
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
    if (CAN_VIEW_OFFICE) {
        currentJobType = 'jobs';
    } else if (CAN_VIEW_MA) {
        currentJobType = 'ma';
    }

    initMap();
    if (CAN_VIEW_OFFICE) {
        updateDispatchModeBanner('jobs');
        loadJobs();
    } else if (CAN_VIEW_MA) {
        switchDispatchView('ma');
    }

    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
        loadRescheduleHistory();
    }
    if (window.JobClose?.checkAlerts && CAN_VIEW_OFFICE) JobClose.checkAlerts();

    document.getElementById('dispatchViewJobsBtn')?.addEventListener('click', () => switchDispatchView('jobs'));
    document.getElementById('dispatchViewMABtn')?.addEventListener('click', () => switchDispatchView('ma'));
    document.getElementById('dispatchViewMapBtn')?.addEventListener('click', () => switchDispatchView('map'));
    document.getElementById('dispatchViewRescheduleBtn')?.addEventListener('click', () => switchDispatchView('reschedule'));
    document.getElementById('navigateSelectedBtn')?.addEventListener('click', handleNavigateSelected);
    document.getElementById('selectAllJobs')?.addEventListener('change', handleSelectAll);

    // 🌟 ดักจับป้องกัน IS_ADMIN ให้ปลอดภัย
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
        document.getElementById('jobExcelFile')?.addEventListener('change', handleExcelUpload);
        document.getElementById('maExcelFile')?.addEventListener('change', handleMAExcelUpload);
        document.getElementById('exportExcelBtn')?.addEventListener('click', handleExportExcel);
        document.getElementById('addManualJobBtn')?.addEventListener('click', openManualJobModal);
        document.getElementById('addManualMaJobBtn')?.addEventListener('click', openManualMaJobModal);
        document.getElementById('manualJobForm')?.addEventListener('submit', handleManualJobSubmit);
        document.getElementById('manualMaJobForm')?.addEventListener('submit', handleManualMaJobSubmit);
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
    document.getElementById('statusFilter')?.addEventListener('change', renderUI);
    document.getElementById('limitFilter')?.addEventListener('change', renderUI);
});

function switchDispatchView(view) {
    if (view === 'jobs' && typeof CAN_VIEW_OFFICE !== 'undefined' && !CAN_VIEW_OFFICE) return;
    if (view === 'ma' && typeof CAN_VIEW_MA !== 'undefined' && !CAN_VIEW_MA) return;
    if (view === 'map' && typeof CAN_VIEW_OFFICE !== 'undefined' && !CAN_VIEW_OFFICE) return;

    if (view === 'jobs' || view === 'ma') {
        currentJobType = view;
        activeDispatchView = 'list';
        selectedJobIds.clear();
        updateSelectionUI();
    } else if (view === 'reschedule') {
        activeDispatchView = 'reschedule';
    } else {
        activeDispatchView = 'map';
    }
    
    const jobsPanel = document.getElementById('jobViewPanel');
    const mapPanel = document.getElementById('mapViewPanel');
    const reschedulePanel = document.getElementById('rescheduleHistoryPanel');
    const jobsBtn = document.getElementById('dispatchViewJobsBtn');
    const maBtn = document.getElementById('dispatchViewMABtn');
    const mapBtn = document.getElementById('dispatchViewMapBtn');
    const rescheduleBtn = document.getElementById('dispatchViewRescheduleBtn');

    jobsPanel?.classList.toggle('hidden', activeDispatchView !== 'list');
    mapPanel?.classList.toggle('hidden', activeDispatchView !== 'map');
    reschedulePanel?.classList.toggle('hidden', activeDispatchView !== 'reschedule');
    
    jobsBtn?.classList.toggle('is-active', view === 'jobs');
    if (maBtn) maBtn.classList.toggle('is-active', view === 'ma');
    mapBtn?.classList.toggle('is-active', view === 'map');
    if (rescheduleBtn) rescheduleBtn.classList.toggle('is-active', view === 'reschedule');

    const importBtn = document.getElementById('importOfficeBtn') || document.querySelector('button[onclick="document.getElementById(\'jobExcelFile\').click()"]');
    const importMABtn = document.getElementById('importMABtn');
    const manualBtn = document.getElementById('addManualJobBtn');
    const manualMaBtn = document.getElementById('addManualMaJobBtn');
    const dlTemplateBtn = document.getElementById('downloadTemplateBtn');
    const dlMATemplateBtn = document.getElementById('downloadMATemplateBtn');
    
    if (importBtn) importBtn.style.display = (view === 'jobs') ? '' : 'none';
    if (importMABtn) importMABtn.style.display = (view === 'ma') ? '' : 'none';
    if (manualBtn) manualBtn.style.display = (view === 'jobs') ? '' : 'none';
    if (manualMaBtn) manualMaBtn.style.display = (view === 'ma') ? '' : 'none';
    if (dlTemplateBtn) dlTemplateBtn.style.display = (view === 'jobs') ? '' : 'none';
    if (dlMATemplateBtn) dlMATemplateBtn.style.display = (view === 'ma') ? '' : 'none';

    if (view === 'jobs' || view === 'ma') {
        updateDispatchModeBanner(view);
    }

    if (view === 'jobs' || view === 'ma') {
        loadJobs();
    } else if (view === 'reschedule') {
        if (typeof loadRescheduleHistory === 'function') loadRescheduleHistory();
    } else {
        renderUI();
    }

    if (activeDispatchView === 'map') {
        setTimeout(() => { if (map) map.invalidateSize(); }, 80);
    }
}

function updateDispatchModeBanner(view) {
    const banner = document.getElementById('dispatchModeBanner');
    if (!banner) return;
    if (view === 'ma') {
        banner.className = 'shrink-0 rounded-xl px-4 py-2 text-xs font-black tracking-wide bg-violet-100 text-violet-800 border border-violet-200';
        banner.textContent = '🔧 โหมดงาน MA — แจกจ่ายและปิดงานแยกจากช่าง Office';
        banner.classList.remove('hidden');
    } else if (view === 'jobs') {
        banner.className = 'shrink-0 rounded-xl px-4 py-2 text-xs font-black tracking-wide bg-indigo-100 text-indigo-800 border border-indigo-200';
        banner.textContent = '👷 โหมดงาน Office — แจกจ่ายและปิดงานแยกจากงาน MA';
        banner.classList.remove('hidden');
    } else {
        banner.classList.add('hidden');
    }
}

function canActOnCurrentJobType(job) {
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) return false;
    if (currentJobType === 'ma') {
        return typeof CAN_ACT_MA !== 'undefined' && CAN_ACT_MA && canActOnMaJob(job);
    }
    return typeof CAN_ACT_OFFICE !== 'undefined' && CAN_ACT_OFFICE && !!job.team_id;
}

// 🌟 ซ่อมลิงก์นำทาง Google Maps
function handleNavigateSelected() {
    if (selectedJobIds.size === 0) return;
    
    const selectedIdsArray = Array.from(selectedJobIds);
    let jobsToNav = allJobs.filter(j => selectedIdsArray.includes(String(j.id)));
    
    jobsToNav.sort((a, b) => (a.seq || 999) - (b.seq || 999));
    
    const validJobs = jobsToNav.filter(j => getJobLatLng(j));
    
    if (validJobs.length === 0) {
        return Swal.fire('ไม่พบพิกัด', 'งานที่เลือกไม่มีข้อมูลพิกัดละติจูด/ลองจิจูด', 'warning');
    }
    
    if (validJobs.length === 1) {
        const coords = getJobLatLng(validJobs[0]);
        window.open(`https://maps.google.com/?q=${coords.lat},${coords.lng}`, '_blank');
        return;
    }

    const destination = validJobs[validJobs.length - 1];
    const destCoords = getJobLatLng(destination);
    const waypoints = validJobs.slice(0, validJobs.length - 1).map(j => {
        const c = getJobLatLng(j);
        return `${c.lat},${c.lng}`;
    }).join('|');
    
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${destCoords.lat},${destCoords.lng}&waypoints=${waypoints}&travelmode=driving`, '_blank');
}

function getApiUrl(base) {
    const symbol = base.includes('?') ? '&' : '?';
    return currentJobType === 'ma' ? `${base}${symbol}type=ma` : base;
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
        const apiUrl = getApiUrl('api/dispatch/get_jobs.php');
        const separator = apiUrl.includes('?') ? '&' : '?';
        const res = await fetch(`${apiUrl}${separator}_=${new Date().getTime()}`);
        const text = await res.text(); 
        
        let data;
        try {
            data = JSON.parse(text); 
        } catch(parseErr) {
            console.error("เซิร์ฟเวอร์ไม่ได้ส่งกลับมาเป็น JSON. ข้อความที่ได้คือ:", text);
            Swal.fire('ระบบขัดข้อง', 'เซิร์ฟเวอร์ส่งข้อมูลผิดรูปแบบ (กรุณากด F12 ดู Console เพื่อดูสาเหตุ)', 'error');
            return;
        }

        if (data.success) {
            try {
                allJobs = data.data || [];
                currentTeams = data.teams || [];
                currentOfficeTechs = data.office_techs || [];
                currentMaTechs = data.ma_techs || [];

                if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
                    const filter = document.getElementById('teamFilter');
                    if (filter) {
                        filter.innerHTML = '<option value="all">📍 ทุกทีม</option><option value="unassigned">⏳ ยังไม่จ่าย</option>';
                        currentTeams.forEach(t => { 
                            filter.innerHTML += `<option value="${t.id}">${escapeHTML(t.team_name)}</option>`; 
                        });
                    }
                }
                renderTeamList();
                renderUI();
            } catch (uiErr) {
                console.error("ข้อผิดพลาดในการวาดหน้าจอ:", uiErr);
                Swal.fire('ข้อผิดพลาดหน้าเว็บ', 'เกิดปัญหาตอนสร้างหน้าจอ: ' + uiErr.message, 'error');
            }
        } else {
            Swal.fire('เซิร์ฟเวอร์แจ้งเตือน', data.error || 'ดึงข้อมูลไม่สำเร็จ', 'warning');
        }

    } catch (netErr) {
        console.error("ข้อผิดพลาดเครือข่าย:", netErr);
        Swal.fire('การเชื่อมต่อล้มเหลว', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ หรือเน็ตหลุด', 'error');
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
    const isMa = currentJobType === 'ma';
    const unassignedJobs = isMa 
        ? allJobs.filter(j => !j.assigned_user_id).length 
        : allJobs.filter(j => !j.team_id).length;

    if (unassignedJobs === 0) return Swal.fire('แจ้งเตือน', 'ไม่มีงานรอจ่าย', 'info');

    document.getElementById('unassignedCount').textContent = unassignedJobs;
    
    // เปลี่ยนสี Header ตามประเภทงาน
    const headerEl = document.querySelector('#dispatchModal .bg-indigo-600, #dispatchModal .bg-violet-600');
    if (headerEl) {
        if (isMa) {
            headerEl.classList.remove('bg-indigo-600');
            headerEl.classList.add('bg-violet-600');
            headerEl.querySelector('h3').textContent = 'จ่ายงานช่าง MA (Auto-Dispatch)';
        } else {
            headerEl.classList.remove('bg-violet-600');
            headerEl.classList.add('bg-indigo-600');
            headerEl.querySelector('h3').textContent = 'Auto-Dispatch';
        }
    }

    const container = document.getElementById('dispatchTeamList');
    container.innerHTML = '';

    if (isMa) {
        if (currentMaTechs.length === 0) {
            container.innerHTML = '<p class="text-center text-slate-500 py-4 text-[10px] font-bold">ไม่มีช่าง MA ในระบบ</p>';
            document.getElementById('confirmDispatchBtn').disabled = true;
        } else {
            document.getElementById('confirmDispatchBtn').disabled = false;
            currentMaTechs.forEach((u, i) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 bg-white rounded border border-slate-100';
                div.innerHTML = `
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded flex items-center justify-center text-white font-bold text-[10px]" style="background-color: ${getColor(i)}">${u.full_name.charAt(0)}</div>
                        <span class="font-bold text-slate-700 text-[11px]">${u.full_name}</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <input type="number" id="dist-quota-user-${u.id}" value="0" min="0" max="${unassignedJobs}" class="w-14 px-1 py-1 rounded border border-slate-200 text-center font-bold text-indigo-600 text-[11px] h-6 focus:ring-1 focus:ring-indigo-500">     
                    </div>
                `;
                container.appendChild(div);
            });
        }
    } else {
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
                        <input type="number" id="dist-quota-team-${t.id}" value="0" min="0" max="${unassignedJobs}" class="w-14 px-1 py-1 rounded border border-slate-200 text-center font-bold text-indigo-600 text-[11px] h-6 focus:ring-1 focus:ring-indigo-500">     
                    </div>
                `;
                container.appendChild(div);
            });
        }
    }
    document.getElementById('dispatchModal').classList.remove('hidden');
}

function closeDispatchModal() { 
    document.getElementById('dispatchModal').classList.add('hidden'); 
}

window.openManualJobModal = async function() {
    document.getElementById('manualJobForm')?.reset();
    document.getElementById('addManualJobModal').classList.remove('hidden');

    try {
        const res = await fetch('api/users/get_technicians.php?type=office');
        const data = await res.json();
        const select = document.getElementById('officeTechSelect');
        if (select && data.success) {
            select.innerHTML = '<option value="">-- เลือกช่างซ่อม --</option>';
            data.users.forEach(u => {
                const teamName = u.team_name ? ` (ทีม: ${escapeHTML(u.team_name)})` : '';
                select.innerHTML += `<option value="${u.id}">${escapeHTML(u.full_name)}${teamName}</option>`;
            });
        }
    } catch (e) {
        console.error('Failed to fetch technicians', e);
    }
};

window.closeManualJobModal = function() {
    document.getElementById('addManualJobModal').classList.add('hidden');
};

async function handleManualJobSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    showLoader('บันทึกข้อมูลงาน...');
    try {
        const res = await fetch(getApiUrl('api/dispatch/add_manual_job.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            Swal.fire({
                title: 'สำเร็จ',
                text: 'เพิ่มงานเรียบร้อยแล้ว',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
            closeManualJobModal();
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', result.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
}

async function openManualMaJobModal() {
    document.getElementById('manualMaJobForm')?.reset();
    document.getElementById('addManualMaJobModal').classList.remove('hidden');
    
    // Fetch technicians and populate the select box
    try {
        const res = await fetch('api/users/get_technicians.php?type=ma');
        const data = await res.json();
        const select = document.getElementById('maTechSelect');
        if (select && data.success) {
            select.innerHTML = '<option value="">-- เลือกช่างซ่อม --</option>';
            data.users.forEach(u => {
                select.innerHTML += `<option value="${u.id}">${escapeHTML(u.full_name)}</option>`;
            });
        }
    } catch (e) {
        console.error('Failed to fetch technicians', e);
    }
}

window.closeManualMaJobModal = function() {
    document.getElementById('addManualMaJobModal').classList.add('hidden');
};

async function handleManualMaJobSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    showLoader('บันทึกข้อมูลงาน MA...');
    try {
        const res = await fetch('api/dispatch/add_manual_ma_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            Swal.fire({
                title: 'สำเร็จ',
                text: 'เพิ่มงาน MA เรียบร้อยแล้ว',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
            closeManualMaJobModal();
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', result.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
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
    const isMa = currentJobType === 'ma';
    
    if (isMa) {
        currentMaTechs.forEach(u => {
            const el = document.getElementById(`dist-quota-user-${u.id}`);
            if (el && parseInt(el.value) > 0) quotas.push({ user_id: u.id, limit: parseInt(el.value) });
        });
    } else {
        currentTeams.forEach(t => {
            const el = document.getElementById(`dist-quota-team-${t.id}`);
            if (el && parseInt(el.value) > 0) quotas.push({ team_id: t.id, limit: parseInt(el.value) });
        });
    }

    if (quotas.length === 0) return alert('กรุณาระบุจำนวนงานที่ต้องการจ่าย');

    closeDispatchModal();
    showLoader('กำลังกระจายงาน...');
    try {
        const res = await fetch(getApiUrl('api/dispatch/auto_assign.php'), { 
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
        const res = await fetch(getApiUrl('api/dispatch/bulk_delete.php'), { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ ids, job_type: currentJobType === 'ma' ? 'ma' : 'jobs' })
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
        const res = await fetch(getApiUrl('api/dispatch/delete_all_jobs.php'));
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
        const res = await fetch(getApiUrl('api/dispatch/clear_assignments.php'));
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
        const res = await fetch(getApiUrl('api/dispatch/optimize_route.php'));
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
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN && teamEl) teamVal = teamEl.value;

    const dateVal = document.getElementById('dateFilter')?.value;
    const statusVal = document.getElementById('statusFilter')?.value;

    let filteredJobs = [...allJobs];

    if (teamVal === 'unassigned') filteredJobs = filteredJobs.filter(j => !j.team_id);
    else if (teamVal !== 'all') filteredJobs = filteredJobs.filter(j => j.team_id == teamVal);
    
    if (dateVal) filteredJobs = filteredJobs.filter(j => j.plan_arrival_date === dateVal);

    if (statusVal && statusVal !== 'all') {
        filteredJobs = filteredJobs.filter(j => {
            const currentStatus = (j.status || 'pending').toLowerCase();
            if (statusVal === 'pending') {
                return currentStatus !== 'failed' && currentStatus !== 'completed';
            } else if (statusVal === 'failed') {
                return currentStatus === 'failed';
            }
            return true;
        });
    }

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
        const jobStatus = (job.status || '').toLowerCase();
        const isDone = jobStatus === 'completed' || jobStatus === 'failed';

        let actionButtons = '';
        if (!isDone && job.team_id) {
            actionButtons = `
            <div class="grid grid-cols-2 gap-1.5 mt-2 pt-2 border-t border-slate-100">
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>ปิดงาน
                </button>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-rose-500 text-white hover:bg-rose-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); handleJobNotSuccess(${job.id})">
                    <i data-lucide="x-circle" class="w-3 h-3"></i>ไม่สำเร็จ
                </button>
            </div>`;
        } else if (jobStatus === 'failed') {
            actionButtons = `
            <div class="flex justify-between items-center mt-2 pt-2 border-t border-slate-100">
                <span class="rounded px-2 py-1 text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-100 text-center">ไม่สำเร็จ</span>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors shadow-sm" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>แก้เป็นสำเร็จ
                </button>
            </div>`;
        }

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

function syncVisibleSelection(visibleJobs) {
    const selectAll = document.getElementById('selectAllJobs');
    if (selectAll) {
        const visibleIds = visibleJobs.map(j => String(j.id));
        const selectedVisible = visibleIds.filter(id => selectedJobIds.has(id)).length;
        selectAll.checked = visibleIds.length > 0 && selectedVisible === visibleIds.length;
        selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleIds.length;
    }
}

function detailItem(label, value) {
    return `
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-2 min-w-0">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-wide">${label}</div>
            <div class="text-[11px] font-bold text-slate-700 mt-1 break-words">${displayValue(value)}</div>
        </div>`;
}

function showJobPopupById(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    focusMapOnJob(job.id);
    showJobPopup(job, color);
}

// 🌟 ซ่อมลิงก์นำทางสำหรับปุ่มแต่ละงาน
function openJobNavigationById(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    const coords = getJobLatLng(job);
    if (!coords) return Swal.fire('ไม่พบพิกัด', 'งานนี้ยังไม่มีละติจูด/ลองจิจูดที่ถูกต้อง', 'warning');
    window.open(`https://maps.google.com/?q=${coords.lat},${coords.lng}`, '_blank');
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

function statusBadge(status, job) {
    const value = rawValue(status, 'Pending').toLowerCase();
    
    // Status: Completed
    if (value === 'completed') {
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200"><i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1 text-emerald-600"></i>เสร็จแล้ว</span>';
    }
    
    // Status: Failed
    if (value === 'failed') {
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200"><i data-lucide="x-circle" class="w-3.5 h-3.5 mr-1 text-rose-600"></i>ไม่สำเร็จ</span>';
    }
    
    // Status: Pending but Assigned
    if (job && (job.team_id || job.assigned_user_id)) {
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-sky-50 text-sky-700 border border-sky-200 shadow-sm"><i data-lucide="user-check" class="w-3.5 h-3.5 mr-1 text-sky-600"></i>มอบหมายแล้ว</span>';
    }

    // Status: Pending / Unassigned
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-200 shadow-sm"><i data-lucide="clock" class="w-3.5 h-3.5 mr-1 text-amber-600 animate-pulse"></i>รอจ่ายงาน</span>';
}

function formatThaiDateShort(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function rescheduleInfoBadge(job) {
    if (!job.last_reschedule_from && !job.last_reschedule_to) return '';
    return `<span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-sky-50 text-sky-800 border border-sky-200 ml-1">
        <i data-lucide="calendar-clock" class="w-3 h-3 mr-1"></i>เลื่อนจาก ${formatThaiDateShort(job.last_reschedule_from)}
    </span>`;
}

function createJobRow(job, index) {
    const div = document.createElement('article');
    div.className = 'dispatch-job-card bg-white border border-slate-200 shadow-sm hover:border-indigo-300 transition-all duration-200 cursor-pointer flex flex-col p-4 animate-row relative group';

    const isSelected = selectedJobIds.has(String(job.id));
    const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    const coords = getJobLatLng(job);
    const jobStatus = (job.status || '').toLowerCase();
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
                        ${statusBadge(job.status, job)}
                        ${rescheduleInfoBadge(job)}
                    </div>
                    <div class="text-[11px] font-bold text-slate-500 mt-1">นัดติดตั้ง: ${displayValue(job.plan_arrival_date)}</div>
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
                <div class="text-[12px] text-slate-700 font-bold leading-relaxed break-words">
                    ${displayValue(job.address)}
                    ${job.sub_district ? ' ต.' + escapeHTML(job.sub_district) : ''}
                    ${job.district ? ' อ.' + escapeHTML(job.district) : ''}
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                ${currentJobType === 'ma' ? maJobExtraHtml(job) : ''}
                ${currentJobType !== 'ma' ? detailItem('แพ็กเกจ', job.package) : ''}
                ${currentJobType !== 'ma' ? detailItem('สินค้า', job.product) : ''}
                ${currentJobType !== 'ma' ? detailItem('Order No.', job.order_no) : ''}
                ${currentJobType !== 'ma' ? detailItem('Task Order', job.task_order) : ''}
                ${currentJobType !== 'ma' ? detailItem('Task Type', job.task_type) : ''}
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
        ${canActOnCurrentJobType(job) && jobStatus !== 'completed' && jobStatus !== 'failed' ? `
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                <i data-lucide="check-circle" class="w-4 h-4"></i>ปิดงาน
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); handleJobNotSuccess(${job.id})">
                <i data-lucide="x-circle" class="w-4 h-4"></i>ไม่สำเร็จ
            </button>
        </div>` : ''}
        ${jobStatus === 'failed' ? `
        <div class="mt-2 rounded-lg bg-rose-50 border border-rose-100 px-3 py-2 flex items-center justify-between">
            <span class="text-[10px] font-black text-rose-700">สถานะ: ไม่สำเร็จ</span>
            <button type="button" class="rounded-lg px-2 py-1.5 text-[10px] font-black bg-emerald-500 hover:bg-emerald-600 text-white transition-colors flex items-center gap-1 shadow-sm" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                <i data-lucide="check-circle" class="w-3 h-3"></i> แก้เป็นสำเร็จ
            </button>
        </div>` : ''}
    `;

    div.onclick = () => {
        focusMapOnJob(job.id);
        showJobPopup(job, color);
    };

    return div;
}

function showJobPopup(job, color) {
    const coords = getJobLatLng(job);
    const gmapsLink = coords ? `https://maps.google.com/?q=${coords.lat},${coords.lng}` : null;

    let actionButtons = '';
    const popupStatus = (job.status || '').toLowerCase();
    
    let editButtonHTML = '';
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
        editButtonHTML = `
            <button onclick="Swal.close(); openEditJobModal(${job.id})" class="absolute top-4 right-10 text-slate-400 hover:text-indigo-600 transition-colors z-[9999]" title="แก้ไขข้อมูลงาน">
                <i data-lucide="edit" class="w-5 h-5"></i>
            </button>
        `;
    }

    if (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN && popupStatus !== 'completed' && popupStatus !== 'failed') {
        if (canActOnCurrentJobType(job)) actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <button onclick="Swal.close(); openCompleteJobModal(${job.id})" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ปิดงาน
                </button>
                <button onclick="Swal.close(); handleJobNotSuccess(${job.id})" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ทำไม่สำเร็จ
                </button>
            </div>
        `;
    } else if (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN && popupStatus === 'failed' && canActOnCurrentJobType(job)) {
        actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <div class="rounded-lg bg-rose-50 border border-rose-100 px-3 py-2 flex items-center justify-center">
                    <span class="text-xs font-black text-rose-700">สถานะ: ไม่สำเร็จ</span>
                </div>
                <button onclick="Swal.close(); openCompleteJobModal(${job.id})" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    แก้เป็นปิดงานสำเร็จ
                </button>
            </div>
        `;
    }

    let reassignHTML = '';
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN && popupStatus !== 'completed' && popupStatus !== 'failed') {
        if (currentJobType === 'jobs') {
            const teamOptions = currentTeams.map(t => 
                `<option value="${t.id}" ${t.id == job.team_id ? 'selected' : ''}>${escapeHTML(t.team_name)}</option>`
            ).join('');
            
            const techOptions = currentOfficeTechs.map(tech => 
                `<option value="${tech.id}" data-team-id="${tech.team_id || ''}" ${tech.id == job.assigned_user_id ? 'selected' : ''}>${escapeHTML(tech.full_name)}</option>`
            ).join('');

            reassignHTML = `
                <div class="bg-indigo-50 border border-indigo-100 p-3 rounded-lg mt-3 flex flex-col gap-2">
                    <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-wide">เปลี่ยนทีมรับผิดชอบ (Office)</p>
                    <div class="flex flex-col gap-2">
                        <select id="reassignTechSelect_${job.id}" class="input !py-1.5 !px-2 text-xs font-bold w-full" onchange="handleReassignTechChange(${job.id}, 'office')">
                            <option value="">-- เลือกช่าง --</option>
                            ${techOptions}
                        </select>
                        <div class="flex gap-2">
                            <select id="reassignTeamSelect_${job.id}" class="input !py-1.5 !px-2 text-xs font-bold flex-1" onchange="handleReassignTeamChange(${job.id}, 'office')">
                                <option value="">-- รอจ่าย / ไม่ระบุทีม --</option>
                                ${teamOptions}
                            </select>
                            <button onclick="reassignJob(${job.id})" class="bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold px-3 py-1.5 rounded shadow-sm shrink-0">
                                บันทึก
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            const techOptions = currentMaTechs.map(tech => 
                `<option value="${tech.id}" data-team-id="${tech.team_id || ''}" ${tech.id == job.assigned_user_id ? 'selected' : ''}>${escapeHTML(tech.full_name)}</option>`
            ).join('');

            reassignHTML = `
                <div class="bg-violet-50 border border-violet-100 p-3 rounded-lg mt-3 flex flex-col gap-2">
                    <p class="text-[10px] font-bold text-violet-500 uppercase tracking-wide">เปลี่ยนช่างรับผิดชอบ (MA)</p>
                    <div class="flex gap-2">
                        <select id="reassignTechSelect_${job.id}" class="input !py-1.5 !px-2 text-xs font-bold flex-1" onchange="handleReassignTechChange(${job.id}, 'ma')">
                            <option value="">-- รอจ่าย / ไม่ระบุช่าง --</option>
                            ${techOptions}
                        </select>
                        <input type="hidden" id="reassignTeamSelect_${job.id}" value="${job.team_id || ''}">
                        <button onclick="reassignJob(${job.id})" class="bg-violet-600 hover:bg-violet-700 text-white text-[10px] font-bold px-3 py-1.5 rounded shadow-sm shrink-0">
                            บันทึก
                        </button>
                    </div>
                </div>
            `;
        }
    }

    Swal.fire({
        title: `<div class="text-left relative"><div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รายละเอียดงาน</div><div class="font-black text-lg" style="color:${color};">${displayValue(job.access_no, 'N/A')}</div>${editButtonHTML}</div>`,
        html: `
            <div class="text-left mt-1 font-sans space-y-3">
                <div class="bg-white border border-slate-100 p-4 rounded-lg shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase">ลูกค้า</p>
                            <p class="text-sm font-black text-slate-800">${displayValue(job.customer, 'ไม่ระบุชื่อลูกค้า')}</p>
                        </div>
                        ${statusBadge(job.status, job)}
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">สถานที่ติดตั้ง</p>
                        <p class="text-xs text-slate-700 font-bold leading-relaxed">${displayValue(job.address)}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        ${detailItem('วันที่', job.plan_arrival_date)}
                        ${detailItem('ทีม', job.team_name || 'รอจ่าย')}
                        ${detailItem('โทรศัพท์', job.phone)}
                        <div class="rounded-lg bg-slate-50 border border-slate-100 p-2 min-w-0 col-span-2 sm:col-span-1">
                            <div class="flex justify-between items-center mb-1">
                                <div class="text-[9px] font-black text-slate-400 uppercase tracking-wide">พิกัด</div>
                                <button type="button" onclick="editJobCoords(${job.id}, '${coords ? coords.lat : ''}', '${coords ? coords.lng : ''}')" class="text-[9px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200 transition-colors">
                                    <i data-lucide="edit-3" class="w-3 h-3 inline-block mr-0.5"></i>แก้ไข
                                </button>
                            </div>
                            <div class="text-[11px] font-bold text-slate-700 break-words">${coords ? `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}` : 'ไม่มีพิกัด'}</div>
                        </div>
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
                ${reassignHTML}
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

window.editJobCoords = async function(jobId, currentLat, currentLng) {
    Swal.close();
    const { value: formValues } = await Swal.fire({
        title: 'แก้ไขพิกัด (Lat / Lng)',
        html:
            `<div class="space-y-3 text-left">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">ละติจูด (Latitude)</label>
                    <input id="swal-input-lat" type="number" step="any" class="input !py-2 !px-3 text-sm font-bold w-full" value="${currentLat || ''}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">ลองจิจูด (Longitude)</label>
                    <input id="swal-input-lng" type="number" step="any" class="input !py-2 !px-3 text-sm font-bold w-full" value="${currentLng || ''}">
                </div>
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'บันทึกพิกัด',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#4f46e5',
        customClass: {
            popup: 'rounded-2xl p-4 shadow-xl z-[9999]',
            confirmButton: 'rounded-lg px-4 py-2 text-xs font-bold w-full mt-2',
            cancelButton: 'rounded-lg px-4 py-2 text-xs font-bold w-full mt-2',
            actions: 'flex-col w-full px-2'
        },
        preConfirm: () => {
            return {
                lat: document.getElementById('swal-input-lat').value,
                lng: document.getElementById('swal-input-lng').value
            }
        }
    });

    if (formValues) {
        setTimeout(async () => {
        try {
            const res = await fetch(getApiUrl('api/dispatch/update_job_coords.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: jobId, lat: formValues.lat, lng: formValues.lng })
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    title: 'สำเร็จ',
                    text: 'อัปเดตพิกัดเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                loadJobs();
            } else {
                Swal.fire('ข้อผิดพลาด', data.error, 'error');
            }
        } catch (e) {
            Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
        } finally {
            hideLoader();
        }
        }, 100);
    }
}

window.handleReassignTechChange = function(jobId, type) {
    const techSelect = document.getElementById(`reassignTechSelect_${jobId}`);
    const teamSelect = document.getElementById(`reassignTeamSelect_${jobId}`);
    if (!techSelect || !teamSelect) return;
    
    const selectedOption = techSelect.options[techSelect.selectedIndex];
    const teamId = selectedOption.getAttribute('data-team-id');
    
    if (teamId && type === 'office') {
        teamSelect.value = teamId;
    } else if (teamId && type === 'ma') {
        teamSelect.value = teamId;
    }
};

window.handleReassignTeamChange = function(jobId, type) {
    if (type !== 'office') return;
    const techSelect = document.getElementById(`reassignTechSelect_${jobId}`);
    const teamSelect = document.getElementById(`reassignTeamSelect_${jobId}`);
    if (!techSelect || !teamSelect) return;
    
    const teamId = teamSelect.value;
    if (teamId) {
        const techOption = Array.from(techSelect.options).find(opt => opt.getAttribute('data-team-id') === String(teamId));
        if (techOption) {
            techSelect.value = techOption.value;
        }
    }
};

window.reassignJob = async function(jobId) {
    const teamSelect = document.getElementById(`reassignTeamSelect_${jobId}`);
    const techSelect = document.getElementById(`reassignTechSelect_${jobId}`);
    
    const newTeamId = teamSelect ? teamSelect.value : null;
    const newTechId = techSelect ? techSelect.value : null;

    Swal.close();
    showLoader('กำลังบันทึก...');
    try {
        const res = await fetch(getApiUrl('api/dispatch/reassign_job.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                job_id: jobId, 
                team_id: newTeamId || null, 
                assigned_user_id: newTechId || null,
                job_type: currentJobType === 'ma' ? 'ma' : 'jobs' 
            })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                title: 'สำเร็จ',
                text: 'บันทึกข้อมูลเรียบร้อย',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
            loadJobs();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
    } finally {
        hideLoader();
    }
};

window.updateJobStatus = async function(jobId, status) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;

    if (status === 'completed') {
        openCompleteJobModal(jobId);
        return;
    }
};

async function postJobStatusUpdate(payload) {
    if (currentJobType === 'ma') {
        return postMaJobStatusUpdate(payload);
    }
    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch(getApiUrl('api/dispatch/update_job_status.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                title: 'สำเร็จ',
                text: data.message || 'บันทึกเรียบร้อย',
                icon: 'success',
                timer: 2200,
                showConfirmButton: false,
                didClose: () => {
                    loadJobs();
                    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) loadRescheduleHistory();
                    refreshLucideIcons();
                }
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
    }
}

window.handleJobNotSuccess = async function(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;

    const choose = await Swal.fire({
        title: 'งานไม่สำเร็จ',
        html: `<p class="text-sm text-slate-600 mb-1">งาน: <strong>${escapeHTML(job.access_no)}</strong></p>
               <p class="text-xs text-slate-500">เลือกกรณีที่ตรงกับสถานการณ์</p>`,
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'ระบุเหตุผล (ไม่สำเร็จ)',
        denyButtonText: 'ลูกค้าขอเลื่อนนัด',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444',
        denyButtonColor: '#f59e0b',
        customClass: { popup: 'rounded-xl', confirmButton: 'rounded-lg text-xs', denyButton: 'rounded-lg text-xs', cancelButton: 'rounded-lg text-xs' }
    });

    if (choose.isDismissed) return;

    if (choose.isConfirmed) {
        const { value: text } = await Swal.fire({
            title: 'ระบุสาเหตุที่ไม่สำเร็จ',
            html: `<p class="text-sm text-slate-600 mb-2">งาน: <strong>${escapeHTML(job.access_no)}</strong></p>`,
            input: 'textarea',
            inputPlaceholder: 'เขียนหมายเหตุสาเหตุที่ติดตั้งไม่สำเร็จ...',
            inputValidator: (v) => (!v || !String(v).trim() ? 'กรุณาระบุหมายเหตุ' : undefined),
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'บันทึก',
            cancelButtonText: 'ยกเลิก'
        });
        if (!text) return;
        await postJobStatusUpdate({ job_id: jobId, status: 'failed', remark: String(text).trim() });
        return;
    }

    if (choose.isDenied) {
        const todayObj = new Date();
        const todayD = String(todayObj.getDate()).padStart(2, '0');
        const todayM = String(todayObj.getMonth() + 1).padStart(2, '0');
        const todayY = todayObj.getFullYear();
        const defaultDate = `${todayY}-${todayM}-${todayD}`;

        const { value: form } = await Swal.fire({
            title: '<span style="font-size:1rem;font-weight:900;">เลื่อนวันนัดติดตั้ง</span>',
            html: `
                <div style="text-align:left;font-size:0.8rem;color:#475569;margin-bottom:12px;line-height:1.6;">
                    <span style="color:#94a3b8;font-size:0.7rem;font-weight:700;text-transform:uppercase;">ลูกค้า</span><br>
                    <strong style="color:#1e293b;font-size:0.9rem;">${escapeHTML(job.customer || '-')}</strong><br>
                    <span style="color:#94a3b8;font-size:0.7rem;font-weight:700;text-transform:uppercase;">นัดเดิม</span><br>
                    <strong style="color:#f59e0b;">${formatThaiDateShort(job.plan_arrival_date)}</strong>
                </div>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px;margin-bottom:10px;">
                    <label style="display:block;text-align:left;font-size:0.7rem;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
                        วันที่นัดใหม่ <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" id="swalResDate" value="${defaultDate}" class="w-full">
                </div>
                <div style="text-align:left;">
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">หมายเหตุ (ถ้ามี)</label>
                    <textarea id="swalRescheduleRemark" style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:0.82rem;font-weight:600;resize:none;box-sizing:border-box;" rows="3" placeholder="เช่น ลูกค้าไม่ว่าง / ขอเลื่อนเป็นวันที่..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '✔ ยืนยันเลื่อนนัด',
            cancelButtonText: 'ยกเลิก',
            focusConfirm: false,
            width: '420px',
            customClass: {
                popup: 'rounded-2xl shadow-xl',
                title: 'pt-4 pb-2',
                htmlContainer: 'px-4 pb-2',
                confirmButton: 'rounded-lg px-5 py-2.5 text-sm font-black',
                cancelButton: 'rounded-lg px-5 py-2.5 text-sm font-bold',
            },
            didOpen: () => {
                const dateInput = document.getElementById('swalResDate');
                if (dateInput && window.initDatepickers) {
                    window.initDatepickers();
                    const textInput = dateInput.previousElementSibling;
                    if (textInput && textInput.classList.contains('datepicker-display')) {
                        textInput.style.cssText = "width:100%;padding:10px;border:1.5px solid #fcd34d;border-radius:8px;font-size:0.9rem;font-weight:800;background:#fff;color:#1e293b;box-sizing:border-box;";
                    }
                }
            },
            preConfirm: () => {
                const newDate = document.getElementById('swalResDate')?.value || '';
                const remarkEl = document.getElementById('swalRescheduleRemark');
                if (!newDate) {
                    Swal.showValidationMessage('กรุณาเลือกวันที่นัดใหม่');
                    return false;
                }
                const testDate = new Date(newDate);
                if (isNaN(testDate.getTime())) {
                    Swal.showValidationMessage('วันที่ที่เลือกไม่ถูกต้อง');
                    return false;
                }
                return {
                    reschedule_date: newDate,
                    remark: (remarkEl?.value || '').trim()
                };
            }
        });
        if (!form) return;
        await postJobStatusUpdate({
            job_id: jobId,
            status: 'rescheduled',
            reschedule_date: form.reschedule_date,
            remark: form.remark
        });
    }
};

window.loadRescheduleHistory = async function() {
    if (typeof IS_ADMIN === 'undefined' || !IS_ADMIN) return;
    const body = document.getElementById('rescheduleHistoryBody');
    if (!body) return;

    body.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400 font-bold">กำลังโหลด...</td></tr>';

    try {
        const res = await fetch(getApiUrl('api/dispatch/get_reschedule_history.php?limit=100'));
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-rose-600">${escapeHTML(data.error || 'โหลดไม่สำเร็จ')}</td></tr>`;
            return;
        }

        const records = data.records || [];
        const pending = records.filter(r => !r.acknowledged_at).length;
        const badge = document.getElementById('reschedulePendingBadge');
        if (badge) {
            if (pending > 0) {
                badge.textContent = String(pending);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        if (records.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400 font-bold">ยังไม่มีประวัติเลื่อนนัด</td></tr>';
            return;
        }

        body.innerHTML = records.map(r => {
            const created = r.created_at ? new Date(r.created_at).toLocaleString('th-TH') : '-';
            const ack = r.acknowledged_at
                ? `<span class="text-emerald-700 font-bold text-[10px]">รับทราบแล้ว<br>${escapeHTML(r.acknowledged_by_name || '')}</span>`
                : `<button type="button" onclick="acknowledgeReschedule(${r.id})" class="text-[10px] font-black px-2 py-1 rounded bg-amber-500 text-white hover:bg-amber-600">รับทราบ</button>`;
            return `<tr class="hover:bg-amber-50/50 relative group">
                <td class="px-3 py-2 whitespace-nowrap text-slate-600">${escapeHTML(created)}</td>
                <td class="px-3 py-2"><div class="font-bold text-slate-800">${escapeHTML(r.tech_name)}</div><div class="text-[10px] text-slate-500">${escapeHTML(r.team_name || '-')}</div></td>
                <td class="px-3 py-2"><div class="font-bold">${escapeHTML(r.access_no)}</div><div class="text-[10px] text-slate-500">${escapeHTML(r.customer || '')}</div></td>
                <td class="px-3 py-2 font-bold text-amber-800 whitespace-nowrap">${formatThaiDateShort(r.previous_plan_date)} → ${formatThaiDateShort(r.new_plan_date)}</td>
                <td class="px-3 py-2 text-slate-600 max-w-[200px] break-words">${escapeHTML(r.remark || '-')}</td>
                <td class="px-3 py-2 text-center">
                    <div class="flex items-center justify-center gap-2">
                        ${ack}
                        <button type="button" onclick="deleteRescheduleHistory(${r.id})" class="text-rose-400 hover:text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg transition-colors opacity-0 group-hover:opacity-100" title="ลบประวัตินี้">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        refreshLucideIcons();
    } catch (e) {
        body.innerHTML = '<tr><td colspan="6" class="px-4 py-4 text-rose-600">เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ</td></tr>';
    }
};

window.acknowledgeReschedule = async function(id) {
    try {
        const res = await fetch(getApiUrl('api/dispatch/acknowledge_reschedule.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            loadRescheduleHistory();
            if (typeof loadNotifications === 'function') loadNotifications();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สำเร็จ', 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อไม่สำเร็จ', 'error');
    }
};

window.deleteRescheduleHistory = async function(id) {
    if (!confirm('ต้องการลบประวัติการเลื่อนนัดนี้ใช่หรือไม่?')) return;
    
    try {
        const res = await fetch(getApiUrl('api/dispatch/delete_reschedule_history.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            loadRescheduleHistory();
            Swal.fire({
                title: 'สำเร็จ',
                text: 'ลบประวัติเรียบร้อยแล้ว',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถลบได้', 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อไม่สำเร็จ', 'error');
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
// IMPORT EXCEL (MA)
// ==========================================
function handleMAExcelUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    Swal.fire({
        title: 'นำเข้าข้อมูล MA Excel?',
        text: 'ระบบจะนำเข้าข้อมูลงาน MA เข้าระบบ',
        icon: 'question', 
        showCancelButton: true, 
        confirmButtonColor: '#4f46e5', 
        confirmButtonText: 'นำเข้า', 
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) processMAExcel(file);
        else e.target.value = '';
    });
}

function processMAExcel(file) {
    showLoader('กำลังอ่านไฟล์ MA Excel...');
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(worksheet, {header: 1});

            if (rows.length < 2) throw new Error('ไฟล์ว่างเปล่า หรือรูปแบบไม่ถูกต้อง');

            const headerRow = rows[0].map(h => String(h || '').trim());
            const findCol = (keys) => headerRow.findIndex(h => keys.some(k => h.toLowerCase().includes(k.toLowerCase())));

            const timeIdx = findCol(['เวลา', 'time']);
            const nonIdx = findCol(['NON', 'access_no', 'รหัส']);
            const customerIdx = findCol(['ชื่อลูกค้า', 'ลูกค้า', 'customer', 'รายชื่อ']);
            const phoneIdx = findCol(['เบอร์', 'โทร', 'phone']);
            const symptomsIdx = findCol(['อาการ', 'symptom']);
            const addressIdx = findCol(['ที่อยู่']);
            const teamIdx = findCol(['ทีมช่าง', 'ทีม', 'team']);
            const areaIdx = findCol(['พื้นที่', 'area', 'AIS', '3BB']);
            const remarkIdx = findCol(['หมายเหตุ', 'remark']);
            const dateIdx = findCol(['วันที่', 'date']);

            if (nonIdx === -1) throw new Error('ไฟล์ Excel ขาดหัวคอลัมน์ NON');

            const parsedJobs = [];
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                if (!row[nonIdx]) continue;

                let planDate = dateIdx !== -1 ? row[dateIdx] : null;
                if (planDate && !isNaN(planDate) && String(planDate).indexOf('-') === -1 && String(planDate).indexOf('/') === -1) {
                    const dateObj = new Date((planDate - 25569) * 86400 * 1000);
                    planDate = dateObj.toISOString().split('T')[0];
                } else if (planDate && typeof planDate === 'string') {
                    planDate = planDate.trim().split(' ')[0];
                    if (planDate.includes('/')) {
                        const parts = planDate.split('/');
                        if (parts.length === 3 && parts[2].length === 4) {
                            planDate = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
                        }
                    }
                }

                parsedJobs.push({
                    plan_arrival_date: planDate || null,
                    access_no: String(row[nonIdx] || '').trim(),
                    customer: customerIdx !== -1 ? String(row[customerIdx] || '').trim() : '',
                    phone: phoneIdx !== -1 ? String(row[phoneIdx] || '').trim() : '',
                    symptoms: symptomsIdx !== -1 ? String(row[symptomsIdx] || '').trim() : '',
                    address: addressIdx !== -1 ? String(row[addressIdx] || '').trim() : '',
                    team_name: teamIdx !== -1 ? String(row[teamIdx] || '').trim() : '',
                    area_provider: areaIdx !== -1 ? String(row[areaIdx] || '').trim() : '',
                    job_time: timeIdx !== -1 ? String(row[timeIdx] || '').trim() : '',
                    remark: remarkIdx !== -1 ? String(row[remarkIdx] || '').trim() : ''
                });
            }

            if (parsedJobs.length === 0) throw new Error('ไม่พบข้อมูลงาน MA');

            showLoader('บันทึกข้อมูลเข้าระบบ...');
            const res = await fetch('api/dispatch/upload_ma_jobs.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ jobs: parsedJobs })
            });
            const rData = await res.json();
            if (rData.success) {
                Swal.fire({ title: 'สำเร็จ', text: rData.message || `นำเข้า MA ${rData.imported} งานเรียบร้อย!`, icon: 'success' });
                loadJobs();
            } else {
                throw new Error(rData.error);
            }
        } catch (err) {
            Swal.fire('ข้อผิดพลาด', err.message, 'error');
            hideLoader();
        } finally {
            if (document.getElementById('maExcelFile')) document.getElementById('maExcelFile').value = '';
        }
    };
    reader.readAsArrayBuffer(file);
}

// ==========================================
// MA JOB COMPLETION
// ==========================================
window.openMaCompleteModal = function(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;
    document.getElementById('maCompleteJobId').value = jobId;
    document.getElementById('maCompleteJobLabel').textContent = `${job.access_no} — ${job.customer || 'ไม่ระบุชื่อ'}`;
    document.getElementById('maProofImages').value = '';
    document.getElementById('maCompleteRemark').value = '';
    document.getElementById('maCompleteModal')?.classList.remove('hidden');
};

window.closeMaCompleteModal = function() {
    document.getElementById('maCompleteModal')?.classList.add('hidden');
};

window.submitMaCompleteJob = async function() {
    const jobId = document.getElementById('maCompleteJobId')?.value;
    const status = document.querySelector('input[name="ma_status"]:checked')?.value || 'completed';
    const remark = document.getElementById('maCompleteRemark')?.value.trim() || '';
    
    const formData = new FormData();
    formData.append('job_id', jobId);
    formData.append('status', status);
    formData.append('remark', remark);

    if (status === 'completed') {
        const files = document.getElementById('maProofImages')?.files;
        const signalAfter = document.getElementById('maSignalAfter')?.value.trim();
        const powerRx = document.getElementById('maPowerRx')?.value.trim();
        const problemCause = document.getElementById('maProblemCause')?.value.trim();
        const solution = document.getElementById('maSolution')?.value.trim();

        if (!files || files.length === 0) {
            Swal.fire('แจ้งเตือน', 'กรุณาอัปโหลดรูปภาพหลักฐาน', 'warning');
            return;
        }
        if (!signalAfter) {
            Swal.fire('แจ้งเตือน', 'กรุณากรอกค่าสัญญาณหลังออนไลน์', 'warning');
            return;
        }
        if (!powerRx) {
            Swal.fire('แจ้งเตือน', 'กรุณากรอกค่า Power RX', 'warning');
            return;
        }
        if (!problemCause) {
            Swal.fire('แจ้งเตือน', 'กรุณาระบุสาเหตุของปัญหา', 'warning');
            return;
        }
        if (!solution) {
            Swal.fire('แจ้งเตือน', 'กรุณาระบุวิธีการแก้ไข', 'warning');
            return;
        }

        formData.append('signal_after', signalAfter);
        formData.append('power_rx', powerRx);
        formData.append('problem_cause', problemCause);
        formData.append('solution', solution);

        for (let i = 0; i < files.length; i++) {
            formData.append('proof_images[]', files[i]);
        }
    } else {
        if (!remark) {
            Swal.fire('แจ้งเตือน', 'กรุณาระบุหมายเหตุหรือสาเหตุที่ไม่จบงาน', 'warning');
            return;
        }
    }

    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch(getApiUrl('api/dispatch/update_ma_job_status.php'), { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeMaCompleteModal();
            Swal.fire({ title: 'สำเร็จ', text: data.message, icon: 'success', timer: 2000, showConfirmButton: false });
            loadJobs();
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', e.message, 'error');
    }
};

async function postMaJobStatusUpdate(payload) {
    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const formData = new FormData();
        formData.append('job_id', payload.job_id);
        formData.append('status', payload.status);
        formData.append('remark', payload.remark || '');
        if (payload.reschedule_date) formData.append('reschedule_date', payload.reschedule_date);

        const res = await fetch(getApiUrl('api/dispatch/update_ma_job_status.php'), { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ title: 'สำเร็จ', text: data.message, icon: 'success', timer: 2200, showConfirmButton: false });
            loadJobs();
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', e.message || 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
    }
}

(function wrapCompleteJobModal() {
    const orig = window.openCompleteJobModal;
    window.openCompleteJobModal = function(jobId) {
        if (currentJobType === 'ma') {
            openMaCompleteModal(jobId);
            return;
        }
        if (typeof orig === 'function') orig(jobId);
    };
})();

function maJobExtraHtml(job) {
    if (currentJobType !== 'ma') return '';
    let html = '';
    if (hasValue(job.job_time)) html += detailItem('เวลา', job.job_time);
    if (hasValue(job.assigned_user_name)) html += detailItem('ช่างซ่อม', job.assigned_user_name);
    if (hasValue(job.price)) html += detailItem('ราคา', job.price);
    if (hasValue(job.area_provider)) html += detailItem('พื้นที่', job.area_provider);
    
    if (hasValue(job.symptoms)) {
        html += `<div class="rounded-lg bg-amber-50 border border-amber-100 p-3 col-span-full">
            <div class="text-[9px] font-black text-amber-600 uppercase">อาการ</div>
            <div class="text-[12px] font-bold text-amber-800 mt-1">${displayValue(job.symptoms)}</div></div>`;
    }
    
    if (job.team_match_status === 'unmatched' && typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
        html += `<div class="rounded-lg bg-rose-50 border border-rose-200 p-2 col-span-full text-[11px] font-bold text-rose-700">
            ⚠ ทีม "${displayValue(job.team_name_import)}" ไม่ตรงในระบบ — กรุณาเลือกทีมใหม่</div>`;
    }
    return html;
}

function canActOnMaJob(job) {
    return job.team_id || job.assigned_user_id;
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
        let filtered = allJobs;
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

window.openEditJobModal = function(jobId) {
    const job = allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;

    let html = '';
    
    if (currentJobType === 'jobs') {
        html = `
            <form id="editJobForm" class="text-left space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Circuit ID / Access No <span class="text-rose-500">*</span></label><input type="text" id="edit_access_no" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.access_no)}" required></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">วันที่เข้าทำ <span class="text-rose-500">*</span></label><input type="date" id="edit_plan_date" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.plan_arrival_date)}" required></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">ชื่อลูกค้า</label><input type="text" id="edit_customer" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.customer || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">เบอร์โทรศัพท์</label><input type="text" id="edit_phone" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.phone || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">สถานที่ติดตั้ง / ที่อยู่</label><textarea id="edit_address" rows="2" class="input !py-2 !px-3 text-sm font-bold w-full resize-none">${escapeHTML(job.address || '')}</textarea></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Lat</label><input type="number" step="any" id="edit_lat" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.lat || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Lng</label><input type="number" step="any" id="edit_lng" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.lng || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">แพ็กเกจ</label><input type="text" id="edit_package" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.package || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">สินค้า</label><input type="text" id="edit_product" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.product || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Order No</label><input type="text" id="edit_order_no" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.order_no || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Task Order</label><input type="text" id="edit_task_order" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.task_order || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">Task Type</label><input type="text" id="edit_task_type" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.task_type || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุ</label><textarea id="edit_remark" rows="2" class="input !py-2 !px-3 text-sm font-bold w-full resize-none">${escapeHTML(job.remark || '')}</textarea></div>
                </div>
            </form>
        `;
    } else {
        html = `
            <form id="editJobForm" class="text-left space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">NON <span class="text-rose-500">*</span></label><input type="text" id="edit_access_no" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.access_no)}" required></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">วันที่ (Plan Date) <span class="text-rose-500">*</span></label><input type="date" id="edit_plan_date" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.plan_arrival_date)}" required></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">ชื่อลูกค้า</label><input type="text" id="edit_customer" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.customer || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">เบอร์โทรศัพท์</label><input type="text" id="edit_phone" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.phone || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">พื้นที่ (Network)</label>
                        <select id="edit_area_provider" class="input !py-2 !px-3 text-sm font-bold w-full">
                            <option value="">-- เลือกพื้นที่ --</option>
                            <option value="AIS" ${job.area_provider === 'AIS' ? 'selected' : ''}>AIS</option>
                            <option value="3BB" ${job.area_provider === '3BB' ? 'selected' : ''}>3BB</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">ตำบล</label><input type="text" id="edit_sub_district" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.sub_district || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">อำเภอ</label><input type="text" id="edit_district" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.district || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">ราคา</label><input type="number" step="any" id="edit_price" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.price || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">ที่อยู่ติดตั้ง</label><textarea id="edit_address" rows="2" class="input !py-2 !px-3 text-sm font-bold w-full resize-none">${escapeHTML(job.address || '')}</textarea></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Lat</label><input type="number" step="any" id="edit_lat" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.lat || '')}"></div>
                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Lng</label><input type="number" step="any" id="edit_lng" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.lng || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">Order No</label><input type="text" id="edit_order_no" class="input !py-2 !px-3 text-sm font-bold w-full" value="${escapeHTML(job.order_no || '')}"></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">อาการ</label><textarea id="edit_symptoms" rows="2" class="input !py-2 !px-3 text-sm font-bold w-full resize-none">${escapeHTML(job.symptoms || '')}</textarea></div>
                    <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุ</label><textarea id="edit_remark" rows="2" class="input !py-2 !px-3 text-sm font-bold w-full resize-none">${escapeHTML(job.remark || '')}</textarea></div>
                </div>
            </form>
        `;
    }

    Swal.fire({
        title: '<div class="text-left text-sm font-bold">แก้ไขข้อมูลงาน</div>',
        html: html,
        showCancelButton: true,
        confirmButtonText: 'บันทึกข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-2xl p-4 shadow-xl z-[9999] w-full max-w-2xl',
            confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-xs font-bold w-full mt-2',
            cancelButton: 'px-4 py-2 bg-white text-slate-600 rounded-lg font-bold text-xs border border-slate-200 hover:bg-slate-100 transition-colors w-full mt-2',
            actions: 'flex-col sm:flex-row w-full px-2 gap-2'
        },
        preConfirm: () => {
            const getVal = (id) => document.getElementById(id) ? document.getElementById(id).value : '';
            if (!getVal('edit_access_no') || !getVal('edit_plan_date')) {
                Swal.showValidationMessage('กรุณาระบุรหัสงานและวันที่');
                return false;
            }
            
            return {
                job_id: job.id,
                job_type: currentJobType,
                access_no: getVal('edit_access_no'),
                plan_arrival_date: getVal('edit_plan_date'),
                customer: getVal('edit_customer'),
                phone: getVal('edit_phone'),
                address: getVal('edit_address'),
                lat: getVal('edit_lat'),
                lng: getVal('edit_lng'),
                package: getVal('edit_package'),
                product: getVal('edit_product'),
                order_no: getVal('edit_order_no'),
                task_order: getVal('edit_task_order'),
                task_type: getVal('edit_task_type'),
                remark: getVal('edit_remark'),
                area_provider: getVal('edit_area_provider'),
                sub_district: getVal('edit_sub_district'),
                district: getVal('edit_district'),
                price: getVal('edit_price'),
                symptoms: getVal('edit_symptoms')
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            submitEditJob(result.value);
        }
    });
};

async function submitEditJob(payload) {
    showLoader('กำลังบันทึก...');
    try {
        const res = await fetch(getApiUrl('api/dispatch/edit_job.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                title: 'สำเร็จ',
                text: 'อัปเดตข้อมูลเรียบร้อย',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
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
// DOWNLOAD TEMPLATES
window.downloadTemplate = function(type) {
    let ws_data = [];
    let fileName = '';

    if (type === 'office') {
        fileName = 'template_office.xlsx';
        ws_data = [
            ["????", "??????????", "????????", "???????", "???????", "????????", "??????", "???????", "????????"],
            ["AC-12345", "????? ????", "0812345678", "123 ?.1 ?.????? ?.????? ?.????????", "13.123456", "100.123456", "2023-12-31", "Fiber 1000/500", "?????????"]
        ];
    } else if (type === 'ma') {
        fileName = 'template_ma.xlsx';
        ws_data = [
            ["????", "NON", "??????????", "????????", "???????", "????", "?????", "???????", "?????", "???", "????????", "??????"],
            ["09:00", "NON-9999", "?????? ??????", "0898765432", "AIS", "??????", "??????", "456 ?.2", "????????????", "??? A", "??????????", "2023-12-31"]
        ];
    }

    if (typeof XLSX === 'undefined') {
        Swal.fire('??????????', '??????????????????????????? Excel', 'error');
        return;
    }

    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    
    // Auto-size columns slightly
    const colWidths = ws_data[0].map(col => ({ wch: Math.max(col.length + 5, 12) }));
    ws['!cols'] = colWidths;

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Template");
    XLSX.writeFile(wb, fileName);
};
