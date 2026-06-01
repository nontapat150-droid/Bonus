// assets/js/dispatch.js - Unified Dispatch Management

// Global State
window.allJobs = [];
window.currentTeams = []; 
window.selectedJobIds = new Set();
window.activeDispatchView = 'jobs';

// Leaflet map and markers
window.map = null;
window.markersGroup = null;
window.jobMarkerMap = new Map();

// Helper: Clean latitude/longitude
function cleanCoordinate(value) {
    if (value === null || value === undefined || value === '') return null;
    const cleaned = String(value).replace(/[^0-9.-]/g, '').trim();
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : null;
}

// Helper: Get Job LatLng
function getJobLatLng(job) {
    const lat = cleanCoordinate(job?.lat);
    const lng = cleanCoordinate(job?.lng);
    if (lat === null || lng === null) return null;
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
    return { lat, lng };
}

// Helper: Check Value Presence
function hasValue(value) {
    if (value === null || value === undefined) return false;
    const text = String(value).trim();
    return text !== '' && text !== '-' && text.toLowerCase() !== 'null';
}

// Helper: Escape HTML
function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

// Helper: Display Value with Fallback
function displayValue(value, fallback = '-') {
    return hasValue(value) ? escapeHTML(String(value).trim()) : fallback;
}

// Helper: Raw Value with Fallback
function rawValue(value, fallback = '-') {
    return hasValue(value) ? String(value).trim() : fallback;
}

// Helper: Set Text Content
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

// Helper: Refresh Icons
function refreshLucideIcons() {
    if (window.lucide?.createIcons) window.lucide.createIcons();
}

// Helper: Team Colors
const teamColors = [
    '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#4f46e5', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

// 🗺️ Map Initialization
window.initMap = function() {
    try {
        if (!document.getElementById('map')) return;
        if (window.map) return; // Prevent double init
        window.map = L.map('map', { zoomControl: false }).setView([13.736717, 100.523186], 6);
        L.control.zoom({ position: 'bottomright' }).addTo(window.map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        markersGroup = L.layerGroup().addTo(map);
    } catch (e) {
        console.warn('Leaflet init failed', e);
    }
};

// 🗺️ Update Map Markers
window.updateMapMarkers = function(jobs) {
    if (!window.L || !window.map || !window.markersGroup) return;
    window.markersGroup.clearLayers();
    window.jobMarkerMap.clear();

    const valid = (jobs || []).map(job => {
        const coords = getJobLatLng(job);
        return coords ? { ...coords, job } : null;
    }).filter(Boolean);

    setText('mapCountBadge', valid.length);
    setText('mapMissingBadge', Math.max((jobs || []).length - valid.length, 0));

    if (valid.length === 0) {
        window.map.setView([13.736717, 100.523186], 6);
        setTimeout(() => { if (window.map) window.map.invalidateSize(); }, 300);
        return;
    }

    valid.forEach((v, idx) => {
        try {
            const teamIdx = window.currentTeams.findIndex(t => t.id == v.job.team_id);
            const color = v.job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
            const label = v.job.seq || idx + 1;
            const icon = L.divIcon({
                className: '',
                html: `<div class="dispatch-marker" style="background-color:${color};"><span>${label}</span></div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -30]
            });

            const marker = L.marker([v.lat, v.lng], { icon });
            marker.bindTooltip(`${v.job.seq || idx + 1}. ${rawValue(v.job.access_no, 'N/A')}`, {
                direction: 'top', offset: [0, -28], opacity: 0.9
            });
            marker.on('click', () => {
                focusMapOnJob(v.job.id);
                showJobPopup(v.job, color);
            });
            window.markersGroup.addLayer(marker);
            window.jobMarkerMap.set(String(v.job.id), marker);
        } catch (e) { console.warn('marker failed', e); }
    });

    if (valid.length === 1) {
        window.map.setView([valid[0].lat, valid[0].lng], 14);
    } else {
        const bounds = window.markersGroup.getBounds();
        if (bounds && bounds.isValid()) {
            try { window.map.fitBounds(bounds.pad(0.18), { maxZoom: 13 }); } catch (e) { }
        }
    }

    setTimeout(() => { if (window.map) window.map.invalidateSize(); }, 300);
};

// 🗺️ Focus Map on Job
function focusMapOnJob(jobId) {
    const marker = window.jobMarkerMap.get(String(jobId));
    if (!marker || !window.map) return false;
    const latLng = marker.getLatLng();
    window.map.setView(latLng, Math.max(window.map.getZoom(), 14), { animate: true });
    return true;
}

// 📦 Data Loading
window.loadJobs = async function() {
    showLoader('ซิงค์ข้อมูล...');
    try {
        const res = await fetch('api/dispatch/get_jobs.php?_=' + new Date().getTime());
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

    document.getElementById('dispatchViewJobsBtn')?.addEventListener('click', () => switchDispatchView('jobs'));
    document.getElementById('dispatchViewMapBtn')?.addEventListener('click', () => switchDispatchView('map'));
    document.getElementById('navigateSelectedBtn')?.addEventListener('click', handleNavigateSelected);
    document.getElementById('selectAllJobs')?.addEventListener('change', handleSelectAll);

            if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
                const filter = document.getElementById('teamFilter');
                if (filter) {
                    filter.innerHTML = '<option value="all">📍 ทุกทีม</option><option value="unassigned">⏳ ยังไม่จ่าย</option>';
                    window.currentTeams.forEach(t => { 
                        filter.innerHTML += `<option value="${t.id}">${t.team_name}</option>`; 
                    });
                }
            }
            renderTeamList();
            renderUI();
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', `เชื่อมต่อล้มเหลว: ${e.message}`, 'error');
    } finally {
        hideLoader();
    }
};

    document.getElementById('dateFilter')?.addEventListener('change', renderUI);
    document.getElementById('statusFilter')?.addEventListener('change', renderUI);
    });

function createJobRow(job, index) {
    const dateVal = document.getElementById('dateFilter')?.value;
    const statusVal = document.getElementById('statusFilter')?.value; 

    let filteredJobs = [...window.allJobs];

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
        const teamIdx = window.currentTeams.findIndex(t => t.id == job.team_id);
        const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
        const coords = getJobLatLng(job);
        const jobStatus = (job.status || '').toLowerCase();
        const isDone = jobStatus === 'completed' || jobStatus === 'failed';
        
        let actionButtons = '';
        if (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN && !isDone && job.team_id) {
            actionButtons = `
            <div class="grid grid-cols-2 gap-1.5 mt-2 pt-2 border-t border-slate-100">
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); window.openCompleteJobModal(${job.id})">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>ปิดงาน
                </button>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-rose-500 text-white hover:bg-rose-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); window.updateJobStatus(${job.id}, 'failed')">
                    <i data-lucide="x-circle" class="w-3 h-3"></i>ไม่สำเร็จ
                </button>
            </div>`;
        } else if (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN && jobStatus === 'failed') {
            actionButtons = `
            <div class="flex justify-between items-center mt-2 pt-2 border-t border-slate-100">
                <span class="rounded px-2 py-1 text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-100 text-center">ไม่สำเร็จ</span>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors shadow-sm" onclick="event.stopPropagation(); window.openCompleteJobModal(${job.id})">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>แก้เป็นสำเร็จ
                </button>
            </div>`;
        }
        
        return `
            <div class="p-3 hover:bg-slate-50 border-b border-slate-100 last:border-b-0 transition-colors space-y-2">
                <button type="button" class="w-full text-left" onclick="window.showMapJobDetail('${escapeHTML(job.id)}')">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg text-white flex items-center justify-center text-xs font-black shrink-0" style="background:${color};">${job.seq || index + 1}</div>
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

// 🃏 Job Card Creation
function createJobRow(job, index) {
    const div = document.createElement('article');
    div.className = 'dispatch-job-card bg-white border border-slate-200 shadow-sm hover:border-indigo-300 transition-all duration-200 cursor-pointer flex flex-col p-4 animate-row relative group';

    const isSelected = window.selectedJobIds.has(String(job.id));
    const teamIdx = window.currentTeams.findIndex(t => t.id == job.team_id);
    const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#64748b';
    const coords = getJobLatLng(job);
    const jobStatus = (job.status || '').toLowerCase();
    const jobId = String(job.id);
    const queueLabel = job.seq || index + 1;

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
                        data-id="${jobId}" ${isSelected ? 'checked' : ''} onchange="window.toggleJobSelection('${jobId}')">
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
                    <div class="text-[12px] font-black text-emerald-700 mt-1 break-words">${displayValue(job.phone)}</div>
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
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center gap-1" onclick="event.stopPropagation(); window.showJobPopupById('${jobId}')">
                <i data-lucide="file-text" class="w-4 h-4"></i>รายละเอียด
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black flex items-center justify-center gap-1 ${mapButtonClass}" ${coords ? '' : 'disabled'} onclick="event.stopPropagation(); window.openJobNavigationById('${jobId}')">
                <i data-lucide="navigation" class="w-4 h-4"></i>นำทาง
            </button>
        </div>
        ${job.team_id && jobStatus !== 'completed' && jobStatus !== 'failed' ? `
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); window.openCompleteJobModal(${job.id})">
                <i data-lucide="check-circle" class="w-4 h-4"></i>ปิดงาน
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); window.updateJobStatus(${job.id}, 'failed')">
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

function showJobPopup(job, color) {
    const coords = getJobLatLng(job);
    const gmapsLink = coords ? `https://www.google.com/maps/dir/?api=1&destination=$${coords.lat},${coords.lng}` : null;

    let actionButtons = '';
    const popupStatus = (job.status || '').toLowerCase();
    
    if (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN && popupStatus !== 'completed' && popupStatus !== 'failed') {
        actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <button onclick="Swal.close(); window.openCompleteJobModal(${job.id})" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">ปิดงาน</button>
                <button onclick="Swal.close(); window.updateJobStatus(${job.id}, 'failed')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">ไม่สำเร็จ</button>
            </div>`;
    }

    Swal.fire({
        title: `<div class="text-left"><div class="text-[10px] font-black text-slate-400 uppercase">รายละเอียดงาน</div><div class="font-black text-lg" style="color:${color};">${displayValue(job.access_no, 'N/A')}</div></div>`,
        html: `
            <div class="text-left mt-1 space-y-3 font-sans">
                <div class="bg-white border border-slate-100 p-4 rounded-lg shadow-sm space-y-3">
                    <div><p class="text-[9px] font-bold text-slate-400 uppercase">ลูกค้า</p><p class="text-sm font-black text-slate-800">${displayValue(job.customer)}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100"><p class="text-[9px] font-bold text-slate-400 uppercase mb-1">สถานที่ติดตั้ง</p><p class="text-xs text-slate-700 font-bold">${displayValue(job.address)}</p></div>
                    <div class="grid grid-cols-2 gap-2">
                        ${detailItem('วันที่', job.plan_arrival_date)}
                        ${detailItem('ทีม', job.team_name || 'รอจ่าย')}
                        ${detailItem('โทรศัพท์', job.phone)}
                        ${detailItem('พิกัด', coords ? `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}` : 'ไม่มี')}
                    </div>
                </div>
                ${actionButtons}
            </div>`,
        showCancelButton: true, showCloseButton: true, showConfirmButton: !!gmapsLink,
        confirmButtonColor: color, cancelButtonColor: '#f1f5f9', confirmButtonText: 'นำทางด้วย Google Maps', cancelButtonText: '<span class="text-slate-500 font-bold">ปิด</span>',
        customClass: { popup: 'rounded-2xl p-4 shadow-xl', confirmButton: 'rounded-lg px-4 py-2.5 font-bold w-full mt-2 text-xs', actions: 'flex-col w-full px-2' },
        didOpen: refreshLucideIcons
    }).then((result) => { if (result.isConfirmed && gmapsLink) window.open(gmapsLink, '_blank'); });
}

window.updateJobStatus = async function(jobId, status) {
    const job = window.allJobs.find(j => String(j.id) === String(jobId));
    if (!job) return;

    if (status === 'completed') {
        window.openCompleteJobModal(jobId);
        return;
    }

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
        window.open(`https://www.google.com/maps?q=${coords.lat},${coords.lng}`, '_blank');
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
        const res = await fetch('api/dispatch/update_job_status.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: jobId, status: status, remark: remark })
        });
        const data = await res.json();
        if (data.success) loadJobs();
        else Swal.fire('ข้อผิดพลาด', data.error, 'error');
    } catch (e) { Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error'); } finally { hideLoader(); }
};

// 🗺️ Navigation actions
window.handleNavigateSelected = function() {
    if (window.selectedJobIds.size === 0) return;
    const selectedIds = Array.from(window.selectedJobIds);
    let jobs = window.allJobs.filter(j => selectedIds.includes(String(j.id))).sort((a,b) => (a.seq || 999) - (b.seq || 999));
    const valid = jobs.filter(j => getJobLatLng(j));
    if (valid.length === 0) return Swal.fire('ไม่พบพิกัด', 'งานที่เลือกไม่มีพิกัด', 'warning');
    
    if (valid.length === 1) {
        const c = getJobLatLng(valid[0]);
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lng}`, '_blank');
        return;
    }

    const dest = valid[valid.length-1];
    const destC = getJobLatLng(dest);
    const wps = valid.slice(0, -1).map(j => { const c = getJobLatLng(j); return `${c.lat},${c.lng}`; }).join('|');
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${destC.lat},${destC.lng}&waypoints=${wps}&travelmode=driving`, '_blank');
};

// 📊 Auto-Dispatch Modal
window.openDispatchModal = function() {
    const unassigned = window.allJobs.filter(j => !j.team_id).length;
    if (unassigned === 0) return Swal.fire('แจ้งเตือน', 'ไม่มีงานรอจ่าย', 'info');
    setText('unassignedCount', unassigned);
    const container = document.getElementById('dispatchTeamList');
    if (!container) return;
    container.innerHTML = '';
    if (window.currentTeams.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-[10px] font-bold">ไม่มีทีมในระบบ</p>';
        document.getElementById('confirmDispatchBtn').disabled = true;
    } else {
        document.getElementById('confirmDispatchBtn').disabled = false;
        window.currentTeams.forEach((t, i) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 bg-white rounded border border-slate-100';
            div.innerHTML = `<div class="flex items-center space-x-2"><div class="w-6 h-6 rounded flex items-center justify-center text-white font-bold text-[10px]" style="background-color: ${getColor(i)}">${t.team_name.charAt(0)}</div><span class="font-bold text-[11px]">${escapeHTML(t.team_name)}</span></div><input type="number" id="dist-quota-${t.id}" value="0" min="0" max="${unassigned}" class="w-14 px-1 py-1 rounded border text-center font-bold text-[11px] h-6 focus:ring-1 focus:ring-indigo-500">`;
            container.appendChild(div);
        });
    }
    document.getElementById('dispatchModal').classList.remove('hidden');
};

window.closeDispatchModal = function() { document.getElementById('dispatchModal').classList.add('hidden'); };

window.runAutoDispatch = async function() {
    const quotas = [];
    window.currentTeams.forEach(t => {
        const el = document.getElementById(`dist-quota-${t.id}`);
        if (el && parseInt(el.value) > 0) quotas.push({ team_name: t.team_name, limit: parseInt(el.value) });
    });
    if (quotas.length === 0) return alert('กรุณาระบุจำนวนงาน');
    closeDispatchModal();
    showLoader('กำลังกระจายงาน...');
    try {
        const res = await fetch('api/dispatch/auto_assign.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ quotas }) });
        const data = await res.json();
        if (data.success) loadJobs();
        else Swal.fire('ข้อผิดพลาด', data.error, 'error');
    } catch (e) { Swal.fire('ข้อผิดพลาด', 'ระบบทำงานผิดพลาด', 'error'); } finally { hideLoader(); }
};

// 🗑️ Bulk Operations
window.handleBulkDelete = async function() {
    if (window.selectedJobIds.size === 0) return;
    if (!confirm(`ยืนยันลบ ${window.selectedJobIds.size} รายการ?`)) return;
    showLoader('ลบข้อมูล...');
    try {
        const res = await fetch('api/dispatch/bulk_delete.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ ids: Array.from(window.selectedJobIds) }) });
        const data = await res.json();
        if (data.success) { window.selectedJobIds.clear(); loadJobs(); }
    } catch (e) { Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error'); } finally { hideLoader(); }
};

window.handleDeleteAllJobs = async function() {
    if (!confirm('ล้างข้อมูลงานทั้งหมด?')) return;
    showLoader('ล้างข้อมูล...');
    try {
        const res = await fetch('api/dispatch/delete_all_jobs.php');
        const data = await res.json();
        if (data.success) loadJobs();
    } catch (e) { Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error'); } finally { hideLoader(); }
};

window.handleClearAssignments = async function() {
    if (!confirm('ยกเลิกการจ่ายงานทั้งหมด?')) return;
    showLoader('ดึงงานกลับ...');
    try {
        const res = await fetch('api/dispatch/clear_assignments.php');
        const data = await res.json();
        if (data.success) loadJobs();
    } catch (e) { Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error'); } finally { hideLoader(); }
};

window.runOptimizeRoute = async function() {
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
    if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN && teamEl) teamVal = teamEl.value;

    const dateVal = document.getElementById('dateFilter')?.value;
    const statusVal = document.getElementById('statusFilter')?.value; // ดึงค่าจาก Dropdown ใหม่

    let filteredJobs = [...allJobs];

    // กรองตามทีม
    if (teamVal === 'unassigned') filteredJobs = filteredJobs.filter(j => !j.team_id);
    else if (teamVal !== 'all') filteredJobs = filteredJobs.filter(j => j.team_id == teamVal);
    
    // กรองตามวันที่
    if (dateVal) filteredJobs = filteredJobs.filter(j => j.plan_arrival_date === dateVal);

    // 🌟 กรองตามสถานะ 🌟
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
        const jobStatus = (job.status || '').toLowerCase();
        const isDone = jobStatus === 'completed' || jobStatus === 'failed';
        
        let actionButtons = '';
        if (!isDone && job.team_id) {
            actionButtons = `
            <div class="grid grid-cols-2 gap-1.5 mt-2 pt-2 border-t border-slate-100">
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-emerald-500 text-white hover:bg-emerald-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>ปิดงาน
                </button>
                <button type="button" class="rounded px-2 py-1 text-[9px] font-bold bg-rose-500 text-white hover:bg-rose-600 flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'failed')">
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
        ${job.team_id && jobStatus !== 'completed' && jobStatus !== 'failed' ? `
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); openCompleteJobModal(${job.id})">
                <i data-lucide="check-circle" class="w-4 h-4"></i>ปิดงาน
            </button>
            <button type="button" class="rounded-lg px-3 py-2 text-xs font-black bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center gap-1 transition-colors" onclick="event.stopPropagation(); updateJobStatus(${job.id}, 'failed')">
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
    const popupStatus = (job.status || '').toLowerCase();
    if (!IS_ADMIN && popupStatus !== 'completed' && popupStatus !== 'failed') {
        actionButtons = `
            <div class="grid grid-cols-2 gap-2 mt-3">
                <button onclick="Swal.close(); openCompleteJobModal(${job.id})" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ปิดงาน
                </button>
                <button onclick="Swal.close(); updateJobStatus(${job.id}, 'failed')" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-lg shadow-sm text-xs">
                    ทำไม่สำเร็จ
                </button>
            </div>
        `;
    } else if (!IS_ADMIN && popupStatus === 'failed') {
        actionButtons = `
            <div class="mt-3 rounded-lg bg-rose-50 border border-rose-100 px-3 py-2 text-center">
                <span class="text-xs font-black text-rose-700">สถานะ: ไม่สำเร็จ</span>
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

    if (status === 'completed') {
        openCompleteJobModal(jobId);
        return;
    }

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
        if (!text) return;
        remark = text;
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
            Swal.fire({
                title: 'สำเร็จ',
                text: 'บันทึกการไม่สำเร็จเรียบร้อย',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false,
                didClose: () => loadJobs()
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

// 📂 Import/Export
window.handleExcelUpload = function(e) {
    const file = e.target.files[0];
    if (!file) return;
    Swal.fire({ title: 'นำเข้าข้อมูล Excel?', text: 'นำงานเข้าสู่ระบบ (ไม่ล้างงานเดิม)', icon: 'question', showCancelButton: true }).then((r) => { if (r.isConfirmed) processExcel(file); else e.target.value = ''; });
};

function processExcel(file) {
    showLoader('กำลังอ่านไฟล์ Excel...');
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(worksheet, {header: 1});

            if (rows.length < 2) throw new Error('ไฟล์ว่างเปล่า');

            const headerRow = rows[0].map(h => String(h).toLowerCase().replace(/\s/g, ''));
            const findCol = (keys) => headerRow.findIndex(h => keys.some(k => h.includes(k)));

            const accessIdx = findCol(['access', 'รหัสงาน']);
            const latIdx = findCol(['lat', 'latitude', 'ละติจูด']);
            const lngIdx = findCol(['lng', 'long', 'longitude', 'ลองจิจูด']); 
            const custIdx = findCol(['customer', 'ชื่อลูกค้า']);
            const addrIdx = findCol(['address', 'ที่อยู่']);
            const dateIdx = findCol(['date', 'วัน', 'arrival']);
            const packageIdx = findCol(['package', 'แพ็กเกจ', 'แพคเกจ']);
            const prodIdx = findCol(['product', 'สินค้า']);
            const orderIdx = findCol(['order', 'ออเดอร์']);

            if (accessIdx === -1 || latIdx === -1 || lngIdx === -1) throw new Error('ขาดหัวคอลัมน์สำคัญ (รหัสงาน, ละติจูด, ลองจิจูด)');

            const parsedJobs = [];
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                if (row[accessIdx] && row[latIdx] && row[lngIdx]) {
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
                        address: row[addrIdx] || '-',
                        lat: String(row[latIdx]).replace(/[^0-9.-]/g, ''),
                        lng: String(row[lngIdx]).replace(/[^0-9.-]/g, ''),
                        plan_arrival_date: planDate || null,
                        package: packageIdx !== -1 ? row[packageIdx] : null,
                        product: prodIdx !== -1 ? row[prodIdx] : null,
                        order_no: orderIdx !== -1 ? row[orderIdx] : null
                    });
                }
            }

            if (parsedJobs.length === 0) throw new Error('ไม่พบข้อมูลงานที่มีพิกัดถูกต้อง');

            const res = await fetch('api/dispatch/upload_jobs.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ jobs: parsedJobs }) 
            });
            const rData = await res.json();
            if (rData.success) {
                Swal.fire('สำเร็จ', `นำเข้า ${rData.imported} งานเรียบร้อย!`, 'success');
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

window.handleExportExcel = function() {
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
            'ทีม': j.team_name || '',
            'สถานะ': j.status || 'Pending'
        })));
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Dispatch");
        XLSX.writeFile(wb, `Dispatch_Jobs.xlsx`);
        hideLoader();
    }, 500);
}
